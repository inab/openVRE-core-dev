<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	$login = $_POST['id'];
	$status = $_POST['userStatus'];
	$user = getUserById($login);
	if ($user['_id']) {
		$newdata = array('$set' => array('Status' => $status));
		$GLOBALS['usersCol']->updateOne(array('_id' => $login), $newdata);
		echo '1';
	} else {
		echo '0';
	}
} else {
	redirect($GLOBALS['URL']);
}
