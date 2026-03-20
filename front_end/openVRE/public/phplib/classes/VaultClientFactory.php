<?php

namespace OpenVRE;

use OpenVRE\VaultClient;
use OpenVRE\VaultTokenProvider;


class VaultClientFactory
{
    public static function create($userSecretsId): VaultClient
    {
        $tokenProvider = new VaultTokenProvider($_SESSION['userToken']->getToken(), $GLOBALS['vaultRolename'], $GLOBALS['vaultUrl']);
        return new VaultClient($userSecretsId, $GLOBALS['secretPath'], $tokenProvider->getToken(), $GLOBALS['vaultUrl']);
    }
}
