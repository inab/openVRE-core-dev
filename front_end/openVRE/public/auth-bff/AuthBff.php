<?php

declare(strict_types=1);

/**
 * OAuth token-handler: session cookie in, Bearer out. No JSON rewrite.
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
    public const ALLOWED_FIRST_SEGMENTS = ['files'];

    private const PREFIX = '/auth-bff';

    public function __construct(private AuthBffTransport $transport)
    {
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
    public function handle(array $server, array $session, string $body): array
    {
        $accessToken = $this->sessionAccessToken($session);
        if ($accessToken === null) {
            return self::error(401, 'UNAUTHORIZED', 'Not logged in');
        }

        $relativePath = $this->relativeApiPath($server);
        if ($relativePath === null) {
            return self::error(403, 'FORBIDDEN', 'Invalid path');
        }

        $firstSegment = explode('/', $relativePath, 2)[0];
        if (!in_array($firstSegment, self::ALLOWED_FIRST_SEGMENTS, true)) {
            return self::error(403, 'FORBIDDEN', 'Path is not allowed');
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $query = (string) ($server['QUERY_STRING'] ?? '');
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
