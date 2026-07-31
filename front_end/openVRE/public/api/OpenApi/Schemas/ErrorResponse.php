<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi\Schemas;

use OpenApi\Attributes as OA;

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