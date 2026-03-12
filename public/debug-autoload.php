<?php
declare(strict_types=1);

header('Content-Type: text/plain');

echo "autoload file: ";
$autoload = __DIR__ . '/../vendor/autoload.php';
var_dump(file_exists($autoload));

if (!file_exists($autoload)) {
    exit;
}

require $autoload;

echo "\nclass Router exists: ";
var_dump(class_exists(\TeaTimeLounge\ApiGateway\Http\Router::class));

echo "\nclass Request exists: ";
var_dump(class_exists(\TeaTimeLounge\ApiGateway\Http\Request::class));

echo "\ncomposer psr4 map:\n";
$mapFile = __DIR__ . '/../vendor/composer/autoload_psr4.php';
if (file_exists($mapFile)) {
    $map = require $mapFile;
    print_r($map);
} else {
    echo "autoload_psr4.php not found\n";
}