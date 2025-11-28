<?php

declare(strict_types=1);

namespace App\H5P;

use H5PFrameworkInterface;
use PDO;

/**
 * Implementación “standalone” de H5PFrameworkInterface
 * usando MySQL y las tablas h5p_* que ya creaste.
 */
class H5PFramework implements H5PFrameworkInterface
{
    /** @var Database */
    protected $db;

    /** @var array */
    protected $config;

    /** @var array */
    protected $messages = [
        'info'  => [],
        'error' => [],
    ];

    public function __construct(Database $db, array $config)
    {
        $this->db     = $db;
        $this->config = $config;
    }

    protected function pdo(): PDO
    {
        return $this->db->getConnection();
    }

    /* =========================================================
     *  Plataforma / mensajes / traducción
     * ======================================================= */

    public function getPlatformInfo()
    {
        return [
            'name'       => 'Slim H5P Service',
            'version'    => '1.0.0',
            'h5pVersion' => '1.x', // versión del “plugin”/integración
        ];
    }

    public function fetchExternalData($url, $data = NULL, $blocking = TRUE, $stream = NULL, $fullData = FALSE, $headers = array(), $files = array(), $method = 'POST')
    {
        // Implementación mínima sin soporte para archivos ($files)
        $opts = [
            'http' => [
                'method'        => $method,
                'timeout'       => $blocking ? 30 : 1,
                'ignore_errors' => true,
            ],
        ];

        $httpHeaders = [];
        foreach ($headers as $k => $v) {
            $httpHeaders[] = $k . ': ' . $v;
        }
        if (!empty($httpHeaders)) {
            $opts['http']['header'] = implode("\r\n", $httpHeaders);
        }

        if (!empty($data)) {
            if (strtoupper($method) === 'GET') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
            } else {
                $opts['http']['content'] = http_build_query($data);
                $opts['http']['header'] = ($opts['http']['header'] ?? '') . "\r\nContent-Type: application/x-www-form-urlencoded";
            }
        }

        $ctx    = stream_context_create($opts);
        $result = @file_get_contents($url, false, $ctx);
        if ($result === false) {
            return NULL;
        }

        if ($stream) {
            @file_put_contents($stream, $result);
        }

        if (!$fullData) {
            return $result;
        }

        $responseHeaders = [];
        if (isset($http_response_header)) {
            foreach ($http_response_header as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
            }
        }

