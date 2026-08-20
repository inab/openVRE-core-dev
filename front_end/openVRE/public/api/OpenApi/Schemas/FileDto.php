<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use JsonSerializable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'FileItem',
    type: 'object',
    required: ['fileId', 'filename', 'format', 'type', 'dataType', 'date', 'size']
)]
final readonly class FileDto implements JsonSerializable
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

    #[OA\Property(description: 'Unique identifier of the parent directory', type: 'string')]
    public ?string $parentId;

    #[OA\Property(description: 'Tag for a subtype of file/folder', type: 'string', enum: ['file', 'file_unvalidated', 'folder', 'folder_empty', 'folder_uploads', 'folder_repository'])]
    public string $kind;


    public function __construct(
        string $fileId,
        string $filename,
        string $format,
        string $type,
        string $dataType,
        string $date,
        int $size,
        string $path,
        ?string $parentId,
        string $kind
    ) {
        $this->fileId = $fileId;
        $this->filename = $filename;
        $this->format = $format;
        $this->type = $type;
        $this->dataType = $dataType;
        $this->date = $date;
        $this->size = $size;
        $this->path = $path;
        $this->parentId = $parentId;
        $this->kind = $kind;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
