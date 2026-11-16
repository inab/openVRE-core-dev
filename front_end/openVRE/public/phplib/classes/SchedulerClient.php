<?php

namespace OpenVRE;

use Monolog\Logger;

/**
 * HTTP client for the OpenVRE scheduler (scheduler/), shared by batch Jobs
 * (ProcessK8s) and interactive sessions (K8sSession).
 *
 * Environment variables:
 *   OPENVRE_K8S_NAMESPACE       namespace for Jobs and sessions (required)
 *   OPENVRE_K8S_SCHEDULER_URL   base URL of the scheduler service (required)
 *   OPENVRE_K8S_SCHEDULER_TOKEN shared token: who is calling (required)
 *
 * Every request also carries the logged-in user's Keycloak access token
 * (X-OpenVRE-User-Token), refreshed when needed: on whose behalf. The scheduler
 * takes the user from that token and checks who owns a Job or session.
 */
class SchedulerClient
{
    private Logger $logger;
    private string $namespace;
    private string $url;
    private string $token;

    public function __construct()
    {
        $this->logger = LoggerFactory::getLogger("Scheduler client");
        $this->namespace = self::env("OPENVRE_K8S_NAMESPACE");
        $this->url = rtrim(self::env("OPENVRE_K8S_SCHEDULER_URL"), "/");
        $this->token = self::env("OPENVRE_K8S_SCHEDULER_TOKEN");
    }

    /** A required environment variable; throws when it is not set at all. */
    public static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            throw new \RuntimeException("Environment variable {$name} is not set");
        }
        return $value;
    }

    public static function optionalEnv(string $name, string $default = ""): string
    {
        $value = getenv($name);
        return ($value === false) ? $default : $value;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /** Empty when the client can be used, else what is missing. */
    public function configError(): string
    {
        if ($this->url === "") {
            return "OPENVRE_K8S_SCHEDULER_URL is not set";
        }
        if ($this->namespace === "") {
            return "OPENVRE_K8S_NAMESPACE is not set";
        }
        return "";
    }

    /** "<collection>/<name>?namespace=..." for GET and DELETE on one object. */
    public function objectPath(string $collection, string $name): string
    {
        return "/" . $collection . "/" . rawurlencode($name) . "?namespace=" . rawurlencode($this->namespace);
    }

    /**
     * Sends one request. Returns ["ok" => true, "data" => array] or
     * ["ok" => false, "error" => string]; never throws.
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $err = $this->configError();
        if ($err !== "") {
            return array("ok" => false, "error" => $err);
        }
        if (!function_exists("curl_init")) {
            return array("ok" => false, "error" => "PHP curl extension is required");
        }

        $headers = array("Content-Type: application/json");
        if ($this->token !== "") {
            $headers[] = "Authorization: Bearer " . $this->token;
        }
        $userToken = $this->userAccessToken();
        if ($userToken !== "") {
            $headers[] = "X-OpenVRE-User-Token: " . $userToken;
        }

        $ch = curl_init($this->url . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return array("ok" => false, "error" => "Scheduler request failed: " . $err);
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return self::parseResponse($code, $raw);
    }

    private static function parseResponse(int $code, string $raw): array
    {
        $json = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($json) && isset($json["error"]) ? $json["error"] : ("HTTP " . $code . " from scheduler");
            return array("ok" => false, "error" => $msg);
        }
        if (!is_array($json)) {
            return array("ok" => false, "error" => "Invalid JSON response from scheduler");
        }
        if (isset($json["ok"]) && !$json["ok"]) {
            return array("ok" => false, "error" => $json["error"] ?? "Scheduler error");
        }
        return array("ok" => true, "data" => $json);
    }

    /**
     * The logged-in user's Keycloak access token, refreshed when it has expired
     * or expires within 30 seconds. Empty when there is no logged-in user or the
     * login can no longer be refreshed.
     */
    private function userAccessToken(): string
    {
        $token = $_SESSION['userToken'] ?? null;
        if (!is_object($token) || !method_exists($token, 'getToken')) {
            return "";
        }
        $expires = method_exists($token, 'getExpires') ? (int)$token->getExpires() : 0;
        if ($expires > 0 && $expires - 30 < time()) {
            $refresh = method_exists($token, 'getRefreshToken') ? $token->getRefreshToken() : null;
            if (!$refresh) {
                $this->logger->warning("User token expired and no refresh token in session");
                return "";
            }
            try {
                $token = (new Oauth2Provider([]))->getAccessToken('refresh_token', ['refresh_token' => $refresh]);
                $_SESSION['userToken'] = $token;
            } catch (\Throwable $e) {
                $this->logger->warning("Could not refresh the user token: " . $e->getMessage());
                return "";
            }
        }
        return preg_replace('/[\r\n]/', '', (string)$token->getToken());
    }

    /**
     * Job or session info while the real state cannot be read: keeps it
     * PENDING, so a scheduler outage does not finalize running work.
     */
    public static function unknownStateInfo(string $pid): array
    {
        return array(
            "pid" => $pid,
            "state" => "PENDING",
            "job_name" => $pid,
            "scheduler_unreachable" => true,
        );
    }
}
