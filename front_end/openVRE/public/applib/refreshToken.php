<?php

require __DIR__."/../../config/bootstrap.php";

$referer = $_SERVER['HTTP_REFERER'] ?? '/';
if (strpos($referer, 'refreshToken.php') !== false) {
    $referer = $_SESSION['lastSafePage'] ?? '/';
}

$force = isset($_REQUEST['force']);

// The refresh token lives in mod_auth_openidc: ask it to renew the access token
// now, then come back to the referer (session.inc syncs the new token)
if ($force && isset($_SERVER['OIDC_access_token'])) {
    \OpenVRE\LoggerFactory::getLogger('Token refresh')->debug(sprintf(
        "Forced token refresh requested by user %s (current token expires %s), returning to %s",
        $_SESSION['userId'] ?? 'unknown',
        date('c', (int)($_SERVER['OIDC_access_token_expires'] ?? 0)),
        $referer
    ));
    redirect($GLOBALS['URL'] . 'redirect_uri?' . http_build_query([
        'refresh'      => $referer,
        'access_token' => $_SERVER['OIDC_access_token'],
    ]));
    exit;
}

$r = refresh_token($force);
if (!$r) {
    $_SESSION['errorData']['Error'][] = "Your session has expired. Please log in again.";
    redirect($GLOBALS['URL'] . 'redirect_uri?logout=' . $GLOBALS['URL_logout']);
    exit;
}

redirect($referer);
