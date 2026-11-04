<?php

// import vendor libs
require_once dirname(__FILE__) . "/../vendor/autoload.php";

use OpenVRE\LoggerFactory;

// Buffer output so the exception handler can discard a half-rendered page.
// Chunked so large file downloads keep streaming instead of filling memory.
ob_start(null, 1024 * 1024);

// Whether redirecting to home/redirect.php would bring the user back into the
// failing request (or cannot work at all), causing a redirect loop
function exceptionRedirectWouldLoop(): bool
{
    // Exception raised before the session or libs were loaded
    if (session_status() !== PHP_SESSION_ACTIVE || !function_exists('checkLoggedIn')) {
        return true;
    }

    // Login/logout flow: redirect.php sends the user straight back through it
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (["/login.php", "/logout.php", "/applib/loginToken.php", "/home/redirect.php"] as $flowScript) {
        if (str_ends_with($script, $flowScript)) {
            return true;
        }
    }

    // No valid user in session: every page would send the user to login again
    try {
        if (!checkLoggedIn()) {
            return true;
        }
    } catch (Throwable) {
        return true;
    }

    // redirect.php sends the user to the currentPage cookie (or home/)
    $target = parse_url($_COOKIE['currentPage'] ?? ($GLOBALS['BASEURL'] . "home/"));
    $request = parse_url(urldecode($_SERVER['REQUEST_URI'] ?? ''));
    $normalize = fn($path) => rtrim(preg_replace('#/index\.php$#', '/', $path ?? ''), '/');

    return $normalize($target['path'] ?? '') === $normalize($request['path'] ?? '')
        && ($target['query'] ?? '') === ($request['query'] ?? '');
}

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

    $errorMessage = $e->getMessage();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Usual case: show the error on the user's page, keeping the session
    if (!headers_sent() && !exceptionRedirectWouldLoop()) {
        $_SESSION['errorData']['Error'][] = $errorMessage;
        redirect($GLOBALS['BASEURL'] . "home/redirect.php");
    }

    // Otherwise render the error in place to avoid a redirect loop
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
