<?php

require __DIR__ . "/../../config/bootstrap.php";

redirectOutside();

if (! $_REQUEST['uploadType']) {
	$_SESSION['errorData']['getData'][] = "Please specify a source data";
	die(0);
	//redirect($GLOBALS['BASEURL']."/workspace/"); # Bug fix for: TOO LONG REQUEST
}

$user = getUserById($_SESSION['userId']);
if (is_null($user)) {
	$_SESSION['errorData']['getData'][] = "User not found";
	die(0);
}

switch ($_REQUEST['uploadType']) {
	case 'file':
		header("Connection: close");
		getData_fromLocal($user);
		break;

	case 'url':
		getData_fromUrl($user, $_REQUEST['url']);
		break;

	case 'txt':
		echo getData_fromTXT($user);
		break;
	case 'repository':
		$url = $_REQUEST['url'];
		$datatype = $_REQUEST['data_type'] ?? "";
		$filetype = $_REQUEST['filetype'] ?? "";
		$descrip = $_REQUEST['description'] ?? "";
		getData_fromRepository($user, $url, $datatype, $filetype, $descrip);
		break;
	case 'sampleData':
		getData_fromSampleData($user, $_REQUEST);
		break;

	case 'EGA':
		$datasetIds = $_REQUEST['datasetIds'];
		$fileIds = $_REQUEST['fileIds'];
		$filenames = $_REQUEST['displayNames'];
		$fileSizes = $_REQUEST['fileSizes'];
		getData_fromEGA($user, $datasetIds, $fileIds, $filenames, $fileSizes);
		break;

	default:
		die(0);
}
