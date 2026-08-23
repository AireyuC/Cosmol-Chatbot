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
 * Endpoint de la API para Socios
 * Maneja las peticiones entrantes desde n8n.
 */
class SocioEndpoint extends Controller
{
    public function handleRequest()
    {
        $cod_socio = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Soportar tanto JSON como Form Data
            $input = json_decode(file_get_contents('php://input'), true);
            $cod_socio = $_POST['cod_socio'] ?? ($input['cod_socio'] ?? null);
            $action = $_POST['action'] ?? ($input['action'] ?? 'validar');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $cod_socio = $_GET['cod_socio'] ?? null;
            $action = $_GET['action'] ?? 'validar';
        } else {
            
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if (!Validator::codigoSocio((string)$cod_socio)) {
            $this->json(['success' => false, 'message' => 'El parámetro cod_socio es inválido o no fue proporcionado.', 'data' => null], 400);
            return;
        }

        try {
            // Instanciar el cliente HTTP de la API externa
            $clienteApi = new ClienteApiCosmol();

            // Inyección de dependencias
            $repository = new RepositorioSocioApi($clienteApi);
            $service = new SocioService($repository);

            // Ejecutar la lógica de negocio según la acción
            if ($action === 'deudas') {
                $resultado = $service->obtenerDeudas((string)$cod_socio);
            } else {
                // Por defecto: validar
                $resultado = $service->validarSocio((string)$cod_socio);
            }

            // Devolver la respuesta siempre con HTTP 200 para que n8n no rompa el flujo.
            // El estado real va dentro del JSON ('status' => 'not_found' o 'success').
            $httpStatus = 200;
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            \App\Core\Logger::error('Error crítico en SocioEndpoint', [
                'exception' => $e->getMessage(),
                'codigo_socio' => $cod_socio ?? null,
                'action' => $action ?? null,
            ]);
            $this->json(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor'], 500);
        }
    }
}

$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
