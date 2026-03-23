<?php
require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\NotFoundException;

$isLoggedIn = checkLoggedIn();
if ($isLoggedIn) {
    if (!checkAdmin()) {
        $_SESSION['errorData']['Error'][] = "Cannot impersonate a user. Permission denied.";
        die(0);
    }

    // Load requested user
    if ($_REQUEST['id']) {
        try {
            loadUser($_REQUEST['id'], true);
        } catch (NotFoundException $e) {
            $_SESSION['errorData']['Error'][] = $e->getMessage();
        }
    }
}

redirect("../home/redirect.php");
