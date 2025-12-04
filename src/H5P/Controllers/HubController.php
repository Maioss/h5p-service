<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Framework\H5PFramework;
use H5PCore;
use H5peditor;

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
            '/h5p', // URL path
            'en',   // Language
            true    // Export enabled
        );
    }

    /**
     * Endpoint para obtener content types del Hub
     */
    public function getContentTypes(Request $request, Response $response)
    {
        try {
            // Obtener content types desde el caché
            $contentTypes = $this->framework->getContentTypeCache();

            // Si no hay caché, actualizar desde H5P.org
            if (empty($contentTypes)) {
                $this->updateHubCache();
                $contentTypes = $this->framework->getContentTypeCache();
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'contentTypes' => $contentTypes
            ]));

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
     * Actualizar caché desde H5P.org
     */
    private function updateHubCache()
    {
        $url = 'https://api.h5p.org/v1/content-types/';

        $result = $this->framework->fetchExternalData($url, null, true);

        if (isset($result['data'])) {
            $data = json_decode($result['data']);
            if ($data && isset($data->contentTypes)) {
                $this->framework->replaceContentTypeCache($data->contentTypes);
                $this->framework->setContentTypeCacheUpdatedAt(time());
            }
        }
    }

    /**
     * Mostrar página del Hub
     */
    public function showHub(Request $request, Response $response)
    {
        $html = file_get_contents(__DIR__ . '/../../public/hub.html');
        $response->getBody()->write($html);
        return $response;
    }
}
