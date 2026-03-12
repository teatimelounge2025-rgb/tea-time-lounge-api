<?php
declare(strict_types=1);

use Teatimelounge\ApiGateway\Controllers\LeadImportController;
use Teatimelounge\ApiGateway\Controllers\MetaController;
use Teatimelounge\ApiGateway\Controllers\HealthController;
use Teatimelounge\ApiGateway\Controllers\Bingo\CreateGameController;
use Teatimelounge\ApiGateway\Controllers\Bingo\GetGameController;
use Teatimelounge\ApiGateway\Controllers\Bingo\CallNumberController;
use Teatimelounge\ApiGateway\Controllers\Bingo\ClaimController;

/** @var \Teatimelounge\ApiGateway\Http\Router $router */
$router->get('/health', [HealthController::class, 'check']);
$router->get('/meta', new MetaController());


// Bingo
$router->post('/bingo/games', new CreateGameController());
$router->get('/bingo/games/{id}', new GetGameController());
$router->post('/bingo/games/{id}/call', new CallNumberController());
$router->post('/bingo/games/{id}/claim', new ClaimController());
$router->post('/leads/import', new LeadImportController());