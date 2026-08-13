<?php

declare(strict_types=1);

// Punto de entrada HTTP del módulo de Socios.
// bootstrap.php carga el autoloader (PSR-4), la configuración global y los headers de la API.
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Database;
use App\Data\Repositories\MySQL\SocioRepository as MySQLSocioRepository;
use App\Data\Repositories\Cosmol\SocioRepository as CosmolSocioRepository;
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
            $input        = json_decode(file_get_contents('php://input'), true);
            $codigo_socio = $input['codigo_socio'] ?? null;
            $action       = $input['action'] ?? 'validar';

        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $codigo_socio = $_GET['codigo_socio'] ?? null;
            $action       = $_GET['action'] ?? 'validar';

        } else {
            $this->json(['success' => false, 'message' => 'Método HTTP no soportado', 'data' => null], 405);
            return;
        }

        if ($codigo_socio === null) {
            $this->json(['success' => false, 'message' => 'El parámetro codigo_socio es requerido', 'data' => null], 400);
            return;
        }

        try {
            // --- Selección de Repositorio según entorno ---
            // Si COSMOL_API_URL está definida en el .env, usamos la API real de COSMOL.
            // Si no, fallback al repositorio MySQL local de desarrollo.
            if (defined('COSMOL_API_URL') && !empty(COSMOL_API_URL)) {
                $repository = new CosmolSocioRepository();
            } else {
                $db         = Database::getInstance();
                $repository = new MySQLSocioRepository($db);
            }

            $service = new SocioService($repository);

            // --- Despacho de acciones ---
            switch ($action) {

                case 'deudas':
                    $resultado  = $service->consultarDeuda((string) $codigo_socio);
                    $httpStatus = $resultado['success'] ? 200 : 404;
                    $this->json($resultado, $httpStatus);
                    break;

                case 'validar':
                default:
                    $resultado  = $service->validarSocio((string) $codigo_socio);
                    $httpStatus = $resultado['success'] ? 200 : 404;
                    $this->json($resultado, $httpStatus);
                    break;
            }

        } catch (Exception $e) {
            error_log("Error crítico en SocioEndpoint: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Ocurrió un error interno en el servidor', 'data' => null], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
