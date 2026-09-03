<?php

namespace OpenVRE;

use OpenVRE\VaultClient;
use OpenVRE\VaultTokenProvider;


class VaultClientFactory
{
    public static function create($userSecretsId): VaultClient
    {
        if (empty($_SESSION['userToken']) || !is_object($_SESSION['userToken'])) {
            throw new \UnexpectedValueException('No valid user token in session.');
        }

        $tokenProvider = new VaultTokenProvider($_SESSION['userToken']->getToken(), $GLOBALS['vaultRolename'], $GLOBALS['vaultUrl']);
        return new VaultClient($userSecretsId, $GLOBALS['secretPath'], $tokenProvider->getToken(), $GLOBALS['vaultUrl']);
    }
}
