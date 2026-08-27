<?php

declare(strict_types=1);

namespace OpenVREAPI\Controllers;

use OpenApi\Attributes as OA;
use OpenVREAPI\Services\FileService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

/**
 * Handles all file-related endpoints under /files.
 *
 * userId is never taken from the URL — it's derived from the authenticated
 * Bearer token's subject claim (see AuthMiddleware), so a request can only
 * ever act on the caller's own files.
 *
 * OA attributes document the intended contract; docs/openapi.yaml is
 * regenerated from these via `composer run docs`.
 */
final class FileController
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    private const MAX_Q_LENGTH = 200;

    public function __construct(private readonly ?FileService $fileService = null)
    {
    }

    #[OA\Get(
        path: '/files',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                description: 'Optional number of items to skip. Ignored unless `limit` and/or `offset` is provided; defaults to 0 when paging.',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 0)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Optional maximum items to return (capped at 200). When neither `limit` nor `offset` is set, the full list is returned.',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: self::MAX_LIMIT)
            ),
            new OA\Parameter(
                name: 'q',
                in: 'query',
                description: 'Optional case-insensitive substring match against file path; applied before pagination. Non-string values are ignored; truncated to 200 characters.',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: self::MAX_Q_LENGTH)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of files and directories belonging to the user. Without `limit`/`offset`, returns the full list sorted by path. With either param, applies skip/limit after sorting by path.',
                content: new OA\JsonContent(ref: '#/components/schemas/GetUserFilesResponse')
            ),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function list(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim
        $queryParams = $request->getQueryParams();
        $paging = $this->pagingOptions($queryParams);
        $q = $this->searchQuery($queryParams);

        try {
            $result = $this->fileService()->findByUserId(
                $userId,
                $paging['offset'] ?? null,
                $paging['limit'] ?? null,
                $q,
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return $this->jsonError($response, 404, 'NOT_FOUND', 'User not found');
            }

            return $this->jsonError($response, 500, 'DATABASE_ERROR', 'Failed to fetch files: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return $this->jsonError($response, 500, 'DATABASE_ERROR', 'Failed to fetch files: ' . $e->getMessage());
        }

        $total = $result['total'];
        $payload = json_encode([
            'userId' => $userId,
            'offset' => $paging['offset'] ?? 0,
            'limit' => $paging['limit'] ?? $total,
            'total' => $total,
            'files' => $result['files'],
        ], JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    #[OA\Delete(
        path: '/files/{fileId}',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        responses: [
            new OA\Response(response: 204, description: 'File deleted successfully'),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User or file not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function delete(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'deleteFile');
    }

    #[OA\Patch(
        path: '/files/{fileId}/rename',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RenameFileRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'File renamed successfully', content: new OA\JsonContent(ref: '#/components/schemas/FileItem')),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User or file not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'A file with the new name already exists at this location', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function rename(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'renameFile');
    }

    #[OA\Patch(
        path: '/files/{fileId}/move',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/MoveFileRequest')
        ),
        responses: [
            new OA\Response(response: 200, description: 'File moved successfully', content: new OA\JsonContent(ref: '#/components/schemas/FileItem')),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User, file, or destination path not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'A file already exists at the destination path', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function move(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'moveFile');
    }

    #[OA\Get(
        path: '/files/{fileId}/download',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'File content stream',
                content: new OA\MediaType(mediaType: 'application/octet-stream', schema: new OA\Schema(type: 'string', format: 'binary'))
            ),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User or file not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function download(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'downloadFile');
    }

    #[OA\Post(
        path: '/files/{fileId}/compress',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        responses: [
            new OA\Response(response: 202, description: 'Compression started/completed; returns the resulting file', content: new OA\JsonContent(ref: '#/components/schemas/FileItem')),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User or file not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'File is already compressed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function compress(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'compressFile');
    }

    #[OA\Post(
        path: '/files/{fileId}/uncompress',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/FileIdParam'),
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Uncompression started/completed; returns the resulting file(s)',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/FileItem'))
            ),
            new OA\Response(response: 401, description: 'Missing or malformed Authorization header', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 403, description: 'Token present but invalid or expired', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'User or file not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 409, description: 'File is not a compressed archive', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ]
    )]
    public function uncompress(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('userId'); // set by AuthMiddleware from the token's subject claim

        return $this->notImplemented($response, 'uncompressFile');
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{offset: int, limit: int}|null Null when neither limit nor offset is provided.
     */
    private function pagingOptions(array $queryParams): ?array
    {
        $hasOffset = $this->hasQueryParam($queryParams, 'offset');
        $hasLimit = $this->hasQueryParam($queryParams, 'limit');
        if (!$hasOffset && !$hasLimit) {
            return null;
        }

        $offset = $hasOffset ? max(0, (int) $queryParams['offset']) : 0;
        $rawLimit = $hasLimit ? (int) $queryParams['limit'] : self::DEFAULT_LIMIT;
        $limit = min(self::MAX_LIMIT, max(1, $rawLimit));

        return ['offset' => $offset, 'limit' => $limit];
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function hasQueryParam(array $queryParams, string $key): bool
    {
        return array_key_exists($key, $queryParams)
            && $queryParams[$key] !== ''
            && $queryParams[$key] !== null;
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function searchQuery(array $queryParams): string
    {
        $raw = $queryParams['q'] ?? '';
        if (!is_string($raw)) {
            return '';
        }

        $q = trim($raw);
        if ($q === '') {
            return '';
        }

        if (mb_strlen($q) > self::MAX_Q_LENGTH) {
            return mb_substr($q, 0, self::MAX_Q_LENGTH);
        }

        return $q;
    }

    private function fileService(): FileService
    {
        return $this->fileService ?? new FileService();
    }

    private function notImplemented(Response $response, string $operationId): Response
    {
        return $this->jsonError(
            $response,
            501,
            'NOT_IMPLEMENTED',
            sprintf('%s has no processing logic yet.', $operationId),
        );
    }

    private function jsonError(Response $response, int $status, string $code, string $message): Response
    {
        $payload = json_encode([
            'code' => $code,
            'status' => $status,
            'message' => $message,
        ], JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
