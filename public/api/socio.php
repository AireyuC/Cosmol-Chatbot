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

        if ($cod_socio === null || !Validator::codigoSocio((string)$cod_socio)) {
            $this->json(['success' => false, 'message' => 'El parámetro cod_socio es inválido o requerido (solo dígitos, 1-10 caracteres).', 'data' => null], 400);
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
            $status = $resultado['status'] ?? ($resultado['success'] ?? false ? 'success' : 'error');
            $httpStatus = $status === 'success' ? 200 : ($status === 'not_found' ? 404 : 400);
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            Logger::error('Error crítico en SocioEndpoint', [
                'exception'    => $e->getMessage(),
                'codigo_socio' => $cod_socio ?? null,
                'action'       => $action ?? null,
            ]);
            $this->json(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor'], 500);
        }
    }
}

// Ejecutar el endpoint
$endpoint = new SocioEndpoint();
$endpoint->handleRequest();
