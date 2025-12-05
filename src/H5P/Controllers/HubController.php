<?php

namespace App\H5P\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\H5P\Framework\H5PFramework;
use H5PCore;

/**
 * Hub Controller - Implementación según especificación oficial H5P
 * 
 * Basado en:
 * - https://github.com/Lumieducation/H5P-Nodejs-library/wiki/Communication-with-the-H5P-Hub
 * - https://github.com/h5p/h5p-wordpress-plugin
 * - https://github.com/h5p/h5p-editor-php-library
 */
class HubController
{
    private $framework;
    private $core;

    public function __construct(H5PFramework $framework)
    {
        $this->framework = $framework;

        // Inicializar H5P Core
        $this->core = new H5PCore(
            $this->framework,
            $this->framework->getH5pPath(),
            '/h5p',
            'en',
            true
        );
    }

    /**
     * Endpoint principal: obtener content types del Hub
     * GET /api/content-types
     * 
     * Según especificación oficial, esto debe retornar:
     * - Content types del Hub (normalizados)
     * - Información de librerías locales
     * - Metadata adicional (outdated, user, apiVersion, etc.)
     */
    public function getContentTypes(Request $request, Response $response)
    {
        try {
            // PASO 1: Obtener content types del Hub (con caché)
            $hubContentTypes = $this->getHubContentTypes();

            // PASO 2: Obtener librerías instaladas localmente
            $localLibraries = $this->getLocalLibraries();

            // PASO 3: Combinar y normalizar (según especificación oficial)
            $mergedContentTypes = $this->mergeLocalLibsIntoCachedLibs(
                $hubContentTypes,
                $localLibraries
            );

            // PASO 4: Crear respuesta según formato oficial
            $result = [
                'success' => true,
                'contentTypes' => $mergedContentTypes,
                'outdated' => $this->isCacheOutdated(),
                'user' => 'local',  // o 'external' según tu configuración
                'recentlyUsed' => [],  // TODO: implementar según usuario
                'apiVersion' => [
                    'major' => 1,
                    'minor' => 24
                ],
                'details' => null  // mensajes informativos si es necesario
            ];

            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));

            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * Obtener content types del Hub H5P
     * Usa caché en BD, actualiza si está desactualizado
     * 
     * @return array Content types desde H5P.org
     */
    private function getHubContentTypes()
    {
        $contentTypes = $this->framework->getContentTypeCache();

        // Si no hay caché O está desactualizado, actualizar desde H5P.org
        if (empty($contentTypes) || $this->isCacheOutdated()) {
            $updated = $this->updateHubCache();

            if ($updated) {
                $contentTypes = $this->framework->getContentTypeCache();
            } else {
                // Si falla la actualización pero hay caché antiguo, usarlo
                if (empty($contentTypes)) {
                    throw new \Exception('No se pudo obtener content types desde H5P.org');
                }
            }
        }

        return $contentTypes;
    }

    /**
     * Obtener librerías instaladas localmente
     * Formato: { id, machineName, title, majorVersion, minorVersion, patchVersion, hasIcon, restricted }
     * 
     * @return array Librerías locales
     */
    private function getLocalLibraries()
    {
        try {
            $allLibraries = $this->framework->loadLibraries();
            $runnableLibraries = [];

            foreach ($allLibraries as $lib) {
                // Solo incluir librerías "runnable" (que se pueden ejecutar como contenido principal)
                if (isset($lib['runnable']) && $lib['runnable']) {
                    $runnableLibraries[] = [
                        'id' => $lib['id'] ?? $lib['libraryId'],
                        'machineName' => $lib['machineName'] ?? $lib['name'],
                        'title' => $lib['title'] ?? $lib['machineName'],
                        'majorVersion' => (int)($lib['majorVersion'] ?? $lib['major_version'] ?? 1),
                        'minorVersion' => (int)($lib['minorVersion'] ?? $lib['minor_version'] ?? 0),
                        'patchVersion' => (int)($lib['patchVersion'] ?? $lib['patch_version'] ?? 0),
                        'hasIcon' => file_exists($this->framework->getH5pPath() . '/libraries/' .
                            ($lib['machineName'] ?? $lib['name']) . '-' .
                            ($lib['majorVersion'] ?? $lib['major_version']) . '.' .
                            ($lib['minorVersion'] ?? $lib['minor_version']) . '/icon.svg'),
                        'restricted' => false  // TODO: implementar lógica de restricción
                    ];
                }
            }

            return $runnableLibraries;
        } catch (\Exception $e) {
            // Si falla, retornar array vacío (no es crítico)
            return [];
        }
    }

