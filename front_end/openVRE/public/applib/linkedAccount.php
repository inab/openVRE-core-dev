<?php

require __DIR__."/../../config/bootstrap.php";

redirectOutside();

// Check query
if(!$_REQUEST){
	redirect($GLOBALS['URL']);

}elseif (!isset($_REQUEST['account'])) {
	redirect($_SERVER['HTTP_REFERER']);
}

$siteId = $_REQUEST['site_id'] ?? null;

addUserLinkedAccount($_POST['account'], $_POST['action'], $_SESSION['User']['_id'], $siteId, $_POST);

?>
