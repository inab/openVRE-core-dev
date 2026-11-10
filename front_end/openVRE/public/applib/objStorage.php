<?php
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require __DIR__ . "/../../config/bootstrap.php";

use OpenVRE\LoggerFactory;

$siteId = $_REQUEST['site'] ?? $_GET['site'] ?? null;
$action = $_REQUEST['action'] ?? null;

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

    return $site;
}

function handleObjectStorageRequest($site, $action, $params = [])
{
    $siteConfig = $site['storage'] ?? null;

    if (!$siteConfig) {
        echo json_encode([
            'error' => true,
            'message' => 'Site not found: ' . $site
        ]);
        return [
            'error' => true,
            'message' => 'Site not found: ' . $site
        ];
    }

    $storageType = strtolower($siteConfig['type'] ?? '');

    switch ($storageType) {
        case 's3':
            require_once __DIR__ . '/../phplib/objStorage_s3.php';
            return handleS3StorageRequest($siteConfig, $action, $params);

        case 'swift':
        case 'openstack':
            require_once __DIR__ . '/../phplib/objStorage_openstack.php';
            return handleOpenstackStorageRequest($siteConfig, $action, $params);

        default:
            $payload = [
                'error' => true,
                'message' => 'Unsupported storage type: ' . $storageType
            ];
            echo json_encode($payload);
            return $payload;
    }
}

function handleOpenstackStorageRequest($storageConfig, $action, $params = [])
{
    require_once __DIR__ . '/../phplib/objStorage_openstack.php';

    try {
        switch ($action) {
            case 'getOpenstackUser':
                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                $_SESSION['swiftClient'] = $swiftClient;
                $payload = getContainers($swiftClient);
                echo json_encode($payload);
                return $payload;

            case 'getContainerFiles':
                $container = trim((string)($params['container'] ?? ''));
                if ($container === '') {
                    logError('No container specified.');
                }

                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                $payload = getContainerFiles($container, $swiftClient);

                echo json_encode($payload);
                return $payload;

            case 'downloadFile':
                $fileName = trim((string)($params['fileName'] ?? ''));
                $container = trim((string)($params['container'] ?? ''));

                if ($fileName === '') {
                    logError('No file specified for download.');
                }

                if ($container === '') {
                    logError('No container specified for download.');
                }

                $swiftClient = getSwiftClient();

                if (!$swiftClient) {
                    logError('Failed to obtain Swift client.');
                }

                $fileId = initiateFileDownload(
                    $swiftClient,
                    $fileName,
                    $container
                );

                $payload = [
                    'status' => 'success',
                    'fileId' => $fileId
                ];

                echo json_encode($payload);
                return $payload;

            default:
                logError('Unsupported OpenStack object storage action: ' . $action);
        }
    } catch (Throwable $e) {
        getObjectStorageLogger()->error(
            "File download failed: " . $e->getMessage()
        );

        $payload = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];

        echo json_encode($payload);
        return $payload;
    }
}

function handleS3StorageRequest($storageConfig, $action, $params = [])
{
    try {
        switch ($action) {
            case 'getS3User':
                $s3Client = getS3Client($storageConfig);
                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $payload = getS3Containers($s3Client);
                echo json_encode($payload);
                return $payload;

            case 'getContainerFiles':
                $container = trim((string)($params['container'] ?? ''));
                if ($container === '') {
                    logError('No container specified.');
                }
                $s3Client = getS3Client($storageConfig);
                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $payload = getS3ContainerFiles($container, $s3Client);
                echo json_encode($payload);
                return $payload;

            case 'downloadFile':
                $fileName = trim((string)($params['fileName'] ?? ''));
                $container = trim((string)($params['container'] ?? ''));
                if ($fileName === '') {
                    logError('No file name specified.');
                }
                if ($container === '') {
                    logError('No container specified.');
                }
                $s3Client = getS3Client($storageConfig);

                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $payload = [
                    'status' => 'success',
                    'fileId' => initiateS3FileDownload(
                        $s3Client,
                        $fileName,
                        $container
                    )
                ];
                echo json_encode($payload);
                return $payload;

            case 'downloadDirectory':
                $dirPath = trim((string)($params['dirPath'] ?? ''));
                $container = trim((string)($params['container'] ?? ''));
                if ($dirPath === '') {
                    logError('No directory path specified.');
                }
                if ($container === '') {
                    logError('No container specified.');
                }
                $s3Client = getS3Client($storageConfig);
                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $payload = initiateS3DirectoryDownload(
                    $s3Client,
                    $dirPath,
                    $container
                );
                echo json_encode($payload);
                return $payload;

            case 'downloadDirectoryNewWorkspace':
                $dirPath = trim((string)($params['dirPath'] ?? ''));
                $container = trim((string)($params['container'] ?? ''));
                $projectName = trim((string)($params['projectName'] ?? $container));
                if ($dirPath === '') {
                    logError('No directory path specified.');
                }
                if ($container === '') {
                    logError('No container specified.');
                }
                $s3Client = getS3Client($storageConfig);
                if (!$s3Client) {
                    logError('Failed to obtain S3 client.');
                }
                $payload = initiateS3DirectoryDownloadInNewWorkspace(
                    $s3Client,
                    $dirPath,
                    $container,
                    $projectName
                );
                echo json_encode($payload);
                return $payload;

            default:
                logError('Unsupported S3 object storage action: ' . $action);
        }
    } catch (Throwable $e) {
        $message = 'Failed to access S3 storage: ' . $e->getMessage();
        getObjectStorageLogger()->error($message);

        $payload = [
            'error' => true,
            'message' => $message
        ];

        echo json_encode($payload);
        return $payload;
    }
}