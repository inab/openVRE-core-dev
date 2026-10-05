<?php

declare(strict_types=1);

use League\OAuth2\Client\Token\AccessToken;

/**
 * BFF-safe access-token refresh: updates session from OIDC proxy headers.
 * No redirects or exit — suitable for JSON fetch handlers (AuthBff).
 */
interface SessionTokenRefresherInterface
{
    /**
     * Ensure the session holds a usable access token.
     *
     * When $force is false, returns true if the current token is not expired.
     * When expired (or $force is true), reads OIDC_access_token from $server
     * and updates $session['userToken'] when a new token is available.
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed> $server
     */
    public function ensureFreshToken(array &$session, array $server, bool $force = false): bool;
}

final class SessionTokenRefresher implements SessionTokenRefresherInterface
{
    public function ensureFreshToken(array &$session, array $server, bool $force = false): bool
    {
        $existing = $this->existingTokenFromSession($session);
        if ($existing === null) {
            return $this->adoptOidcToken($session, $server);
        }

        $userToken = $session['userToken'];
        if (!$force && !$this->tokenHasExpired($userToken)) {
            return true;
        }

        return $this->adoptOidcToken($session, $server);
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed> $server
     */
    private function adoptOidcToken(array &$session, array $server): bool
    {
        $fresh = $this->oidcAccessToken($server);
        if ($fresh === null) {
            return false;
        }

        $oidcExpires = $this->oidcExpiry($server, $fresh);
        if ($oidcExpires <= time()) {
            return false;
        }

        $session['userToken'] = $this->buildAccessToken($fresh, $oidcExpires);

        return true;
    }

    /**
     * @param array<string, mixed> $session
     * @return non-empty-string|null
     */
    private function existingTokenFromSession(array $session): ?string
    {
        $userToken = $session['userToken'] ?? null;
        if (!is_object($userToken) || !method_exists($userToken, 'getToken')) {
            return null;
        }

        $token = $userToken->getToken();

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param array<string, mixed> $server
     * @return non-empty-string|null
     */
    private function oidcAccessToken(array $server): ?string
    {
        $token = $server['OIDC_access_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function oidcExpiry(array $server, string $accessToken): int
    {
        $fromHeader = (int) ($server['OIDC_access_token_expires'] ?? 0);
        if ($fromHeader > 0) {
            return $fromHeader;
        }

        return $this->jwtExp($accessToken) ?? 0;
    }

    private function buildAccessToken(string $accessToken, int $expires): AccessToken
    {
        return new AccessToken([
            'access_token' => $accessToken,
            'expires' => $expires,
        ]);
    }

    private function tokenHasExpired(object $userToken): bool
    {
        if (method_exists($userToken, 'getExpires')) {
            $expires = $userToken->getExpires();
            if ($expires !== null && $expires <= time()) {
                return true;
            }
        }

        if (!method_exists($userToken, 'getToken')) {
            return false;
        }

        $token = $userToken->getToken();
        if (!is_string($token) || $token === '') {
            return false;
        }

        $jwtExp = $this->jwtExp($token);

        return $jwtExp !== null && $jwtExp <= time();
    }

    /**
     * Best-effort JWT exp claim (unverified) when OIDC expiry headers are missing.
     */
    private function jwtExp(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payload = $parts[1];
        $pad = strlen($payload) % 4;
        if ($pad > 0) {
            $payload .= str_repeat('=', 4 - $pad);
        }

        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $claims = json_decode($json, true);
        if (!is_array($claims) || !isset($claims['exp']) || !is_numeric($claims['exp'])) {
            return null;
        }

        return (int) $claims['exp'];
    }
}
