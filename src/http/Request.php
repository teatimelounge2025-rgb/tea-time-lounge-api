<?php
declare(strict_types=1);

namespace TeaTimeLounge\ApiGateway\Http;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    public readonly array $headers;

    private ?array $jsonBody = null;
    private ?string $rawBody = null;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $this->query = $_GET ?? [];
        $this->headers = $this->collectHeaders();
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $raw = trim($this->rawBody());
        if ($raw === '') {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            // we gooien geen exception: controller kan zelf beslissen wat te doen
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $this->jsonBody = $decoded;
        return $this->jsonBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $needle) return $v;
        }
        return $default;
    }

    private function collectHeaders(): array
    {
        // Works on Apache/Nginx; for PHP built-in server this is OK too
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        // Content-Type/Length sometimes not in HTTP_*
        if (isset($_SERVER['CONTENT_TYPE'])) $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        if (isset($_SERVER['CONTENT_LENGTH'])) $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        return $headers;
    }
}