<?php

declare(strict_types=1);

namespace OpenVREAPI\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Holds document-level OpenAPI metadata. This class has no runtime behavior —
 * it exists purely as an attribute target for `zircote/swagger-php` to scan.
 *
 * Regenerate docs/openapi.yaml with:
 *   composer run docs
 */
#[OA\Info(title: 'User Files API', version: '1.0.0')]
#[OA\Server(url: '/api/v1')]
#[OA\OpenApi(security: [['BearerAuth' => []]])]
#[OA\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Parameter(
    parameter: 'FileIdParam',
    name: 'fileId',
    in: 'path',
    required: true,
    description: 'Identifier of the target file',
    schema: new OA\Schema(type: 'string')
)]
final class Definition
{
}