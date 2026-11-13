<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	$user = getUserById($_SESSION['userId']);

	if ($user->get_id()) {
		$user->setName(ucfirst($_POST['name']));
		$user->setSurname(ucfirst($_POST['surname']));
		$user->setInstitution($_POST['institution']);
		$user->setTermsAccepted($_POST['terms']);
		updateUser($user);
		echo '1';
	} else {
		echo '0';
	}
} else {
	redirect($GLOBALS['URL']);
}
