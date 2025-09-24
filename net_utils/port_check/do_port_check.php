<?php
// 设置错误报告和异常处理
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生产环境中不显示错误
ini_set('log_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // 如果需要跨域支持
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 初始化结果数组
$result = ['ret' => 0, 'msg' => ''];

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只允许POST请求');
    }

    function checkip($str)
    {
        // 改进的IP/域名验证
        if (empty($str)) {
            return false;
        }

        // 检查长度
        if (strlen($str) > 253) {
            return false;
        }

        // 基本格式检查
        if (!preg_match("/^[A-Za-z0-9.-]{1,253}$/", $str)) {
            return false;
        }

        // 检查是否为有效IP地址
        if (filter_var($str, FILTER_VALIDATE_IP)) {
            return true;
        }

        // 检查是否为有效域名
        if (filter_var($str, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }

        // 备用域名检查
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $str)) {
            return true;
        }

        return false;
    }

    function checkport($str)
    {
        if (empty($str)) {
            return false;
        }

        // 检查是否为纯数字
        if (!ctype_digit($str)) {
            return false;
        }

        $port = intval($str);
        return ($port >= 1 && $port <= 65535);
    }

    function sanitizeInput($input)
    {
        // 清理输入，防止命令注入
        return preg_replace('/[^a-zA-Z0-9.-]/', '', trim($input));
    }

    // 读取POST数据
    $json = file_get_contents("php://input");

    if (empty($json)) {
        throw new Exception('没有接收到数据');
    }

    // 解码JSON数据
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON格式错误: ' . json_last_error_msg());
    }

    if (!$data || !is_array($data)) {
        throw new Exception('数据格式不正确');
    }

    // 验证必需字段
    if (!isset($data['ip']) || !isset($data['port']) || !isset($data['port_type'])) {
        throw new Exception('缺少必需的参数');
    }

    $ip = trim($data['ip']);
    $port = trim($data['port']);
    $port_type = intval($data['port_type']);

    // 验证输入
    if (!checkip($ip)) {
        $result['ret'] = 0;
        $result['msg'] = "请输入合法域名或者IP地址！";
        echo json_encode($result);
        exit;
    }

    if (!checkport($port)) {
        $result['ret'] = 0;
        $result['msg'] = "端口范围必须是1-65535！";
        echo json_encode($result);
        exit;
    }

    // 清理输入以防止命令注入
    $safe_ip = sanitizeInput($ip);
    $safe_port = sanitizeInput($port);

    // 双重检查清理后的输入
    if (empty($safe_ip) || empty($safe_port)) {
        throw new Exception('输入包含非法字符');
    }

    // 使用纯PHP实现端口检测
    if ($port_type == 0) {
        // TCP端口测试
        error_log("执行TCP端口测试: $safe_ip:$safe_port");

        // 创建TCP socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new Exception('无法创建TCP socket: ' . socket_strerror(socket_last_error()));
        }

        // 设置非阻塞模式以便控制超时
        socket_set_nonblock($socket);

        // 尝试连接
        $connect_result = socket_connect($socket, $safe_ip, intval($safe_port));

        if ($connect_result === false) {
            $error = socket_last_error($socket);

            // EINPROGRESS 表示连接正在进行中（非阻塞模式正常情况）
            if ($error == SOCKET_EINPROGRESS || $error == SOCKET_EALREADY) {
                // 使用select等待连接完成，超时10秒
                $read = array();
                $write = array($socket);
                $except = array($socket);

                $select_result = socket_select($read, $write, $except, 10, 0);

                if ($select_result === false) {
                    socket_close($socket);
                    throw new Exception('Socket select失败: ' . socket_strerror(socket_last_error()));
                } elseif ($select_result === 0) {
                    // 超时
                    socket_close($socket);
                    $result['ret'] = 0;
                    $result['msg'] = "TCP连接超时";
                } elseif (in_array($socket, $except)) {
                    // 连接异常
                    socket_close($socket);
                    $result['ret'] = 0;
                    $result['msg'] = "TCP连接异常";
                } elseif (in_array($socket, $write)) {
                    // 检查是否真正连接成功
                    $so_error = socket_get_option($socket, SOL_SOCKET, SO_ERROR);
                    socket_close($socket);

                    if ($so_error === 0) {
                        $result['ret'] = 1;
                        $result['msg'] = "TCP端口开放，连接成功";
                    } else {
                        $result['ret'] = 0;
                        $result['msg'] = "TCP连接被拒绝";
                    }
                }
            } else {
                socket_close($socket);
                $result['ret'] = 0;
                if ($error == SOCKET_ECONNREFUSED) {
                    $result['msg'] = "TCP连接被拒绝，端口可能关闭";
                } elseif ($error == SOCKET_ETIMEDOUT) {
                    $result['msg'] = "TCP连接超时";
                } elseif ($error == SOCKET_EHOSTUNREACH) {
                    $result['msg'] = "主机不可达";
                } else {
                    $result['msg'] = "TCP连接失败: " . socket_strerror($error);
                }
            }
        } else {
            // 立即连接成功
            socket_close($socket);
            $result['ret'] = 1;
            $result['msg'] = "TCP端口开放，连接成功";
        }

    } else {
        // UDP端口测试 - 使用sudo nmap检测
        error_log("执行UDP端口测试: $safe_ip:$safe_port");

        // 构建nmap命令，使用sudo执行
        $nmap_cmd = 'sudo /usr/bin/nmap -sU -p ' . escapeshellarg($safe_port) .
            ' -T4 --max-retries 2 --host-timeout 20s --max-rtt-timeout 1000ms ' .
            escapeshellarg($safe_ip) . ' 2>/dev/null';

        error_log("执行nmap命令: $nmap_cmd");

        // 执行nmap命令
        $output = array();
        $return_code = 0;

        // 设置超时执行
        $start_time = time();
        exec($nmap_cmd, $output, $return_code);
        $execution_time = time() - $start_time;

        if ($execution_time > 25) {
            $result['ret'] = 0;
            $result['msg'] = "UDP端口检测超时";
        } elseif ($return_code !== 0 && $return_code !== 1) {
            // nmap返回码不是0或1
            $result['ret'] = 0;
            $result['msg'] = "nmap执行出错，返回码: $return_code";
            error_log("nmap执行出错: " . implode("\n", $output));
        } else {
            // 解析nmap输出
            $output_text = implode("\n", $output);
            error_log("nmap输出: " . $output_text);

            // 解析UDP扫描结果
            if (preg_match('/(\d+)\/udp\s+([^\s]+)(?:\s+(.*))?/i', $output_text, $matches)) {
                $port_num = $matches[1];
                $state = strtolower($matches[2]);
                $service = isset($matches[3]) ? trim($matches[3]) : '';

                switch ($state) {
                    case 'open':
                        $result['ret'] = 1;
                        $result['msg'] = "UDP端口 {$port_num} 开放" . ((!empty($service) && trim($service) !== '') ? " (服务: " . trim($service) . ")" : "");
                        break;

                    case 'closed':
                        $result['ret'] = 0;
                        $result['msg'] = "UDP端口 {$port_num} 关闭";
                        break;

                    case 'filtered':
                        $result['ret'] = 0;
                        $result['msg'] = "UDP端口 {$port_num} 被过滤（可能被防火墙阻止）";
                        break;

                    case 'open|filtered':
                        $result['ret'] = 0;
                        $result['msg'] = "UDP端口 {$port_num} 状态不确定（开放或被过滤）" . ((!empty($service) && trim($service) !== '') ? " - 可能是" . trim($service) . "服务" : "");
                        break;

                    default:
                        $result['ret'] = 0;
                        $result['msg'] = "UDP端口 {$port_num} 状态: $state";
                        break;
                }
            } elseif (preg_match('/Host seems down|no response received/i', $output_text)) {
                $result['ret'] = 0;
                $result['msg'] = "目标主机无响应或不可达";
            } elseif (preg_match('/could not resolve|Name or service not known/i', $output_text)) {
                $result['ret'] = 0;
                $result['msg'] = "无法解析主机名";
            } elseif (empty(trim($output_text))) {
                $result['ret'] = 0;
                $result['msg'] = "nmap未返回结果，请检查网络连接或目标地址";
            } else {
                // 无法解析输出，返回原始信息
                $result['ret'] = 0;
                $result['msg'] = "UDP端口检测完成，但状态不明确";
                error_log("无法解析nmap输出: " . $output_text);
            }
        }
    }

}
catch (Exception $e) {
    // 捕获异常并返回错误信息
    error_log("Port check error: " . $e->getMessage());
    $result['ret'] = 0;
    $result['msg'] = "服务器错误: " . $e->getMessage();
}
catch (Error $e) {
    // 捕获PHP 7+ 的Error
    error_log("PHP Error: " . $e->getMessage());
    $result['ret'] = 0;
    $result['msg'] = "系统错误，请联系管理员";
}

// 确保输出JSON格式
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
