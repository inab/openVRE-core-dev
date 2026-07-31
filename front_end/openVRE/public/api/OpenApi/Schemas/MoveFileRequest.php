<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'MoveFileRequest', type: 'object', required: ['path'])]
class MoveFileRequest
{
    #[OA\Property(description: 'New destination path for the file', type: 'string', example: '/documents/archive')]
    public string $path;
}