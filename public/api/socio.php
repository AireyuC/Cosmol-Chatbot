<?php

declare(strict_types=1);

// Carga manual de dependencias debido a que aún no hay Autoloader (Fase 4)
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Core/Controller.php';
require_once __DIR__ . '/../../app/Data/Interfaces/SocioRepositoryInterface.php';
require_once __DIR__ . '/../../app/Integrations/CosmolApi/ClienteApiCosmol.php';
require_once __DIR__ . '/../../app/Data/Repositories/Api/RepositorioSocioApi.php';
require_once __DIR__ . '/../../app/Modules/Socio/SocioService.php';

use App\Core\Controller;
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
        // Obtener el código de socio, ya sea de GET (query param) o POST (JSON payload)
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
            // Método no permitido
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if ($cod_socio === null) {
            $this->json(['status' => 'error', 'message' => 'El parámetro cod_socio es requerido'], 400);
        }

        // Elimino la lectura redundante de action que estaba aquí

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

            // Devolver la respuesta usando el método del Controller base
            $httpStatus = $resultado['status'] === 'success' ? 200 : ($resultado['status'] === 'not_found' ? 404 : 400);
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            // Manejo de errores a nivel superior (ej. falla en conexión BD)
            error_log("Error crítico en SocioEndpoint: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor'], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
