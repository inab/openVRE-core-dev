<?php

namespace OpenVRE;

use Monolog\Logger;

class LinkedAccount
{
    private Logger $logger;
    private Site $site;

    public function __construct(Site $site)
    {
        $this->logger = LoggerFactory::getLogger('Linked account');
        $this->site = $site;
    }


    public function getSite(): Site
    {
        return $this->site;
    }


    public function getCredentials(string $userSecretsId)
    {
        $vaultClient = VaultClientFactory::create($userSecretsId);
        return $vaultClient->retrieveDatafromVault($this->site);
    }


    public function storeCredentials(string $userSecretsId, #[\SensitiveParameter] $credentials)
    {
        $this->logger->info("Storing credentials for site: " . $this->site->value);

        $vaultData = [];
        $vaultData['data'][$this->site->value] = [];
        foreach ($credentials as $key => $value) {
            $vaultData['data'][$this->site->value][$key] = $value;
        }
        
        $vaultClient = VaultClientFactory::create($userSecretsId);
        $vaultClient->uploadFileToVault($this->site, $vaultData);

        $this->logger->info("Stored credentials for site: " . $this->site->value);
    }


    public function removeCredentials()
    {
        $this->logger->info("Removing credentials for site: " . $this->site->value);

        // TODO: To be implemented
    }
}
