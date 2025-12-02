<?php
// setup_assets.php
// Ejecuta esto una vez (o cada vez que actualices composer): php setup_assets.php

echo "🔄 Iniciando copia de assets de H5P...\n";

$sourceDir = __DIR__ . '/vendor/h5p';
$destDir = __DIR__ . '/public/assets/h5p';

if (!is_dir($sourceDir)) {
    die("❌ Error: No se encuentra la carpeta vendor/h5p. ¿Ejecutaste composer install?\n");
}

// Función recursiva para copiar
function recurseCopy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst, 0777, true);

    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recurseCopy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// 1. Copiar H5P Core
echo "📦 Copiando H5P Core...\n";
recurseCopy($sourceDir . '/h5p-core', $destDir . '/core');

// 2. Copiar H5P Editor
echo "📦 Copiando H5P Editor...\n";
recurseCopy($sourceDir . '/h5p-editor', $destDir . '/editor');

echo "✅ ¡Listo! Assets copiados en public/assets/h5p/\n";
echo "👉 Ahora actualiza tu config/h5p.php para apuntar a las nuevas rutas.\n";
