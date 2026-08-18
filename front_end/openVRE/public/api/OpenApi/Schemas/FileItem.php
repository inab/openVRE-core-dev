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