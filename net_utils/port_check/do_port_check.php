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

    function checkTCPPort($ip, $port)
    {
        error_log("执行TCP端口测试: $ip:$port");

        $result = ['ret' => 0, 'msg' => ''];

        // 创建TCP socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            $result['msg'] = '无法创建TCP socket: ' . socket_strerror(socket_last_error());
            return $result;
        }

        // 设置socket选项
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 10, 'usec' => 0));
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 10, 'usec' => 0));

        // 设置非阻塞模式以便控制超时
        socket_set_nonblock($socket);

        $start_time = time();

        // 尝试连接
        $connect_result = @socket_connect($socket, $ip, intval($port));

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
                    $result['msg'] = 'Socket select失败: ' . socket_strerror(socket_last_error());
                } elseif ($select_result === 0) {
                    // 超时
                    socket_close($socket);
                    $result['msg'] = "TCP连接超时";
                } elseif (in_array($socket, $except)) {
                    // 连接异常
                    socket_close($socket);
                    $result['msg'] = "TCP连接异常";
                } elseif (in_array($socket, $write)) {
                    // 检查是否真正连接成功
                    $so_error = socket_get_option($socket, SOL_SOCKET, SO_ERROR);
                    socket_close($socket);

                    if ($so_error === 0) {
                        $result['ret'] = 1;
                        $result['msg'] = "TCP端口 {$port} 开放，连接成功";
                    } else {
                        $result['msg'] = "TCP端口 {$port} 连接被拒绝";
                    }
                }
            } else {
                socket_close($socket);
                if ($error == SOCKET_ECONNREFUSED) {
                    $result['msg'] = "TCP端口 {$port} 连接被拒绝，端口关闭";
                } elseif ($error == SOCKET_ETIMEDOUT) {
                    $result['msg'] = "TCP端口 {$port} 连接超时";
                } elseif ($error == SOCKET_EHOSTUNREACH) {
                    $result['msg'] = "主机 {$ip} 不可达";
                } elseif ($error == SOCKET_ENETUNREACH) {
                    $result['msg'] = "网络不可达";
                } else {
                    $result['msg'] = "TCP端口 {$port} 连接失败: " . socket_strerror($error);
                }
            }
        } else {
            // 立即连接成功
            socket_close($socket);
            $result['ret'] = 1;
            $result['msg'] = "TCP端口 {$port} 开放，连接成功";
        }

        $execution_time = time() - $start_time;
        error_log("TCP检测完成，耗时: {$execution_time}秒");

        return $result;
    }

    function checkUDPPort($ip, $port)
    {
        error_log("执行UDP端口测试: $ip:$port");

        $result = ['ret' => 0, 'msg' => ''];

        // 创建UDP socket
        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            $result['msg'] = '无法创建UDP socket: ' . socket_strerror(socket_last_error());
            return $result;
        }

        // 设置超时选项
        @socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0));
        @socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0));

        $start_time = time();

        // 准备测试数据包
        $test_data = getUDPProbeData($port);

        // 发送测试数据包
        $bytes_sent = @socket_sendto($socket, $test_data, strlen($test_data), 0, $ip, intval($port));

        if ($bytes_sent === false) {
            $error = socket_last_error($socket);
            $error_msg = socket_strerror($error);
            socket_close($socket);

            if ($error == SOCKET_EHOSTUNREACH) {
                $result['msg'] = "主机 {$ip} 不可达";
            } elseif ($error == SOCKET_ENETUNREACH) {
                $result['msg'] = "网络不可达";
            } elseif ($error == SOCKET_ECONNREFUSED) {
                $result['msg'] = "UDP端口 {$port} 连接被拒绝（端口关闭）";
            } elseif ($error == SOCKET_ETIMEDOUT) {
                $result['msg'] = "UDP端口 {$port} 发送超时";
            } else {
                $result['msg'] = "UDP端口 {$port} 发送失败: " . $error_msg;
            }

            return $result;
        }

        // 尝试接收响应 - 简化版本
        $response = '';
        $from_ip = '';
        $from_port = 0;

        // 设置socket为非阻塞模式
        @socket_set_nonblock($socket);

        // 等待响应，最多尝试3秒
        $max_wait = 3;
        $wait_time = 0;
        $received = false;

        while ($wait_time < $max_wait && !$received) {
            $bytes_received = @socket_recvfrom($socket, $response, 1024, MSG_DONTWAIT, $from_ip, $from_port);

            if ($bytes_received !== false && $bytes_received > 0) {
                socket_close($socket);
                $result['ret'] = 1;
                $result['msg'] = "UDP端口 {$port} 开放（收到 {$bytes_received} 字节响应）";

                // 如果能识别服务类型，添加到消息中
                $service_info = identifyUDPService($port, $response);
                if (!empty($service_info)) {
                    $result['msg'] .= " - {$service_info}";
                }

                return $result;
            }

            // 如果没有收到数据，等待100ms再试
            usleep(100000); // 100ms
            $wait_time += 0.1;
        }

        socket_close($socket);

        $execution_time = time() - $start_time;
        error_log("UDP检测完成，耗时: {$execution_time}秒");

        // UDP特殊处理：发送成功但无响应
        if ($bytes_sent > 0) {
            $result['ret'] = 1; // 保守地认为可能开放
            $result['msg'] = "UDP端口 {$port} 可能开放（数据发送成功，但无响应）";

            // 添加常见服务提示
            $common_service = getCommonUDPService($port);
            if (!empty($common_service)) {
                $result['msg'] .= " - 可能是 {$common_service} 服务";
            }
        } else {
            $result['msg'] = "UDP端口 {$port} 状态未知";
        }

        return $result;
    }

    function getUDPProbeData($port)
    {
        // 针对常见UDP服务返回特定的探测数据
        switch (intval($port)) {
            case 53: // DNS
                return "\x12\x34\x01\x00\x00\x01\x00\x00\x00\x00\x00\x00\x07example\x03com\x00\x00\x01\x00\x01";

            case 123: // NTP
                return "\x1b" . str_repeat("\x00", 47);

            case 161: // SNMP
                return "\x30\x26\x02\x01\x00\x04\x06public\xa0\x19\x02\x04\x00\x00\x00\x00\x02\x01\x00\x02\x01\x00\x30\x0b\x30\x09\x06\x05\x2b\x06\x01\x02\x01\x05\x00";

            case 67: // DHCP
            case 68:
                return "\x01\x01\x06\x00\x12\x34\x56\x78\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

            case 69: // TFTP
                return "\x00\x01test\x00octet\x00";

            case 514: // Syslog
                return "<134>test message";

            default:
                // 通用探测数据
                return "UDP_PORT_TEST_" . date('His');
        }
    }

    function identifyUDPService($port, $response)
    {
        $port = intval($port);

        // 简单的服务识别
        switch ($port) {
            case 53:
                if (strlen($response) > 12)
                    return "DNS服务";
                break;
            case 123:
                if (strlen($response) == 48)
                    return "NTP服务";
                break;
            case 161:
                if (strpos($response, "\x30") === 0)
                    return "SNMP服务";
                break;
            case 67:
            case 68:
                if (strlen($response) > 200)
                    return "DHCP服务";
                break;
            case 514:
                return "Syslog服务";
                break;
        }

        return "";
    }

    function getCommonUDPService($port)
    {
        $common_udp_ports = [
            53 => "DNS",
            67 => "DHCP服务器",
            68 => "DHCP客户端",
            69 => "TFTP",
            123 => "NTP",
            161 => "SNMP",
            162 => "SNMP Trap",
            514 => "Syslog",
            1194 => "OpenVPN",
            4500 => "IPSec NAT-T",
            5353 => "mDNS",
        ];

        return isset($common_udp_ports[intval($port)]) ? $common_udp_ports[intval($port)] : "";
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

    // 执行端口检测
    if ($port_type == 0) {
        // TCP端口测试
        $result = checkTCPPort($safe_ip, $safe_port);
    } else {
        // UDP端口测试
        $result = checkUDPPort($safe_ip, $safe_port);
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