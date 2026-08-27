<?php

declare(strict_types=1);

/**
 * OAuth token-handler (not a Newman BFF): session cookie in, Bearer out.
 *
 * Browser  GET|PATCH|POST|DELETE  /auth-bff/{path}?query  + session cookie
 * PHP      same method/query/body /api/v1/{path}?query    + Authorization: Bearer
 * Browser  ← status + body unchanged
 *
 * Add first-path-segment entries to AuthBff::ALLOWED_FIRST_SEGMENTS when a new island exists.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/globals.inc.php';
require_once __DIR__ . '/../phplib/session.inc';
require_once __DIR__ . '/AuthBff.php';

/**
 * @param array{status: int, contentType: string, body: string} $result
 */
function emitAuthBffResult(array $result): never
{
    http_response_code($result['status']);
    if ($result['contentType'] !== '') {
        header('Content-Type: ' . $result['contentType']);
    }
    echo $result['body'];
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    emitAuthBffResult(AuthBff::error(401, 'UNAUTHORIZED', 'No active session'));
}

$body = (string) file_get_contents('php://input');
$authBff = new AuthBff(
    new CurlTransport(),
    getenv('REACT_ISLAND_USE_FIXTURES') === '1',
    AuthBff::defaultFixturesPath(),
);
emitAuthBffResult($authBff->handle($_SERVER, $_SESSION, $body));
