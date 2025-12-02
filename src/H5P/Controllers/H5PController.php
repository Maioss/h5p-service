<?php

declare(strict_types=1);

namespace App\H5P\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\H5P\H5PFramework;
use H5PCore;
use H5peditor;
use H5PEditorAjax;
use H5PValidator;
use H5PStorage;

class H5PController
{
    private H5PCore $h5pCore;
    private H5peditor $h5pEditor;
    private H5PEditorAjax $h5pEditorAjax;
    private H5PFramework $h5pFramework;
    private array $config;

    public function __construct(
        H5PCore $h5pCore,
        H5peditor $h5pEditor,
        H5PEditorAjax $h5pEditorAjax,
        H5PFramework $h5pFramework,
        array $config
    ) {
        $this->h5pCore = $h5pCore;
        $this->h5pEditor = $h5pEditor;
        $this->h5pEditorAjax = $h5pEditorAjax;
        $this->h5pFramework = $h5pFramework;
        $this->config = $config;
    }

    /**
     * Renders the H5P Editor UI.
     */
    public function showEditor(Request $request, Response $response): Response
    {
        $editorSettings = $this->getEditorSettings();
        $libraries = $this->h5pEditor->getLibraries();
        $assets = $this->getEditorAssets();

        $viewData = [
            'h5pIntegration' => $editorSettings,
            'libraries' => $libraries,
            'jsAssets' => $assets['js'],
            'cssAssets' => $assets['css']
        ];

        return $this->renderView($response, 'h5p-editor.php', $viewData);
    }

    /**
     * Renders the H5P Library Upload UI.
     */
    public function showUploadForm(Request $request, Response $response): Response
    {
        return $this->renderView($response, 'upload.php', [
            'baseUrl' => $this->config['urls']['base']
        ]);
    }

