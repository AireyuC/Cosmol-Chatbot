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
 *
 * Selección de repositorio según entorno:
 *  - Si COSMOL_API_URL está configurado en el .env → usa CosmolSocioRepository (API real).
 *  - Si no → usa MySQLSocioRepository (BD local de desarrollo).
 *
 * Acciones disponibles (parámetro GET 'action'):
 *  - 'validar'  → busca si el socio existe (por defecto)
 *  - 'deudas'   → devuelve las deudas/facturas pendientes del socio
 */
class SocioEndpoint extends Controller
{
    public function handleRequest()
    {
        $codigo_socio = null;
        $action       = 'validar'; // acción por defecto

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Soportar tanto JSON como Form Data
            $input = json_decode(file_get_contents('php://input'), true);
            $cod_socio = $_POST['cod_socio'] ?? ($input['cod_socio'] ?? null);
            $action = $_POST['action'] ?? ($input['action'] ?? 'validar');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $cod_socio = $_GET['cod_socio'] ?? null;
            $action = $_GET['action'] ?? 'validar';
        } else {
            $this->json(['success' => false, 'message' => 'Método HTTP no soportado', 'data' => null], 405);
            return;
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
            error_log("Error crítico en SocioEndpoint: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Ocurrió un error interno en el servidor', 'data' => null], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
