<?php
declare(strict_types=1);

use TeaTimelounge\ApiGateway\Controllers\LeadImportController;
use TeaTimelounge\ApiGateway\Controllers\MetaController;
use TeaTimelounge\ApiGateway\Controllers\HealthController;
use TeaTimelounge\ApiGateway\Controllers\Bingo\CreateGameController;
use TeaTimelounge\ApiGateway\Controllers\Bingo\GetGameController;
use TeaTimelounge\ApiGateway\Controllers\Bingo\CallNumberController;
use TeaTimelounge\ApiGateway\Controllers\Bingo\ClaimController;

/** @var \Teatimelounge\ApiGateway\Http\Router $router */
$router->get('/health', [HealthController::class, 'check']);
$router->get('/meta', new MetaController());


// Bingo
$router->post('/bingo/games', new CreateGameController());
$router->get('/bingo/games/{id}', new GetGameController());
$router->post('/bingo/games/{id}/call', new CallNumberController());
$router->post('/bingo/games/{id}/claim', new ClaimController());
$router->post('/leads/import', new LeadImportController());