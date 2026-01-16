<?php

namespace Teatimelounge\ApiGateway\Controllers;

class MetaController
{
    public function __invoke(): array
    {
        return [
            'service' => 'Tea Time Lounge API Gateway',
            'environment' => 'local',
            'php_version' => PHP_VERSION,
            'timestamp' => date('c'),
            'features' => [
                'psr4_autoloading',
                'lightweight_router',
                'api_gateway_pattern',
            ],
        ];
    }
}