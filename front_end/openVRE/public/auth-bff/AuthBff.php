<?php

declare(strict_types=1);

require_once __DIR__ . '/SessionTokenRefresher.php';

/**
 * OAuth token-handler: session cookie in, Bearer out. No JSON rewrite of live API responses.
 *
 * Temporary fixtures:
 * - GET /auth-bff/files when $useFixtures is true (until live files shape is enough)
 * - GET /auth-bff/tools always (no /api/v1/tools yet)
 */
interface AuthBffTransport
{
    /**
     * @param list<string> $headers
     * @return array{status: int, contentType: string, body: string}
     */
    public function send(string $url, string $method, array $headers, string $body): array;
}

final class CurlTransport implements AuthBffTransport
{
    public function send(string $url, string $method, array $headers, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to reach API');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            curl_close($ch);
            throw new RuntimeException('Failed to reach API');
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            'status' => $status > 0 ? $status : 502,
            'contentType' => is_string($contentType) ? $contentType : '',
            'body' => $responseBody,
        ];
    }
}

final class AuthBff
{
    public const ALLOWED_FIRST_SEGMENTS = ['files', 'tools'];

    private const PREFIX = '/auth-bff';

    /**
     * Resolve the shared React island fixtures JSON (`{ files, tools }`).
     *
     * Order: REACT_ISLAND_FIXTURES_PATH env, monorepo-relative path (host),
     * then docker-compose mount outside the public web tree.
     */
    public static function defaultFixturesPath(): string
    {
        $fromEnv = getenv('REACT_ISLAND_FIXTURES_PATH');
        if (is_string($fromEnv) && $fromEnv !== '' && is_readable($fromEnv)) {
            return $fromEnv;
        }

        $candidates = [
            __DIR__ . '/../../../../react-frontend/src/fixtures/workspaceFixtures.json',
            // docker-compose mounts the same JSON outside the public web tree
            __DIR__ . '/../../fixtures/workspaceFixtures.json',
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return $candidates[0];
    }

    public function __construct(
        private AuthBffTransport $transport,
        private bool $useFixtures = false,
        private ?string $fixturesPath = null,
        private ?SessionTokenRefresherInterface $tokenRefresher = null,
    ) {
    }

    private function tokenRefresher(): SessionTokenRefresherInterface
    {
        return $this->tokenRefresher ??= new SessionTokenRefresher();
    }

    /**
     * @return array{status: int, contentType: string, body: string}
     */
    public static function error(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'contentType' => 'application/json',
            'body' => json_encode([
                'code' => $code,
                'status' => $status,
                'message' => $message,
            ], JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $session
     * @return array{status: int, contentType: string, body: string}
     */
    public function handle(array $server, array &$session, string $body): array
    {
        if ($this->sessionAccessToken($session) === null) {
            return self::notLoggedInError();
        }

        $relativePath = $this->relativeApiPath($server);
        if ($relativePath === null) {
            return self::error(403, 'FORBIDDEN', 'Invalid path');
        }

        if (!$this->isAllowedPath($relativePath)) {
            return self::error(403, 'FORBIDDEN', 'Path is not allowed');
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $query = (string) ($server['QUERY_STRING'] ?? '');

        $fixtureResponse = $this->fixtureResponseIfApplicable($method, $relativePath, $session);
        if ($fixtureResponse !== null) {
            return $fixtureResponse;
        }

        return $this->proxyLiveApi($server, $session, $body, $relativePath, $query, $method);
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $session
     * @return array{status: int, contentType: string, body: string}
     */
    private function proxyLiveApi(
        array $server,
        array &$session,
        string $body,
        string $relativePath,
        string $query,
        string $method,
    ): array {
        $accessToken = $this->refreshAndResolveAccessToken($session, $server);
        if ($accessToken === null) {
            return self::sessionExpiredError();
        }

        $result = $this->proxyToApi($server, $accessToken, $relativePath, $query, $method, $body);
        if ($result['status'] !== 403) {
            return $result;
        }

        $accessToken = $this->refreshAndResolveAccessToken($session, $server, force: true);
        if ($accessToken === null) {
            return self::sessionExpiredError();
        }

        $result = $this->proxyToApi($server, $accessToken, $relativePath, $query, $method, $body);

        return $result['status'] === 403 ? self::sessionExpiredError() : $result;
    }

    /**
     * @param array<string, mixed> $session
     * @return non-empty-string|null
     */
    private function refreshAndResolveAccessToken(array &$session, array $server, bool $force = false): ?string
    {
        if (!$this->tokenRefresher()->ensureFreshToken($session, $server, $force)) {
            return null;
        }

        return $this->sessionAccessToken($session);
    }

    private function isAllowedPath(string $relativePath): bool
    {
        $firstSegment = explode('/', $relativePath, 2)[0];

        return in_array($firstSegment, self::ALLOWED_FIRST_SEGMENTS, true);
    }

    /**
     * @param array<string, mixed> $session
     * @return array{status: int, contentType: string, body: string}|null
     */
    private function fixtureResponseIfApplicable(
        string $method,
        string $relativePath,
        array $session,
    ): ?array {
        // Island currently loads the full list and filters/pages in React.
        if ($this->useFixtures && $method === 'GET' && $relativePath === 'files') {
            return $this->filesFixtureResponse($session);
        }

        // No /api/v1/tools yet — always serve the tools fixture for GET,
        // even when REACT_ISLAND_USE_FIXTURES=0 (live files API).
        if ($method === 'GET' && $relativePath === 'tools') {
            return $this->toolsFixtureResponse();
        }

        return null;
    }

    /**
     * @return array{status: int, contentType: string, body: string}
     */
    private static function notLoggedInError(): array
    {
        return self::error(401, 'UNAUTHORIZED', 'Not logged in');
    }

    /**
     * @return array{status: int, contentType: string, body: string}
     */
    private static function sessionExpiredError(): array
    {
        return self::error(401, 'UNAUTHORIZED', 'Session expired');
    }

    /**
     * @param array<string, mixed> $server
     * @return array{status: int, contentType: string, body: string}
     */
    private function proxyToApi(
        array $server,
        string $accessToken,
        string $relativePath,
        string $query,
        string $method,
        string $body,
    ): array {
        $target = 'http://127.0.0.1/api/v1/' . $relativePath;
        if ($query !== '') {
            $target .= '?' . $query;
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Host: ' . ((($server['SERVER_NAME'] ?? '') !== '') ? (string) $server['SERVER_NAME'] : 'localhost'),
            'Expect:',
        ];

        $contentType = (string) ($server['CONTENT_TYPE'] ?? '');
        if ($contentType !== '') {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $outboundBody = in_array($method, ['GET', 'HEAD'], true) ? '' : $body;

        try {
            return $this->transport->send($target, $method, $headers, $outboundBody);
        } catch (RuntimeException) {
            return self::error(502, 'BAD_GATEWAY', 'Failed to reach API');
        }
    }

    /**
     * Temporary stand-in for GET /files until the API returns FileItem-shaped rows.
     * Returns the full fixture list (matches current island: no server-side offset/limit/q).
     *
     * TODO(delete): remove when FileController::list emits OpenAPI FileItem rows and
     * REACT_ISLAND_USE_FIXTURES is retired — AuthBff should only proxy.
     *
     * @param array<string, mixed> $session
     * @return array{status: int, contentType: string, body: string}
     */
    private function filesFixtureResponse(array $session): array
    {
        $loaded = $this->loadFixtures();
        if ($loaded['error'] !== null) {
            return $loaded['error'];
        }

        $userId = $this->sessionUserId($session);
        if ($userId === null) {
            return self::error(401, 'UNAUTHORIZED', 'No user in session');
        }

        $files = $loaded['files'];
        $total = count($files);
        $payload = [
            'userId' => $userId,
            'offset' => 0,
            'limit' => $total,
            'total' => $total,
            'files' => $files,
        ];

        return [
            'status' => 200,
            'contentType' => 'application/json',
            'body' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Temporary stand-in for GET /tools until a live Tools API exists.
     * Serves the `tools` section of workspaceFixtures.json as { tools: [...] }.
     * Always used for GET /tools (independent of REACT_ISLAND_USE_FIXTURES).
     *
     * TODO(delete): remove when /api/v1/tools exists — AuthBff should only proxy.
     *
     * @return array{status: int, contentType: string, body: string}
     */
    private function toolsFixtureResponse(): array
    {
        $loaded = $this->loadFixtures();
        if ($loaded['error'] !== null) {
            return $loaded['error'];
        }

        return [
            'status' => 200,
            'contentType' => 'application/json',
            'body' => json_encode(['tools' => $loaded['tools']], JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @return array{
     *   files: list<array<string, mixed>>,
     *   tools: list<array<string, mixed>>,
     *   error: array{status: int, contentType: string, body: string}|null
     * }
     */
    private function loadFixtures(): array
    {
        $path = $this->fixturesPath ?? self::defaultFixturesPath();
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [
                'files' => [],
                'tools' => [],
                'error' => self::error(502, 'BAD_GATEWAY', 'Fixtures not readable'),
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'files' => [],
                'tools' => [],
                'error' => self::error(502, 'BAD_GATEWAY', 'Fixtures are invalid JSON'),
            ];
        }

        return [
            'files' => $this->objectList($decoded['files'] ?? null),
            'tools' => $this->objectList($decoded['tools'] ?? null),
            'error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function objectList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Same identity the live API puts in GetUserFilesResponse.userId (session login id).
     * Only used while AuthBff builds the fixture envelope itself.
     *
     * TODO(delete): remove with filesFixtureResponse when the live Files API is wired —
     * userId then comes from FileController, not the BFF.
     *
     * @param array<string, mixed> $session
     * @return non-empty-string|null
     */
    private function sessionUserId(array $session): ?string
    {
        $user = $session['User'] ?? null;
        if (!is_array($user)) {
            return null;
        }

        // Prefer id (email / login id); _id is the Mongo user document key.
        foreach (['id', '_id'] as $key) {
            $value = $user[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $session
     * @return non-empty-string|null
     */
    private function sessionAccessToken(array $session): ?string
    {
        $userToken = $session['userToken'] ?? null;
        if (!is_object($userToken) || !method_exists($userToken, 'getToken')) {
            return null;
        }

        $token = $userToken->getToken();
        if (!is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }

    /**
     * @param array<string, mixed> $server
     * @return non-empty-string|null
     */
    private function relativeApiPath(array $server): ?string
    {
        $uriPath = parse_url((string) ($server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (!is_string($uriPath) || $uriPath === '') {
            return null;
        }

        $baseUrl = rtrim((string) ($GLOBALS['BASEURL'] ?? ''), '/');
        if ($baseUrl !== '' && str_starts_with($uriPath, $baseUrl)) {
            $uriPath = substr($uriPath, strlen($baseUrl));
        }

        if (!str_starts_with($uriPath, self::PREFIX)) {
            return null;
        }

        $relative = rawurldecode(substr($uriPath, strlen(self::PREFIX)));
        $relative = ltrim($relative, '/');
        if ($relative === '') {
            return null;
        }

        if (
            str_contains($relative, '..')
            || str_contains($relative, ':')
            || str_contains($relative, '//')
            || preg_match('#^[A-Za-z0-9._~/-]+$#', $relative) !== 1
        ) {
            return null;
        }

        return $relative;
    }
}
