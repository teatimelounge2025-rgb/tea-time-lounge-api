<?php

namespace TeaTimeLounge\ApiGateway\Controllers;

class HealthController
{
    public function __invoke(): array
    {
        return [
            'status' => 'ok',
            'uptime' => $this->getUptime(),
            'checks' => [
                'php' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'timezone' => date_default_timezone_get(),
            ],
        ];
    }

    private function getUptime(): string
    {
        // Simpel en platform-onafhankelijk (demo)
        return gmdate('H:i:s', time() - $_SERVER['REQUEST_TIME']);
    }
}