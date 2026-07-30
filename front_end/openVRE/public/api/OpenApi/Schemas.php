<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FileItem',
    type: 'object',
    required: ['fileId', 'filename', 'format', 'type', 'dataType', 'date', 'size']
)]
class FileItem
{
    #[OA\Property(description: 'Unique identifier of the file or directory', type: 'string')]
    public string $fileId;

    #[OA\Property(description: 'Name of the file or directory', type: 'string')]
    public string $filename;

    #[OA\Property(description: "File format/extension (e.g. 'pdf', 'jpg')", type: 'string')]
    public string $format;

    #[OA\Property(description: 'Whether the item is a file or a directory', type: 'string', enum: ['file', 'dir'])]
    public string $type;

    #[OA\Property(description: 'Content/data type of the file (e.g. MIME type or category)', type: 'string')]
    public string $dataType;

    #[OA\Property(
        description: 'Mongo-style ISO 8601 date-time string with milliseconds and UTC offset',
        type: 'string',
        format: 'date-time',
        example: '2026-07-17T11:41:19.000+00:00'
    )]
    public string $date;

    #[OA\Property(description: 'Size of the file in bytes', type: 'integer', minimum: 0)]
    public int $size;

    #[OA\Property(description: "Current path of the file within the user's storage", type: 'string')]
    public string $path;
}

#[OA\Schema(
    schema: 'GetUserFilesResponse',
    type: 'object',
    required: ['userId', 'offset', 'limit', 'total', 'files']
)]
class GetUserFilesResponse
{
    #[OA\Property(description: "Identifier of the authenticated user (from the bearer token), whose files these are", type: 'string')]
    public string $userId;

    #[OA\Property(description: 'Number of items skipped for this page of results', type: 'integer', minimum: 0)]
    public int $offset;

    #[OA\Property(description: 'Maximum number of items returned for this page', type: 'integer', minimum: 1)]
    public int $limit;

    #[OA\Property(description: 'Total number of files available for this user', type: 'integer', minimum: 0)]
    public int $total;

    /** @var FileItem[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/FileItem'))]
    public array $files;
}

#[OA\Schema(schema: 'RenameFileRequest', type: 'object', required: ['filename'])]
class RenameFileRequest
{
    #[OA\Property(description: 'New name for the file (without changing its path)', type: 'string')]
    public string $filename;
}

#[OA\Schema(schema: 'MoveFileRequest', type: 'object', required: ['path'])]
class MoveFileRequest
{
    #[OA\Property(description: 'New destination path for the file', type: 'string', example: '/documents/archive')]
    public string $path;
}

#[OA\Schema(schema: 'Error', type: 'object', required: ['code', 'status', 'message'])]
class ErrorResponse
{
    #[OA\Property(description: 'Machine-readable error code', type: 'string', example: 'UNAUTHORIZED')]
    public string $code;

    #[OA\Property(description: 'HTTP status code, echoed in the body for convenience', type: 'integer', example: 401)]
    public int $status;

    #[OA\Property(description: 'Human-readable explanation', type: 'string', example: 'Authorization header not found')]
    public string $message;
}