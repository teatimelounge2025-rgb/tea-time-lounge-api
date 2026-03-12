<?php
declare(strict_types=1);

namespace TeaTimelounge\ApiGateway\Controllers\Bingo;

final class ClaimController
{
    public function __invoke(\Teatimelounge\ApiGateway\Http\Request $request, array $params): array
{
    $body = $request->json();

    return [
        'ok' => true,
        'game_id' => $params['id'] ?? null,
        'claim' => 'received',
        'input' => $body,
        'valid' => null,
    ];
}
}