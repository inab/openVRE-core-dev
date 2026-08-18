<?php

declare(strict_types=1);

use OpenVREAPI\Controllers\FileController;
use OpenVREAPI\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    // API documentation — intentionally NOT behind AuthMiddleware, so the
    // spec and Swagger UI are browsable without a token.
    $app->get('/openapi.yaml', function (Request $_, Response $response) {
        $yaml = file_get_contents(__DIR__ . '/docs/openapi.yaml');
        $response->getBody()->write($yaml);
 
        return $response->withHeader('Content-Type', 'application/yaml');
    });
 
    $app->get('/docs', function (Request $_, Response $response) {
        $html = file_get_contents(__DIR__ . '/index.html');
        $response->getBody()->write($html);
 
        return $response->withHeader('Content-Type', 'text/html');
    });
 
    $app->group('/files', function ($group) {
        $group->get('', [FileController::class, 'list']);
 
        $group->delete('/{fileId}', [FileController::class, 'delete']);
        $group->patch('/{fileId}/rename', [FileController::class, 'rename']);
        $group->patch('/{fileId}/move', [FileController::class, 'move']);
        $group->get('/{fileId}/download', [FileController::class, 'download']);
        $group->post('/{fileId}/compress', [FileController::class, 'compress']);
        $group->post('/{fileId}/uncompress', [FileController::class, 'uncompress']);
    })->add(AuthMiddleware::class); // Auth is mandatory for every route in this group
};
