<?php

// import vendor libs
require_once dirname(__FILE__) . "/../vendor/autoload.php";

use OpenVRE\LoggerFactory;

// Buffer output so the exception handler can discard a half-rendered page.
// Chunked so large file downloads keep streaming instead of filling memory.
ob_start(null, 1024 * 1024);

set_exception_handler(function (Throwable $e) {
    $errorId = bin2hex(random_bytes(6));

    $logger = LoggerFactory::getLogger("Bootstrap interface");
    $logger->error($e->getTraceAsString());
    $logger->error(sprintf(
        "[%s] %s in %s:%d",
        $errorId,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    // Render the error in place: redirecting sends the user back to the failing
    // page (via the currentPage cookie) and ends up in a redirect loop
    $errorMessage = $e->getMessage();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

    if (headers_sent()) {
        // Part of the page already reached the browser: append a notice instead
        // of a second HTML document, which would break the existing layout
        printf(
            '<div style="position:fixed;top:0;left:0;right:0;z-index:99999;padding:15px;background:#fbe1e3;color:#e73d4a;font:14px sans-serif;text-align:center;">'
                . 'Something went wrong: %s (reference: <code>%s</code>)</div>',
            htmlspecialchars($errorMessage),
            $errorId
        );
        exit(1);
    }

    http_response_code(500);
    if ($isAjax) {
        header("Content-Type: application/json");
        echo json_encode(["error" => $errorMessage, "errorId" => $errorId]);
    } else {
        header("Content-Type: text/html; charset=utf-8");
        include dirname(__FILE__) . "/../public/htmlib/fatalError.inc.php";
    }
    exit(1);
});

// set up app settings
require_once dirname(__FILE__) . "/../config/globals.inc.php";

// import local libs
foreach (glob(dirname(__FILE__) . "/../public/phplib/*.php") as $lib) {
    require_once $lib;
}

// initialize session
require_once dirname(__FILE__) . "/../public/phplib/session.inc";
