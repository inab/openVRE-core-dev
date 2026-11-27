<?php

require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\Site;

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

	case 'remote':
		header('Content-Type: application/json');
		$source = Site::tryFrom($_POST['source'] ?? '');
		$files = json_decode($_POST['files'] ?? '', true);
		if (!$source?->isRemote() || !is_array($files)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid remote source or file list']);
			break;
		}

		try {
			$uploadedFileIds = getData_fromRemote($user, $source, $files);
		} catch (InvalidArgumentException $e) {
			http_response_code(400);
			echo json_encode(['error' => $e->getMessage()]);
			break;
		}

		echo json_encode(['fileIds' => $uploadedFileIds]);
		break;

	default:
		die(0);
}
