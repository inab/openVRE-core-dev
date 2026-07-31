<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RenameFileRequest', type: 'object', required: ['filename'])]
class RenameFileRequest
{
    #[OA\Property(description: 'New name for the file (without changing its path)', type: 'string')]
    public string $filename;
}