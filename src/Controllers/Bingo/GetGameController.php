<?php
declare(strict_types=1);

namespace Teatimelounge\ApiGateway\Controllers\Bingo;

final class GetGameController
{
    public function __invoke(array $params): array
    {
        return [
            'ok' => true,
            'game_id' => $params['id'] ?? null,
            'status' => 'active',
            'calls' => [],
        ];
    }
}