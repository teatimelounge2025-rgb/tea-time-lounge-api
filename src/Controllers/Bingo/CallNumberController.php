<?php
declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Controllers\Bingo;

final class CallNumberController
{
    public function __invoke(array $params): array
    {
        return [
            'ok' => true,
            'game_id' => $params['id'] ?? null,
            'called' => random_int(1, 75),
        ];
    }
}