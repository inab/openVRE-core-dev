<?php

declare(strict_types=1);

namespace OpenVREAPI\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Handles all file-related endpoints under /{userId}/files.
 *
 * All methods are stubs for now — no business logic / processing implemented.
 * Each returns a 501 Not Implemented placeholder so routes can be tested
 * end-to-end (including auth) before real logic is added.
 */
final class FileController
{
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'getUserFiles');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'deleteFile');
    }

    public function rename(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'renameFile');
    }

    public function move(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'moveFile');
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'downloadFile');
    }

    public function compress(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'compressFile');
    }

    public function uncompress(Request $request, Response $response, array $args): Response
    {
        return $this->notImplemented($response, 'uncompressFile');
    }

    private function notImplemented(Response $response, string $operationId): Response
    {
        $payload = json_encode([
            'code' => 'NOT_IMPLEMENTED',
            'message' => sprintf('%s has no processing logic yet.', $operationId),
        ], JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(501);
    }
}
