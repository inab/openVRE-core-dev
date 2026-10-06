<?php

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
    $storage = $site['storage'];
    $credentials = getS3Credentials($site);

    return new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => $storage['endpoint'],
        'use_path_style_endpoint' => true,
        'credentials' => $credentials
    ]);
}

function getS3Credentials(array $site)
{
    // TODO: replace with credential_ref/OpenBao lookup
    return [
        'key' => 'admin',
        'secret' => 'secret'
    ];
}
