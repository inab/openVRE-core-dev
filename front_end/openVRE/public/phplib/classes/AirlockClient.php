<?php

namespace OpenVRE;


/**
 * Minimal client for the TRE Airlock API (https://<airlock>/api/docs).
 *
 * Requests are authenticated with the logged user's OIDC access token, so
 * Airlock records the real user as the authenticated actor.
 *
 * Every method returns array("ok" => bool, "data" => array) on success or
 * array("ok" => false, "error" => string, "status" => int) on failure.
 */
class AirlockClient
{
    private const API_PREFIX = "/api/v1";
    private const TIMEOUT = 60;

    private string $baseUrl;
    private string $accessToken;
    private ?string $caFile;


    public function __construct(string $baseUrl, string $accessToken, ?string $caFile = null)
    {
        $this->baseUrl = rtrim($baseUrl, "/");
        $this->accessToken = $accessToken;
        $this->caFile = empty($caFile) ? null : $caFile;
    }


    /**
     * Build a client for the logged user from the global configuration.
     */
    public static function forCurrentUser(): self
    {
        if (empty($GLOBALS['airlockUrl'])) {
            throw new \UnexpectedValueException("Airlock is not configured (AIRLOCK_URL is empty).");
        }

        return new self($GLOBALS['airlockUrl'], self::currentAccessToken(), $GLOBALS['airlockSslCaFile'] ?? null);
    }


    /**
     * The user's access token. mod_auth_openidc refreshes the one in the
     * request environment, so it is preferred over the one stored in session.
     */
    private static function currentAccessToken(): string
    {
        if (!empty($_SERVER['OIDC_access_token'])) {
            return $_SERVER['OIDC_access_token'];
        }
        if (!empty($_SESSION['userToken']) && is_object($_SESSION['userToken']) && !$_SESSION['userToken']->hasExpired()) {
            return $_SESSION['userToken']->getToken();
        }

        throw new \UnexpectedValueException("No valid user token to authenticate against the Airlock. Please, log in again.");
    }


    public function uiUrl(): string
    {
        return $this->baseUrl;
    }


    public function createLandingMove(array $request): array
    {
        return $this->request("POST", "/landing-moves", $request);
    }


    public function getLandingMove(string $moveId): array
    {
        return $this->request("GET", "/landing-moves/" . rawurlencode($moveId));
    }


    public function getLandingArtifactSet(string $artifactSetId): array
    {
        return $this->request("GET", "/landing-artifact-sets/" . rawurlencode($artifactSetId));
    }


    public function createEgressSubmission(array $request): array
    {
        return $this->request("POST", "/egress-submissions", $request);
    }


    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists("curl_init")) {
            return array("ok" => false, "status" => 0, "error" => "PHP curl extension is required");
        }

        $ch = curl_init($this->baseUrl . self::API_PREFIX . $path);
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer " . $this->accessToken,
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        if ($this->caFile !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->caFile);
        }
        if ($payload !== null) {
            $headers[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return array("ok" => false, "status" => 0, "error" => "Airlock request failed: " . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            return array("ok" => false, "status" => $code, "error" => self::errorMessage($code, $json, $raw));
        }
        if (!is_array($json)) {
            return array("ok" => false, "status" => $code, "error" => "Invalid JSON response from Airlock");
        }

        return array("ok" => true, "status" => $code, "data" => $json);
    }


    /**
     * Human readable message from an Airlock (FastAPI) error response.
     */
    private static function errorMessage(int $code, $json, string $raw): string
    {
        $detail = is_array($json) ? ($json['detail'] ?? null) : null;

        if (is_string($detail)) {
            return "Airlock error (HTTP $code): $detail";
        }
        if (is_array($detail)) {
            // 422 validation errors: [{loc: [...], msg: "...", type: "..."}]
            $messages = array();
            foreach ($detail as $error) {
                if (!is_array($error)) {
                    continue;
                }
                $loc = isset($error['loc']) && is_array($error['loc']) ? implode(".", $error['loc']) : "";
                $messages[] = trim($loc . ": " . ($error['msg'] ?? json_encode($error)), ": ");
            }
            if ($messages) {
                return "Airlock rejected the request (HTTP $code): " . implode("; ", $messages);
            }
        }

        return "Airlock error (HTTP $code)" . ($raw !== "" ? ": " . substr(strip_tags($raw), 0, 300) : "");
    }
}
