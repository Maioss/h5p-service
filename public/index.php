<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use App\H5PFramework;

// Es crucial que Composer cargue las clases del vendor
require __DIR__ . '/../vendor/autoload.php';

// Configuración de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inicializar Slim
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

// ============================================================================
// RUTA 1: VISTA DEL EDITOR (GET /editor)
// ============================================================================
$app->get('/editor', function (Request $request, Response $response) {
    
    // 1. Instanciar componentes H5P
    $framework = new H5PFramework();
    $path_h5p_folder = __DIR__ . '/h5p'; 
    
    // El constructor de H5PCore y H5PEditor ahora aceptan la clase Framework
    // ya que implementa todas las interfaces necesarias.
    $h5pCore = new H5PCore($framework, $path_h5p_folder, '/h5p', 'es', true);
    $h5pEditor = new H5PEditor($h5pCore, $framework, $framework); // Argumentos 2 y 3 son el storage y ajax

    // Instanciamos el Validador para obtener la semántica de derechos de autor.
    $h5pValidator = new H5PContentValidator($framework, $h5pCore);
    $copyrightSemantics = $h5pValidator->getCopyrightSemantics();

    // 2. Recopilar Assets (JS y CSS)
    $files = $h5pCore->getDependenciesFiles([], $framework->getCoreAssetsUrl());
    
    $jsFiles = array_merge($files['scripts'], H5PCore::$scripts, H5PEditor::$scripts);
    $cssFiles = array_merge($files['styles'], H5PCore::$styles, H5PEditor::$styles);

    // 3. Convertir nombres de archivo en URLs HTML
    $scriptsHtml = '';
    foreach($jsFiles as $script) {
        $url = '';
        if (strpos($script, 'http') === 0) { $url = $script; }
        elseif (in_array($script, H5PEditor::$scripts) || strpos($script, 'h5p-editor') !== false) {
            $url = $framework->getEditorAssetsUrl() . '/' . $script;
        } else {
            $url = $framework->getCoreAssetsUrl() . '/' . $script;
        }
        $scriptsHtml .= '<script src="' . $url . '"></script>' . "\n";
    }

    $stylesHtml = '';
    foreach($cssFiles as $style) {
        $url = '';
        if (strpos($style, 'http') === 0) { $url = $style; }
        elseif (in_array($style, H5PEditor::$styles) || strpos($style, 'h5p-editor') !== false) {
            $url = $framework->getEditorAssetsUrl() . '/' . $style;
        } else {
            $url = $framework->getCoreAssetsUrl() . '/' . $style;
        }
        $stylesHtml .= '<link rel="stylesheet" href="' . $url . '">' . "\n";
    }

    // 4. Configuración JavaScript (H5PIntegration)
    $h5pIntegration = [
        'core' => ['scripts' => [], 'styles' => []],
        'editor' => [
            'filesPath' => '/h5p/editor',
            'fileIcon' => ['path' => '/h5p/editor/images/binary-file.png', 'width' => 50, 'height' => 50],
            'ajaxPath' => '/editor_ajax?action=', 
            'libraryUrl' => '/h5p/editor/',
            'copyrightSemantics' => $copyrightSemantics, 
            'assets' => ['css' => $cssFiles, 'js' => $jsFiles],
            'apiVersion' => H5PCore::$coreApi,
            'language' => 'es'
        ]
    ];
    $settingsJson = json_encode($h5pIntegration);

    // 5. Renderizar HTML
    $html = <<<EOT
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>H5P Editor</title>
    <style>
        body { font-family: sans-serif; background: #f0f0f0; margin: 0; padding: 20px; }
        .h5p-container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #333; }
    </style>
    $stylesHtml
</head>
<body>
    <div class="h5p-container">
        <h1>Crear Nuevo Contenido H5P</h1>
        <div id="h5p-editor-region">Cargando editor...</div>
    </div>

    <script>
        window.H5PIntegration = $settingsJson;
    </script>
    $scriptsHtml
    <script>
        (function($) {
            $(document).ready(function() {
                new H5PEditor.Editor(
                    undefined, 
                    undefined, 
                    document.getElementById('h5p-editor-region'),
                    function(html) { console.log("Editor cargado correctamente"); }
                );
            });
        })(H5P.jQuery);
    </script>
</body>
</html>
EOT;

    $response->getBody()->write($html);
    return $response;
});

// ============================================================================
// RUTA 2: AJAX REAL DEL EDITOR (POST/GET /editor_ajax)
// ============================================================================
$app->any('/editor_ajax', function (Request $request, Response $response) {
    
    $framework = new H5PFramework();
    $path_h5p_folder = __DIR__ . '/h5p'; 
    $h5pCore = new H5PCore($framework, $path_h5p_folder, '/h5p', 'es', true);
    $h5pEditor = new H5PEditor($h5pCore, $framework, $framework);

    $queryParams = $request->getQueryParams();
    $action = isset($queryParams['action']) ? $queryParams['action'] : null;

    if (!$action) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'No action specified']));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    ob_start(); 
    
    switch ($action) {
        case 'libraries':
            $h5pEditor->ajax->action(H5PEditorEndpoints::LIBRARIES);
            break;
        case 'translations':
            $language = isset($queryParams['language']) ? $queryParams['language'] : 'es';
            $h5pEditor->ajax->action(H5PEditorEndpoints::TRANSLATIONS, $language);
            break;
        case 'files':
            $h5pEditor->ajax->action(H5PEditorEndpoints::FILES);
            break;
        default:
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Action not implemented: ' . $action]));
            return $response->withHeader('Content-Type', 'application/json');
    }

    $output = ob_get_clean(); 
    
    if (empty($output)) {
        $output = json_encode(['success' => false, 'message' => 'Empty response from H5P logic']);
    }

    $response->getBody()->write($output);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();