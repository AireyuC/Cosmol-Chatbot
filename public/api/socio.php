<?php

declare(strict_types=1);

// Carga manual de dependencias debido a que aún no hay Autoloader (Fase 4)
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Core/Controller.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Data/Interfaces/SocioRepositoryInterface.php';
require_once __DIR__ . '/../../app/Data/Repositories/MySQL/SocioRepository.php';
require_once __DIR__ . '/../../app/Modules/Socio/SocioService.php';

use App\Core\Controller;
use App\Core\Database;
use App\Data\Repositories\MySQL\SocioRepository;
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
            $input = json_decode(file_get_contents('php://input'), true);
            $cod_socio = $input['cod_socio'] ?? null;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $cod_socio = $_GET['cod_socio'] ?? null;
        } else {
            // Método no permitido
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if ($cod_socio === null) {
            $this->json(['status' => 'error', 'message' => 'El parámetro cod_socio es requerido'], 400);
        }

        try {
            // Instanciar la base de datos (Singleton)
            $db = Database::getInstance();

            // Inyección de dependencias manual (Wiring)
            $repository = new SocioRepository($db);
            $service = new SocioService($repository);

            // Ejecutar la lógica de negocio
            $resultado = $service->validarSocio((string)$cod_socio);

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
