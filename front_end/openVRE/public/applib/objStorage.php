<?php
header('Content-Type: application/json');

require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\LoggerFactory;

$siteId = $_GET['site'] ?? null;
$action = $_REQUEST['action'] ?? null;

error_log('objStorage DEBUG _REQUEST: ' . json_encode($_REQUEST));
getObjectStorageLogger()->debug('objStorage _REQUEST: ' . print_r($_REQUEST, true));

$site = getObjectStorageSite($siteId);

handleObjectStorageRequest(
    $site,
    $action,
    $_REQUEST
);

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


function handleObjectStorageRequest($site, $action, $params = [])
{
    $siteConfig = $site['storage'] ?? null;

    if (!$siteConfig) {
        return [
            'error' => true,
            'message' => 'Site not found: ' . $site
        ];
    }

    $storageType = strtolower(
        $siteConfig['type'] ?? ''
    );

    switch ($storageType) {

        case 's3':
            require_once __DIR__ . '/../phplib/objStorage_s3.php';
            $message = "Storage site found: $storageType. Handling action: $action";
            getObjectStorageLogger()->debug($message);
            return handleS3StorageRequest(
                $siteConfig,
                $action,
                $params
            );

        case 'swift':
        case 'openstack':
            require_once '/../phplib/objStorage_openstack.php';

            return handleOpenstackStorageRequest(
                $siteConfig,
                $action,
                $params
            );

        default:
            return [
                'error' => true,
                'message' => 'Unsupported storage type: ' . $storageType
            ];
    }
}

function handleOpenstackStorageRequest($storageConfig, $action, $params = [])
{
    require_once __DIR__ . '/../phplib/objStorage_openstack.php';

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

            if (!isset($params['container'])) {
                logError('No container specified.');
            }

            $container = $params['container'];

            getObjectStorageLogger()->info(
                "Main script - received container: $container"
            );

            $swiftClient = getSwiftClient();

            if (!$swiftClient) {
                logError('Failed to obtain Swift client.');
            }

            $files = getContainerFiles(
                $container,
                $swiftClient
            );

            getObjectStorageLogger()->info(
                "Main script - files: " . print_r($files, true)
            );

            echo json_encode($files);
            exit;


        // Download OpenStack file
        case 'downloadFile':

            if (!isset($params['fileName'])) {
                logError('No file specified for download.');
            }

            if (!isset($params['container'])) {
                logError('No container specified for download.');
            }

            $fileName = $params['fileName'];
            $container = $params['container'];

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


function handleS3StorageRequest($storageConfig, $action, $params = [])
{
    switch ($action) {

        // Get S3 containers/buckets
        case 'getS3User':
            try {
                $s3Client = getS3Client($storageConfig);

                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }

                $containers = getS3Containers($s3Client);
                echo json_encode($containers);
                exit;
            } catch (Exception $e) {
                $message = 'Failed to connect to S3: ' . $e->getMessage();
                getObjectStorageLogger()->error($message);
                echo json_encode([
                    'error' => true,
                    'message' => $message
                ]);
                exit;
            }

        // Get files inside an S3 container/bucket
        case 'getContainerFiles':

            if (!isset($params['container'])) {
                logError('No container specified.');
            }
            $container = $params['container'];
            getObjectStorageLogger()->info(
                "Main script - received S3 container: $container"
            );

            try {
                $s3Client = getS3Client($storageConfig);
                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $files = getS3ContainerFiles(
                    $container,
                    $s3Client
                );

                getObjectStorageLogger()->info(
                    "Main script - S3 files: " . print_r($files, true)
                );
                echo json_encode($files);
                exit;
            } catch (Exception $e) {
                $message = 'Failed to access S3 container: ' . $e->getMessage();
                getObjectStorageLogger()->error($message);
                echo json_encode([
                    'error' => true,
                    'message' => $message
                ]);
                exit;
            }

        // Download S3 file
        case 'downloadFile':
            if (!isset($params['fileName'])) {
                logError('No file specified for download.');
            }
            if (!isset($params['container'])) {
                logError('No container specified for download.');
            }
            $fileName = $params['fileName'];
            $container = $params['container'];

            try {
                $s3Client = getS3Client($storageConfig);

                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }

                $fileId = initiateS3FileDownload(
                    $s3Client,
                    $fileName,
                    $container
                );
                getObjectStorageLogger()->info(
                    "S3 file downloaded successfully. File ID: $fileId is present in the workspace."
                );
                echo json_encode([
                    'status' => 'success',
                    'fileId' => $fileId
                ]);

            } catch (Exception $e) {
                $message = 'S3 file download failed: ' . $e->getMessage();
                getObjectStorageLogger()->error($message);
                echo json_encode([
                    'status' => 'error',
                    'message' => $message
                ]);
            }
            exit;
        default:
            logError(
                'Unsupported S3 object storage action: ' . $action
            );
    }
}

/*
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
*/