<?php
require __DIR__ . "/../config/bootstrap.php";

// Check if PHP session exists
$r = checkLoggedIn();

// Recover guest user
if (isset($_REQUEST['id']) && $_REQUEST['id']) {
    if (!getUserById($_REQUEST['id'])) {
        unset($_REQUEST['id']);
    }

    $r = loadUser($_REQUEST['id'], false);
} else {
    // Load WS with sample data, if tool requested
    if (isset($_REQUEST['from']) && $_REQUEST['from']) {
        $tool = getTool_fromId($_REQUEST['from'], 1);
        if (!isset($tool['_id'])) {
            $_SESSION['userData']['Warning'][] = "Cannot load '" . $_REQUEST['from'] . "'. Tool not found";
            redirect("../home/redirect.php");
        }

        if (isset($_REQUEST['sd'])) {
            $sampleData = $_REQUEST['sd'];
        } elseif (isset($tool['$sampleData'])) {
            $sampleData = $tool['$sampleData'];
        } else {
            $sampleData = $tool['_id'];
        }
    }

    // Get access creating an a anonymous guest account
    $r = createUserAnonymous($sampleData);
    if (!$r) {
        exit('Login error: cannot create anonymous VRE user');
    }

    // Redirect to WS with a welcome modal
    if (isset($_REQUEST['from']) && $_REQUEST['from']) {
        redirect("../workspace/?from=" . $_REQUEST['from']);
    }
}

redirect($GLOBALS['BASEURL'] . "home/redirect.php");
