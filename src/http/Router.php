<?php
namespace Teatimelounge\ApiGateway\Http;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (isset($this->routes[$method][$uri])) {
            header('Content-Type: application/json');
            echo json_encode(($this->routes[$method][$uri])(), JSON_PRETTY_PRINT);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}