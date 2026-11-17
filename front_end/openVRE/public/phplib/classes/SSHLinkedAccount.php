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

    
    public function storeCredentials(string $userSecretsId, #[\SensitiveParameter] $credentials)
    {
        $this->logger->info('Storing SSH credentials');

        if (openssl_pkey_get_private($credentials['private_key']) === false) {
            throw new InvalidArgumentException('Invalid private key');
        }

        return parent::storeCredentials( $userSecretsId, $credentials);
    }
}
