<?php

namespace OpenVRE;

use Monolog\Logger;
use UnexpectedValueException;

class EGALinkedAccount extends LinkedAccount
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('EGA linked account');

        parent::__construct(Site::EGA);
    }


    public function getAuthToken(#[\SensitiveParameter] string $password, #[\SensitiveParameter] string $username)
    {
        $this->logger->info('Fetching EGA token');

        $params = [
            'client_id' => 'metadata-api',
            'username' => $username,
            'password' => $password,
            'grant_type' => 'password'
        ];

        $ch = curl_init($GLOBALS['EGA_METADATA_TOKEN_ENDPOINT']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $jsonData = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new UnexpectedValueException('cURL error: ' . curl_error($ch));
        }

        $tokenDataArray = json_decode($jsonData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedValueException('Error decoding JSON data: ' . json_last_error_msg());
        }

        $accessToken = $tokenDataArray['access_token'] ?? null;
        if ($accessToken === null) {
            throw new UnexpectedValueException('Error fetching EGA token. Check your credentials and try again.');
        }

        return $accessToken;
    }


    public function fetchDatasets($accessToken, $offset = 0, $limit = 10)
    {
        $egaDatasetsEndpoint = $GLOBALS['EGA_METADATA_API'] . '/datasets?offset=' . $offset . '&limit=' . $limit;
        $context = stream_context_create([
            "http" => [
                "header" => "Authorization: Bearer $accessToken"
            ]
        ]);

        $jsonData = file_get_contents($egaDatasetsEndpoint, false, $context);
        if ($jsonData === false) {
            $this->logger->error('Error fetching datasets.');
            throw new UnexpectedValueException('Error fetching datasets.');
        }

        return json_decode($jsonData, true);
    }


    public function fetchDatasetFiles($dataset_id, $accessToken, $offset = 0, $limit = 10)
    {
        $egaDatasetFilesEndpoint = $GLOBALS['EGA_METADATA_API'] . '/datasets/' . $dataset_id . '/files?offset=' . $offset . '&limit=' . $limit;
        $context = stream_context_create([
            "http" => [
                "header" => "Authorization: Bearer $accessToken"
            ]
        ]);

        $jsonData = file_get_contents($egaDatasetFilesEndpoint, false, $context);
        if ($jsonData === false) {
            $this->logger->error('Error fetching files for dataset ' . $dataset_id . '.');
            throw new UnexpectedValueException('Error fetching files for dataset ' . $dataset_id . '.');
        }

        return json_decode($jsonData, true);
    }

    public function storeCredentials(string $userSecretsId, #[\SensitiveParameter] $credentials)
    {
        $this->getAuthToken($credentials['password'], $credentials['username']); // Check if the credentials are valid
        return parent::storeCredentials( $userSecretsId, $credentials);
    }
}
