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
                'Name' => $key,
                'name' => $key,
                'path' => $key,
                'size' => (int)($item['Size'] ?? 0),
                'Size' => (int)($item['Size'] ?? 0),
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
