<?php
declare(strict_types=1);

use TeaTimeLounge\ApiGateway\Controllers\LeadImportController;
use TeaTimeLounge\ApiGateway\Controllers\MetaController;
use TeaTimeLounge\ApiGateway\Controllers\HealthController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\CreateGameController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\GetGameController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\CallNumberController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\ClaimController;

/** @var \TeatimeLounge\ApiGateway\Http\Router $router */
$router->get('/health', function ($req) {
    return (new HealthController())->index($req);
});
$router->get('/meta', new MetaController());


// Bingo
$router->post('/bingo/games', new CreateGameController());
$router->get('/bingo/games/{id}', new GetGameController());
$router->post('/bingo/games/{id}/call', new CallNumberController());
$router->post('/bingo/games/{id}/claim', new ClaimController());
$router->post('/leads/import', new LeadImportController());