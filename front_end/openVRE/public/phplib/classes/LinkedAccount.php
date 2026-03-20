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


    public function storeCredentials(#[\SensitiveParameter] $credentials)
    {
        $this->logger->info("Storing credentials for site: " . $this->site);

        $vaultData = [];
        $vaultData['data'][$this->site] = [];
        foreach ($credentials as $key => $value) {
            $vaultData['data'][$this->site][$key] = $value;
        }
        
        $vaultClient = VaultClientFactory::create();
        $vaultClient->uploadFileToVault($this->site, $vaultData);

        $this->logger->info("Stored credentials for site: " . $this->site);
    }


    public function removeCredentials()
    {
        $this->logger->info("Removing credentials for site: " . $this->site);

        // TODO: To be implemented
    }
}
