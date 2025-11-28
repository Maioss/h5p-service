<?php

declare(strict_types=1);

namespace App\H5P;

use H5PFrameworkInterface;
use PDO;
use PDOException;
use InvalidArgumentException;

/**
 * Implementación optimizada de H5PFrameworkInterface
 * Basada en MySQL con gestión robusta de errores y transacciones
 */
class H5PFramework implements H5PFrameworkInterface
{
    private PDO $pdo;
    private array $config;
    private array $messages = ['info' => [], 'error' => []];

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;

        // Configurar PDO para excepciones
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /* =========================================================
     *  INFORMACIÓN DE PLATAFORMA Y MENSAJERÍA
     * ======================================================= */

    public function getPlatformInfo(): array
    {
        return [
            'name'       => $this->config['platform_name'] ?? 'H5P Microservice',
            'version'    => $this->config['platform_version'] ?? '1.0.0',
            'h5pVersion' => $this->config['h5p_version'] ?? '1.27',
        ];
    }

    public function setErrorMessage($message, $code = NULL): void
    {
        $fullMessage = $code !== NULL ? "[{$code}] {$message}" : $message;
        $this->messages['error'][] = $fullMessage;
        error_log("H5P Error: {$fullMessage}");
    }

    public function setInfoMessage($message): void
    {
        $this->messages['info'][] = $message;
    }

    public function getMessages($type): array
    {
        return $this->messages[$type] ?? [];
    }

    public function t($message, $replacements = array()): string
    {
        foreach ($replacements as $key => $value) {
            $message = str_replace($key, $value, $message);
        }
        return $message;
    }

    /* =========================================================
     *  GESTIÓN DE LIBRERÍAS
     * ======================================================= */

    public function loadLibraries(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT * FROM h5p_libraries 
                 ORDER BY name, major_version DESC, minor_version DESC, patch_version DESC"
            );

