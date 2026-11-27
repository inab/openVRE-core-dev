<?php

//
// start or resume PHP session restarting timeout

// set session timeout
$timeout = (isset($GLOBALS['TIMEOUT'])?$GLOBALS['TIMEOUT']:3600); 

// ..for server storing session data
ini_set('session.gc_maxlifetime', $timeout);

// ..for client remembering session id
session_set_cookie_params($timeout);

// start or resume session
session_start();

$_SESSION['VREtimeout'] = time();

// mod_auth_openidc renews the access token on its own (OIDCRefreshAccessTokenBeforeExpiry);
// keep the logged-in user's session copy in sync with the one it forwards
$oidcAccessToken = $_SERVER['OIDC_access_token'] ?? null;
if ($oidcAccessToken && isset($_SESSION['userToken'])
    && $_SESSION['userToken']->getToken() !== $oidcAccessToken) {
    \OpenVRE\LoggerFactory::getLogger('Session')->debug(sprintf(
        "Access token renewed by mod_auth_openidc for user %s: expiry %s -> %s",
        $_SESSION['userId'] ?? 'unknown',
        date('c', (int)$_SESSION['userToken']->getExpires()),
        date('c', (int)($_SERVER['OIDC_access_token_expires'] ?? 0))
    ));
    $_SESSION['userToken'] = new \League\OAuth2\Client\Token\AccessToken([
        'access_token' => $oidcAccessToken,
        'expires'      => (int)($_SERVER['OIDC_access_token_expires'] ?? 0),
    ]);
}
unset($oidcAccessToken);
