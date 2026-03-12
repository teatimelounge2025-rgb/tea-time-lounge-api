<?php
declare(strict_types=1);

header('Content-Type: text/plain');

$routerFile = __DIR__ . '/../src/Http/Router.php';
$requestFile = __DIR__ . '/../src/Http/Request.php';
$autoload = __DIR__ . '/../vendor/autoload.php';

echo "autoload file: ";
var_dump(file_exists($autoload));

echo "router file: ";
var_dump(file_exists($routerFile));

echo "request file: ";
var_dump(file_exists($requestFile));

echo "\nrouter file contents:\n";
echo file_exists($routerFile) ? file_get_contents($routerFile) : "missing";

echo "\n\n====================\n\nrequest file contents:\n";
echo file_exists($requestFile) ? file_get_contents($requestFile) : "missing";