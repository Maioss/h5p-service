<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor H5P</title>

    <!-- CSS Assets -->
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

    <!-- JS Assets -->
    <?php foreach ($jsAssets as $js): ?>
        <script src="<?php echo htmlspecialchars($js); ?>"></script>
    <?php endforeach; ?>

    <script>
        // Initialize H5P Integration
        var H5PIntegration = <?php echo json_encode($h5pIntegration, JSON_UNESCAPED_SLASHES); ?>;

        (function($) {
            $(document).ready(function() {
                var $editorContainer = $('#h5p-editor');
                var $editorContent = $editorContainer.find('.h5p-editor-content');

                // Check for libraries
                if (!H5PIntegration.editor.libraries || Object.keys(H5PIntegration.editor.libraries).length === 0) {
                    $editorContainer.html(
                        '<div style="padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px;">' +
                        '<strong>⚠️ Atención:</strong> No hay librerías H5P instaladas. ' +
                        'Por favor, instala librerías usando el endpoint de carga o el Hub.' +
                        '</div>'
                    );
                    return;
                }

                // Initialize Library Selector
                if (H5PEditor.LibrarySelector) {
                    var librarySelector = new H5PEditor.LibrarySelector(
                        H5PIntegration.editor.libraries,
                        H5PIntegration.editor.defaultLibrary || null,
                        $editorContent
                    );

                    librarySelector.appendTo($editorContent);

                    // Expose for debugging/external use
                    window.h5pLibrarySelector = librarySelector;
                } else {
                    console.error('H5PEditor.LibrarySelector not found. Check if assets are loaded correctly.');
                }
            });
        })(H5P.jQuery);
    </script>

</body>

</html>