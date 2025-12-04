<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor H5P</title>

    <?php foreach ($cssAssets as $css): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
    <?php endforeach; ?>

    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }

        .editor-wrapper {
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
        }
    </style>
</head>

<body>

    <div class="editor-wrapper">
        <h1>Crear Contenido H5P</h1>
        <div id="h5p-editor" class="h5p-editor">
            <div class="h5p-create">
                <div class="h5p-editor-wrapper">
                    <div class="h5p-editor-content"></div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($jsAssets as $js): ?>
        <script src="<?php echo htmlspecialchars($js); ?>"></script>
    <?php endforeach; ?>

    <script>
        // 1. Inicializar configuración desde PHP
        var H5PIntegration = <?php echo json_encode($h5pIntegration, JSON_UNESCAPED_SLASHES); ?>;

        // 2. [CRÍTICO] Definir la función getAjaxUrl
        // El Hub usa esto para construir las URLs hacia tu backend (e.g., install-library, content-type-cache)
        H5PEditor.getAjaxUrl = function(action, parameters) {
            var url = H5PIntegration.editor.ajaxPath + action;

            if (parameters !== undefined) {
                for (var property in parameters) {
                    if (parameters.hasOwnProperty(property)) {
                        url += '&' + property + '=' + parameters[property];
                    }
                }
            }
            return url;
        };

        (function($) {
            $(document).ready(function() {
                var $editorContainer = $('#h5p-editor');
                var $editorContent = $editorContainer.find('.h5p-editor-content');

                // 3. Verificación: ¿Está el Hub activo en la configuración?
                // Verificamos en ambos niveles por seguridad
                var hubIsEnabled = (H5PIntegration.hubIsEnabled === true) ||
                    (H5PIntegration.editor && H5PIntegration.editor.hubIsEnabled === true);

                // 4. Verificación: ¿Existen librerías instaladas?
                var hasLibraries = H5PIntegration.editor.libraries && Object.keys(H5PIntegration.editor.libraries).length > 0;

                // 5. Lógica de bloqueo CORREGIDA
                // Solo mostramos error si NO hay librerías Y el Hub está DESACTIVADO.
                if (!hasLibraries && !hubIsEnabled) {
                    $editorContainer.html(
                        '<div style="padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px;">' +
                        '<strong>⚠️ Atención:</strong> No hay librerías H5P instaladas y el Hub está desactivado.<br>' +
                        'Por favor, habilita el Hub o sube librerías manualmente.' +
                        '</div>'
                    );
                    return;
                }

                // 6. Inicializar Selector de Librerías (Hub o Lista Local)
                if (H5PEditor.LibrarySelector) {
                    var librarySelector = new H5PEditor.LibrarySelector(
                        H5PIntegration.editor.libraries || {}, // Asegurar objeto aunque esté vacío
                        H5PIntegration.editor.defaultLibrary || null,
                        $editorContent
                    );

                    librarySelector.appendTo($editorContent);

                    // Exponer para depuración
                    window.h5pLibrarySelector = librarySelector;
                } else {
                    console.error('H5PEditor.LibrarySelector not found. Check if assets are loaded correctly.');
                }
            });
        })(H5P.jQuery);
    </script>

</body>

</html>