<?php

/**
 * Router para el servidor PHP integrado
 * Uso: php -S localhost:8080 -t public public/router.php
 */


// Si es un archivo estático que existe, déjalo pasar
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Servir archivos estáticos directamente si existen
if ($path !== '/' && file_exists($file) && is_file($file)) {
    return false; // Dejar que el servidor PHP integrado lo maneje
}

// De lo contrario, pasar todo a index.php
require __DIR__ . '/index.php';
