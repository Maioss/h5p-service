<?php

namespace App\H5P\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\H5P\Framework\H5PFramework;
use H5PCore;

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
     */
    public function getContentTypes(Request $request, Response $response)
    {
        try {
            // Intentar obtener desde caché local (BD)
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

            $response->getBody()->write(json_encode([
                'success' => true,
                'contentTypes' => $contentTypes,
                'cached' => true,
                'lastUpdate' => $this->framework->getOption('h5p_content_type_cache_updated_at', 0)
            ]));

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
     * Endpoint para forzar actualización del caché
     * POST /api/update-hub-cache
     */
    public function forceUpdateCache(Request $request, Response $response)
    {
        try {
            $updated = $this->updateHubCache();

            if ($updated) {
                $contentTypes = $this->framework->getContentTypeCache();

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Caché actualizado exitosamente',
                    'count' => count($contentTypes),
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
     * 
     * @return string UUID del sitio
     * @throws \Exception si no se puede obtener UUID
     */
    private function getOrCreateUUID()
    {
        // Intentar obtener UUID existente de BD
        $uuid = $this->framework->getOption('h5p_site_uuid', null);

        // Si ya existe, retornarlo directamente
        if (!empty($uuid)) {
            return $uuid;
        }

        // Si no existe, registrar el sitio por primera vez
        $uuid = $this->registerSite();

        // Guardar UUID en BD para futuros usos
        $this->framework->setOption('h5p_site_uuid', $uuid);

        return $uuid;
    }

    /**
     * PASO 2: Registrar sitio en H5P.org (solo primera vez)
     * 
     * @return string UUID obtenido de H5P.org
     * @throws \Exception si falla el registro
     */
    private function registerSite()
    {
        $url = 'https://api.h5p.org/v1/sites';

        // CRC32 del directorio como local_id único
        $localId = abs(crc32(__DIR__));

        $postData = [
            'uuid' => '',  // Vacío = nuevo registro
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

        $response = json_decode($result['data']);

        if (!$response || !isset($response->uuid)) {
            throw new \Exception('Respuesta inválida de H5P.org: ' . $result['data']);
        }

        return $response->uuid;
    }

    /**
     * PASO 3: Actualizar caché de content types desde H5P.org
     * 
     * @return bool true si se actualizó exitosamente
     */
    private function updateHubCache()
    {
        try {
            // Obtener UUID (registrar solo si no existe)
            $uuid = $this->getOrCreateUUID();

            $url = 'https://api.h5p.org/v1/content-types/';

            // Obtener estadísticas de librerías instaladas
            $libraries = $this->getLibraryStats();

            $localId = abs(crc32(__DIR__));

            $postData = [
                // === DATOS DE AUTENTICACIÓN (OBLIGATORIOS) ===
                'uuid' => $uuid,
                'platform_name' => 'H5P Service POC',
                'platform_version' => '1.0',
                'h5p_version' => '1.24',
                'disabled' => 0,
                'local_id' => $localId,
                'type' => 'local',
                'core_api_version' => '1.24',

                // === ESTADÍSTICAS DE USO (OPCIONALES) ===
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

            // Guardar content types en caché de BD
            $this->framework->replaceContentTypeCache($data->contentTypes);

            // Actualizar timestamp del caché
            $this->framework->setContentTypeCacheUpdatedAt(time());

            return true;
        } catch (\Exception $e) {
            error_log('H5P Hub Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estadísticas de librerías instaladas
     * Para enviar a H5P.org (opcional pero recomendado)
     * 
     * @return array Lista de librerías con sus estadísticas
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
                    'numContent' => 0  // Por ahora 0, se puede calcular
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            // Si falla, retornar array vacío
            return [];
        }
    }

    /**
     * Endpoint de diagnóstico - verificar estado del Hub
     * GET /api/hub-status
     */
    public function getHubStatus(Request $request, Response $response)
    {
        $uuid = $this->framework->getOption('h5p_site_uuid', null);
        $lastUpdate = $this->framework->getOption('h5p_content_type_cache_updated_at', 0);
        $contentTypes = $this->framework->getContentTypeCache();

        $status = [
            'uuid_exists' => !empty($uuid),
            'uuid' => $uuid ? substr($uuid, 0, 8) . '...' : null,  // Solo primeros 8 chars
            'cache_exists' => !empty($contentTypes),
            'content_types_count' => count($contentTypes),
            'last_update' => $lastUpdate,
            'last_update_human' => $lastUpdate ? date('Y-m-d H:i:s', $lastUpdate) : 'Never',
            'cache_age_hours' => $lastUpdate ? round((time() - $lastUpdate) / 3600, 2) : null,
            'is_outdated' => $this->isCacheOutdated(),
            'libraries_installed' => count($this->framework->loadLibraries())
        ];

        $response->getBody()->write(json_encode($status, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
