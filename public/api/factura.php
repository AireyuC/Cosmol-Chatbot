<?php

declare(strict_types=1);

// bootstrap.php carga el autoloader (PSR-4), la configuración global y los headers de la API.
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Validator;
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
            $this->json(['success' => false, 'message' => 'Método HTTP no soportado', 'data' => null], 405);
            return;
        }

        if ($cod_socio === null || !Validator::codigoSocio((string)$cod_socio)) {
            $this->json(['success' => false, 'message' => 'El parámetro cod_socio es inválido o requerido (solo dígitos, 1-10 caracteres).', 'data' => null], 400);
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
            $this->json(['success' => false, 'message' => 'Ocurrió un error interno en el servidor.', 'data' => null], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new FacturaEndpoint();
$endpoint->handleRequest();
