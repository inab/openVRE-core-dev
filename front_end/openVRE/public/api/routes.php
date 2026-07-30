<?php

declare(strict_types=1);

use OpenVREAPI\Controllers\FileController;
use OpenVREAPI\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {
    $app->get('/', function (Request $_, Response $response) {
        $response->getBody()->write("Welcome to the openVRE API!\n");
        return $response;
    });

    $app->group('/{userId}/files', function ($group) {
        $group->get('', [FileController::class, 'list']);

        $group->delete('/{fileId}', [FileController::class, 'delete']);
        $group->patch('/{fileId}/rename', [FileController::class, 'rename']);
        $group->patch('/{fileId}/move', [FileController::class, 'move']);
        $group->get('/{fileId}/download', [FileController::class, 'download']);
        $group->post('/{fileId}/compress', [FileController::class, 'compress']);
        $group->post('/{fileId}/uncompress', [FileController::class, 'uncompress']);
    })->add(new AuthMiddleware());
};
