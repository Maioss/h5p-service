<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Librería H5P</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .upload-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h1 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .file-drop-area {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 100%;
            padding: 25px;
            border: 2px dashed rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            transition: 0.2s;
            box-sizing: border-box;
            background-color: #fafafa;
        }

        .file-drop-area.is-active {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: #007bff;
        }

        .fake-btn {
            flex-shrink: 0;
            background-color: rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            padding: 8px 15px;
            margin-right: 10px;
            font-size: 12px;
            text-transform: uppercase;
        }

        .file-msg {
            font-size: small;
            font-weight: 300;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #666;
        }

        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 100%;
            cursor: pointer;
            opacity: 0;
        }

        .btn-submit {
            margin-top: 1.5rem;
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background-color: #0056b3;
        }

        .btn-submit:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        #message {
            margin-top: 1rem;
            padding: 10px;
            border-radius: 6px;
            display: none;
            font-size: 0.9rem;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <div class="upload-card">
        <h1>Instalar Librería H5P</h1>

        <form id="uploadForm">
            <div class="file-drop-area">
                <span class="fake-btn">Elegir archivo</span>
                <span class="file-msg">o arrastra aquí tu .h5p</span>
                <input class="file-input" type="file" name="h5p_file" accept=".h5p" required>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">Subir e Instalar</button>
        </form>

        <div id="message"></div>
    </div>

    <script>
        const form = document.getElementById('uploadForm');
        const fileInput = document.querySelector('.file-input');
        const fileMsg = document.querySelector('.file-msg');
        const submitBtn = document.getElementById('submitBtn');
        const messageDiv = document.getElementById('message');

        // UI: Show selected filename
        fileInput.addEventListener('change', function() {
            if (fileInput.files && fileInput.files.length > 1) {
                fileMsg.textContent = fileInput.files.length + " archivos seleccionados";
            } else if (fileInput.files && fileInput.files.length === 1) {
                fileMsg.textContent = fileInput.files[0].name;
            } else {
                fileMsg.textContent = "o arrastra aquí tu .h5p";
            }
        });

        // AJAX Submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(form);

            // Reset UI
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Procesando...';
            messageDiv.style.display = 'none';
            messageDiv.className = '';

            try {
                debugger;
                const response = await fetch('<?php echo $baseUrl; ?>h5p-service/public/h5p/upload-library', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                messageDiv.style.display = 'block';

                if (result.success) {
                    messageDiv.textContent = '✅ ' + result.message;
                    messageDiv.classList.add('success');
                    form.reset();
                    fileMsg.textContent = "o arrastra aquí tu .h5p";
                } else {
                    throw new Error(result.message || 'Error desconocido al subir.');
                }

            } catch (error) {
                messageDiv.style.display = 'block';
                messageDiv.textContent = '❌ ' + error.message;
                messageDiv.classList.add('error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Subir e Instalar';
            }
        });
    </script>

</body>

</html>