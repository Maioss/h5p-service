<?php

namespace App\H5P\Framework;

use PDO;
use H5PFrameworkInterface;

class H5PFramework implements \H5PFrameworkInterface
{

    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    // ============================================
    // MÉTODOS CRÍTICOS PARA EL HUB
    // ============================================

    /**
     * Información de la plataforma
     */
    public function getPlatformInfo()
    {
        return [
            'name' => 'H5P Service POC',
            'version' => '1.0',
            'h5pVersion' => '1.24'
        ];
    }

    /**
     * Hacer peticiones HTTP (CRÍTICO para Hub)
     */
    public function fetchExternalData(
        $url,
        $data = null,
        $blocking = true,
        $stream = null,
        $alldata = false,
        $headers = [],
        $files = [],
        $method = 'POST'
    ) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Solo para desarrollo
            CURLOPT_SSL_VERIFYHOST => false  // Solo para desarrollo
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }

        return [
            'status' => $status,
            'data' => $response
        ];
    }

    /**
     * Obtener opción de configuración
     */
    public function getOption($name, $default = null)
    {
        $options = [
            'h5p_hub_is_enabled' => true,
            'h5p_send_usage_statistics' => false,
            'h5p_export' => true,
            'h5p_embed' => true,
            'h5p_copyright' => true,
            'h5p_icon' => true,
            'h5p_content_type_cache_updated_at' => time()
        ];

        return $options[$name] ?? $default;
    }

    /**
     * Guardar opción
     */
    public function setOption($name, $value)
    {
        // Implementar si necesitas persistencia
        return true;
    }

    /**
     * Obtener librerías instaladas
     */
    public function loadLibraries()
    {
        $stmt = $this->db->prepare("
            SELECT id as libraryId, 
                   name as machineName, 
                   title, 
                   major_version as majorVersion, 
                   minor_version as minorVersion, 
                   patch_version as patchVersion,
                   runnable, 
                   restricted
            FROM h5p_libraries
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener datos de una librería específica
     */
    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM h5p_libraries
            WHERE name = :name 
            AND major_version = :major 
            AND minor_version = :minor
            ORDER BY patch_version DESC
            LIMIT 1
        ");

        $stmt->execute([
            'name' => $machineName,
            'major' => $majorVersion,
            'minor' => $minorVersion
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ============================================
    // MÉTODOS DEL HUB CACHE
    // ============================================

    /**
     * Obtener caché del Hub
     */
    public function getContentTypeCache($machineName = null)
    {
        if ($machineName) {
            $stmt = $this->db->prepare("
                SELECT * FROM h5p_libraries_hub_cache
                WHERE machine_name = :name
                ORDER BY is_recommended DESC, popularity DESC
            ");
            $stmt->execute(['name' => $machineName]);
        } else {
            $stmt = $this->db->query("
                SELECT * FROM h5p_libraries_hub_cache
                ORDER BY is_recommended DESC, popularity DESC
            ");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reemplazar caché del Hub
     */
    public function replaceContentTypeCache($contentTypeCache)
    {
        // Limpiar caché anterior
        $this->db->exec("TRUNCATE TABLE h5p_libraries_hub_cache");

        // Insertar nuevo caché
        $stmt = $this->db->prepare("
            INSERT INTO h5p_libraries_hub_cache 
            (machine_name, major_version, minor_version, patch_version,
             title, summary, description, icon, is_recommended, 
             popularity, example, tutorial, keywords, categories, owner)
            VALUES 
            (:machine_name, :major_version, :minor_version, :patch_version,
             :title, :summary, :description, :icon, :is_recommended,
             :popularity, :example, :tutorial, :keywords, :categories, :owner)
        ");

        foreach ($contentTypeCache as $ct) {
            $stmt->execute([
                'machine_name' => $ct->id,
                'major_version' => $ct->version->major ?? 1,
                'minor_version' => $ct->version->minor ?? 0,
                'patch_version' => $ct->version->patch ?? 0,
                'title' => $ct->title ?? '',
                'summary' => $ct->summary ?? '',
                'description' => $ct->description ?? '',
                'icon' => $ct->icon ?? '',
                'is_recommended' => $ct->isRecommended ?? 0,
                'popularity' => $ct->popularity ?? 0,
                'example' => $ct->example ?? '',
                'tutorial' => $ct->tutorial ?? '',
                'keywords' => isset($ct->keywords) ? json_encode($ct->keywords) : null,
                'categories' => isset($ct->categories) ? json_encode($ct->categories) : null,
                'owner' => $ct->owner ?? ''
            ]);
        }
    }

    /**
     * Fecha de última actualización del caché
     */
    public function getContentTypeCacheMaxAge()
    {
        return $this->getOption('h5p_content_type_cache_updated_at', 0);
    }

    /**
     * Actualizar fecha del caché
     */
    public function setContentTypeCacheUpdatedAt($time)
    {
        $this->setOption('h5p_content_type_cache_updated_at', $time);
    }

    // ============================================
    // MÉTODOS DE TRADUCCIÓN Y MENSAJES
    // ============================================

    public function t($message, $replacements = [])
    {
        // Sistema simple de traducción
        foreach ($replacements as $key => $value) {
            $message = str_replace($key, $value, $message);
        }
        return $message;
    }

    public function setErrorMessage($message, $code = null)
    {
        error_log("H5P Error: $message");
    }

    public function setInfoMessage($message)
    {
        error_log("H5P Info: $message");
    }

    // ============================================
    // MÉTODOS DE FILESYSTEM (Simplificados)
    // ============================================

    public function getH5pPath()
    {
        return __DIR__ . '/../../h5p';
    }

    public function getUploadedH5pFolderPath()
    {
        return $this->getH5pPath() . '/temp';
    }

    public function getUploadedH5pPath()
    {
        return $this->getUploadedH5pFolderPath() . '/uploaded.h5p';
    }

    // ============================================
    // MÉTODOS NO IMPLEMENTADOS (Para POC)
    // ============================================

    public function mayUpdateLibraries()
    {
        return true;
    }
    public function saveLibraryData(&$libraryData, $new = true)
    {
        return 1;
    }
    public function insertContent($content, $contentMainId = null)
    {
        return 1;
    }
    public function updateContent($content, $contentMainId = null)
    {
        return 1;
    }
    public function resetContentUserData($contentId) {}
    public function deleteLibraryUsage($libraryId) {}
    public function saveLibraryDependencies($libraryId, $dependencies, $dependencyType) {}
    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = null) {}
    public function deleteContentData($contentId) {}
    public function deleteLibraryDependencies($libraryId) {}
    public function lockDependencyStorage() {}
    public function unlockDependencyStorage() {}
    public function getNumNotFiltered()
    {
        return 0;
    }
    public function getNumContent($libraryId, $skip = null)
    {
        return 0;
    }
    public function isContentSlugAvailable($slug)
    {
        return true;
    }
    public function getLibraryStats($type)
    {
        return [];
    }
    public function getNumAuthors()
    {
        return 1;
    }
    public function saveCachedAssets($key, $libraries) {}
    public function deleteCachedAssets($keys) {}
    public function getLibraryContentCount()
    {
        return [];
    }
    public function afterExportCreated($content, $filename) {}
    public function hasPermission($permission, $contentUserId = null)
    {
        return true;
    }
    public function libraryHasUpgrade($library)
    {
        return false;
    }
    public function libraryHasUpdated($library)
    {
        return false;
    }
    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion) {}
    public function clearFilteredParameters($library_ids) {}
    public function deleteLibrary($library) {}
    public function getAdminUrl() {}
    public function getContentHubMetadataCache($lang = 'en') {}
    public function getContentHubMetadataChecked($lang = 'en') {}
    public function getLibraryConfig($libraries = NULL) {}
    public function getLibraryFileUrl($libraryFolderName, $fileName) {}
    public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL) {}
    public function getLibraryUsage($libraryId, $skipContent = FALSE) {}
    public function getMessages($type) {}
    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist) {}

    public function isInDevMode()
    {
        return false;
    }
    public function isPatchedLibrary($library)
    {
        return false;
    }
    public function loadAddons() {}
    public function loadContent($id) {}
    public function loadContentDependencies($id, $type = NULL) {}
    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion) {}
    public function replaceContentHubMetadataCache($metadata, $lang) {}
    public function saveLibraryUsage($contentId, $librariesInUse) {}
    public function setContentHubMetadataChecked($time, $lang = 'en') {}
    public function setLibraryTutorialUrl($machineName, $tutorialUrl) {}
    public function updateContentFields($id, $fields) {}
}
