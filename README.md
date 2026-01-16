# Tea Time Lounge – PHP API Gateway

A lightweight, framework-less PHP API gateway demonstrating modern PHP practices:
PSR-4 autoloading, invokable controllers, and a minimal routing layer.

This repository was created to showcase hands-on PHP experience without relying on
large frameworks such as Laravel or Symfony.

---

## ✨ Features

- PHP 8.1+
- PSR-4 autoloading via Composer
- Minimal HTTP router
- Invokable controllers
- JSON-only API responses
- Health & meta endpoints
- Suitable as an API gateway or edge service

---

## 📂 Project Structure

. ├── public/ │ └── index.php # Front controller ├── src/ │ ├── Http/ │ │ └── Router.php # Minimal router │ └── Controllers/ │ ├── MetaController.php │ └── HealthController.php ├── vendor/ ├── composer.json └── README.md

---

## 🚀 Getting Started

### Requirements

- PHP 8.1 or higher
- Composer

### Install dependencies

```bash
composer install

Start the development server

php -S localhost:8000 -t public


---

🔌 Available Endpoints

GET /meta

Returns basic service metadata.

{
  "service": "Tea Time Lounge API Gateway",
  "environment": "local",
  "php_version": "8.5.1",
  "timestamp": "2026-01-16T06:21:09+00:00",
  "features": [
    "psr4_autoloading",
    "lightweight_router",
    "api_gateway_pattern"
  ]
}


---

GET /health

Health check endpoint suitable for load balancers or monitoring.

{
  "status": "ok",
  "uptime": "00:00:00",
  "checks": {
    "php": "8.5.1",
    "memory_limit": "128M",
    "timezone": "UTC"
  }
}


---

🧠 Design Notes

No framework by design — focuses on understanding PHP internals

Composer PSR-4 autoloading instead of manual includes

Controllers are invokable classes

Router intentionally minimal to keep responsibilities explicit



---

📜 License

MIT