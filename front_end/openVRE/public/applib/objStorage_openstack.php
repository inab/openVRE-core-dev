<?php
header('Content-Type: application/json');

require __DIR__."/../../config/bootstrap.php";

function logError($errorMessage, $responseText = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['errorData']['Error'])) {
        $_SESSION['errorData']['Error'] = [];
    }

    $_SESSION['errorData']['Error'][] = $errorMessage;

    if (!empty($responseText)) {
        $_SESSION['errorData']['Error'][] = 'Response: ' . $responseText;
    }

    // Also echo JSON so JS can see it instantly
    echo json_encode([
        'error' => true,
        'message' => $errorMessage,
        'response' => $responseText
    ]);
    exit;
}

function logSuccess($successMessage) {
    session_start();
    if (!isset($_SESSION['errorData']['Info'])) {
        $_SESSION['errorData']['Info'][] = ['Info' => []];
    }
    $_SESSION['errorData']['Info'][] = $successMessage;
}

if ($_REQUEST) {
    if ($_REQUEST['action'] == "logError" && isset($_POST['errorMessage'])) {
        $errorMessage = $_POST['errorMessage'];
        $responseText = isset($_POST['responseText']) ? $_POST['responseText'] : '';
        logError($errorMessage, $responseText);
        echo json_encode(array('status' => 'error logged'));
        exit;
    }

    if ($_REQUEST['action'] == "logSuccess" && isset($_POST['successMessage'])) {
        $successMessage = $_POST['successMessage'];
        logSuccess($successMessage);
        echo json_encode(array('status' => 'success logged'));
        exit;
    }

    // Get user openstack credentials.
    if ($_REQUEST['action'] == "getOpenstackUser") {
        $vaultUrl = $GLOBALS['vaultUrl'];
        #$accessToken = $_SESSION['userToken']['access_token'];
        $accessToken = json_decode($_SESSION['userVaultInfo']['jwt'], true)["access_token"];
        $vaultRolename = $_SESSION['userVaultInfo']['vaultRolename'];
        $username = $_POST['username'];

        // Obtain the SwiftClient directly:
        $swiftClient = getSwiftClient($vaultUrl, $accessToken, $vaultRolename, $username);

        if (!$swiftClient) {
            logError('Failed to obtain Swift client.');
            echo json_encode(array('error' => 'Failed to obtain Swift client.'));
            exit;
        }

        $_SESSION['swiftClient'] = $swiftClient;

        $containers = getContainers($swiftClient);

        echo json_encode($containers);
        exit;
    }

    // Get container files
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == "getContainerFiles" && isset($_POST['container'])) {
	    $container = $_POST['container'];
	    error_log("Main script - received container: $container");
	    $vaultUrl = $GLOBALS['vaultUrl'];
	    $accessToken = $_SESSION['userToken']['access_token'];
	    $vaultRolename = $_SESSION['userVaultInfo']['vaultRolename'];
	    $username = $_POST['username'];
    
	    $swiftClient = getSwiftClient($vaultUrl, $accessToken, $vaultRolename, $username);
    
	    if (!$swiftClient) {
		    logError('Failed to obtain Swift client.');
		    echo json_encode(array('error' => 'Failed to obtain Swift client.'));
		    exit;
	    }
	    $files = getContainerFiles($container, $swiftClient);
	    error_log("Main script - files: " . print_r($files, true));

	    echo json_encode($files);
	    exit;
    }

    // Download file
    if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'downloadFile' && isset($_POST['fileName'])) {
        $fileName = $_POST['fileName']; // Get the file URL (container/filename)
        $container = $_POST['container'];
        $vaultUrl = $GLOBALS['vaultUrl'];
        $accessToken = $_SESSION['userToken']['access_token'];
        $vaultRolename = $_SESSION['userVaultInfo']['vaultRolename'];
        $username = $_POST['username'];

        // Obtain the SwiftClient directly:
        $swiftClient = getSwiftClient($vaultUrl, $accessToken, $vaultRolename, $username);

        if (!$swiftClient) {
            logError('Failed to obtain Swift client.');
            echo json_encode(array('error' => 'Failed to obtain Swift client.'));
            exit;
        }

        $success = initiateFileDownload($swiftClient, $fileName, $container);
        if (!$success) {
            logError('File download failed.');
            echo json_encode(array('error' => 'File download failed.'));
            exit;
        } else {
            logSuccess('File downloaded successfully. File ID: ' . $success['fileId'] . ' is present in the workspace.');
            echo json_encode($success);
            exit;
        }
    }
}

echo '{}';
exit;
?>

