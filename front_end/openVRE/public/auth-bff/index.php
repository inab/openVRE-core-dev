<?php

declare(strict_types=1);

/**
 * OAuth token-handler (not a Newman BFF): session cookie in, Bearer out.
 *
 * Browser  GET|PATCH|POST|DELETE  /auth-bff/{path}?query  + session cookie
 * PHP      same method/query/body /api/v1/{path}?query    + Authorization: Bearer
 * Browser  ← status + body unchanged
 *
 * Add first-path-segment entries to ALLOWED_FIRST_SEGMENTS when a new island exists.
 */

const ALLOWED_FIRST_SEGMENTS = [
    'files'
];

const AUTH_BFF_PREFIX = '/auth-bff';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/globals.inc.php';
require_once __DIR__ . '/../phplib/session.inc';

if (session_status() !== PHP_SESSION_ACTIVE) {
    jsonError(401, 'UNAUTHORIZED', 'No active session');
}

$accessToken = sessionAccessToken();
if ($accessToken === null) {
    jsonError(401, 'UNAUTHORIZED', 'Not logged in');
}

$relativePath = relativeApiPath();
if ($relativePath === null) {
    jsonError(403, 'FORBIDDEN', 'Invalid path');
}

$firstSegment = explode('/', $relativePath, 2)[0];
if (!in_array($firstSegment, ALLOWED_FIRST_SEGMENTS, true)) {
    jsonError(403, 'FORBIDDEN', 'Path is not allowed');
}

proxyToLocalApi($relativePath, $accessToken);

/**
 * @return non-empty-string|null
 */
function sessionAccessToken(): ?string
{
    $userToken = $_SESSION['userToken'] ?? null;
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
 * Path after /auth-bff/, sanitized for use as a local /api/v1 suffix.
 *
 * @return non-empty-string|null
 */
function relativeApiPath(): ?string
{
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($uriPath) || $uriPath === '') {
        return null;
    }

    $baseUrl = rtrim((string) ($GLOBALS['BASEURL'] ?? ''), '/');
    if ($baseUrl !== '' && str_starts_with($uriPath, $baseUrl)) {
        $uriPath = substr($uriPath, strlen($baseUrl));
    }

    if (!str_starts_with($uriPath, AUTH_BFF_PREFIX)) {
        return null;
    }

    $relative = rawurldecode(substr($uriPath, strlen(AUTH_BFF_PREFIX)));
    $relative = ltrim($relative, '/');
    if ($relative === '') {
        return null;
    }

    // Pin to a relative API path: no traversal, no scheme/host, no prefix tricks.
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

function proxyToLocalApi(string $relativePath, string $accessToken): void
{
    // Always loop back to this Apache. Do not use the request Host (SSRF).
    $target = 'http://127.0.0.1/api/v1/' . $relativePath;
    $query = $_SERVER['QUERY_STRING'] ?? '';
    if ($query !== '') {
        $target .= '?' . $query;
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $body = in_array($method, ['GET', 'HEAD'], true) ? '' : (string) file_get_contents('php://input');

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Host: ' . (($_SERVER['SERVER_NAME'] ?? '') !== '' ? $_SERVER['SERVER_NAME'] : 'localhost'),
        'Expect:',
    ];

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if ($contentType !== '') {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    $ch = curl_init($target);
    if ($ch === false) {
        jsonError(502, 'BAD_GATEWAY', 'Failed to reach API');
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
        jsonError(502, 'BAD_GATEWAY', 'Failed to reach API');
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $apiContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    http_response_code($status > 0 ? $status : 502);
    if (is_string($apiContentType) && $apiContentType !== '') {
        header('Content-Type: ' . $apiContentType);
    }

    echo $responseBody;
    exit;
}

function jsonError(int $status, string $code, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'code' => $code,
        'status' => $status,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
