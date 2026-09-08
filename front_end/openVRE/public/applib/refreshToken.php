<?php

require __DIR__."/../../config/bootstrap.php";

$referer = $_SERVER['HTTP_REFERER'] ?? '/';
if (strpos($referer, 'refreshToken.php') !== false) {
    $referer = $_SESSION['lastSafePage'] ?? '/';
}

$force = isset($_REQUEST['force']);

$r = refresh_token($force);
if (!$r) {
    $_SESSION['errorData']['Error'][] = "Your session has expired. Please log in again.";
    redirect($GLOBALS['BASEURL'] . '/logout.php');
    exit;
}

redirect($referer);
