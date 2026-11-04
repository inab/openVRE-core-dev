<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	$login = $_POST['id'];
	$status = $_POST['userStatus'];
	$user = getUserById($login);
	if ($user->get_id()) {
		$user->setStatus($status);
		modifyUser($login, ['Status' => $status]);
		echo '1';
	} else {
		echo '0';
	}
} else {
	redirect($GLOBALS['URL']);
}
