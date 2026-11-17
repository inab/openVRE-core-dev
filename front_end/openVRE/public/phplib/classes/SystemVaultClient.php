<?php

namespace OpenVRE;
use Monolog\Logger;
use UnexpectedValueException;




class SystemVaultClient
{
    private string $url;
    private string $roleId;
    private string $secretId;
    private ?string $caFile;
    private string $secretPath;


    public function __construct()
    {
        $this->url = rtrim(
            getenv('BAO_ADDR') ?: $GLOBALS['baoUrl'],
            '/'
        );

        $roleFile = getenv('BAO_ROLE_ID_FILE');
        $secretFile = getenv('BAO_SECRET_ID_FILE');

        if (!$roleFile || !$secretFile ||
            !is_readable($roleFile) || !is_readable($secretFile)) {
            throw new UnexpectedValueException(
                'OpenBao AppRole credentials are not accessible.'
            );
        }

        $this->roleId = trim(file_get_contents($roleFile));
        $this->secretId = trim(file_get_contents($secretFile));
        $this->secretPath = rtrim(getenv('BAO_SECRET_PATH'));
        $this->caFile = getenv('VAULT_CERT_CAFILE') ?: null;

        if ($this->roleId === '' || $this->secretId === '') {
            throw new UnexpectedValueException(
                'OpenBao AppRole credentials are empty.'
            );
        }

    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null
    ): array {

        $ch = curl_init($this->url . '/' . ltrim($path, '/'));
        

        $headers = ['Content-Type: application/json'];

        if ($token !== null) {
            $headers[] = 'X-Vault-Token: ' . $token;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body === null
                ? null
                : json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 10,
        ]);

        if (str_starts_with($this->url, 'https://')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if ($this->caFile) {
                curl_setopt($ch, CURLOPT_CAINFO, $this->caFile);
            }
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new UnexpectedValueException(
                'OpenBao request failed: ' . $error
            );
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            // Do not log or include the response body: it may contain secrets.
            throw new UnexpectedValueException(
                "OpenBao request failed with HTTP  {$status}: " . $response
            );
        }

        return $decoded;
    }

    private function getToken(): string
    {
        $response = $this->request(
            'POST',
            'v1/auth/approle/login',
            [
                'role_id' => $this->roleId,
                'secret_id' => $this->secretId,
            ]
        );

        $token = $response['auth']['client_token'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new UnexpectedValueException(
                'OpenBao did not return an AppRole token.'
            );
        }

        return $token;
    }

    public function getSeaweedCredentials(string $credentialRef): array
    {
        $token = $this->getToken();

        $response = $this->request(
            'GET',
           'v1/' . rtrim($this->secretPath, '/') . '/' . ltrim($credentialRef, '/'),
            null,
            $token
        );

        $data = $response['data']['data'] ?? null;

        if (!is_array($data) ||
            empty($data['access_key']) ||
            empty($data['secret_key'])) {
            throw new UnexpectedValueException(
                'SeaweedFS admin credentials are missing in OpenBao.'
            );
        }

        return [
            'key' => $data['access_key'],
            'secret' => $data['secret_key'],
        ];
    }
}
