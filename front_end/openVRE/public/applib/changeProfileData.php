<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	$user = getUserById($_SESSION['userId']);

	if ($user->get_id()) {
		$user->setName(ucfirst($_POST['Name']));
		$user->setSurname(ucfirst($_POST['Surname']));
		$user->setInst($_POST['Inst']);
		$user->setTermsAccepted($_POST['terms']);
		updateUser($user);
		echo '1';
	} else {
		echo '0';
	}
} else {
	redirect($GLOBALS['URL']);
}
