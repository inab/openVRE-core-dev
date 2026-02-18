<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	$user = getUserById($_SESSION['UserId']);

	if ($user['_id']) {
		$newdata = array('$set' => array(
			'Surname' => ucfirst($_POST['Surname']),
			'Name'     => ucfirst($_POST['Name']),
			'Inst'     => $_POST['Inst'],
			'terms'    => $_POST['terms']
		));

		$GLOBALS['usersCol']->updateOne(array('_id' => $_SESSION['UserId']), $newdata);
		
		$user->setName(ucfirst($_POST['Name']));
		$user->setSurname(ucfirst($_POST['Surname']));
		$user->setInst($_POST['Inst']);
		$user->setTerms($_POST['terms']);

		$_SESSION['lastUserLogin'] = $user->getLastLogin();

		echo '1';
	} else {
		echo '0';
	}
} else {
	redirect($GLOBALS['URL']);
}
