 <?php
	//apk add netcat-openbsd


	function checkip($str)
	{
		if (preg_match("/^[A-Za-z0-9-.]{1,63}$/", $str)) {
			return true;
		} else {
			return false;
		}
	}

	function checkport($str)
	{
		if (preg_match("/^([0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/", $str)) {
			return true;
		} else {
			return false;
		}
	}


	$json = file_get_contents("php://input");
	$data = json_decode($json, true);
	if ($data) {
		$ip = trim($data['ip']);
		$port = trim($data['port']);
		$port_type = $data['port_type'];
		if (!checkip($ip)) {
			$result['ret'] = 0;
			$result['msg'] = "请输入合法域名或者IP地址！";
		} elseif (!checkport($port)) {
			$result['ret'] = 0;
			$result['msg'] = "端口范围必须是1-65535！";
		} else {
			if ($port_type == 0) {
				//error_log("\n执行tcp",3,"/tmp/test.log");
				$r = exec("nc -w 5 -vz $ip $port 2>&1", $output, $status);
			} else {
				//error_log("\n执行udp",3,"/tmp/test.log");
				$r = exec("nc -w 5 -vzu $ip $port 2>&1", $output, $status);
			}
			if (strpos($r, 'succeeded') !== false) {
				$result['ret'] = 1;
			} else {
				$result['ret'] = 0;
			}
			$result['msg'] = $r;
		}
	} else {
		$result['ret'] = 0;
		$result['msg'] = "非法的数据格式!";
	}
	header('Content-Type:application/json; charset=utf-8');
	echo json_encode($result);
	?>
