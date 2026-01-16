<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Teatimelounge\ApiGateway\Http\Router;
use Teatimelounge\ApiGateway\Controllers\MetaController;
use Teatimelounge\ApiGateway\Controllers\HealthController;

require __DIR__ . '/../vendor/autoload.php';

$router = new Router();

$router->get('/meta', new MetaController());
$router->get('/health', new HealthController());

$router->dispatch();
