<?php

require __DIR__."/../../config/bootstrap.php";
redirectOutside();

$user = getUserById($_SESSION['userId']);
$tools = getTools_ListComplete($user);

foreach($tools as $tool) {

	if($_REQUEST["tool"] == $tool["_id"]) {
		$comb = getInputFilesCombinations($tool);
		break;
	}

}

echo json_encode(explode("~", $comb));

