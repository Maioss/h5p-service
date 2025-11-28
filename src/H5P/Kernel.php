<?php

declare(strict_types=1);

namespace App\H5P;

use H5PCore;
use H5peditor;
use H5PDefaultStorage;
use App\H5P\Database;
use App\H5P\H5PFramework;

final class Kernel
{
    private H5PFramework $framework;
    private H5PCore $core;
    private H5peditor $editor;
    private array $config;

    public function __construct(array $config, Database $db)
    {
        $this->config = $config;

        // Implementación de H5PFrameworkInterface que ya tienes
        $this->framework = new H5PFramework($db, $config);

        $paths = $config['paths'];
        $urls  = $config['urls'] ?? [];

        $contentDir   = rtrim($paths['content'], '/');      // ej: storage/h5p/content
        $tmpDir       = rtrim($paths['temp'], '/');         // ej: storage/h5p/temp
        $editorTmpDir = rtrim($paths['editor_tmp'], '/');   // ej: storage/h5p/editor_tmp
        $librariesDir = rtrim($paths['libraries'], '/');    // ej: storage/h5p/libraries

        $language = $config['language'] ?? 'en';
        $baseUrl  = isset($urls['base']) ? rtrim($urls['base'], '/') : 'http://localhost:8080';

        // Core de H5P
        $this->core = new H5PCore(
            $this->framework,
            $contentDir,   // carpeta de contenidos
            $baseUrl,      // URL base
            $language,
            false          // export (de momento no)
        );

        // Storage por defecto
        $this->core->fs = new H5PDefaultStorage(
            $this->framework,
            $contentDir,
            $tmpDir
        );

        // Editor (lado servidor)
        $this->editor = new H5peditor(
            $this->core,
            $this->framework,
            $editorTmpDir,
            $librariesDir
        );
    }

    public function getFramework(): H5PFramework
    {
        return $this->framework;
    }

    public function getCore(): H5PCore
    {
        return $this->core;
    }

    public function getEditor(): H5peditor
    {
        return $this->editor;
    }
}
