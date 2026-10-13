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
        $buckets = $s3Client->listBuckets();
        getObjectStorageS3Logger()->debug('S3 connection successful. Buckets: ' . json_encode($buckets));
        
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
    getObjectStorageLogger()->info(
        'Getting S3 buckets'
    );

    if (!$s3Client) {
        return [
            'error' => true,
            'message' => 'Invalid S3 client.'
        ];
    }

    // TEST DATA
    return [
        'status' => 'success',
        'containers' => [
            'my-bucket'
        ]
    ];
}