            $libraries = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = $row['name'];
                if (!isset($libraries[$name])) {
                    $libraries[$name] = [];
                }
                $libraries[$name][] = $this->mapLibraryRow($row);
            }

            return $libraries;
        } catch (PDOException $e) {
            $this->setErrorMessage("Error loading libraries: " . $e->getMessage());
            return [];
        }
    }

    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM h5p_libraries
                 WHERE name = :name 
                   AND major_version = :major 
                   AND minor_version = :minor
                 ORDER BY patch_version DESC
                 LIMIT 1"
            );

            $stmt->execute([
                ':name'  => $machineName,
                ':major' => $majorVersion,
                ':minor' => $minorVersion,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return FALSE;
            }

            $library = $this->mapLibraryRow($row);
            $library = array_merge($library, $this->loadLibraryDependencies((int)$row['id']));

            return $library;
        } catch (PDOException $e) {
            $this->setErrorMessage("Error loading library: " . $e->getMessage());
            return FALSE;
        }
    }

    public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL)
    {
        try {
            if ($majorVersion === NULL || $minorVersion === NULL) {
                $stmt = $this->pdo->prepare(
                    "SELECT id FROM h5p_libraries
                     WHERE name = :name
                     ORDER BY major_version DESC, minor_version DESC, patch_version DESC
                     LIMIT 1"
                );
                $stmt->execute([':name' => $machineName]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT id FROM h5p_libraries
                     WHERE name = :name 
                       AND major_version = :major 
                       AND minor_version = :minor
                     ORDER BY patch_version DESC
                     LIMIT 1"
                );
                $stmt->execute([
                    ':name'  => $machineName,
                    ':major' => $majorVersion,
                    ':minor' => $minorVersion,
                ]);
            }

            $id = $stmt->fetchColumn();
            return $id ? (int)$id : FALSE;
        } catch (PDOException $e) {
            $this->setErrorMessage("Error getting library ID: " . $e->getMessage());
            return FALSE;
        }
    }

    public function saveLibraryData(&$libraryData, $new = TRUE)
    {
        try {
            $this->pdo->beginTransaction();

            $params = [
                ':title'            => $libraryData['title'],
                ':major'            => $libraryData['majorVersion'],
                ':minor'            => $libraryData['minorVersion'],
                ':patch'            => $libraryData['patchVersion'],
                ':runnable'         => !empty($libraryData['runnable']) ? 1 : 0,
                ':restricted'       => !empty($libraryData['restricted']) ? 1 : 0,
                ':fullscreen'       => !empty($libraryData['fullscreen']) ? 1 : 0,
                ':embed_types'      => $this->serializeEmbedTypes($libraryData['embedTypes'] ?? []),
                ':preloaded_js'     => $this->serializePaths($libraryData['preloadedJs'] ?? []),
                ':preloaded_css'    => $this->serializePaths($libraryData['preloadedCss'] ?? []),
                ':drop_css'         => $this->serializeDropCss($libraryData['dropLibraryCss'] ?? []),
                ':semantics'        => $libraryData['semantics'] ?? '',
                ':tutorial_url'     => $libraryData['tutorialUrl'] ?? '',
                ':has_icon'         => !empty($libraryData['hasIcon']) ? 1 : 0,
                ':metadata_settings' => isset($libraryData['metadataSettings'])
                    ? json_encode($libraryData['metadataSettings']) : NULL,
                ':add_to'           => isset($libraryData['addTo'])
                    ? json_encode($libraryData['addTo']) : NULL,
            ];

            if ($new) {
                $params[':name'] = $libraryData['machineName'];

                $sql = "INSERT INTO h5p_libraries
                        (created_at, updated_at, name, title, major_version, minor_version, 
                         patch_version, runnable, restricted, fullscreen, embed_types,
                         preloaded_js, preloaded_css, drop_library_css, semantics, 
                         tutorial_url, has_icon, metadata_settings, add_to)
                        VALUES (NOW(), NOW(), :name, :title, :major, :minor, :patch, 
                                :runnable, :restricted, :fullscreen, :embed_types,
                                :preloaded_js, :preloaded_css, :drop_css, :semantics, 
                                :tutorial_url, :has_icon, :metadata_settings, :add_to)";

                $this->pdo->prepare($sql)->execute($params);
                $libraryData['libraryId'] = (int)$this->pdo->lastInsertId();
            } else {
                $params[':id'] = $libraryData['libraryId'];

                $sql = "UPDATE h5p_libraries
                        SET updated_at = NOW(), title = :title, runnable = :runnable,
                            restricted = :restricted, fullscreen = :fullscreen, 
                            embed_types = :embed_types, preloaded_js = :preloaded_js,
                            preloaded_css = :preloaded_css, drop_library_css = :drop_css,
                            semantics = :semantics, tutorial_url = :tutorial_url,
                            has_icon = :has_icon, metadata_settings = :metadata_settings,
                            add_to = :add_to
                        WHERE id = :id";

                $this->pdo->prepare($sql)->execute($params);
            }

            $this->pdo->commit();
            return $libraryData['libraryId'];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->setErrorMessage("Error saving library: " . $e->getMessage());
            throw $e;
        }
    }

    public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type): void
    {
        try {
            // Eliminar dependencias existentes del tipo especificado
            $stmt = $this->pdo->prepare(
                "DELETE FROM h5p_libraries_libraries 
                 WHERE library_id = :id AND dependency_type = :type"
            );
            $stmt->execute([':id' => $libraryId, ':type' => $dependency_type]);

            if (empty($dependencies)) {
                return;
            }

            // Insertar nuevas dependencias
            $stmt = $this->pdo->prepare(
                "INSERT INTO h5p_libraries_libraries 
                 (library_id, required_library_id, dependency_type)
                 VALUES (:library_id, :required_id, :type)"
            );

            foreach ($dependencies as $dep) {
                $requiredId = $this->getLibraryId(
                    $dep['machineName'],
                    $dep['majorVersion'],
                    $dep['minorVersion']
                );

                if ($requiredId) {
                    $stmt->execute([
                        ':library_id'  => $libraryId,
                        ':required_id' => $requiredId,
                        ':type'        => $dependency_type,
                    ]);
                }
            }
        } catch (PDOException $e) {
            $this->setErrorMessage("Error saving library dependencies: " . $e->getMessage());
            throw $e;
        }
    }

    /* =========================================================
     *  GESTIÓN DE CONTENIDOS
     * ======================================================= */

    public function insertContent($content, $contentMainId = NULL): void
    {
        try {
            $sql = "INSERT INTO h5p_contents
                    (created_at, updated_at, user_id, title, library_id, parameters,
                     filtered, slug, embed_type, disable, content_type, authors, 
                     source, license, default_language)
                    VALUES (NOW(), NOW(), :user_id, :title, :library_id, :parameters,
                            :filtered, :slug, :embed_type, :disable, :content_type, 
                            :authors, :source, :license, :language)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id'      => (int)($content['user_id'] ?? 0),
                ':title'        => $content['title'],
                ':library_id'   => $content['library']['libraryId'],
                ':parameters'   => $content['params'],
                ':filtered'     => $content['filtered'] ?? '',
                ':slug'         => $content['slug'],
                ':embed_type'   => $content['embedType'] ?? 'div',
                ':disable'      => $content['disable'] ?? 0,
                ':content_type' => $content['content_type'] ?? NULL,
                ':authors'      => $content['authors'] ?? NULL,
                ':source'       => $content['source'] ?? NULL,
                ':license'      => $content['license'] ?? NULL,
                ':language'     => $content['defaultLanguage'] ?? NULL,
            ]);

            $content['id'] = (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->setErrorMessage("Error inserting content: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateContent($content, $contentMainId = NULL): void
    {
        try {
            $sql = "UPDATE h5p_contents
                    SET updated_at = NOW(), title = :title, library_id = :library_id,
                        parameters = :parameters, filtered = :filtered, slug = :slug,
                        embed_type = :embed_type, disable = :disable
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title'      => $content['title'],
                ':library_id' => $content['library']['libraryId'],
                ':parameters' => $content['params'],
                ':filtered'   => $content['filtered'] ?? '',
                ':slug'       => $content['slug'],
                ':embed_type' => $content['embedType'] ?? 'div',
                ':disable'    => $content['disable'] ?? 0,
                ':id'         => $content['id'],
            ]);
        } catch (PDOException $e) {
            $this->setErrorMessage("Error updating content: " . $e->getMessage());
            throw $e;
        }
    }

    public function loadContent($id)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT c.*, l.name AS libraryName, l.major_version AS libraryMajorVersion,
                        l.minor_version AS libraryMinorVersion, l.embed_types AS libraryEmbedTypes,
                        l.fullscreen AS libraryFullscreen
                 FROM h5p_contents c
                 JOIN h5p_libraries l ON l.id = c.library_id
                 WHERE c.id = :id"
            );

            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return FALSE;
            }

            return [
                'contentId'           => (int)$row['id'],
                'title'               => $row['title'],
                'params'              => $row['parameters'],
                'embedType'           => $row['embed_type'],
                'language'            => $row['default_language'],
                'slug'                => $row['slug'],
                'filtered'            => $row['filtered'],
                'disable'             => (int)$row['disable'],
                'libraryId'           => (int)$row['library_id'],
                'libraryName'         => $row['libraryName'],
                'libraryMajorVersion' => (int)$row['libraryMajorVersion'],
                'libraryMinorVersion' => (int)$row['libraryMinorVersion'],
                'libraryEmbedTypes'   => $row['libraryEmbedTypes'],
                'libraryFullscreen'   => (int)$row['libraryFullscreen'],
            ];
        } catch (PDOException $e) {
            $this->setErrorMessage("Error loading content: " . $e->getMessage());
            return FALSE;
        }
    }

    /* =========================================================
     *  UTILIDADES Y CONFIGURACIÓN
     * ======================================================= */

    public function fetchExternalData(
        $url,
        $data = NULL,
        $blocking = TRUE,
        $stream = NULL,
        $fullData = FALSE,
        $headers = array(),
        $files = array(),
        $method = 'POST'
    ) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $method === 'GET' && $data ? $url . '?' . http_build_query($data) : $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $blocking ? 30 : 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if (!empty($headers)) {
            $curlHeaders = [];
            foreach ($headers as $key => $value) {
                $curlHeaders[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            return NULL;
        }

        if ($stream) {
            file_put_contents($stream, $result);
        }

        return $fullData ? ['status' => $httpCode, 'data' => $result] : $result;
    }

    public function getOption($name, $default = NULL)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT value FROM h5p_options WHERE name = :name");
            $stmt->execute([':name' => $name]);
            $value = $stmt->fetchColumn();

            if ($value === FALSE) {
                return $default;
            }

            $decoded = json_decode($value, TRUE);
            return ($decoded === NULL && json_last_error() !== JSON_ERROR_NONE) ? $value : $decoded;
        } catch (PDOException $e) {
            return $default;
        }
    }

    public function setOption($name, $value): void
    {
        try {
            $encoded = is_scalar($value) ? (string)$value : json_encode($value);

            $stmt = $this->pdo->prepare(
                "INSERT INTO h5p_options (name, value) VALUES (:name, :value)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)"
            );

            $stmt->execute([':name' => $name, ':value' => $encoded]);
        } catch (PDOException $e) {
            $this->setErrorMessage("Error setting option: " . $e->getMessage());
        }
    }

    /* =========================================================
     *  MÉTODOS HELPER PRIVADOS
     * ======================================================= */

    private function mapLibraryRow(array $row): array
    {
        return [
            'libraryId'      => (int)$row['id'],
            'title'          => $row['title'],
            'machineName'    => $row['name'],
            'majorVersion'   => (int)$row['major_version'],
            'minorVersion'   => (int)$row['minor_version'],
            'patchVersion'   => (int)$row['patch_version'],
            'runnable'       => (int)$row['runnable'],
            'restricted'     => (int)$row['restricted'],
            'fullscreen'     => (int)$row['fullscreen'],
            'embedTypes'     => $row['embed_types'],
            'preloadedJs'    => $row['preloaded_js'],
            'preloadedCss'   => $row['preloaded_css'],
            'dropLibraryCss' => $row['drop_library_css'],
            'semantics'      => $row['semantics'] ?? '',
        ];
    }

    private function loadLibraryDependencies(int $libraryId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.name, l.major_version, l.minor_version, ll.dependency_type
             FROM h5p_libraries_libraries ll
             JOIN h5p_libraries l ON l.id = ll.required_library_id
             WHERE ll.library_id = :id"
        );

        $stmt->execute([':id' => $libraryId]);

        $preloaded = $dynamic = $editor = [];

        while ($dep = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entry = [
                'machineName'  => $dep['name'],
                'majorVersion' => (int)$dep['major_version'],
                'minorVersion' => (int)$dep['minor_version'],
            ];

            switch ($dep['dependency_type']) {
                case 'preloaded':
                    $preloaded[] = $entry;
                    break;
                case 'dynamic':
                    $dynamic[] = $entry;
                    break;
                case 'editor':
                    $editor[] = $entry;
                    break;
            }
        }

        $result = [];
        if ($preloaded) $result['preloadedDependencies'] = $preloaded;
        if ($dynamic)   $result['dynamicDependencies'] = $dynamic;
        if ($editor)    $result['editorDependencies'] = $editor;

        return $result;
    }

    private function serializePaths(array $items): string
    {
        return implode(',', array_column($items, 'path'));
    }

    private function serializeDropCss(array $items): string
    {
        return implode(',', array_column($items, 'machineName'));
    }

    private function serializeEmbedTypes($embedTypes): string
    {
        return is_array($embedTypes) ? implode(',', $embedTypes) : (string)$embedTypes;
    }

    /* =========================================================
     *  MÉTODOS RESTANTES (Implementación básica)
     * ======================================================= */

    public function getLibraryFileUrl($libraryFolderName, $fileName): string
    {
        $base = rtrim($this->config['urls']['libraries'] ?? '/h5p/libraries', '/');
        return "{$base}/{$libraryFolderName}/" . ltrim($fileName, '/');
    }

    public function getUploadedH5pFolderPath(): string
    {
        return rtrim($this->config['paths']['tmp'] ?? sys_get_temp_dir(), '/') . '/h5p-upload';
    }

    public function getUploadedH5pPath(): string
    {
        return $this->getUploadedH5pFolderPath() . '.h5p';
    }

    public function loadAddons(): array
    {
        return [];
    }
    public function getLibraryConfig($libraries = NULL): array
    {
        return [];
    }
    public function getAdminUrl(): string
    {
        return '/admin/h5p';
    }
    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist): string
    {
        return $isLibrary ? $defaultLibraryWhitelist : $defaultContentWhitelist;
    }
    public function isPatchedLibrary($library): bool
    {
        return false;
    }
    public function isInDevMode(): bool
    {
        return !empty($this->config['development']);
    }
    public function mayUpdateLibraries(): bool
    {
        return true;
    }
    public function setLibraryTutorialUrl($machineName, $tutorialUrl): void {}
    public function resetContentUserData($contentId): void {}
    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL): void {}
    public function deleteContentData($contentId): void {}
    public function deleteLibraryUsage($contentId): void {}
    public function saveLibraryUsage($contentId, $librariesInUse): void {}
    public function getLibraryUsage($libraryId, $skipContent = FALSE): array
    {
        return ['content' => 0, 'libraries' => 0];
    }
    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion): string
    {
        return '';
    }
    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion): void {}
    public function deleteLibraryDependencies($libraryId): void {}
    public function lockDependencyStorage(): void
    {
        $this->pdo->beginTransaction();
    }
    public function unlockDependencyStorage(): void
    {
        if ($this->pdo->inTransaction()) $this->pdo->commit();
    }
    public function deleteLibrary($library): void {}
    public function loadContentDependencies($id, $type = NULL): array
    {
        return [];
    }
    public function updateContentFields($id, $fields): void {}
    public function clearFilteredParameters($library_ids): void {}
    public function getNumNotFiltered(): int
    {
        return 0;
    }
    public function getNumContent($libraryId, $skip = NULL): int
    {
        return 0;
    }
    public function isContentSlugAvailable($slug): bool
    {
        return true;
    }
    public function getLibraryStats($type): array
    {
        return [];
    }
    public function getNumAuthors(): int
    {
        return 1;
    }
    public function saveCachedAssets($key, $libraries): void {}
    public function deleteCachedAssets($library_id): array
    {
        return [];
    }
    public function getLibraryContentCount(): int
    {
        return 0;
    }
    public function afterExportCreated($content, $filename): void {}
    public function hasPermission($permission, $id = NULL): bool
    {
        return true;
    }
    public function replaceContentTypeCache($contentTypeCache): void {}
    public function libraryHasUpgrade($library): bool
    {
        return false;
    }
    public function replaceContentHubMetadataCache($metadata, $lang): void {}
    public function getContentHubMetadataCache($lang = 'en')
    {
        return null;
    }
    public function getContentHubMetadataChecked($lang = 'en')
    {
        return null;
    }
    public function setContentHubMetadataChecked($time, $lang = 'en'): void {}
}