    /**
     * Combinar librerías del Hub con librerías locales
     * 
     * Según especificación oficial de H5P:
     * https://github.com/Lumieducation/H5P-Nodejs-library/wiki/Communication-with-the-H5P-Hub
     * 
     * Esta es la función CRÍTICA que normaliza todo.
     * 
     * @param array $hubLibs Content types desde H5P Hub
     * @param array $localLibs Librerías instaladas localmente
     * @return array Content types normalizados y combinados
     */
    private function mergeLocalLibsIntoCachedLibs($hubLibs, $localLibs)
    {
        $merged = [];

        // PASO 1: Normalizar content types del Hub
        foreach ($hubLibs as $hubLib) {
            if (is_object($hubLib)) {
                $hubLib = (array) $hubLib;
            }

            // Estructura según especificación oficial
            $normalized = [
                'id' => $hubLib['id'] ?? $hubLib['machineName'] ?? uniqid(),
                'machineName' => $hubLib['machineName'] ?? $hubLib['id'],
                'majorVersion' => (int)($hubLib['majorVersion'] ?? $hubLib['version']['major'] ?? 1),
                'minorVersion' => (int)($hubLib['minorVersion'] ?? $hubLib['version']['minor'] ?? 0),
                'patchVersion' => (int)($hubLib['patchVersion'] ?? $hubLib['version']['patch'] ?? 0),
                'h5pMajorVersion' => (int)($hubLib['h5pMajorVersion'] ?? $hubLib['coreApiVersionNeeded']['major'] ?? 1),
                'h5pMinorVersion' => (int)($hubLib['h5pMinorVersion'] ?? $hubLib['coreApiVersionNeeded']['minor'] ?? 24),
                'title' => $hubLib['title'] ?? 'Sin título',
                'summary' => $hubLib['summary'] ?? '',
                'description' => $hubLib['description'] ?? '',
                'icon' => $hubLib['icon'] ?? '',
                'createdAt' => $hubLib['createdAt'] ?? $hubLib['created_at'] ?? time(),
                'updatedAt' => $hubLib['updatedAt'] ?? $hubLib['updated_at'] ?? time(),
                'isRecommended' => (bool)($hubLib['isRecommended'] ?? false),
                'popularity' => (int)($hubLib['popularity'] ?? 0),
                'screenshots' => $this->ensureArray($hubLib['screenshots'] ?? []),
                'license' => $this->ensureArray($hubLib['license'] ?? []),
                'owner' => $hubLib['owner'] ?? '',

                // Flags de estado (se actualizan después con info local)
                'installed' => false,
                'isUpToDate' => false,
                'restricted' => false,
                'canInstall' => true,
            ];

            // Campos opcionales (solo incluir si no están vacíos)
            if (!empty($hubLib['categories'])) {
                $normalized['categories'] = $this->ensureArray($hubLib['categories']);
            }
            if (!empty($hubLib['keywords'])) {
                $normalized['keywords'] = $this->ensureArray($hubLib['keywords']);
            }
            if (!empty($hubLib['tutorial'])) {
                $normalized['tutorial'] = $hubLib['tutorial'];
            }
            if (!empty($hubLib['example'])) {
                $normalized['example'] = $hubLib['example'];
            }

            $merged[$hubLib['machineName'] ?? $hubLib['id']] = $normalized;
        }

        // PASO 2: Actualizar con información de librerías locales
        foreach ($localLibs as $localLib) {
            $machineName = $localLib['machineName'];

            if (isset($merged[$machineName])) {
                // La librería existe en el Hub - actualizar flags
                $merged[$machineName]['installed'] = true;
                $merged[$machineName]['restricted'] = $localLib['restricted'] ?? false;
                $merged[$machineName]['localMajorVersion'] = $localLib['majorVersion'];
                $merged[$machineName]['localMinorVersion'] = $localLib['minorVersion'];
                $merged[$machineName]['localPatchVersion'] = $localLib['patchVersion'];

                // Verificar si la versión local está actualizada
                if ($this->isLocalLibUpToDate($merged[$machineName], $localLib)) {
                    $merged[$machineName]['isUpToDate'] = true;
                }

                // Establecer path del ícono local
                if ($localLib['hasIcon']) {
                    $merged[$machineName]['icon'] = sprintf(
                        '/h5p/libraries/%s-%d.%d/icon.svg',
                        $machineName,
                        $localLib['majorVersion'],
                        $localLib['minorVersion']
                    );
                }
            } else {
                // Librería solo está instalada localmente (no en el Hub)
                // Agregar con información mínima
                $merged[$machineName] = [
                    'id' => $localLib['id'],
                    'machineName' => $machineName,
                    'title' => $localLib['title'],
                    'description' => '',
                    'majorVersion' => $localLib['majorVersion'],
                    'minorVersion' => $localLib['minorVersion'],
                    'patchVersion' => $localLib['patchVersion'],
                    'localMajorVersion' => $localLib['majorVersion'],
                    'localMinorVersion' => $localLib['minorVersion'],
                    'localPatchVersion' => $localLib['patchVersion'],
                    'canInstall' => false,
                    'installed' => true,
                    'isUpToDate' => true,
                    'owner' => '',
                    'restricted' => $localLib['restricted'] ?? false,
                    'icon' => $localLib['hasIcon'] ? sprintf(
                        '/h5p/libraries/%s-%d.%d/icon.svg',
                        $machineName,
                        $localLib['majorVersion'],
                        $localLib['minorVersion']
                    ) : ''
                ];
            }
        }

        // PASO 3: Convertir de array asociativo a array indexado
        return array_values($merged);
    }

