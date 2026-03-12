<?php
declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Controllers;

use TeaTimeLounge\ApiGateway\Http\Request;

final class HealthController
{
    public function index(Request $request): array
    {
        return [
            'status' => 200,
            'body' => [
                'status' => 'ok',
            ],
        ];
    }
}