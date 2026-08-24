<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'GetUserFilesResponse',
    type: 'object',
    required: ['userId', 'offset', 'limit', 'total', 'files']
)]
class GetUserFilesResponse
{
    #[OA\Property(description: 'Identifier of the authenticated user (from the bearer token), whose files these are', type: 'string')]
    public string $userId;

    #[OA\Property(description: 'Number of items skipped for this page of results', type: 'integer', minimum: 0)]
    public int $offset;

    #[OA\Property(description: 'Maximum number of items returned for this page', type: 'integer', minimum: 1, maximum: 100)]
    public int $limit;

    #[OA\Property(description: 'Total number of files matching the current filter (owner and optional q), not just this page', type: 'integer', minimum: 0)]
    public int $total;

    /** @var FileItem[] */
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/FileItem'))]
    public array $files;
}