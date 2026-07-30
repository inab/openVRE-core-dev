<?php

declare(strict_types=1);

require_once __DIR__ . "/../../vendor/autoload.php";

use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->setBasePath('/api/v1');

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// displayErrorDetails=true is for local development only; disable in production.
$app->addErrorMiddleware(true, true, true);

$registerRoutes = require_once __DIR__ . '/routes.php'; // closure
$registerRoutes($app);

$app->run();
