<?php
declare(strict_types=1);

use TeaTimeLounge\ApiGateway\Controllers\LeadImportController;
use TeaTimeLounge\ApiGateway\Controllers\MetaController;
use TeaTimeLounge\ApiGateway\Controllers\HealthController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\CreateGameController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\GetGameController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\CallNumberController;
use TeaTimeLounge\ApiGateway\Controllers\Bingo\ClaimController;
use TeaTimeLounge\ApiGateway\Http\Request;

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

// Leads
$router->post('/leads/import', function (array $params = []) {
    return (new LeadImportController())(new Request());
});

$router->post('/api/leads/{id}/generate-email', function (array $params = []) {
    $_GET['id'] = $params['id'] ?? null;
    return (new LeadImportController())->generateEmail(new Request());
});

$router->post('/api/leads/generate-follow-up', function () {
    return (new LeadImportController())->generateFollowUp(new Request());
});

