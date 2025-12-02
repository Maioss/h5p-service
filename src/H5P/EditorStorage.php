<?php

declare(strict_types=1);

namespace App\H5P;

use H5peditorStorage;
use H5peditorFile;
use PDO;

/**
 * Implementación de H5peditorStorage para el editor H5P
 */
class EditorStorage implements H5peditorStorage
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Carga el archivo de idioma (JSON) desde la base de datos
     */
    public function getLanguage($machineName, $majorVersion, $minorVersion, $language)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT language_json FROM h5p_libraries_languages
                 WHERE library_id = (
                     SELECT id FROM h5p_libraries
                     WHERE name = :name
                       AND major_version = :major
                       AND minor_version = :minor
                     LIMIT 1
                 ) AND language_code = :lang"
            );

            $stmt->execute([
                ':name'  => $machineName,
                ':major' => $majorVersion,
                ':minor' => $minorVersion,
                ':lang'  => $language,
            ]);

            $result = $stmt->fetchColumn();
            return $result ?: null;
        } catch (\PDOException $e) {
            error_log("Error loading language: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Carga la lista de códigos de idioma disponibles desde la base de datos
     */
    public function getAvailableLanguages($machineName, $majorVersion, $minorVersion)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT language_code FROM h5p_libraries_languages
                 WHERE library_id = (
                     SELECT id FROM h5p_libraries
                     WHERE name = :name
                       AND major_version = :major
                       AND minor_version = :minor
                     LIMIT 1
                 )"
            );

            $stmt->execute([
                ':name'  => $machineName,
                ':major' => $majorVersion,
                ':minor' => $minorVersion,
            ]);

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            error_log("Error loading available languages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Marca el archivo dado como un archivo permanente
     */
    public function keepFile($fileId)
    {
        // Implementación básica - en producción deberías tener una tabla de archivos temporales
        // Por ahora, no hacemos nada ya que no estamos rastreando archivos temporales
    }

    /**
     * Decide qué tipos de contenido debe tener el editor
     */
    public function getLibraries($libraries = null)
    {
        try {
            if ($libraries !== null) {
                // Cargar bibliotecas específicas
                $result = [];
                foreach ($libraries as $libraryData) {
                    $parts = explode(' ', $libraryData);
                    $machineName = $parts[0];
                    $version = explode('.', $parts[1]);

                    $stmt = $this->pdo->prepare(
                        "SELECT * FROM h5p_libraries
                         WHERE name = :name
                           AND major_version = :major
                           AND minor_version = :minor
                         LIMIT 1"
                    );

                    $stmt->execute([
                        ':name'  => $machineName,
                        ':major' => $version[0],
                        ':minor' => $version[1],
                    ]);

                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $result[] = $this->mapLibraryRow($row);
                    }
                }
                return $result;
            }

            // Cargar todas las bibliotecas ejecutables
            $stmt = $this->pdo->query(
                "SELECT * FROM h5p_libraries
                 WHERE runnable = 1
                 ORDER BY title ASC"
            );

            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $this->mapLibraryRow($row);
            }

            // Si no hay bibliotecas, devolver array vacío
            // El editor mostrará la interfaz del H5P Hub para instalar bibliotecas
            return $result;
        } catch (\PDOException $e) {
            error_log("Error loading libraries: " . $e->getMessage());
            // Devolver array vacío en caso de error para que el editor pueda mostrar el Hub
            return [];
        }
    }

    /**
     * Altera estilos y scripts
     */
    public function alterLibraryFiles(&$files, $libraries)
    {
        // Implementación básica - puedes personalizar esto según tus necesidades
    }

    /**
     * Guarda un archivo o lo mueve temporalmente
     */
    public static function saveFileTemporarily($data, $move_file)
    {
        $tempDir = sys_get_temp_dir() . '/h5p-editor';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $tempFile = $tempDir . '/' . uniqid('h5p-') . '.h5p';

        if ($move_file) {
            if (rename($data, $tempFile)) {
                return (object)['dir' => $tempDir, 'fileName' => basename($tempFile)];
            }
        } else {
            if (file_put_contents($tempFile, $data) !== false) {
                return (object)['dir' => $tempDir, 'fileName' => basename($tempFile)];
            }
        }

        return false;
    }

    /**
     * Marca un archivo para limpieza posterior
     */
    public static function markFileForCleanup($file, $content_id)
    {
        // Implementación básica - en producción deberías rastrear archivos para limpieza
    }

    /**
     * Limpia archivos guardados temporalmente
     */
    public static function removeTemporarilySavedFiles($filePath)
    {
        if (is_dir($filePath)) {
            $files = array_diff(scandir($filePath), ['.', '..']);
            foreach ($files as $file) {
                $path = $filePath . '/' . $file;
                if (is_dir($path)) {
                    self::removeTemporarilySavedFiles($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($filePath);
        } elseif (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Mapea una fila de biblioteca a un array
     */
    private function mapLibraryRow(array $row): array
    {
        return [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'title'          => $row['title'],
            'majorVersion'   => (int)$row['major_version'],
            'minorVersion'   => (int)$row['minor_version'],
            'patchVersion'   => (int)$row['patch_version'],
            'runnable'       => (int)$row['runnable'],
            'restricted'     => !empty($row['restricted']),
            'metadataSettings' => !empty($row['metadata_settings'])
                ? json_decode($row['metadata_settings'], true)
                : null,
        ];
    }
}