    /**
     * Verificar si la versión local está actualizada
     * 
     * @param array $hubLib Info del Hub
     * @param array $localLib Info local
     * @return bool true si local >= Hub
     */
    private function isLocalLibUpToDate($hubLib, $localLib)
    {
        // Comparar versiones: major.minor.patch
        $hubVersion = ($hubLib['majorVersion'] * 10000) +
            ($hubLib['minorVersion'] * 100) +
            $hubLib['patchVersion'];

        $localVersion = ($localLib['majorVersion'] * 10000) +
            ($localLib['minorVersion'] * 100) +
            $localLib['patchVersion'];

        return $localVersion >= $hubVersion;
    }

    /**
     * Asegurar que un valor sea un array válido
     * 
     * @param mixed $value Valor a convertir
     * @return array Array válido
     */
    private function ensureArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (empty($value)) {
                return [];
            }
            // Intentar parsear como JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            // Si tiene comas, dividir
            if (strpos($value, ',') !== false) {
                return array_map('trim', explode(',', $value));
            }
            return [$value];
        }

        if (is_object($value)) {
            return (array) $value;
        }

        return [];
    }

    /**
     * Endpoint para forzar actualización del caché
     * POST /api/update-hub-cache
     */
    public function forceUpdateCache(Request $request, Response $response)
    {
        try {
            $updated = $this->updateHubCache();

            if ($updated) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Caché actualizado exitosamente',
                    'timestamp' => time()
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'No se pudo actualizar el caché'
                ]));
            }

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));

            return $response->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * Mostrar página del Hub
     * GET /hub
     */
    public function showHub(Request $request, Response $response)
    {
        $html = file_get_contents(__DIR__ . '/../../../public/hub.html');
        $response->getBody()->write($html);
        return $response;
    }

    /**
     * Verificar si el caché está desactualizado
     * Considera desactualizado si tiene más de 24 horas
     */
    private function isCacheOutdated()
    {
        $lastUpdate = $this->framework->getOption('h5p_content_type_cache_updated_at', 0);
        $cacheAge = time() - $lastUpdate;

        // 24 horas = 86400 segundos
        return $cacheAge > 86400;
    }

    /**
     * PASO 1: Obtener UUID (registrar solo si no existe)
     */
    private function getOrCreateUUID()
    {
        $uuid = $this->framework->getOption('h5p_site_uuid', null);

        if (!empty($uuid)) {
            return $uuid;
        }

        $uuid = $this->registerSite();
        $this->framework->setOption('h5p_site_uuid', $uuid);

        return $uuid;
    }

    /**
     * PASO 2: Registrar sitio en H5P.org
     */
    private function registerSite()
    {
        $url = 'https://api.h5p.org/v1/sites';
        $localId = abs(crc32(__DIR__));

        $postData = [
            'uuid' => '',
            'platform_name' => 'H5P Service POC',
            'platform_version' => '1.0',
            'h5p_version' => '1.24',
            'disabled' => 0,
            'local_id' => $localId,
            'type' => 'local',
            'core_api_version' => '1.24'
        ];

        $result = $this->framework->fetchExternalData(
            $url,
            $postData,
            true,
            null,
            false,
            ['Content-Type: application/x-www-form-urlencoded'],
            [],
            'POST'
        );

        if (!isset($result['data'])) {
            throw new \Exception('No se recibió respuesta de H5P.org al registrar sitio');
        }

        $responseData = json_decode($result['data']);

        if (!$responseData || !isset($responseData->uuid)) {
            throw new \Exception('Respuesta inválida de H5P.org: ' . $result['data']);
        }

        return $responseData->uuid;
    }

    /**
     * PASO 3: Actualizar caché de content types desde H5P.org
     */
    private function updateHubCache()
    {
        try {
            $uuid = $this->getOrCreateUUID();
            $url = 'https://api.h5p.org/v1/content-types/';
            $libraries = $this->getLibraryStats();
            $localId = abs(crc32(__DIR__));

            $postData = [
                'uuid' => $uuid,
                'platform_name' => 'H5P Service POC',
                'platform_version' => '1.0',
                'h5p_version' => '1.24',
                'disabled' => 0,
                'local_id' => $localId,
                'type' => 'local',
                'core_api_version' => '1.24',
                'num_authors' => $this->framework->getNumAuthors(),
                'libraries' => json_encode($libraries)
            ];

            $result = $this->framework->fetchExternalData(
                $url,
                $postData,
                true,
                null,
                false,
                ['Content-Type: application/x-www-form-urlencoded'],
                [],
                'POST'
            );

            if (!isset($result['data'])) {
                error_log('H5P Hub: No se recibió respuesta de H5P.org');
                return false;
            }

            $data = json_decode($result['data']);

            if (!$data || !isset($data->contentTypes)) {
                error_log('H5P Hub: Respuesta inválida - ' . $result['data']);
                return false;
            }

            // Guardar en BD
            $this->framework->replaceContentTypeCache($data->contentTypes);
            $this->framework->setContentTypeCacheUpdatedAt(time());

            return true;
        } catch (\Exception $e) {
            error_log('H5P Hub Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de librerías
     */
    private function getLibraryStats()
    {
        try {
            $libraries = $this->framework->loadLibraries();
            $stats = [];

            foreach ($libraries as $lib) {
                $stats[] = [
                    'name' => $lib['machineName'] ?? $lib['name'],
                    'major' => $lib['majorVersion'] ?? $lib['major_version'],
                    'minor' => $lib['minorVersion'] ?? $lib['minor_version'],
                    'patch' => $lib['patchVersion'] ?? $lib['patch_version'],
                    'numContent' => 0
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Endpoint de diagnóstico
     * GET /api/hub-status
     */
    public function getHubStatus(Request $request, Response $response)
    {
        $uuid = $this->framework->getOption('h5p_site_uuid', null);
        $lastUpdate = $this->framework->getOption('h5p_content_type_cache_updated_at', 0);
        $contentTypes = $this->framework->getContentTypeCache();
        $localLibraries = $this->getLocalLibraries();

        $status = [
            'uuid_exists' => !empty($uuid),
            'uuid' => $uuid ? substr($uuid, 0, 8) . '...' : null,
            'cache_exists' => !empty($contentTypes),
            'content_types_count' => count($contentTypes),
            'last_update' => $lastUpdate,
            'last_update_human' => $lastUpdate ? date('Y-m-d H:i:s', $lastUpdate) : 'Never',
            'cache_age_hours' => $lastUpdate ? round((time() - $lastUpdate) / 3600, 2) : null,
            'is_outdated' => $this->isCacheOutdated(),
            'libraries_installed' => count($localLibraries),
            'libraries_installed_list' => array_map(function ($lib) {
                return $lib['machineName'] . ' ' . $lib['majorVersion'] . '.' . $lib['minorVersion'];
            }, $localLibraries)
        ];

        $response->getBody()->write(json_encode($status, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
