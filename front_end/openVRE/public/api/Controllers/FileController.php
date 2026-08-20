<?php

declare(strict_types=1);

namespace OpenVREAPI\Controllers;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client as MongoClient;
use MongoDB\Collection;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

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
    private ?MongoClient $mongoClient = null;


    private function getMongoClient(): MongoClient
    {
        if ($this->mongoClient === null) {
            $connectionUri = "mongodb://" . getenv('MONGO_CREDENTIALS') . "@" . getenv('MONGO_SERVER') . "/?authSource=" . getenv('MONGO_MAIN_DB');

            $this->mongoClient = new MongoClient($connectionUri, array(
                'readConcernLevel' => 'local'
            ), array(
                'typeMap' => array(
                    'root'     => 'array',
                    'document' => 'array',
                    'array'    => 'array'
                )
            ));
        }

        return $this->mongoClient;
    }

    private function getCollection(string $name): Collection
    {
        return $this->getMongoClient()->selectDatabase(getenv('MONGO_MAIN_DB'))->selectCollection($name);
    }


    #[OA\Get(
        path: '/files',
        tags: ['Files'],
        parameters: [
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                description: 'Number of items to skip before starting to return results',
                schema: new OA\Schema(type: 'integer', minimum: 0, default: 0)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Maximum number of items to return',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 50)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of files and directories belonging to the user',
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
        $offset = max(0, (int) ($queryParams['offset'] ?? 0));
        $limit = max(1, (int) ($queryParams['limit'] ?? 50));

        $filter = ['userId' => $userId];

        try {
            $usersCollection = $this->getCollection('users');
            $filesCollection = $this->getCollection('files');
            $metadataFilesCollection = $this->getCollection('filesMetadata');
            $userDoc = $usersCollection->findOne(['_id' => $userId], ['projection' => ['id' => 1]]);
            $filter = ['owner' => $userDoc['id']];
            $total = $filesCollection->countDocuments($filter);
            $fileDocs = $filesCollection->find($filter, [
                'projection' => ['_id' => 1, 'files' => 1, 'mtime' => 1, 'parentDir' => 1, 'path' => 1, 'size' => 1, 'type' => 1],
                'skip' => $offset,
                'limit' => $limit,
            ])->toArray();

            $fileIds = array_column($fileDocs, '_id');
            $metadataFileDocs = $metadataFilesCollection->find(['_id' => ['$in' => $fileIds]], [
                'projection' => ['data_type' => 1, 'description' => 1, 'format' => 1, 'validated' => 1]
            ])->toArray();
            $metadataById = array_column($metadataFileDocs, null, '_id');

            $files = array_map(function ($doc) use ($metadataById) {
                $merged = array_merge($doc, $metadataById[$doc['_id']] ?? []);
                return $this->documentToFileItem($merged);
            }, $fileDocs);
        } catch (\Throwable $e) {
            return $this->jsonError($response, 500, 'DATABASE_ERROR', 'Failed to fetch files: ' . $e->getMessage());
        }

        $payload = json_encode([
            'userId' => $userId,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'files' => $files,
        ], JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    /**
     * Maps a raw Mongo document (as an associative array, via find()->toArray())
     * into the FileItem shape defined in the OpenAPI schema.
     */
    private function documentToFileItem(array $doc): array
    {
        return [
            'dataType' => $doc['data_type'] ?? '',
            'date' => $this->mongoDateToIso($doc['mtime'] ?? null),
            'fileId' => (string) $doc['_id'],
            'filename' => basename($doc['path']) ?? null,
            'format' => $doc['format'] ?? '',
            'parentId' => $doc['parentDir'] ?? null,
            'path' => $doc['path'] ?? null,
            'size' => (int) ($doc['size'] ?? 0),
            'type' => $doc['type'] ?? '',
        ];
    }

    /**
     * Converts a MongoDB\BSON\UTCDateTime into the Mongo-style ISO 8601
     * string format used by the OpenAPI schema, e.g. "2026-07-17T11:41:19.000+00:00".
     */
    private function mongoDateToIso(?UTCDateTime $date): string
    {
        if ($date === null) {
            return '';
        }

        return $date->toDateTime()->format('Y-m-d\TH:i:s.v') . '+00:00';
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

    private function notImplemented(Response $response, string $operationId): Response
    {
        return $this->jsonError($response, 501, 'NOT_IMPLEMENTED', sprintf('%s has no processing logic yet.', $operationId));
    }
}
