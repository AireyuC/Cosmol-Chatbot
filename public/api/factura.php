<?php

declare(strict_types=1);

// bootstrap.php carga el autoloader (PSR-4), la configuración global y los headers de la API.
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Logger;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Data\Repositories\Api\RepositorioSocioApi;
use App\Modules\Socio\SocioService;

/**
 * Endpoint para Facturas (Deudas)
 */
class FacturaEndpoint extends Controller
{
    public function handleRequest()
    {
        $cod_socio = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Soportar tanto JSON como Form Data
            $input = json_decode(file_get_contents('php://input'), true);
            $cod_socio = $_POST['cod_socio'] ?? ($input['cod_socio'] ?? null);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $cod_socio = $_GET['cod_socio'] ?? null;
        } else {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if ($cod_socio === null) {
            $this->json(['status' => 'error', 'message' => 'El parámetro cod_socio es requerido'], 400);
        }

        try {
            $clienteApi = new ClienteApiCosmol();
            $repository = new RepositorioSocioApi($clienteApi);
            $service = new SocioService($repository);

            $resultado = $service->obtenerDeudas((string)$cod_socio);

            $httpStatus = 200;
            $this->json($resultado, $httpStatus);
        } catch (Exception $e) {
            Logger::error('Error crítico en FacturaEndpoint', [
                'exception' => $e->getMessage(),
                'cod_socio' => $cod_socio ?? null,
            ]);
            $this->json(['status' => 'error', 'mensaje_texto' => 'Ocurrió un error interno en el servidor'], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new FacturaEndpoint();
$endpoint->handleRequest();
