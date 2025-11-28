<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\H5P\Database;



require __DIR__ . '/../vendor/autoload.php';

// Crear app Slim
$app = AppFactory::create();

// Middleware de routing y errores
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(
    true,   // mostrar detalles de error (dev)
    true,   // loguear errores
    true    // loguear detalles
);

// Ruta raíz
$app->get('/', function (Request $request, Response $response): Response {
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>H5P Editor Service</title>
</head>
<body>
    <h1>H5P Editor Service</h1>
    <ul>
        <li><a href="/ping">/ping</a></li>
        <li><a href="/db-test">/db-test</a></li>
        <li><a href="/editor">/editor (dummy)</a></li>
    </ul>
</body>
</html>
HTML;

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// Ruta de salud
$app->get('/ping', function (Request $request, Response $response): Response {
    $response->getBody()->write('pong');
    return $response;
});

// Ruta para probar la DB
$app->get('/db-test', function (Request $request, Response $response): Response {
    $config = require __DIR__ . '/../config/h5p.php';

    $db = new Database($config['db']);
    $ok = $db->ping();

    $html = '<h1>DB Test</h1>';
    $html .= '<p>Conexión a MySQL: <strong>' . ($ok ? 'OK ✅' : 'FALLÓ ❌') . '</strong></p>';

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

//  TODO: Ruta /editor 


$app->run();
