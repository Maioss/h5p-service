<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Cargar configuración
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Conexión a BD
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset={$_ENV['DB_CHARSET']}",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Inicializar Framework
$framework = new \App\H5P\Framework\H5PFramework($pdo);

// Crear app Slim
$app = AppFactory::create();

// Rutas
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write("H5P Service is running!");
    return $response;
});

// Ruta para mostrar el Hub
$app->get('/hub', function (Request $request, Response $response) use ($framework) {
    $controller = new \App\H5P\Controllers\HubController($framework);
    return $controller->showHub($request, $response);
});

// API para obtener content types
$app->get('/api/content-types', function (Request $request, Response $response) use ($framework) {
    $controller = new \App\H5P\Controllers\HubController($framework);
    return $controller->getContentTypes($request, $response);
});

$app->run();
