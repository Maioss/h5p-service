<?php
// Verificar extensiones
$required = ['curl', 'gd', 'json', 'mbstring', 'mysqli', 'zip'];
foreach ($required as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "✓" : "✗") . "\n";
}

// Verificar conexión BD
try {
    $pdo = new PDO("mysql:host=localhost;dbname=h5p_service", "root", "");
    echo "Database: ✓\n";
} catch (PDOException $e) {
    echo "Database: ✗ - " . $e->getMessage() . "\n";
}

// Verificar archivos
$files = [
    'vendor/h5p/h5p-core',
    'vendor/h5p/h5p-editor',
    'vendor/h5p-hub-client/dist/h5p-hub-client.js',
    'public/assets/js/h5p-hub-client.js'
];

foreach ($files as $file) {
    echo $file . ": " . (file_exists($file) ? "✓" : "✗") . "\n";
}
