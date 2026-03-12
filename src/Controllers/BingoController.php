<?php
declare(strict_types=1);

namespace App\Controllers;

final class BingoController
{
    public function createGame(): array
    {
        // TODO: later: persist in DB
        return [
            'ok' => true,
            'game_id' => 'bingo_' . bin2hex(random_bytes(4)),
            'status' => 'active',
        ];
    }

    public function getGame(string $id): array
    {
        // TODO: fetch from DB
        return [
            'ok' => true,
            'game_id' => $id,
            'status' => 'active',
            'calls' => [],
        ];
    }

    public function callNumber(string $id): array
    {
        // TODO: later: deterministic call stack + uniqueness
        $n = random_int(1, 75);
        return [
            'ok' => true,
            'game_id' => $id,
            'called' => $n,
        ];
    }

    public function claim(string $id): array
    {
        // TODO: validate claim
        return [
            'ok' => true,
            'game_id' => $id,
            'claim' => 'received',
            'valid' => null,
        ];
    }
}