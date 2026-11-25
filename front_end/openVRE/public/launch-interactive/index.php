<?php

require __DIR__ . "/../../config/bootstrap.php";
redirectOutside();

require "../htmlib/header.inc.php";

$pid    = $_GET['pid'] ?? "";
$status = checkStatus($pid);

?>

<body class="page-header-fixed page-sidebar-closed-hide-logo page-content-white page-container-bg-solid page-sidebar-fixed">
    <div class="page-wrapper">

        <?php require "../htmlib/top.inc.php"; ?>
        <?php require "../htmlib/menu.inc.php"; ?>

        <div class="page-content-wrapper">
            <div class="page-content">
                <div class="page-bar">
                    <ul class="page-breadcrumb">
                        <li>
                            <a href="home/">Home</a>
                            <i class="fa fa-circle"></i>
                        </li>
                        <li>
                            <a href="workspace/">Interactive Tool</a>
                            <i class="fa fa-circle"></i>
                        </li>
                    </ul>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light bordered">
                            <div class="portlet-title">
                                <div class="caption">
                                    <i id="it-icon"></i>
                                    <span id="it-title" class="caption-subject bold uppercase"></span>
                                </div>
                            </div>

                            <div class="portlet-body text-center">
                                <h4 id="it-message"></h4>

                                <a id="it-open" class="btn btn-success btn-lg" target="_blank" style="display:none;">
                                    <i class="fa fa-external-link"></i> Open Interactive Session
                                </a>

                                <div id="it-waiting" style="display:none;">
                                    <br>
                                    <div class="progress progress-striped active">
                                        <div class="progress-bar progress-bar-info" style="width:100%"></div>
                                    </div>
                                    <small>Checking the session status every 3 seconds.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" id="it-logs" style="display:none;">
                    <div class="col-md-12">
                        <h3>Launcher logs</h3>
                        <a id="it-log-link" class="btn btn-default" target="_blank">Job Standard Output</a>
                        <pre id="it-log" style="max-height:250px;margin-top:10px;"></pre>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function() {
                var POLL_MS = 3000;
                var statusUrl = "applib/checkInteractiveStatus.php?pid=" + encodeURIComponent(<?= json_encode($pid); ?>);

                function render(status) {
                    var icon = status.ready ? "fa fa-check-circle font-green" :
                        (status.reload ? "fa fa-spinner fa-spin font-blue" : "fa fa-exclamation-circle font-red");

                    document.getElementById("it-icon").className = icon;
                    document.getElementById("it-title").textContent = status.title;
                    document.getElementById("it-message").textContent = status.message;

                    var open = document.getElementById("it-open");
                    open.style.display = status.ready ? "" : "none";
                    if (status.url) open.href = status.url;

                    document.getElementById("it-waiting").style.display = status.reload ? "" : "none";

                    var hasLog = !!(status.log || status.logPath);
                    document.getElementById("it-logs").style.display = hasLog ? "" : "none";
                    if (hasLog) {
                        var pre = document.getElementById("it-log");
                        pre.textContent = status.log || "";
                        pre.scrollTop = pre.scrollHeight;
                        document.getElementById("it-log-link").href =
                            "workspace/workspace.php?op=openPlainFileFromPath&fnPath=" + encodeURIComponent(status.logPath);
                    }
                }

                function poll() {
                    fetch(statusUrl, { credentials: "same-origin" })
                        .then(function(response) { return response.json(); })
                        .then(function(status) {
                            render(status);
                            if (status.reload) setTimeout(poll, POLL_MS);
                        })
                        // network hiccup or non-JSON answer (e.g. session expired): keep trying
                        .catch(function() { setTimeout(poll, POLL_MS); });
                }

                var initial = <?= json_encode($status, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
                render(initial);
                if (initial.reload) setTimeout(poll, POLL_MS);
            })();
        </script>

        <?php
        require "../htmlib/footer.inc.php";
        require "../htmlib/js.inc.php";
        ?>
