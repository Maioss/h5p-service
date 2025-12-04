<?php

declare(strict_types=1);

// Obtener la raíz del proyecto de forma limpia
// Si este archivo está en C:\xampp\htdocs\h5p-service\config\h5p.php
// dirname(__DIR__) nos dará C:\xampp\htdocs\h5p-service
$rootPath = realpath(dirname(__DIR__));

// Detectar si estamos en HTTPS (opcional, pero útil)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Detectar entorno y configurar baseUrl dinámicamente
$isBuiltInServer = php_sapi_name() === 'cli-server';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

if ($isBuiltInServer) {
    // Servidor integrado (puerto 8080): sin ruta adicional
    $baseUrl = "$protocol://$host";
} else {
    // XAMPP o producción: detectar si estamos en subdirectorio
    if (strpos($scriptName, '/h5p-service/public') !== false) {
        $baseUrl = "$protocol://$host/h5p-service/public";
    } else {
        $baseUrl = "$protocol://$host";
    }
}

return [
    'db' => [
        'dsn'      => 'mysql:host=127.0.0.1;dbname=h5p_service;charset=utf8mb4',
        'user'     => 'root',
        'password' => '',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
    'paths' => [
        // Usamos $rootPath para construir rutas absolutas sin ".."
        'content'    => $rootPath . '/storage/h5p/content',
        'temp'       => $rootPath . '/storage/h5p/temp',
        'editor_tmp' => $rootPath . '/storage/h5p/editor_tmp',
        'libraries'  => $rootPath . '/storage/h5p/libraries',
    ],
    'urls' => [
        'base'          => $baseUrl,
        'h5p'           => $baseUrl . '/h5p',
        'libraries_url' => $baseUrl . '/h5p/libraries',
        // URLs estáticas para el Core y Editor (Lo que acabamos de copiar)
        'core'          => $baseUrl . '/assets/h5p/core',
        'editor'        => $baseUrl . '/assets/h5p/editor',
    ],
];
