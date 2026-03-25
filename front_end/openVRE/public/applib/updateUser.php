<?php

require __DIR__ . "/../../config/bootstrap.php";

if ($_POST) {
	//TODO check compulsory field
	if (!$_POST['surname'] || !$_POST['name']) {
		$_SESSION['errorData']['Error'][] = "Name and Surname are compulsory fields";
		redirect($_SERVER['HTTP_REFERER']);
	}

	if (($_POST['type'] == 1) && (!$_POST['tools'])) {
		$_SESSION['errorData']['Error'][] = "If Type of user is Tool Dev, you should select at least one tool.";
		redirect($_SERVER['HTTP_REFERER']);
	}

	$login = $_POST['email'];
	$user = getUserById($login);

	if ($user->get_id()) {
		modifyUser($login, ['surname' => ucfirst($_POST['surname']), 'name' => ucfirst($_POST['name']), 'institution' => $_POST['institution'], 'diskQuota' => $_POST['diskQuota'] * 1024 * 1024 * 1024, 'type' => $_POST['type'], 'developedTools' => $_POST['tools']]);
		$_SESSION['errorData']['Info'][] = "User info successfully updated.";
		redirect($_SERVER['HTTP_REFERER']);
	} else {
		$_SESSION['errorData']['Error'][] = "Non existing user, please check your form";
		redirect($_SERVER['HTTP_REFERER']);
	}
} else {
	redirect($GLOBALS['URL']);
}
