<?php

require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\UserType;


if ($_REQUEST) {
	$user = getUserById(sanitizeString($_REQUEST["id"]));

	if (is_null($user)) {
		$_SESSION['errorData']['Error'][] = "You are trying to remove a non existing user.";
		redirect($GLOBALS['URL'] . 'admin/adminUsers.php');
	}

	if ($user->getType() == UserType::Admin->value) { {
			$_SESSION['errorData']['Error'][] = "You are trying to remove an admin user.";
			redirect($GLOBALS['URL'] . 'admin/adminUsers.php');
		}

		delUser($_REQUEST["id"]);
		redirect($GLOBALS['URL'] . '/admin/adminUsers.php');
	} else {
		redirect($GLOBALS['URL']);
	}
}
