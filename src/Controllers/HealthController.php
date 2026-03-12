<?php
declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Controllers;

final class HealthController
{
    public function index(array $params = []): array
    {
        return [
            'status' => 200,
            'body' => [
                'status' => 'ok',
            ],
        ];
    }
}