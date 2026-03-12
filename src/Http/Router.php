<?php
declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Http;

final class Router
{
    /**
     * @var array<string, array<int, array{pattern:string, regex:string, keys:array<int,string>, handler:callable}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $keys = [];

        // /bingo/games/{id}/call -> regex + ["id"]
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $m) use (&$keys): string {
            $keys[] = $m[1];
            return '([^\/]+)';
        }, $path);

        $regex = '#^' . $regex . '$#';

        $this->routes[$method][] = [
            'pattern' => $path,
            'regex' => $regex,
            'keys' => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $match = $this->match($method, $uri);

        if ($match === null) {
            $this->json(['error' => 'Not Found'], 404);
            return;
        }

        [$handler, $params] = $match;

        try {
            $result = $this->invoke($handler, $params);
            $this->json($result, 200);
        } catch (\Throwable $e) {
            $this->json([
                'error' => 'Server Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{0: callable, 1: array<string,string>}|null
     */
    private function match(string $method, string $uri): ?array
    {
        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $route) {
            if (preg_match($route['regex'], $uri, $m)) {
                array_shift($m); // full match eruit

                $params = [];
                foreach ($route['keys'] as $i => $key) {
                    $params[$key] = $m[$i] ?? '';
                }

                return [$route['handler'], $params];
            }
        }

        return null;
    }

    private function invoke(callable $handler, array $params): mixed
    {
        $request = new Request();

        $ref = new \ReflectionFunction(\Closure::fromCallable($handler));
        $argc = $ref->getNumberOfParameters();

        // 0 params: handler()
        if ($argc === 0) {
            return $handler();
        }

        // 1 param: handler(Request $request) of handler(array $params)
        if ($argc === 1) {
            $p0 = $ref->getParameters()[0] ?? null;
            $type = $p0?->getType();

            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                return $handler($request);
            }

            return $handler($params);
        }

        // 2+ params: handler(Request $request, array $params)
        return $handler($request, $params);
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
