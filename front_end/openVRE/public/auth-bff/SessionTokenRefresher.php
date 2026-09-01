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
            return false;
        }

        $userToken = $session['userToken'];
        if (!$force && !$this->tokenHasExpired($userToken)) {
            return true;
        }

        $fresh = $this->oidcAccessToken($server);
        if ($fresh === null || $fresh === $existing) {
            return false;
        }

        $session['userToken'] = $this->buildAccessToken($fresh, $server);

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
    private function buildAccessToken(string $accessToken, array $server): AccessToken
    {
        return new AccessToken([
            'access_token' => $accessToken,
            'expires' => (int) ($server['OIDC_access_token_expires'] ?? 0),
        ]);
    }

    private function tokenHasExpired(object $userToken): bool
    {
        if (!method_exists($userToken, 'getExpires')) {
            return false;
        }

        $expires = $userToken->getExpires();

        return $expires !== null && $expires <= time();
    }
}
