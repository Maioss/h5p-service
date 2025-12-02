<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

use App\H5P\Database;
use App\H5P\H5PFramework;
use App\H5P\EditorStorage;
use App\H5P\Controllers\H5PController;

require __DIR__ . '/../vendor/autoload.php';


// 1. Configuración del Contenedor
$container = new Container();

// Cargar TU configuración
$container->set('config', function () {
    return require __DIR__ . '/../config/h5p.php';
});

// Base de Datos
$container->set('db_connection', function ($c) {
    $config = $c->get('config');
    $database = new Database($config['db']);
    return $database->getConnection();
});

// Framework H5P
$container->set(H5PFramework::class, function ($c) {
    $config = $c->get('config');
    return new H5PFramework($c->get('db_connection'), [
        'platform_name' => 'H5P Microservice',
        'platform_version' => '1.0.0',
        'h5p_version' => '1.27',
        'paths' => $config['paths'],
        'urls' => $config['urls'],
    ]);
});

// Core H5P
$container->set(H5PCore::class, function ($c) {
    $config = $c->get('config');
    $framework = $c->get(H5PFramework::class);

    $fileStorage = new \H5PDefaultStorage(
        $config['paths']['content'],
        $config['paths']['editor_tmp']
    );

    return new H5PCore(
        $framework,
        $fileStorage,
        $config['urls']['h5p'],
        'es',
        true
    );
});



// Storage del Editor
$container->set(EditorStorage::class, function ($c) {
    return new EditorStorage($c->get('db_connection'), $c->get('config'));
});

// Clases de H5P Editor
$container->set(H5peditor::class, function ($c) {
    return new H5peditor(
        $c->get(H5PCore::class),
        $c->get(EditorStorage::class),
        $c->get(EditorStorage::class)
    );
});

$container->set(H5PEditorAjax::class, function ($c) {
    return new H5PEditorAjax(
        $c->get(H5PCore::class),
        $c->get(H5peditor::class),
        $c->get(EditorStorage::class)
    );
});

// Controlador Principal
$container->set(H5PController::class, function ($c) {
    return new H5PController(
        $c->get(H5PCore::class),
        $c->get(H5peditor::class),
        $c->get(H5PEditorAjax::class),
        $c->get(H5PFramework::class),
        $c->get('config')
    );
});

// 2. Inicializar App
AppFactory::setContainer($container);
$app = AppFactory::create();

// IMPORTANTE: Configurar basePath para XAMPP
// Si accedes vía http://localhost/h5p-service/public, el basePath debe ser '/h5p-service/public'
$app->setBasePath('/h5p-service/public');

// Middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 3. Rutas

// Editor UI
$app->get('/editor', H5PController::class . ':showEditor');

// Upload UI
$app->get('/upload', H5PController::class . ':showUploadForm');

// AJAX H5P
$app->any('/h5p/editor-ajax', H5PController::class . ':handleAjax');

// Upload
$app->post('/h5p/upload-library', H5PController::class . ':uploadLibrary');

// Ruta de Vendor (Utilidad para desarrollo local)
$app->get('/vendor/{path:.+}', function (Request $request, Response $response, array $args) {
    $path = realpath(__DIR__ . '/../vendor/' . $args['path']);
    $vendorRoot = realpath(__DIR__ . '/../vendor');

    if ($path && strpos($path, $vendorRoot) === 0 && file_exists($path)) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2'
        ];
        $response->getBody()->write(file_get_contents($path));
        return $response->withHeader('Content-Type', $mimeTypes[$ext] ?? 'text/plain');
    }
    return $response->withStatus(404);
});

$app->get('/db-check', function ($request, $response) use ($container) {
    try {
        // Obtenemos la conexión del contenedor
        $pdo = $container->get('db_connection');

        // Intentamos una consulta real
        $stmt = $pdo->query("SELECT VERSION()");
        $version = $stmt->fetchColumn();

        // Verificamos si existen tablas clave
        $tables = $pdo->query("SHOW TABLES LIKE 'h5p_libraries'")->fetchAll();
        $tableStatus = count($tables) > 0 ? "✅ Tabla 'h5p_libraries' encontrada." : "❌ FALTA la tabla 'h5p_libraries'.";

        $response->getBody()->write("<h1>Estado de Base de Datos</h1>");
        $response->getBody()->write("<p>🔌 Conexión: <strong>EXITOSA</strong></p>");
        $response->getBody()->write("<p>🐬 Versión MySQL: $version</p>");
        $response->getBody()->write("<p>📋 Estructura: $tableStatus</p>");
    } catch (\Exception $e) {
        $response->getBody()->write("<h1>❌ Error de Conexión</h1>");
        $response->getBody()->write("<p>" . $e->getMessage() . "</p>");
    }
    return $response;
});

$app->run();
