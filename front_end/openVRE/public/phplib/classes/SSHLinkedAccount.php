<?php

namespace OpenVRE;

use Monolog\Logger;
use InvalidArgumentException;

class SshLinkedAccount extends LinkedAccount
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger('SSH linked account');

        parent::__construct(Site::SSH);
    }

    private function isValidKeyPair(#[\SensitiveParameter] string $privateKey, #[\SensitiveParameter] string $publicKey)
    {
        $openSslPrivateKey = openssl_pkey_get_private($privateKey);
        $openSslPublicKey = openssl_pkey_get_public($publicKey);

        if ($openSslPrivateKey === false || $openSslPublicKey === false) {
            return false;
        }

        $testData  = 'key-pair-check-' . random_bytes(16);
        $signature = '';

        if (!openssl_sign($testData, $signature, $openSslPrivateKey, OPENSSL_ALGO_SHA256)) {
            return false;
        }

        return openssl_verify($testData, $signature, $openSslPublicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    
    public function storeCredentials(#[\SensitiveParameter] $credentials)
    {
        $this->logger->info('Storing SSH credentials');

        if (!$this->isValidKeyPair($credentials['private_key'], $credentials['public_key'])) {
            throw new InvalidArgumentException('Invalid key pair');
        }

        return parent::storeCredentials($credentials);
    }
}
