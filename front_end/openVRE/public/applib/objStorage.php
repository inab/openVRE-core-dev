<?php
header('Content-Type: application/json');

require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\LoggerFactory;

$siteId = $_GET['site'] ?? null;
$action = $_REQUEST['action'] ?? null;

error_log('objStorage DEBUG _REQUEST: ' . json_encode($_REQUEST));
getObjectStorageLogger()->debug('objStorage _REQUEST: ' . print_r($_REQUEST, true));

if ($action === 'debugRequest') {
    echo json_encode([
        'ok' => true,
        'action' => $action,
        'site' => $siteId,
        'request' => $_REQUEST,
        'message' => 'debugRequest reached the general objStorage endpoint.'
    ]);
    exit;
    }



function getObjectStorageLogger()
{
    static $logger = null;

    if ($logger === null) {
        $logger = LoggerFactory::getLogger('Object storage interface');
    }

    return $logger;
}


function logError($errorMessage, $responseText = '')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (is_null($_SESSION['errorData']['Error'])) {
        $_SESSION['errorData']['Error'] = [];
    }

    $_SESSION['errorData']['Error'][] = $errorMessage;

    if (!empty($responseText)) {
        $_SESSION['errorData']['Error'][] = 'Response: ' . $responseText;
    }
    header('Content-Type: application/json');
    // Also echo JSON so JS can see it instantly
    echo json_encode([
        'error' => true,
        'message' => $errorMessage,
        'response' => $responseText
    ]);
    exit;
}

function getObjectStorageSite($siteId)
{
    if (empty($siteId)) {
        $message = 'No storage site specified';
        getObjectStorageLogger()->error($message);
        logError($message);
    }

    $site = $GLOBALS['sitesCol']->findOne([
        '_id' => $siteId,
        'type' => 1
    ]);

    if (!$site) {
        $message = "Storage site not found: $siteId";
        getObjectStorageLogger()->error($message);
        logError($message);
    }

    getObjectStorageLogger()->debug(
        'Storage site loaded: ' . print_r($site, true)
    );

    return $site;
}


$site = getObjectStorageSite($siteId);
$storageType = $site['storage']['type'] ?? null;

switch ($storageType) {
    case 's3':
        require_once __DIR__ . '/../phplib/objStorage_s3.inc.php';
        if ($action === 'listObjects') {
            $objects = listS3Objects($site);

            echo json_encode([
                'status' => 'success',
                'objects' => $objects
            ]);
            exit;
        }
        break;

    case 'openstack':
        require_once __DIR__ . '/../phplib/objStorage_openstack.php';
        // Get OpenStack containers
            switch ($action) {

            // Get OpenStack containers
            case 'getOpenstackUser':

                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                $_SESSION['swiftClient'] = $swiftClient;

                $containers = getContainers($swiftClient);

                echo json_encode($containers);
                exit;


            // Get files inside an OpenStack container
            case 'getContainerFiles':

                if (!isset($_POST['container'])) {
                    logError('No container specified.');
                }

                $container = $_POST['container'];

                getObjectStorageLogger()->info(
                    "Main script - received container: $container"
                );

                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                $files = getContainerFiles($container, $swiftClient);

                getObjectStorageLogger()->info(
                    "Main script - files: " . print_r($files, true)
                );

                echo json_encode($files);
                exit;


            // Download OpenStack file
            case 'downloadFile':

                if (!isset($_POST['fileName'])) {
                    logError('No file specified for download.');
                }

                if (!isset($_POST['container'])) {
                    logError('No container specified for download.');
                }

                $fileName = $_POST['fileName'];
                $container = $_POST['container'];

                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                try {

                    $fileId = initiateFileDownload(
                        $swiftClient,
                        $fileName,
                        $container
                    );

                    getObjectStorageLogger()->info(
                        "File downloaded successfully. File ID: $fileId is present in the workspace."
                    );

                    echo json_encode([
                        'status' => 'success',
                        'fileId' => $fileId
                    ]);

                } catch (Exception $e) {

                    getObjectStorageLogger()->error(
                        "File download failed: " . $e->getMessage()
                    );

                    echo json_encode([
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]);
                }

                exit;


            default:

                logError(
                    'Unsupported OpenStack object storage action: ' . $action
                );
        }
}



// Get user openstack credentials.
if ($_REQUEST['action'] == "getOpenstackUser") {
    $swiftClient = getSwiftClient();
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
    getObjectStorageLogger()->info("Main script - received container: $container");
    $swiftClient = getSwiftClient();

    if (!$swiftClient) {
        logError('Failed to obtain Swift client.');
        echo json_encode(array('error' => 'Failed to obtain Swift client.'));
        exit;
    }
    $files = getContainerFiles($container, $swiftClient);
    getObjectStorageLogger()->info("Main script - files: " . print_r($files, true));

    echo json_encode($files);
    exit;
}

// Download file
if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'downloadFile' && isset($_POST['fileName'])) {
    $fileName = $_POST['fileName']; // Get the file URL (container/filename)
    $container = $_POST['container'];
    $swiftClient = getSwiftClient();

    if (!$swiftClient) {
        logError('Failed to obtain Swift client.');
        echo json_encode(array('error' => 'Failed to obtain Swift client.'));
        exit;
    }

    try {
        $fileId = initiateFileDownload($swiftClient, $fileName, $container);
        getObjectStorageLogger()->info("File downloaded successfully. File ID: " . $fileId . " is present in the workspace.");
        echo json_encode(array('status' => 'success', 'fileId' => $fileId));
    } catch (Exception $e) {
        getObjectStorageLogger()->error("File download failed: " . $e->getMessage());
        echo json_encode(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
