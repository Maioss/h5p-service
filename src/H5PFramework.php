<?php

namespace App;

use PDO;
use H5PFrameworkInterface;
use H5PEditorAjaxInterface;
use H5peditorStorage; // <-- Necesaria para el constructor del Editor

class H5PFramework implements H5PFrameworkInterface, H5PEditorAjaxInterface, H5peditorStorage {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // ==========================================
    // MÉTODOS ESTÁTICOS DE H5peditorStorage (FIX FINAL)
    // ==========================================

    // Estos métodos deben ser static para coincidir con tu interfaz
    public static function saveFileTemporarily($data, $move_file) {
        // Devuelve un objeto dummy para que el editor no falle si subes algo.
        $file = new \stdClass();
        $file->id = 0;
        $file->path = '';
        $file->dir = '';
        $file->fileName = '';
        return $file;
    }

    public static function markFileForCleanup($file, $content_id) {
        // Deja vacío, solo cumple con la firma.
        return;
    }

    public static function removeTemporarilySavedFiles($filePath) {
        // Deja vacío, solo cumple con la firma.
        return;
    }

    // ==========================================
    // OTROS MÉTODOS DE H5peditorStorage (Instancia)
    // ==========================================

    public function getLanguage($machineName, $majorVersion, $minorVersion, $language) {
        $stmt = $this->db->prepare(
            "SELECT translation FROM h5p_libraries_languages
             WHERE library_name = ? AND major_version = ? AND minor_version = ? AND language_code = ?"
        );
        $stmt->execute([$machineName, $majorVersion, $minorVersion, $language]);
        return $stmt->fetch(PDO::FETCH_COLUMN);
    }

    public function addTmpFile($file) { return true; }
    public function keepFile($fileId) { return true; }
    public function removeFile($fileId) { return true; }

    public function getLibraries($libraries = NULL) {
        if ($libraries !== NULL) {
            return $this->getLibraryConfig($libraries);
        }
        $query = "SELECT name, title, major_version, minor_version, patch_version,
                  runnable, restricted, tutorial_url, has_icon 
                  FROM h5p_libraries 
                  WHERE runnable = 1
                  ORDER BY title ASC";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function alterLibraryFiles(&$files, $libraries) {
        $this->getLibraryConfig($libraries);
    }

    public function getAvailableLanguages($machineName, $majorVersion, $minorVersion) {
        return array();
    }

    // ==========================================
    // H5PFrameworkInterface & H5PEditorAjaxInterface
    // (Métodos no modificados)
    // ==========================================

    public function getCoreAssetsUrl() { return '/h5p/core'; }
    public function getEditorAssetsUrl() { return '/h5p/editor'; }
    public function getLibraryFileUrl($libraryFolderName, $fileName) { return "/h5p/libraries/$libraryFolderName/$fileName"; }

    public function getAuthorsRecentlyUsedLibraries() { return array(); }
    public function validateEditorToken($token) { return true; }
    public function getLatestLibraryVersions() { return array(); }
    public function getContentTypeCache($machineName = NULL) { return NULL; }
    public function getTranslations($libraries, $language_code) { return array(); }

    public function getPlatformInfo() {
        return array('name' => 'H5P Microservice', 'version' => '1.0.0', 'h5pVersion' => '1.27');
    }

    public function getUploadedH5pFolderPath() { return __DIR__ . '/../h5p/temp'; }
    public function getUploadedH5pPath() { return __DIR__ . '/../h5p/exports'; }

