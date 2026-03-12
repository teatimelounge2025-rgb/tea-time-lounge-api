<?php
declare(strict_types=1);

namespace TeaTimelounge\ApiGateway\Controllers\Bingo;

final class CreateGameController
{
    public function __invoke(): array
    {
        return [
            'ok' => true,
            'game_id' => 'bingo_' . bin2hex(random_bytes(4)),
            'status' => 'active',
        ];
    }
}