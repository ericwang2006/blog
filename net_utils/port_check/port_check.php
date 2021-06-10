<html>

<head>
	<title>端口测试</title>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<!-- <meta name="applicable-device" content="mobile">	 -->
	<meta name="viewport" content="width=device-width,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<style>
		body {
			font-family: Arial;
		}

		.panel {
			padding: 5px;
			font-weight: bold;
			//background:#FE6714;
		}

		.input_panel {
			padding: 5px;
		}

		.info_text {
			resize: none;
			width: 100%;
			height: 30px;
			border: 1px solid #ccc;
			outline: none;
		}

		.dialog {
			display: none;
			color: #F00;
		}

		.dialog_readme {
			color: #0000FF;
		}

		button {
			width: 200px;
			padding: 8px;
			background-color: #428bca;
			border-color: #357ebd;
			color: #fff;
			-moz-border-radius: 10px;
			-webkit-border-radius: 10px;
			border-radius: 10px;
			/* future proofing */
			-khtml-border-radius: 10px;
			/* for old Konqueror browsers */
			text-align: center;
			vertical-align: middle;
			border: 1px solid transparent;
			font-weight: 900;
			font-size: 125%
		}
	</style>
</head>

<body>
	<h2>端口测试</h2>
	<div>

		<div id="token" class="panel">
		</div>
		<div class="panel">
			IP地址或者域名
		</div>
		<div class="input_panel">
			<input id="ip" maxlength="63" class="info_text"></input>
		</div>
		<div class="panel">
			端口号
		</div>
		<div class="input_panel">
			<input id="port" maxlength="5" class="info_text"></input>
		</div>

		<div class="panel">
			端口类型
		</div>

		<div class="input_panel">
			<input type="radio" id="port_type_0" name="port_type" value="0" checked>
			<label onclick="selTCP()">TCP</label>

			<input type="radio" id="port_type_1" name="port_type" value="1">
			<label onclick="selUDP()">UDP</label>
		</div>

		<div id="login_prompt" class="dialog">
		</div>

		<div class="input_panel">
			<button id="btn_login" type="button" class="button" onclick="startTest()">开始检测</button>
		</div>
		<div id="login_prompt" class="dialog_readme">
			注意:TCP端口测试结果通常可信,UDP端口测试结果仅供参考(无连接协议特性决定无法准确探测)
		</div>
	</div>
	<script type="text/javascript">
		function selTCP() {
			$('input:radio[name=port_type]')[0].checked = true;
		}

		function selUDP() {
			$('input:radio[name=port_type]')[1].checked = true;
		}

		function checkIP(str) {
			var re = /^[A-Za-z0-9-.]{1,63}$/;
			if (re.test(str)) {
				return true;
			} else {
				return false;
			}
		}

		function checPort(str) {
			var re = /^([0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/;
			if (re.test(str)) {
				return true;
			} else {
				return false;
			}
		}

		function startTest() {
			$("#btn_login").attr("disabled", "true");

			setTimeout(function() {
				var ip = $("#ip").val().replace(/(^\s*)|(\s*$)/g, "");
				var port = $("#port").val().replace(/(^\s*)|(\s*$)/g, "");
				var port_type = $('input:radio[name=port_type]:checked').val();
				var dlg = $("#login_prompt");

				if (!checkIP(ip)) {
					dlg.text("请输入合法域名或者IP地址！");
					dlg.css("color", "red");
					dlg.fadeIn();
					$("#btn_login").removeAttr("disabled");
					return;
				}

				if (!checPort(port)) {
					dlg.text("端口范围必须是1-65535！");
					dlg.css("color", "red");
					dlg.fadeIn();
					$("#btn_login").removeAttr("disabled");
					return;
				}

				dlg.css("color", "blue");
				dlg.text("正在测试,请稍等...");
				dlg.fadeIn();

				var c = {};
				c.ip = ip;
				c.port = port;
				c.port_type = port_type;

				$.post("do_port_check.php", JSON.stringify(c),
					function(result) {
						if (result.ret == 1) {
							dlg.html("端口测试成功<br/>" + (result.msg == null ? "" : result.msg));
							dlg.css("color", "green");
							dlg.fadeIn();
						} else {
							dlg.html("端口测试失败<br/>" + (result.msg == null ? "" : result.msg));
							dlg.css("color", "red");
							dlg.fadeIn();
						}
						$("#btn_login").removeAttr("disabled");
					}
				);

			}, 100);
		}
	</script>
</body>

</html>