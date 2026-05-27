<?php

use OpenVRE\EGALinkedAccount;
use OpenVRE\LinkedAccount;
use OpenVRE\Site;
use OpenVRE\SshLinkedAccount;

require __DIR__ . "/../../config/bootstrap.php";

redirectOutside();

if (!$_REQUEST) {
	redirect($GLOBALS['URL']);
} elseif (is_null($_REQUEST['account'])) {
	redirect($_SERVER['HTTP_REFERER']);
}

$accountSite = Site::from($_POST['account']);
$action = $_POST['submitOption'];
unset($_POST['account'], $_POST['submitOption']);
$credentials = array_merge($_POST, ['_id' => $_SESSION['userId']]);
$user = getUserById($_SESSION['userId']);

if ($accountSite === Site::SSH) {
	$linkedAccount = new SshLinkedAccount();
} elseif ($accountSite === Site::EGA) {
	$linkedAccount = new EGALinkedAccount();
} else {
	$linkedAccount = new LinkedAccount($accountSite);
}

if ($action === "updateAccount") {
	updateAccount($linkedAccount, $user->getSecretsId(), $credentials);
} elseif ($action === "clearAccount") {
	removeAccount($linkedAccount);
}
