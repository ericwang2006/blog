<!DOCTYPE html>
<html>

<head>
    <title>端口测试</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .panel {
            padding: 5px;
            font-weight: bold;
            margin-top: 10px;
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
            padding: 5px;
            box-sizing: border-box;
        }

        .dialog {
            display: none;
            color: #F00;
            margin: 10px 0;
            padding: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .dialog_readme {
            color: #0000FF;
            margin: 10px 0;
            padding: 10px;
            background-color: #e6f3ff;
            border-radius: 5px;
        }

        button {
            width: 200px;
            padding: 8px;
            background-color: #428bca;
            border-color: #357ebd;
            color: #fff;
            border-radius: 10px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid transparent;
            font-weight: 900;
            font-size: 125%;
            cursor: pointer;
        }

        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .loading {
            display: none;
            text-align: center;
            margin: 10px 0;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 2s linear infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <h2>端口测试工具</h2>
    <div>
        <div id="token" class="panel"></div>

        <div class="panel">IP地址或者域名</div>
        <div class="input_panel">
            <input id="ip" maxlength="63" class="info_text" placeholder="例如：192.168.1.1 或 google.com">
        </div>

        <div class="panel">端口号</div>
        <div class="input_panel">
            <input id="port" maxlength="5" class="info_text" placeholder="例如：80, 443, 22">
        </div>

        <div class="panel">端口类型</div>
        <div class="input_panel">
            <input type="radio" id="port_type_0" name="port_type" value="0" checked>
            <label for="port_type_0" onclick="selTCP()">TCP</label>

            <input type="radio" id="port_type_1" name="port_type" value="1">
            <label for="port_type_1" onclick="selUDP()">UDP</label>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>正在测试，请稍等...
        </div>

        <div id="login_prompt" class="dialog"></div>

        <div class="input_panel">
            <button id="btn_login" type="button" class="button" onclick="startTest()">开始检测</button>
        </div>

        <div class="dialog_readme">
            <strong>注意：</strong>TCP端口测试结果通常可信，UDP端口测试结果仅供参考（无连接协议特性决定无法准确探测）
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
            // 改进的域名/IP验证正则表达式
            var re = /^[A-Za-z0-9.-]{1,63}$/;
            if (re.test(str)) {
                return true;
            } else {
                return false;
            }
        }

        function checkPort(str) {
            var re = /^([0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$/;
            var port = parseInt(str);
            return re.test(str) && port >= 1 && port <= 65535;
        }

        function showMessage(message, type) {
            var dlg = $("#login_prompt");
            dlg.html(message);

            switch (type) {
                case 'error':
                    dlg.css("color", "red");
                    dlg.css("background-color", "#ffe6e6");
                    break;
                case 'success':
                    dlg.css("color", "green");
                    dlg.css("background-color", "#e6ffe6");
                    break;
                case 'info':
                    dlg.css("color", "blue");
                    dlg.css("background-color", "#e6f3ff");
                    break;
                default:
                    dlg.css("color", "black");
                    dlg.css("background-color", "#f9f9f9");
            }

            dlg.fadeIn();
        }

        function startTest() {
            var ip = $("#ip").val().trim();
            var port = $("#port").val().trim();
            var port_type = $('input:radio[name=port_type]:checked').val();

            // 隐藏之前的消息
            $("#login_prompt").fadeOut();

            // 验证输入
            if (!ip) {
                showMessage("请输入IP地址或域名！", 'error');
                return;
            }

            if (!checkIP(ip)) {
                showMessage("请输入合法的域名或者IP地址！", 'error');
                return;
            }

            if (!port) {
                showMessage("请输入端口号！", 'error');
                return;
            }

            if (!checkPort(port)) {
                showMessage("端口范围必须是1-65535！", 'error');
                return;
            }

            // 禁用按钮并显示加载状态
            $("#btn_login").attr("disabled", true);
            $("#loading").fadeIn();

            var requestData = {
                ip: ip,
                port: port,
                port_type: port_type
            };

            // 改进的AJAX请求配置
            $.ajax({
                url: "do_port_check.php",
                type: "POST",
                data: JSON.stringify(requestData),
                contentType: "application/json",
                dataType: "json",
                timeout: 30000, // 30秒超时
                success: function (result) {
                    $("#loading").fadeOut();
                    $("#btn_login").removeAttr("disabled");

                    if (result && result.ret == 1) {
                        var successMsg = "端口测试成功！<br/>";
                        if (result.msg) {
                            successMsg += result.msg;
                        }
                        showMessage(successMsg, 'success');
                    } else {
                        var errorMsg = "端口测试失败！<br/>";
                        if (result && result.msg) {
                            errorMsg += result.msg;
                        } else {
                            errorMsg += "未知错误";
                        }
                        showMessage(errorMsg, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    $("#loading").fadeOut();
                    $("#btn_login").removeAttr("disabled");

                    var errorMsg = "请求失败！<br/>";

                    if (status === 'timeout') {
                        errorMsg += "请求超时，请检查网络连接或稍后重试";
                    } else if (status === 'error') {
                        errorMsg += "网络错误：" + error;
                    } else if (status === 'parsererror') {
                        errorMsg += "数据解析错误，服务器返回的数据格式不正确";
                    } else {
                        errorMsg += "状态：" + status + "，错误：" + error;
                    }

                    showMessage(errorMsg, 'error');
                    console.error("AJAX Error:", status, error, xhr);
                }
            });
        }

        // 允许回车键触发测试
        $(document).ready(function () {
            $("#ip, #port").keypress(function (e) {
                if (e.which == 13) {
                    startTest();
                }
            });
        });
    </script>
</body>

</html>