    /**
     * Handles AJAX requests from the H5P Editor.
     */
    public function handleAjax(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        // H5P expects these in global arrays sometimes, though we try to avoid it.
        // Ideally H5PEditorAjax should be decoupled, but for now we map request data.
        $action = $queryParams['action'] ?? ($parsedBody['action'] ?? 'libraries');

        ob_start();
        try {
            $this->h5pEditorAjax->action($action);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $output = json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }

        $response->getBody()->write($output ?: '');
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Handles H5P library uploads.
     */
    /**
     * Flujo Estándar de Subida H5P
     * 1. Recibe ZIP -> 2. Valida -> 3. Extrae metadatos -> 4. Persiste en BD y Mueve archivos
     */
    public function uploadLibrary(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $file = $uploadedFiles['h5p_file'] ?? null;

        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Error en la subida.'], 400);
        }

        // 1. Obtener la carpeta de extracción (destino)
        $extractDir = $this->h5pFramework->getUploadedH5pFolderPath(); // .../temp/h5p-upload/

        // 2. Definir ruta del ZIP usando nombre seguro
        // Usamos dirname() para subir un nivel y no meter el zip DENTRO de la carpeta de extracción
        $tempBaseDir = dirname($extractDir);

        // Asegurar que existe la carpeta base
        if (!is_dir($tempBaseDir)) mkdir($tempBaseDir, 0777, true);

        // 3. Limpieza PREVIA (Vital)
        // Borramos la carpeta de extracción para que H5P encuentre terreno limpio
        if (is_dir($extractDir)) {
            $this->recursiveRmdir($extractDir);
        }

        // Definir nombre del archivo temporal
        $safeZipFilename = 'subida_' . uniqid() . '.h5p';
        $relativeZipPath = $tempBaseDir . '/' . $safeZipFilename;

        try {
            // 4. Mover el archivo
            $file->moveTo($relativeZipPath);

            // 🛑 TRUCO DE ORO: Obtener la ruta ABSOLUTA del sistema
            // Esto convierte "../storage/..." en "C:\xampp\htdocs\..."
            // ZipArchive en Windows NECESITA esto para ser fiable al 100%
            $absoluteZipPath = realpath($relativeZipPath);

            if (!$absoluteZipPath || !file_exists($absoluteZipPath)) {
                throw new \Exception("Error crítico: El archivo se movió pero no se encuentra la ruta absoluta.");
            }

            // 5. Configurar $_FILES con la ruta ABSOLUTA
            $_FILES['h5p_file'] = [
                'name'     => $file->getClientFilename(),
                'tmp_name' => $absoluteZipPath, // <--- Aquí está la magia
                'error'    => 0,
                'size'     => filesize($absoluteZipPath),
                'type'     => 'application/zip'
            ];

            // 6. Validar
            $validator = new H5PValidator($this->h5pFramework, $this->h5pCore);

            // false, false = Validar todo (Librerías y Contenido)
            if (!$validator->isValidPackage(false, false)) {
                $errors = $this->h5pFramework->getMessages('error');
                // Si falla, borramos el zip para no dejar basura
                @unlink($absoluteZipPath);
                throw new \Exception(implode(', ', $errors ?: ['El paquete es inválido (isValidPackage devolvió false)']));
            }

            // 7. Extraer título (Opcional, para BD)
            $content = null;
            $h5pJsonPath = $extractDir . '/h5p.json'; // El validador ya descomprimió aquí
            if (file_exists($h5pJsonPath)) {
                $json = json_decode(file_get_contents($h5pJsonPath), true);
                $content = ['title' => $json['title'] ?? 'Sin Título'];
            }

            // 8. Guardar en BD y mover a storage final
            $storage = new H5PStorage($this->h5pFramework, $this->h5pCore);
            $storage->savePackage(null, null, true);

            // Limpieza final
            if (file_exists($absoluteZipPath)) @unlink($absoluteZipPath);
            $this->recursiveRmdir($extractDir); // Opcional: limpiar carpeta temporal de extracción

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => '¡Instalado correctamente!'
            ]);
        } catch (\Throwable $e) {
            // Limpieza de emergencia
            if (isset($absoluteZipPath) && file_exists($absoluteZipPath)) @unlink($absoluteZipPath);

            error_log("H5P Upload Error: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper para respuesta JSON
    private function jsonResponse($response, $data, $status = 200)
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    // Helper para borrar carpetas no vacías (necesario en Windows)
    private function recursiveRmdir($dir)
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
    }

    private function getEditorSettings(): array
    {
        $baseUrl = $this->config['urls']['base'];
        $h5pUrl = $this->config['urls']['h5p'];
        $librariesUrl = $this->config['urls']['libraries_url'];

        $settings = [
            'baseUrl' => $baseUrl,
            'url' => $h5pUrl,
            'postUserStatistics' => false,
            'ajax' => [
                'setFinished' => $baseUrl . '/h5p/ajax/set-finished',
                'contentUserData' => $baseUrl . '/h5p/ajax/content-user-data',
            ],
            'saveFreq' => false,
            'siteUrl' => $baseUrl,
            'l10n' => ['H5P' => $this->getLocalizationStrings()],
            'hubIsEnabled' => true,
            'reportingIsEnabled' => false,
            'crossorigin' => null,
            'libraryConfig' => $this->h5pFramework->getLibraryConfig(),
            'pluginCacheBuster' => '?ver=1.27',
            'libraryUrl' => $librariesUrl,
        ];

        $integration = [
            'editor' => [
                'filesPath' => $this->config['paths']['editor_tmp'],
                'fileIcon' => [
                    'path' => $h5pUrl . '/editor/images/binary-file.png',
                    'width' => 50,
                    'height' => 50,
                ],
                'ajaxPath' => $baseUrl . '/h5p/editor-ajax?action=',
                'libraryUrl' => $librariesUrl,
                'copyrightSemantics' => null,
                'metadataSemantics' => null,
                'assets' => [],
                'deleteMessage' => 'Delete content?',
                'apiVersion' => H5PCore::$coreApi,
            ],
        ];

        return array_merge($settings, $integration);
    }

    private function getEditorAssets(): array
    {
        // Usamos las nuevas rutas definidas en config
        $coreUrl = $this->config['urls']['core'];     // public/assets/h5p/core
        $editorUrl = $this->config['urls']['editor']; // public/assets/h5p/editor

        $js = [
            // Core (Fíjate que ya no dice 'vendor', usa la variable limpia)
            $coreUrl . '/js/jquery.js',
            $coreUrl . '/js/h5p.js',
            $coreUrl . '/js/h5p-event-dispatcher.js',
            $coreUrl . '/js/h5p-x-api-event.js',
            $coreUrl . '/js/h5p-x-api.js',
            $coreUrl . '/js/h5p-content-type.js',
            $coreUrl . '/js/h5p-confirmation-dialog.js',
            $coreUrl . '/js/h5p-action-bar.js',
            $coreUrl . '/js/request-queue.js',

            // Editor
            $editorUrl . '/scripts/h5peditor.js',
            $editorUrl . '/scripts/h5peditor-editor.js',
            $editorUrl . '/scripts/h5p-hub-client.js',
            $editorUrl . '/scripts/h5peditor-selector-hub.js',
            $editorUrl . '/scripts/h5peditor-selector-legacy.js',
            $editorUrl . '/scripts/h5peditor-semantic-structure.js',
            $editorUrl . '/scripts/h5peditor-library-selector.js',
            $editorUrl . '/scripts/h5peditor-form.js',
            $editorUrl . '/scripts/h5peditor-fullscreen-bar.js',
            $editorUrl . '/ckeditor/ckeditor.js',
        ];

        // Widgets...
        $widgets = ['text', 'number', 'html', 'textarea', 'file-uploader', 'library', 'boolean', 'select', 'list', 'list-editor', 'group', 'dimensions', 'coordinates', 'none'];
        foreach ($widgets as $widget) {
            $js[] = $editorUrl . "/scripts/h5peditor-{$widget}.js";
        }

        // Resto de JS...
        $js[] = $editorUrl . '/scripts/h5peditor-metadata.js';
        $js[] = $editorUrl . '/scripts/h5peditor-metadata-author-widget.js';
        $js[] = $editorUrl . '/scripts/h5peditor-metadata-changelog-widget.js';
        $js[] = $editorUrl . '/scripts/h5peditor-pre-save.js';
        $js[] = $editorUrl . '/language/es.js';

        // CSS
        $css = [
            $coreUrl . '/styles/h5p.css',
            $coreUrl . '/styles/h5p-confirmation-dialog.css',
            $coreUrl . '/styles/h5p-core-button.css',
            $editorUrl . '/styles/css/h5p-hub-client.css',
            $editorUrl . '/styles/css/fonts.css',
            $editorUrl . '/styles/css/application.css',
            $editorUrl . '/styles/css/libs/zebra_datepicker.min.css',
        ];

        return ['js' => $js, 'css' => $css];
    }

    private function getLocalizationStrings(): array
    {
        return [
            'fullscreen' => 'Pantalla completa',
            'disableFullscreen' => 'Salir de pantalla completa',
            'download' => 'Descargar',
            'copyrights' => 'Derechos de uso',
            'embed' => 'Insertar',
            'size' => 'Tamaño',
            'showAdvanced' => 'Mostrar avanzado',
            'hideAdvanced' => 'Ocultar avanzado',
        ];
    }

    private function renderView(Response $response, string $templateName, array $data): Response
    {
        extract($data);
        ob_start();
        // Assuming templates are in project_root/templates
        require __DIR__ . '/../../../templates/' . $templateName;
        $content = ob_get_clean();

        $response->getBody()->write($content);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
