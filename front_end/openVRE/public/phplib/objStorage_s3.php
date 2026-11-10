<?php

use Aws\S3\S3Client;
use OpenVRE\LoggerFactory;
use OpenVRE\Site;
use OpenVRE\SwiftClient;
use OpenVRE\VaultClientFactory;


function getObjectStorageS3Logger()
{
	static $logger = null;

	if ($logger === null) {
		$logger = LoggerFactory::getLogger('Object storage interface');
	}

	return $logger;
}

function getS3Client(array $site)
{
    getObjectStorageS3Logger()->debug('Initializing S3 client');
    getObjectStorageS3Logger()->debug('Site config: ' . json_encode($site));

    $endpoint = $site['endpoint'] ?? null;
    $bucket = $site['bucket'] ?? null;
    
    if (!$endpoint) {
        throw new Exception('S3 endpoint not configured');
    }
    
    getObjectStorageS3Logger()->debug('Endpoint: ' . $endpoint);
    getObjectStorageS3Logger()->debug('Bucket: ' . $bucket);

    $credentials = getS3Credentials($site);
    getObjectStorageS3Logger()->debug('S3 credentials obtained: ' . json_encode($credentials));

    getObjectStorageS3Logger()->debug(
        'Creating S3Client with endpoint: ' . $endpoint
    );

    try {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => $credentials
        ]);
        
        getObjectStorageS3Logger()->debug('S3Client created successfully');
        
        // Test connection by attempting to list buckets
        getObjectStorageS3Logger()->debug('Testing S3 connection by listing buckets...');
        $bucketsResult = $s3Client->listBuckets();
        $bucketsArray = $bucketsResult->toArray();
        $bucketNames = [];

        if (isset($bucketsArray['Buckets']) && is_array($bucketsArray['Buckets'])) {
            foreach ($bucketsArray['Buckets'] as $bucketInfo) {
                $bucketNames[] = $bucketInfo['Name'] ?? null;
            }
        }

        getObjectStorageS3Logger()->debug('Raw ListBuckets result: ' . json_encode($bucketsArray, JSON_PRETTY_PRINT));
        getObjectStorageS3Logger()->debug('Bucket names parsed from ListBuckets: ' . json_encode(array_filter($bucketNames)));

        if (empty($bucketNames)) {
            getObjectStorageS3Logger()->warning('No buckets returned by S3 ListBuckets. This can mean the Seaweed endpoint is reachable but the bucket list is empty, or the credentials/identity do not see those buckets.');
        }

        getObjectStorageS3Logger()->debug('S3 connection successful. Buckets: ' . json_encode($bucketsArray));
        
        return $s3Client;
    } catch (Exception $e) {
        getObjectStorageS3Logger()->error(
            'Failed to create/connect S3 client: ' . $e->getMessage()
        );
        throw $e;
    }
}

function getS3Credentials(array $site)
{
    $credentialRef = $site['credential_ref'] ?? null;
    
    getObjectStorageS3Logger()->debug('Credential ref: ' . $credentialRef);
    
    // TODO: replace with actual credential_ref/OpenBao lookup
    // For now, using placeholder credentials
    return [
        'key' => 'admin',
        'secret' => 'secret'
    ];
}

