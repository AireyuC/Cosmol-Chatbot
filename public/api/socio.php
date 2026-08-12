<?php

declare(strict_types=1);

// Punto de entrada HTTP del módulo de Socios.
// bootstrap.php carga el autoloader (PSR-4), la configuración global y los headers de la API.
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Database;
use App\Data\Repositories\MySQL\SocioRepository;
use App\Modules\Socio\SocioService;

/**
 * Endpoint de la API para Socios
 */
class SocioEndpoint extends Controller
{
    public function handleRequest()
    {
        // Obtener el código de socio, ya sea de GET (query param) o POST (JSON payload)
        $codigo_socio = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $codigo_socio = $input['codigo_socio'] ?? null;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $codigo_socio = $_GET['codigo_socio'] ?? null;
        } else {
            // Método no permitido
            $this->json(['success' => false, 'message' => 'Método HTTP no soportado', 'data' => null], 405);
        }

        if ($codigo_socio === null) {
            $this->json(['success' => false, 'message' => 'El parámetro codigo_socio es requerido', 'data' => null], 400);
        }

        // --- INICIO DEL MOCK TEMPORAL (Borrar cuando la BD esté lista) ---
        // Simula que la base de datos ya está conectada y encontró al usuario
        $this->json([
            'status' => 'success',
            'mensaje' => "¡Hola! Hemos verificado tu código $cod_socio en la base de datos simulada. No tienes deudas pendientes.",
            'datos_socio' => [
                'nombre' => 'Asociado de Prueba',
                'deuda_total' => 0
            ]
        ], 200);
        return;
        // --- FIN DEL MOCK TEMPORAL ---

        try {
            // Instanciar la base de datos (Singleton)
            $db = Database::getInstance();

            // Inyección de dependencias manual (Wiring)
            $repository = new SocioRepository($db);
            $service = new SocioService($repository);

            // Ejecutar la lógica de negocio
            $resultado = $service->validarSocio((string) $codigo_socio);

            // Devolver la respuesta usando el método del Controller base
            $httpStatus = $resultado['success'] ? 200 : 404;
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            // Manejo de errores a nivel superior (ej. falla en conexión BD)
            error_log("Error crítico en SocioEndpoint: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Ocurrió un error interno en el servidor', 'data' => null], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