    public function loadLibraries() {
        $stmt = $this->db->query("SELECT * FROM h5p_libraries ORDER BY name, major_version DESC, minor_version DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLibraryId($machineName, $majorVersion = NULL, $minorVersion = NULL) {
        if ($majorVersion === NULL) {
            $stmt = $this->db->prepare("SELECT id FROM h5p_libraries WHERE name = ? ORDER BY major_version DESC, minor_version DESC LIMIT 1");
            $stmt->execute([$machineName]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM h5p_libraries WHERE name = ? AND major_version = ? AND minor_version = ?");
            $stmt->execute([$machineName, $majorVersion, $minorVersion]);
        }
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result ? $result : false;
    }

    public function loadLibrary($machineName, $majorVersion, $minorVersion) {
        $stmt = $this->db->prepare("SELECT * FROM h5p_libraries WHERE name = ? AND major_version = ? AND minor_version = ?");
        $stmt->execute([$machineName, $majorVersion, $minorVersion]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion) {
        $stmt = $this->db->prepare("SELECT semantics FROM h5p_libraries WHERE name = ? AND major_version = ? AND minor_version = ?");
        $stmt->execute([$machineName, $majorVersion, $minorVersion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['semantics'] : null;
    }

    public function loadContent($id) {
        $stmt = $this->db->prepare("SELECT * FROM h5p_contents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function loadContentDependencies($id, $type = NULL) {
        $query = "SELECT l.name, l.major_version, l.minor_version, l.patch_version, 
                         l.preloaded_css, l.preloaded_js, cl.drop_css, cl.dependency_type
                  FROM h5p_contents_libraries cl
                  JOIN h5p_libraries l ON cl.library_id = l.id
                  WHERE cl.content_id = ?";
        if ($type !== NULL) {
            $query .= " AND cl.dependency_type = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id, $type]);
        } else {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchExternalData($url, $data = NULL, $blocking = TRUE, $stream = NULL, $fullData = FALSE, $headers = array(), $files = array(), $method = 'POST') {
        try {
            if ($method === 'GET' && $data === NULL) {
                $content = @file_get_contents($url);
                if ($fullData) return ['status' => $content ? 200 : 404, 'data' => $content];
                return $content;
            }
        } catch (\Exception $e) { return NULL; }
        return NULL;
    }

    public function saveLibraryData(&$libraryData, $new = TRUE) {
        if ($new) $libraryData['libraryId'] = rand(1000, 9999);
    }

    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist) { return $defaultContentWhitelist . ($isLibrary ? $defaultLibraryWhitelist : ''); }
    public function isPatchedLibrary($library) { return false; }
    public function isInDevMode() { return false; }
    public function mayUpdateLibraries() { return true; }
    public function insertContent($content, $contentMainId = NULL) { return 0; }
    public function updateContent($content, $contentMainId = NULL) { return 0; }
    public function resetContentUserData($contentId) { }
    public function saveLibraryDependencies($libraryId, $dependencies, $dependency_type) { }
    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = NULL) { }
    public function deleteContentData($contentId) { }
    public function deleteLibraryUsage($contentId) { }
    public function saveLibraryUsage($contentId, $librariesInUse) { }
    public function getLibraryUsage($libraryId, $skipContent = FALSE) { return array('content' => 0, 'libraries' => 0); }
    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion) { }
    public function deleteLibraryDependencies($libraryId) { }
    public function lockDependencyStorage() { }
    public function unlockDependencyStorage() { }
    public function deleteLibrary($library) { }
    public function getOption($name, $default = NULL) { return $default; }
    public function setOption($name, $value) { }
    public function updateContentFields($id, $fields) { }
    public function clearFilteredParameters($library_ids) { }
    public function getNumNotFiltered() { return 0; }
    public function getNumContent($libraryId, $skip = NULL) { return 0; }
    public function isContentSlugAvailable($slug) { return true; }
    public function getLibraryStats($type) { return array(); }
    public function getNumAuthors() { return 1; }
    public function saveCachedAssets($key, $libraries) { }
    public function deleteCachedAssets($library_id) { }
    public function getLibraryContentCount() { return array(); }
    public function afterExportCreated($content, $filename) { }
    public function hasPermission($permission, $id = NULL) { return true; }
    public function replaceContentTypeCache($contentTypeCache) { }
    public function libraryHasUpgrade($library) { return false; }
    public function replaceContentHubMetadataCache($metadata, $lang) { }
    public function getContentHubMetadataCache($lang = 'en') { return null; }
    public function getContentHubMetadataChecked($lang = 'en') { return null; }
    public function setContentHubMetadataChecked($time, $lang = 'en') { }
    public function setLibraryTutorialUrl($machineName, $tutorialUrl) { }
    public function setErrorMessage($message, $code = NULL) { error_log("[H5P Error] " . $message); }
    public function setInfoMessage($message) { error_log("[H5P Info] " . $message); }
    public function getMessages($type) { return null; }
    public function t($message, $replacements = array()) { return $message; }
    public function loadAddons() { return array(); }
    public function getLibraryConfig($libraries = NULL) { return isset($libraries) ? $libraries : []; }
    public function getAdminUrl() { return ''; }
}