function getS3Containers($s3Client)
{
    getObjectStorageS3Logger()->info(
        'Getting S3 buckets'
    );

    if (!$s3Client) {
        return [
            'error' => true,
            'message' => 'Invalid S3 client.'
        ];
    }

    try {
        $bucketsResult = $s3Client->listBuckets();
        $bucketsArray = $bucketsResult->toArray();
        $bucketNames = [];

        if (isset($bucketsArray['Buckets']) && is_array($bucketsArray['Buckets'])) {
            foreach ($bucketsArray['Buckets'] as $bucketInfo) {
                if (!empty($bucketInfo['Name'])) {
                    $bucketNames[] = $bucketInfo['Name'];
                }
            }
        }

        getObjectStorageS3Logger()->debug('Parsed bucket list from AWS SDK: ' . json_encode($bucketNames));

        if (empty($bucketNames)) {
            return [
                'status' => 'success',
                'containers' => []
            ];
        }

        return [
            'status' => 'success',
            'containers' => $bucketNames
        ];
    } catch (Exception $e) {
        getObjectStorageS3Logger()->error('Failed to list S3 buckets: ' . $e->getMessage());
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

function getS3ContainerFiles($container, $s3Client)
{
    getObjectStorageS3Logger()->info('Getting S3 files for bucket: ' . $container);

    if (!$s3Client || empty($container)) {
        return [
            'error' => true,
            'message' => 'Invalid S3 client or container name.'
        ];
    }

    try {
        $result = $s3Client->listObjectsV2([
            'Bucket' => $container,
            'MaxKeys' => 1000
        ]);

        $items = $result->get('Contents', []);
        $files = [];

        foreach ($items as $item) {
            $key = $item['Key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }

            $files[] = [
                'name' => $key,
                'path' => $key,
                'size' => (int)($item['Size'] ?? 0),
                'LastModified' => $item['LastModified'] ?? null,
                'isDir' => false
            ];
        }
        getObjectStorageS3Logger()->debug('Parsed S3 files: ' . json_encode($files));
        return [
            'status' => 'success',
            'files' => $files
        ];
    } catch (Exception $e) {
        getObjectStorageS3Logger()->error('Failed to list S3 container files: ' . $e->getMessage());
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

#Function for donwloading a file from S3 and registering it in the workspace.
function initiateS3FileDownload($s3Client, $fileName, $container)
{
    if (!$s3Client || empty($container) || empty($fileName)) {
        throw new InvalidArgumentException('Invalid S3 client, bucket, or file name.');
    }
    $dataDirPath = getAttr_fromGSFileId($_SESSION['User']['dataDir'], 'path');
    $wd = $dataDirPath . '/uploads';
    $wdP = $GLOBALS['dataDir'] . '/' . $wd;
    getObjectStorageS3Logger()->debug('S3 file download target workspace: dataDirPath=' . $dataDirPath . ', wd=' . $wd . ', wdP=' . $wdP);
    if (!is_dir($wdP) && !mkdir($wdP, 0775, true)) {
        $message = 'Failed to create download working directory: ' . $wdP;
        getObjectStorageS3Logger()->error($message);
        throw new UnexpectedValueException($message);
    }
    $cleanFileName = ltrim((string)$fileName, '/');
    $basename = basename($cleanFileName);
    $fullPath = $wdP . '/' . $basename;
    getObjectStorageS3Logger()->debug('S3 file download final local path: ' . $fullPath);
    try {
        $result = $s3Client->getObject([
            'Bucket' => $container,
            'Key' => $cleanFileName,
        ]);
        $body = $result['Body'] ?? null;
        getObjectStorageS3Logger()->debug('S3 getObject returned body present: ' . ($body !== null ? 'yes' : 'no'));
        if ($body === null) {
            throw new RuntimeException('S3 object body is empty for key: ' . $cleanFileName);
        }
        $contents = $body->getContents();
        getObjectStorageS3Logger()->debug('S3 download content length: ' . strlen((string)$contents));
        if ($contents === false || $contents === null) {
            throw new RuntimeException('Failed to read S3 object contents for key: ' . $cleanFileName);
        }
        if (file_put_contents($fullPath, $contents) === false) {
            throw new RuntimeException('Failed to write downloaded file to: ' . $fullPath);
        }
        chmod($fullPath, 0666);
        getObjectStorageS3Logger()->info('S3 file written to filesystem: ' . $fullPath . ' exists=' . (file_exists($fullPath) ? 'yes' : 'no'));
        $insertData = [
            'owner' => $_SESSION['User']['id'],
            'size' => filesize($fullPath),
            'mtime' => new MongoDB\BSON\UTCDateTime(filemtime($fullPath) * 1000)
        ];
        $metaData = ['validated' => true];
        $registeredPath = "$wd/$basename";
        getObjectStorageS3Logger()->debug('Registering S3 file in Mongo: local path=' . $registeredPath . ', filesystem path=' . $fullPath);
        $fnId = uploadGSFileBNS($registeredPath, $fullPath, $insertData, $metaData, false);
        if ($fnId == '0') {
            $errorMsg = 'Error occurred while registering the downloaded S3 file';
            getObjectStorageS3Logger()->error($errorMsg);
            return ['status' => 'error', 'message' => $errorMsg];
        }
        $registeredFile = getGSFileId_fromPath($registeredPath, false);
        getObjectStorageS3Logger()->info('S3 file registered successfully with ID: ' . $fnId . ', mongo path lookup=' . ($registeredFile ?: 'not-found') . ', validated=' . ($metaData['validated'] ? 'true' : 'false'));
        return $fnId;
    } catch (Exception $e) {
        getObjectStorageS3Logger()->error('Failed to download S3 file: ' . $e->getMessage());
        throw $e;
    }
}

#Function for ensuring that a workspace directory is registered in the MongoDB collection, creating it if necessary.
function ensureS3WorkspaceDirRegistered($workspaceRelativeDir)
{
    if (empty($workspaceRelativeDir)) {
        return;
    }
    $normalizedDir = ltrim((string)$workspaceRelativeDir, '/');
    if ($normalizedDir === '') {
        return;
    }
    $dirId = getGSFileId_fromPath($normalizedDir, 0);
    if (is_null($dirId)) {
        $dirId = createGSDirBNS($normalizedDir, 0);
    }
    if (!isGSDirBNS($GLOBALS['filesCol'], $dirId)) {
        throw new UnexpectedValueException('Registered workspace path is not a directory: ' . $normalizedDir);
    }
}

#Function for downloading a directory from S3 and registering its contents in the current workspace.
function initiateS3DirectoryDownload($s3Client, $dirPath, $container)
{
    getObjectStorageS3Logger()->info('Starting S3 directory import: bucket=' . $container . ', prefix=' . $dirPath);
    if (!$s3Client || empty($container) || empty($dirPath)) {
        throw new InvalidArgumentException('Invalid S3 client, bucket, or directory prefix.');
    }
    $dataDirPath = getAttr_fromGSFileId($_SESSION['User']['dataDir'], 'path');
    $wd = $dataDirPath . '/repository';
    $wdP = $GLOBALS['dataDir'] . '/' . $wd;
    if (!is_dir($wdP) && !mkdir($wdP, 0775, true)) {
        $message = 'Failed to create download working directory: ' . $wdP;
        getObjectStorageS3Logger()->error($message);
        throw new UnexpectedValueException($message);
    }
    $cleanPrefix = trim((string)$dirPath, '/');
    $targetDir = rtrim($cleanPrefix, '/');
    $targetLabel = $targetDir === '' ? 'root' : basename($targetDir);
    $targetLocalDir = $wdP . '/' . $targetLabel;
    if (!is_dir($targetLocalDir) && !mkdir($targetLocalDir, 0775, true)) {
        $message = 'Failed to create import directory: ' . $targetLocalDir;
        getObjectStorageS3Logger()->error($message);
        throw new UnexpectedValueException($message);
    }
    $projectWorkspaceDir = $wd . '/' . $targetLabel;
    ensureS3WorkspaceDirRegistered($projectWorkspaceDir);
    try {
        $result = $s3Client->listObjectsV2([
            'Bucket' => $container,
            'Prefix' => $cleanPrefix,
            'MaxKeys' => 1000
        ]);
        $items = $result->get('Contents', []);
        getObjectStorageS3Logger()->debug('S3 directory listing returned ' . count($items) . ' objects for prefix ' . $cleanPrefix);
        $importedCount = 0;
        foreach ($items as $item) {
            $key = $item['Key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            if (substr($key, -1) === '/' || $key === $cleanPrefix) {
                continue;
            }
            $relativeKey = ltrim(substr($key, strlen($cleanPrefix)), '/');
            if ($relativeKey === '') {
                continue;
            }
            $localRelativePath = $targetLabel . '/' . $relativeKey;
            $localFullPath = $wdP . '/' . $localRelativePath;
            $localDir = dirname($localFullPath);
            if (!is_dir($localDir) && !mkdir($localDir, 0775, true)) {
                $message = 'Failed to create directory for S3 import: ' . $localDir;
                getObjectStorageS3Logger()->error($message);
                throw new UnexpectedValueException($message);
            }
            $workspaceParentDir = dirname($wd . '/' . $localRelativePath);
            if ($workspaceParentDir !== '.') {
                ensureS3WorkspaceDirRegistered($workspaceParentDir);
            }
            $object = $s3Client->getObject([
                'Bucket' => $container,
                'Key' => $key,
            ]);
            $body = $object['Body'] ?? null;
            if ($body === null) {
                getObjectStorageS3Logger()->warning('Skipping empty S3 object: ' . $key);
                continue;
            }
            $contents = $body->getContents();
            if ($contents === false || $contents === null) {
                getObjectStorageS3Logger()->warning('Skipping unreadable S3 object: ' . $key);
                continue;
            }
            if (file_put_contents($localFullPath, $contents) === false) {
                throw new RuntimeException('Failed to write imported S3 object to: ' . $localFullPath);
            }
            chmod($localFullPath, 0666);
            getObjectStorageS3Logger()->info('Written imported file to disk: ' . $localFullPath . ' size=' . filesize($localFullPath));
            $insertData = [
                'owner' => $_SESSION['User']['id'],
                'size' => filesize($localFullPath),
                'mtime' => new MongoDB\BSON\UTCDateTime(filemtime($localFullPath) * 1000)
            ];
            $metaData = ['validated' => true];
            $registeredPath = $wd . '/' . $localRelativePath;
            $fileId = uploadGSFileBNS($registeredPath, $localFullPath, $insertData, $metaData, false);
            if ($fileId == '0') {
                $message = 'Error occurred while registering imported S3 file: ' . $localRelativePath;
                getObjectStorageS3Logger()->error($message);
                throw new RuntimeException($message);
            }
            $importedCount++;
            getObjectStorageS3Logger()->debug('Registered imported file into workspace: path=' . $registeredPath . ', fileId=' . $fileId);
        }
        $_SESSION['errorData']['Info'][] = 'Directory "' . $dirPath . '" imported and registered in the current workspace. Files imported: ' . $importedCount . '.';
        getObjectStorageS3Logger()->info('S3 directory import completed. Imported files: ' . $importedCount);
        return [
            'status' => 'success',
            'message' => 'Imported directory "' . $dirPath . '" into the workspace.',
            'redirect' => $GLOBALS['BASEURL'] . 'workspace/?workspace=' . urlencode($_SESSION['User']['dataDir']),
            'importedCount' => $importedCount
        ];
    } catch (Exception $e) {
        getObjectStorageS3Logger()->error('Failed to import S3 directory: ' . $e->getMessage());
        throw $e;
    }
}

#Function for downloading a directory from S3 and registering its contents in a new workspace.
function registerRepositoryDirectoryFromS3($s3Client, $container, $dirPath, $projectName = null)
{
    if (!$s3Client || empty($container) || empty($dirPath)) {
        throw new InvalidArgumentException('Invalid S3 client, bucket, or directory prefix.');
    }
    $effectiveProjectName = trim((string)($projectName ?: $container ?: 's3-import'));
    $effectiveProjectName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $effectiveProjectName);
    $effectiveProjectName = trim($effectiveProjectName, '-_.');
    if ($effectiveProjectName === '') {
        $effectiveProjectName = 's3-import';
    }
    $existingProject = null;
    $existingProjects = getGSFiles_filteredBy([
        'owner' => $_SESSION['User']['id'],
        'is_a' => 'project',
        'name' => $effectiveProjectName
    ], false);
    if (!empty($existingProjects)) {
        $existingProject = reset($existingProjects);
    }
    if ($existingProject) {
        $dataDirId = $existingProject['_id'];
        $existingProjectPath = $existingProject['path'] ?? null;
        $projectCode = $existingProjectPath ? basename($existingProjectPath) : $_SESSION['User']['activeProject'];
        $_SESSION['User']['activeProject'] = $projectCode;
        $_SESSION['User']['projectName'] = $effectiveProjectName;
        $_SESSION['User']['sourceBucket'] = $container;
        $_SESSION['User']['dataDir'] = $dataDirId;
        modifyUser($_SESSION['User']['_id'], 'activeProject', $projectCode);
        modifyUser($_SESSION['User']['_id'], 'projectName', $effectiveProjectName);
        modifyUser($_SESSION['User']['_id'], 'sourceBucket', $container);
        modifyUser($_SESSION['User']['_id'], 'dataDir', $dataDirId);
        $_SESSION['User'] = getUserById($_SESSION['User']['_id']);
    } else {
        $workspaceProjectCode = createLabel_proj();
        $userHome = $_SESSION['User']['id'];
        $dataDirId = prepUserWorkSpace(
            $userHome,
            $workspaceProjectCode,
            $GLOBALS['sampleData_default'],
            [
                'name'        => $effectiveProjectName,
                'description' => 'Created automatically from S3 bucket ' . $container,
                'keywords'    => 's3,import',
                'source_bucket' => $container,
                'source_prefix' => $dirPath,
                'project_name' => $effectiveProjectName,
                'is_a'        => 'project'
            ]
        );

        if (!$dataDirId) {
            throw new RuntimeException('Failed to create the new workspace for S3 import.');
        }
        $_SESSION['User']['activeProject'] = $workspaceProjectCode;
        $_SESSION['User']['projectName'] = $effectiveProjectName;
        $_SESSION['User']['sourceBucket'] = $container;
        $_SESSION['User']['dataDir'] = $dataDirId;
        modifyUser($_SESSION['User']['_id'], 'activeProject', $workspaceProjectCode);
        modifyUser($_SESSION['User']['_id'], 'projectName', $effectiveProjectName);
        modifyUser($_SESSION['User']['_id'], 'sourceBucket', $container);
        modifyUser($_SESSION['User']['_id'], 'dataDir', $dataDirId);
        $_SESSION['User'] = getUserById($_SESSION['User']['_id']);
    }

    $dataDirPath = getAttr_fromGSFileId($dataDirId, 'path');
    $repositoryRoot = $GLOBALS['dataDir'] . '/' . $dataDirPath . '/repository';
    if (!is_dir($repositoryRoot) && !mkdir($repositoryRoot, 0775, true)) {
        throw new RuntimeException('Failed to create repository root: ' . $repositoryRoot);
    }
    $cleanPrefix = trim((string)$dirPath, '/');
    $targetDirName = basename(rtrim($cleanPrefix, '/')) ?: 'root';
    $targetLocalDir = $repositoryRoot . '/' . $targetDirName;

    if (!is_dir($targetLocalDir) && !mkdir($targetLocalDir, 0775, true)) {
        throw new RuntimeException('Failed to create repository import directory: ' . $targetLocalDir);
    }
    $result = $s3Client->listObjectsV2([
        'Bucket' => $container,
        'Prefix' => $cleanPrefix,
        'MaxKeys' => 1000
    ]);
    $items = $result->get('Contents', []);
    $importedCount = 0;
    foreach ($items as $item) {
        $key = $item['Key'] ?? null;
        if ($key === null || $key === '' || substr($key, -1) === '/' || $key === $cleanPrefix) {
            continue;
        }
        $relativeKey = ltrim(substr($key, strlen($cleanPrefix)), '/');
        if ($relativeKey === '') {
            continue;
        }
        $localRelativePath = $targetDirName . '/' . $relativeKey;
        $localFullPath = $targetLocalDir . '/' . $relativeKey;
        $localDir = dirname($localFullPath);
        if (!is_dir($localDir) && !mkdir($localDir, 0775, true)) {
            throw new RuntimeException('Failed to create import directory: ' . $localDir);
        }
        $object = $s3Client->getObject([
            'Bucket' => $container,
            'Key' => $key,
        ]);
        $body = $object['Body'] ?? null;
        if ($body === null) {
            continue;
        }
        $contents = $body->getContents();
        if ($contents === false || $contents === null) {
            continue;
        }
        if (file_put_contents($localFullPath, $contents) === false) {
            throw new RuntimeException('Failed to write imported S3 object to: ' . $localFullPath);
        }
        chmod($localFullPath, 0666);
        $mongoRelativePath = $dataDirPath . '/repository/' . $localRelativePath;
        $mongoParentPath = dirname($mongoRelativePath);
        if ($mongoParentPath !== '.' && $mongoParentPath !== '/') {
            createGSDirBNS($mongoParentPath, 1);
        }
        $fileId = uploadGSFileBNS(
            $mongoRelativePath,
            $localFullPath,
            [
                'owner' => $_SESSION['User']['id'],
                'mtime' => new MongoDB\BSON\UTCDateTime(filemtime($localFullPath) * 1000),
                'size' => filesize($localFullPath)
            ],
            [
                'data_source' => 'repository',
                'format' => strtolower(pathinfo($localFullPath, PATHINFO_EXTENSION)),
                'validated' => true,
                'visible' => true,
                'data_type' => 'cohort_dataset'
            ],
        );
        if (!$fileId || $fileId === '0') {
            throw new RuntimeException('Failed to register S3 download in workspace: ' . $localFullPath);
        }
        $importedCount++;
    }
    $project = getProject($dataDirId);
    if ($project) {
        syncProjectFiles([$dataDirId => $project]);
    }
    return $dataDirId;
}

#Function for downloading a directory from S3 and registering its contents in a new workspace - main function calling registerRepositoryDirectoryFromS3.
function initiateS3DirectoryDownloadInNewWorkspace($s3Client, $dirPath, $container, $projectName = null)
{
    getObjectStorageS3Logger()->info('Starting S3 directory import into new workspace: bucket=' . $container . ', prefix=' . $dirPath . ', project=' . ($projectName ?? 'n/a'));
    if (!$s3Client || empty($container) || empty($dirPath)) {
        throw new InvalidArgumentException('Invalid S3 client, bucket, or directory prefix.');
    }
    $projectId = registerRepositoryDirectoryFromS3($s3Client, $container, $dirPath, $projectName);
    $project = getProject($projectId);
    $projectNameFinal = $project['name'] ?? ($projectName ?: basename(rtrim($dirPath, '/')) ?: 's3-import');
    $_SESSION['errorData']['Info'][] = 'Directory "' . $dirPath . '" imported into the new workspace "' . $projectNameFinal . '".';
    return [
        'status' => 'success',
        'message' => 'Imported directory "' . $dirPath . '" into new workspace "' . $projectNameFinal . '".',
        'redirect' => $GLOBALS['BASEURL'] . 'workspace/?workspace=' . urlencode($_SESSION['User']['dataDir']),
        'projectName' => $projectNameFinal,
        'workspaceId' => $projectId,
        'importedCount' => 0
    ];
}