        return [
            'data'    => $result,
            'headers' => $responseHeaders,
        ];
    }

    public function setLibraryTutorialUrl($machineName, $tutorialUrl)
    {
        $sql  = 'UPDATE h5p_libraries SET tutorial_url = :url WHERE name = :name';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':url'  => $tutorialUrl,
            ':name' => $machineName,
        ]);
    }

    public function setErrorMessage($message, $code = NULL)
    {
        if ($code !== NULL) {
            $message = '[' . $code . '] ' . $message;
        }
        $this->messages['error'][] = $message;
    }

    public function setInfoMessage($message)
    {
        $this->messages['info'][] = $message;
    }

    public function getMessages($type)
    {
        return isset($this->messages[$type]) ? $this->messages[$type] : [];
    }

    public function t($message, $replacements = array())
    {
        if (!empty($replacements)) {
            $search  = array_keys($replacements);
            $replace = array_values($replacements);
            $message = str_replace($search, $replace, $message);
        }

        return $message; // sin i18n por ahora
    }

    /* =========================================================
     *  Ficheros y paths
     * ======================================================= */

    public function getLibraryFileUrl($libraryFolderName, $fileName)
    {
        // URL base a /h5p/libraries (configurable)
        $base = $this->config['urls']['libraries']
            ?? ($this->config['urls']['storage'] . '/libraries');

        $base = rtrim($base, '/');

        return $base . '/' . $libraryFolderName . '/' . ltrim($fileName, '/');
    }

    public function getUploadedH5pFolderPath()
    {
        $tmpBase = $this->config['paths']['tmp']
            ?? ($this->config['paths']['storage'] . '/tmp');

        return rtrim($tmpBase, '/') . '/h5p-upload';
    }

    public function getUploadedH5pPath()
    {
        return $this->getUploadedH5pFolderPath() . '.h5p';
    }

    /* =========================================================
     *  Librerías (load / stats). Escritura la veremos luego.
     * ======================================================= */

    public function loadAddons()
    {
        $sql  = 'SELECT * FROM h5p_libraries WHERE add_to IS NOT NULL AND add_to <> ""';
        $stmt = $this->pdo()->query($sql);

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function getLibraryConfig($libraries = NULL)
    {
        // Sin configuración extra de momento
        return [];
    }

    public function loadLibraries()
    {
        $sql  = 'SELECT * FROM h5p_libraries ORDER BY name, major_version, minor_version, patch_version';
        $stmt = $this->pdo()->query($sql);
        if (!$stmt) {
            return [];
        }

        $rows      = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $libraries = [];

        foreach ($rows as $row) {
            $name = $row['name'];
            if (!isset($libraries[$name])) {
                $libraries[$name] = [];
            }

            $libraries[$name][] = [
                'libraryId'      => (int) $row['id'],
                'title'          => $row['title'],
                'machineName'    => $row['name'],
                'majorVersion'   => (int) $row['major_version'],
                'minorVersion'   => (int) $row['minor_version'],
                'patchVersion'   => (int) $row['patch_version'],
                'runnable'       => (int) $row['runnable'],
                'restricted'     => (int) $row['restricted'],
                'fullscreen'     => (int) $row['fullscreen'],
                'embedTypes'     => $row['embed_types'],
                'preloadedJs'    => $row['preloaded_js'],
                'preloadedCss'   => $row['preloaded_css'],
                'dropLibraryCss' => $row['drop_library_css'],
            ];
        }

        return $libraries;
    }

    public function getAdminUrl()
    {
        return '/admin/h5p';
    }

    public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL)
    {
        if ($majorVersion === NULL || $minorVersion === NULL) {
            $sql  = 'SELECT id FROM h5p_libraries
                     WHERE name = :name
                     ORDER BY major_version DESC, minor_version DESC, patch_version DESC
                     LIMIT 1';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([':name' => $machineName]);
        } else {
            $sql  = 'SELECT id FROM h5p_libraries
                     WHERE name = :name
                       AND major_version = :major
                       AND minor_version = :minor
                     ORDER BY patch_version DESC
                     LIMIT 1';
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([
                ':name'  => $machineName,
                ':major' => $majorVersion,
                ':minor' => $minorVersion,
            ]);
        }

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : FALSE;
    }

    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist)
    {
        // Dejamos los defaults de H5P
        return $isLibrary ? $defaultLibraryWhitelist : $defaultContentWhitelist;
    }

    public function isPatchedLibrary($library)
    {
        // Permitir tanto stdClass como array
        if (is_object($library)) {
            $library = (array) $library;
        }

        $sql  = 'SELECT 1
               FROM h5p_libraries
              WHERE name = :name
                AND major_version = :major
                AND minor_version = :minor
                AND patch_version > :patch
              LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':name'  => $library['machineName'],
            ':major' => $library['majorVersion'],
            ':minor' => $library['minorVersion'],
            ':patch' => $library['patchVersion'],
        ]);

        return (bool) $stmt->fetchColumn();
    }


    public function isInDevMode()
    {
        return !empty($this->config['development']);
    }

    public function mayUpdateLibraries()
    {
        // Para el microservicio asumimos que siempre es “admin”
        return TRUE;
    }

    /* ---------- helpers internos para librerías ---------- */

    protected function pathsToCsv($items)
    {
        if (empty($items)) {
            return '';
        }

        $paths = [];
        foreach ($items as $item) {
            if (!empty($item['path'])) {
                $paths[] = $item['path'];
            }
        }

        return implode(',', $paths);
    }

    protected function dropCssToCsv($items)
    {
        if (empty($items)) {
            return '';
        }

        $names = [];
        foreach ($items as $item) {
            if (!empty($item['machineName'])) {
                $names[] = $item['machineName'];
            }
        }

        return implode(',', $names);
    }

    public function saveLibraryData(&$libraryData, $new = TRUE)
    {
        // Implementación completa la dejamos para cuando montemos upload de librerías.
        // Por ahora algo funcional para cuando la usemos desde el core.
        $pdo = $this->pdo();

        $preloadedJs    = isset($libraryData['preloadedJs']) ? $this->pathsToCsv($libraryData['preloadedJs']) : '';
        $preloadedCss   = isset($libraryData['preloadedCss']) ? $this->pathsToCsv($libraryData['preloadedCss']) : '';
        $dropLibraryCss = isset($libraryData['dropLibraryCss']) ? $this->dropCssToCsv($libraryData['dropLibraryCss']) : '';
        $embedTypes     = isset($libraryData['embedTypes'])
            ? (is_array($libraryData['embedTypes']) ? implode(',', $libraryData['embedTypes']) : $libraryData['embedTypes'])
            : '';
        $fullscreen       = !empty($libraryData['fullscreen']) ? 1 : 0;
        $runnable         = !empty($libraryData['runnable']) ? 1 : 0;
        $restricted       = !empty($libraryData['restricted']) ? 1 : 0;
        $hasIcon          = !empty($libraryData['hasIcon']) ? 1 : 0;
        $metadataSettings = isset($libraryData['metadataSettings']) ? json_encode($libraryData['metadataSettings']) : NULL;
        $addTo            = isset($libraryData['addTo']) ? json_encode($libraryData['addTo']) : NULL;
        $semantics        = isset($libraryData['semantics']) ? $libraryData['semantics'] : '';

        if ($new) {
            $sql = 'INSERT INTO h5p_libraries
                    (created_at, updated_at, name, title, major_version, minor_version, patch_version,
                     runnable, restricted, fullscreen, embed_types,
                     preloaded_js, preloaded_css, drop_library_css,
                     semantics, tutorial_url, has_icon, metadata_settings, add_to)
                    VALUES
                    (NOW(), NOW(), :name, :title, :major, :minor, :patch,
                     :runnable, :restricted, :fullscreen, :embed_types,
                     :preloaded_js, :preloaded_css, :drop_css,
                     :semantics, :tutorial_url, :has_icon, :metadata_settings, :add_to)';
        } else {
            $sql = 'UPDATE h5p_libraries
                       SET updated_at = NOW(),
                           title = :title,
                           runnable = :runnable,
                           restricted = :restricted,
                           fullscreen = :fullscreen,
                           embed_types = :embed_types,
                           preloaded_js = :preloaded_js,
                           preloaded_css = :preloaded_css,
                           drop_library_css = :drop_css,
                           semantics = :semantics,
                           tutorial_url = :tutorial_url,
                           has_icon = :has_icon,
                           metadata_settings = :metadata_settings,
                           add_to = :add_to
                     WHERE id = :id';
        }

        $params = [
            ':name'             => $libraryData['machineName'],
            ':title'            => $libraryData['title'],
            ':major'            => $libraryData['majorVersion'],
            ':minor'            => $libraryData['minorVersion'],
            ':patch'            => $libraryData['patchVersion'],
            ':runnable'         => $runnable,
            ':restricted'       => $restricted,
            ':fullscreen'       => $fullscreen,
            ':embed_types'      => $embedTypes,
            ':preloaded_js'     => $preloadedJs,
            ':preloaded_css'    => $preloadedCss,
            ':drop_css'         => $dropLibraryCss,
            ':semantics'        => $semantics,
            ':tutorial_url'     => $libraryData['tutorialUrl'] ?? '',
            ':has_icon'         => $hasIcon,
            ':metadata_settings' => $metadataSettings,
            ':add_to'           => $addTo,
        ];

        if (!$new) {
            $params[':id'] = $libraryData['libraryId'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($new) {
            $libraryData['libraryId'] = (int) $pdo->lastInsertId();
        }

        return $libraryData['libraryId'];
    }

    public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type)
    {
        $pdo = $this->pdo();

        $pdo->prepare('DELETE FROM h5p_libraries_libraries WHERE library_id = :id AND dependency_type = :t')
            ->execute([':id' => $libraryId, ':t' => $dependency_type]);

        if (empty($dependencies)) {
            return;
        }

        $sql  = 'INSERT INTO h5p_libraries_libraries (library_id, required_library_id, dependency_type)
                 VALUES (:library_id, :required_library_id, :type)';
        $stmt = $pdo->prepare($sql);

        foreach ($dependencies as $dep) {
            $requiredId = $this->getLibraryId($dep['machineName'], $dep['majorVersion'], $dep['minorVersion']);
            if (!$requiredId) {
                continue;
            }
            $stmt->execute([
                ':library_id'        => $libraryId,
                ':required_library_id' => $requiredId,
                ':type'              => $dependency_type,
            ]);
        }
    }

    public function deleteLibraryDependencies($libraryId)
    {
        $sql  = 'DELETE FROM h5p_libraries_libraries WHERE library_id = :id OR required_library_id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $libraryId]);
    }

    public function lockDependencyStorage()
    {
        // Para nuestro caso basta una transacción
        $this->pdo()->beginTransaction();
    }

    public function unlockDependencyStorage()
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function deleteLibrary($library)
    {
        // Aceptar stdClass o array
        if (is_object($library)) {
            $libraryId = $library->libraryId ?? $library->id ?? null;
        } else {
            $libraryId = $library['libraryId'] ?? $library['id'] ?? null;
        }

        if (!$libraryId) {
            $this->setErrorMessage('No se pudo determinar el ID de la librería a eliminar.');
            return;
        }

        $this->deleteLibraryDependencies($libraryId);

        $pdo = $this->pdo();

        $pdo->prepare('DELETE FROM h5p_libraries_languages WHERE library_id = :id')
            ->execute([':id' => $libraryId]);

        $pdo->prepare('DELETE FROM h5p_libraries_cachedassets WHERE library_id = :id')
            ->execute([':id' => $libraryId]);

        $pdo->prepare('DELETE FROM h5p_libraries WHERE id = :id')
            ->execute([':id' => $libraryId]);

        // El borrado físico lo hace H5PDefaultStorage
    }

    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        $sql  = 'SELECT * FROM h5p_libraries
                 WHERE name = :name
                   AND major_version = :major
                   AND minor_version = :minor
                 ORDER BY patch_version DESC
                 LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':name'  => $machineName,
            ':major' => $majorVersion,
            ':minor' => $minorVersion,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return FALSE;
        }

        $libraryId = (int) $row['id'];

        $library = [
            'libraryId'      => $libraryId,
            'title'          => $row['title'],
            'machineName'    => $row['name'],
            'majorVersion'   => (int) $row['major_version'],
            'minorVersion'   => (int) $row['minor_version'],
            'patchVersion'   => (int) $row['patch_version'],
            'runnable'       => (int) $row['runnable'],
            'restricted'     => (int) $row['restricted'],
            'fullscreen'     => (int) $row['fullscreen'],
            'embedTypes'     => $row['embed_types'],
            'preloadedJs'    => $row['preloaded_js'],
            'preloadedCss'   => $row['preloaded_css'],
            'dropLibraryCss' => $row['drop_library_css'],
            'semantics'      => $row['semantics'],
            'tutorialUrl'    => $row['tutorial_url'],
        ];

        // Dependencias
        $sql  = 'SELECT l2.id, l2.name, l2.major_version, l2.minor_version, ll.dependency_type
                 FROM h5p_libraries_libraries ll
                 JOIN h5p_libraries l2 ON l2.id = ll.required_library_id
                 WHERE ll.library_id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $libraryId]);
        $deps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $preloaded = [];
        $dynamic   = [];
        $editor    = [];

        foreach ($deps as $dep) {
            $entry = [
                'machineName'  => $dep['name'],
                'majorVersion' => (int) $dep['major_version'],
                'minorVersion' => (int) $dep['minor_version'],
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

        if ($preloaded) {
            $library['preloadedDependencies'] = $preloaded;
        }
        if ($dynamic) {
            $library['dynamicDependencies'] = $dynamic;
        }
        if ($editor) {
            $library['editorDependencies'] = $editor;
        }

        return $library;
    }

    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion)
    {
        $lib = $this->loadLibrary($machineName, $majorVersion, $minorVersion);
        return $lib ? $lib['semantics'] : '';
    }

    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion)
    {
        // No hacemos alteraciones custom por ahora
    }

    /* =========================================================
     *  Contenidos (content)
     * ======================================================= */

    public function insertContent($content, $contentMainId = NULL)
    {
        $pdo = $this->pdo();

        $sql = 'INSERT INTO h5p_contents
                (created_at, updated_at, user_id, title, library_id, parameters,
                 filtered, slug, embed_type, disable, content_type, authors, source,
                 year_from, year_to, license, license_version, license_extras,
                 author_comments, changes, default_language)
                VALUES
                (NOW(), NOW(), :user_id, :title, :library_id, :parameters,
                 :filtered, :slug, :embed_type, :disable, :content_type, :authors, :source,
                 :year_from, :year_to, :license, :license_version, :license_extras,
                 :author_comments, :changes, :default_language)';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'         => (int) ($content['user_id'] ?? 0),
            ':title'           => $content['title'],
            ':library_id'      => $content['library']['libraryId'],
            ':parameters'      => $content['params'],
            ':filtered'        => $content['filtered'] ?? '',
            ':slug'            => $content['slug'],
            ':embed_type'      => $content['embedType'] ?? 'div',
            ':disable'         => $content['disable'] ?? 0,
            ':content_type'    => $content['content_type'] ?? NULL,
            ':authors'         => $content['authors'] ?? NULL,
            ':source'          => $content['source'] ?? NULL,
            ':year_from'       => $content['year_from'] ?? NULL,
            ':year_to'         => $content['year_to'] ?? NULL,
            ':license'         => $content['license'] ?? NULL,
            ':license_version' => $content['licenseVersion'] ?? NULL,
            ':license_extras'  => $content['licenseExtras'] ?? NULL,
            ':author_comments' => $content['authorComments'] ?? NULL,
            ':changes'         => $content['changes'] ?? NULL,
            ':default_language' => $content['defaultLanguage'] ?? NULL,
        ]);

        $content['id'] = (int) $pdo->lastInsertId();
    }

    public function updateContent($content, $contentMainId = NULL)
    {
        $pdo = $this->pdo();

        $sql  = 'UPDATE h5p_contents
                    SET updated_at = NOW(),
                        title = :title,
                        library_id = :library_id,
                        parameters = :parameters,
                        filtered = :filtered,
                        slug = :slug,
                        embed_type = :embed_type,
                        disable = :disable
                  WHERE id = :id';
        $stmt = $pdo->prepare($sql);
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
    }

    public function resetContentUserData($contentId)
    {
        $sql  = 'DELETE FROM h5p_contents_user_data WHERE content_id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $contentId]);
    }

    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL)
    {
        $pdo = $this->pdo();

        $sql  = 'INSERT INTO h5p_contents_libraries (content_id, library_id, dependency_type, weight, drop_css)
                 SELECT :new_id, library_id, dependency_type, weight, drop_css
                 FROM h5p_contents_libraries WHERE content_id = :old_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':new_id' => $contentId,
            ':old_id' => $copyFromId,
        ]);
    }

    public function deleteContentData($contentId)
    {
        $pdo = $this->pdo();

        $pdo->prepare('DELETE FROM h5p_contents_libraries WHERE content_id = :id')
            ->execute([':id' => $contentId]);
        $pdo->prepare('DELETE FROM h5p_contents_user_data WHERE content_id = :id')
            ->execute([':id' => $contentId]);
        $pdo->prepare('DELETE FROM h5p_contents_tags WHERE content_id = :id')
            ->execute([':id' => $contentId]);
        $pdo->prepare('DELETE FROM h5p_contents WHERE id = :id')
            ->execute([':id' => $contentId]);
    }

    public function deleteLibraryUsage($contentId)
    {
        $sql  = 'DELETE FROM h5p_contents_libraries WHERE content_id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $contentId]);
    }

    public function saveLibraryUsage($contentId, $librariesInUse)
    {
        $pdo = $this->pdo();

        $pdo->prepare('DELETE FROM h5p_contents_libraries WHERE content_id = :id')
            ->execute([':id' => $contentId]);

        if (empty($librariesInUse)) {
            return;
        }

        $sql  = 'INSERT INTO h5p_contents_libraries
                    (content_id, library_id, dependency_type, weight, drop_css)
                 VALUES (:content_id, :library_id, :type, :weight, :drop_css)';
        $stmt = $pdo->prepare($sql);

        $weight = 0;
        foreach ($librariesInUse as $entry) {
            $lib = $entry['library'];
            $stmt->execute([
                ':content_id' => $contentId,
                ':library_id' => $lib['libraryId'],
                ':type'       => $entry['type'],
                ':weight'     => $weight++,
                ':drop_css'   => !empty($lib['dropLibraryCss']) ? 1 : 0,
            ]);
        }
    }

    public function getLibraryUsage($libraryId, $skipContent = FALSE)
    {
        $pdo = $this->pdo();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM h5p_libraries_libraries WHERE required_library_id = :id');
        $stmt->execute([':id' => $libraryId]);
        $libs = (int) $stmt->fetchColumn();

        $content = 0;
        if (!$skipContent) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM h5p_contents_libraries WHERE library_id = :id');
            $stmt->execute([':id' => $libraryId]);
            $content = (int) $stmt->fetchColumn();
        }

        return [
            'content'   => $content,
            'libraries' => $libs,
        ];
    }

    public function loadContent($id)
    {
        $sql  = 'SELECT c.*, l.name AS libraryName, l.major_version AS libraryMajorVersion,
                         l.minor_version AS libraryMinorVersion, l.embed_types AS libraryEmbedTypes,
                         l.fullscreen AS libraryFullscreen
                  FROM h5p_contents c
                  JOIN h5p_libraries l ON l.id = c.library_id
                 WHERE c.id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return FALSE;
        }

        return [
            'contentId'           => (int) $row['id'],
            'title'               => $row['title'],
            'params'              => $row['parameters'],
            'embedType'           => $row['embed_type'],
            'language'            => $row['default_language'],
            'slug'                => $row['slug'],
            'filtered'            => $row['filtered'],
            'disable'             => (int) $row['disable'],
            'libraryId'           => (int) $row['library_id'],
            'libraryName'         => $row['libraryName'],
            'libraryMajorVersion' => (int) $row['libraryMajorVersion'],
            'libraryMinorVersion' => (int) $row['libraryMinorVersion'],
            'libraryEmbedTypes'   => $row['libraryEmbedTypes'],
            'libraryFullscreen'   => (int) $row['libraryFullscreen'],
        ];
    }

    public function loadContentDependencies($id, $type = NULL)
    {
        $sql = 'SELECT l.id AS libraryId, l.name AS machineName,
                       l.major_version AS majorVersion, l.minor_version AS minorVersion,
                       l.patch_version AS patchVersion,
                       l.preloaded_js AS preloadedJs, l.preloaded_css AS preloadedCss,
                       l.drop_library_css AS dropCss,
                       cl.dependency_type
                  FROM h5p_contents_libraries cl
                  JOIN h5p_libraries l ON l.id = cl.library_id
                 WHERE cl.content_id = :id';
        $params = [':id' => $id];
        if ($type !== NULL) {
            $sql             .= ' AND cl.dependency_type = :type';
            $params[':type'] = $type;
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                'libraryId'    => (int) $row['libraryId'],
                'machineName'  => $row['machineName'],
                'majorVersion' => (int) $row['majorVersion'],
                'minorVersion' => (int) $row['minorVersion'],
                'patchVersion' => (int) $row['patchVersion'],
                'preloadedJs'  => $row['preloadedJs'],
                'preloadedCss' => $row['preloadedCss'],
                'dropCss'      => $row['dropCss'],
            ];
        }

        return $result;
    }

    /* =========================================================
     *  Opciones (settings)
     * ======================================================= */

    public function getOption($name, $default = NULL)
    {
        $sql  = 'SELECT value FROM h5p_options WHERE name = :name';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':name' => $name]);
        $value = $stmt->fetchColumn();

        if ($value === FALSE) {
            return $default;
        }

        $decoded = json_decode($value, TRUE);
        return ($decoded === NULL && json_last_error() !== JSON_ERROR_NONE)
            ? $value
            : $decoded;
    }

    public function setOption($name, $value)
    {
        $pdo     = $this->pdo();
        $encoded = is_scalar($value) ? (string) $value : json_encode($value);

        $sql  = 'INSERT INTO h5p_options (name, value)
                 VALUES (:name, :value)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'  => $name,
            ':value' => $encoded,
        ]);
    }

    public function updateContentFields($id, $fields)
    {
        if (empty($fields)) {
            return;
        }

        $sets   = [];
        $params = [':id' => $id];

        foreach ($fields as $field => $value) {
            $sets[]                 = $field . ' = :' . $field;
            $params[':' . $field]   = $value;
        }

        $sql  = 'UPDATE h5p_contents SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function clearFilteredParameters($library_ids)
    {
        if (empty($library_ids)) {
            return;
        }

        $in  = implode(',', array_fill(0, count($library_ids), '?'));
        $sql = 'UPDATE h5p_contents c
                  JOIN h5p_contents_libraries cl ON cl.content_id = c.id
                   SET c.filtered = ""
                 WHERE cl.library_id IN (' . $in . ')';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($library_ids));
    }

    public function getNumNotFiltered()
    {
        $sql  = 'SELECT COUNT(*) FROM h5p_contents WHERE filtered = ""';
        $stmt = $this->pdo()->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function getNumContent($libraryId, $skip = NULL)
    {
        $pdo = $this->pdo();

        if (!empty($skip)) {
            $placeholders = implode(',', array_fill(0, count($skip), '?'));
            $sql          = 'SELECT COUNT(*) FROM h5p_contents
                             WHERE library_id = ?
                               AND id NOT IN (' . $placeholders . ')';
            $params = array_merge([$libraryId], $skip);
            $stmt   = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql  = 'SELECT COUNT(*) FROM h5p_contents WHERE library_id = ?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$libraryId]);
        }

        return (int) $stmt->fetchColumn();
    }

    public function isContentSlugAvailable($slug)
    {
        $sql  = 'SELECT id FROM h5p_contents WHERE slug = :slug';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':slug' => $slug]);

        return $stmt->fetchColumn() === FALSE;
    }

    public function getLibraryStats($type)
    {
        $sql  = 'SELECT library_name, library_version, num
                   FROM h5p_counters
                  WHERE type = :type';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([':type' => $type]);

        $stats = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key         = $row['library_name'] . ' ' . $row['library_version'];
            $stats[$key] = (int) $row['num'];
        }

        return $stats;
    }

    public function getNumAuthors()
    {
        $sql  = 'SELECT COUNT(DISTINCT user_id) FROM h5p_contents';
        $stmt = $this->pdo()->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function saveCachedAssets($key, $libraries)
    {
        $pdo = $this->pdo();

        $sql  = 'INSERT IGNORE INTO h5p_libraries_cachedassets (library_id, hash)
                 VALUES (:library_id, :hash)';
        $stmt = $pdo->prepare($sql);

        foreach ($libraries as $lib) {
            $stmt->execute([
                ':library_id' => $lib['libraryId'],
                ':hash'       => $key,
            ]);
        }
    }

    public function deleteCachedAssets($library_id)
    {
        $pdo = $this->pdo();

        $sql  = 'SELECT DISTINCT hash FROM h5p_libraries_cachedassets WHERE library_id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $library_id]);
        $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare('DELETE FROM h5p_libraries_cachedassets WHERE library_id = :id')
            ->execute([':id' => $library_id]);

        return $hashes;
    }

    public function getLibraryContentCount()
    {
        $sql  = 'SELECT COUNT(*) FROM h5p_contents';
        $stmt = $this->pdo()->query($sql);

        return (int) $stmt->fetchColumn();
    }

    public function afterExportCreated($content, $filename)
    {
        // No-op por ahora
    }

    public function hasPermission($permission, $id = NULL)
    {
        // El microservicio se asume detrás de auth, devolvemos true
        return TRUE;
    }

    public function replaceContentTypeCache($contentTypeCache)
    {
        // Guardamos el Hub en h5p_libraries_hub_cache
        if (empty($contentTypeCache->libraries)) {
            return;
        }

        $pdo = $this->pdo();
        $pdo->exec('TRUNCATE TABLE h5p_libraries_hub_cache');

        $sql  = 'INSERT INTO h5p_libraries_hub_cache
                    (machine_name, major_version, minor_version, patch_version,
                     h5p_major_version, h5p_minor_version, title, summary, description,
                     icon, created_at, updated_at, is_recommended, popularity, screenshots,
                     license, example, tutorial, keywords, categories, owner)
                 VALUES
                    (:machine_name, :major_version, :minor_version, :patch_version,
                     :h5p_major_version, :h5p_minor_version, :title, :summary, :description,
                     :icon, :created_at, :updated_at, :is_recommended, :popularity, :screenshots,
                     :license, :example, :tutorial, :keywords, :categories, :owner)';
        $stmt = $pdo->prepare($sql);

        foreach ($contentTypeCache->libraries as $lib) {
            $stmt->execute([
                ':machine_name'      => $lib->machineName,
                ':major_version'     => $lib->majorVersion,
                ':minor_version'     => $lib->minorVersion,
                ':patch_version'     => $lib->patchVersion,
                ':h5p_major_version' => $lib->h5pMajorVersion ?? NULL,
                ':h5p_minor_version' => $lib->h5pMinorVersion ?? NULL,
                ':title'             => $lib->title,
                ':summary'           => $lib->summary,
                ':description'       => $lib->description,
                ':icon'              => $lib->icon,
                ':created_at'        => $lib->createdAt,
                ':updated_at'        => $lib->updatedAt,
                ':is_recommended'    => $lib->isRecommended,
                ':popularity'        => $lib->popularity,
                ':screenshots'       => json_encode($lib->screenshots),
                ':license'           => json_encode($lib->license),
                ':example'           => $lib->example,
                ':tutorial'          => $lib->tutorial,
                ':keywords'          => json_encode($lib->keywords),
                ':categories'        => json_encode($lib->categories),
                ':owner'             => $lib->owner,
            ]);
        }
    }

    public function libraryHasUpgrade($library)
    {
        // Igual: soportar stdClass o array
        if (is_object($library)) {
            $library = (array) $library;
        }

        $sql  = 'SELECT 1 FROM h5p_libraries
             WHERE name = :name
               AND (
                    major_version > :major
                    OR (major_version = :major AND minor_version > :minor)
                    OR (major_version = :major AND minor_version = :minor AND patch_version > :patch)
               )
             LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            ':name'  => $library['machineName'],
            ':major' => $library['majorVersion'],
            ':minor' => $library['minorVersion'],
            ':patch' => $library['patchVersion'],
        ]);

        return (bool) $stmt->fetchColumn();
    }


    public function replaceContentHubMetadataCache($metadata, $lang)
    {
        $this->setOption('content_hub_metadata_' . $lang, $metadata);
        return TRUE;
    }

    public function getContentHubMetadataCache($lang = 'en')
    {
        return $this->getOption('content_hub_metadata_' . $lang, NULL);
    }

    public function getContentHubMetadataChecked($lang = 'en')
    {
        return $this->getOption('content_hub_metadata_checked_' . $lang, NULL);
    }

    public function setContentHubMetadataChecked($time, $lang = 'en')
    {
        $this->setOption('content_hub_metadata_checked_' . $lang, $time);
        return TRUE;
    }
}
