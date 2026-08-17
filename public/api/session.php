<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/Controller.php';
require_once __DIR__ . '/../../app/Data/Interfaces/SessionRepositoryInterface.php';
require_once __DIR__ . '/../../app/Data/Repositories/MySQL/RepositorioSessionMySQL.php';
require_once __DIR__ . '/../../app/Modules/Session/SessionService.php';

use App\Core\Controller;
use App\Data\Repositories\MySQL\RepositorioSessionMySQL;
use App\Modules\Session\SessionService;

class SessionEndpoint extends Controller
{
    public function handleRequest()
    {
        $telefono = null;
        $action = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $telefono = $_POST['telefono'] ?? ($input['telefono'] ?? null);
            $action = $_POST['action'] ?? ($input['action'] ?? 'update');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $telefono = $_GET['telefono'] ?? null;
            $action = $_GET['action'] ?? 'get';
        } else {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if ($telefono === null) {
            $this->json(['status' => 'error', 'message' => 'El parámetro telefono es requerido'], 400);
        }

        try {
            $repository = new RepositorioSessionMySQL();
            $service = new SessionService($repository);

            if ($action === 'get') {
                // Obtener estado actual (aplica timeouts automáticamente)
                // Se envía "message" dummy porque la API solo requiere el teléfono para procesar el estado.
                $resultado = $service->processSessionState((string)$telefono, '');
            } elseif ($action === 'update') {
                // Actualizar estado (ej. pasar a MAIN_MENU, o reset a AWAITING_CODE)
                $estado = $_POST['estado_actual'] ?? ($input['estado_actual'] ?? 'AWAITING_CODE');
                $codigoSocio = $_POST['codigo_socio'] ?? ($input['codigo_socio'] ?? null);
                if ($codigoSocio !== null && $codigoSocio !== '') {
                    $codigoSocio = (int)$codigoSocio;
                } else {
                    $codigoSocio = null;
                }
                
                $intentos = $_POST['intentos'] ?? ($input['intentos'] ?? 0);
                $intentos = (int)$intentos;

                $success = $service->updateSession((string)$telefono, $codigoSocio, $estado, $intentos);
                
                if ($success) {
                    $resultado = ['status' => 'success', 'message' => 'Sesión actualizada'];
                    if ($estado === 'AWAITING_CODE') {
                        // El código fue inválido y sumamos un intento
                        $resultado['whatsapp_payload'] = [
                            'type' => 'text',
                            'text' => [
                                'body' => 'No se encontró el asociado o el código es inválido. Por favor, intenta de nuevo.'
                            ]
                        ];
                    }
                } else {
                    $resultado = ['status' => 'error', 'message' => 'No se pudo actualizar la sesión'];
                }
            } elseif ($action === 'reset') {
                $success = $service->resetSession((string)$telefono);
                if ($success) {
                    $resultado = [
                        'status' => 'success', 
                        'message' => 'Sesión reseteada',
                        'whatsapp_payload' => [
                            'type' => 'text',
                            'text' => [
                                'body' => 'Sesión cerrada. Por favor, ingresa tu nuevo código de socio.'
                            ]
                        ]
                    ];
                } else {
                    $resultado = ['status' => 'error', 'message' => 'No se pudo resetear la sesión'];
                }
            } else {
                $resultado = ['status' => 'error', 'message' => 'Acción no soportada'];
            }

            $httpStatus = (isset($resultado['status']) && $resultado['status'] === 'success') ? 200 : 400;
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            error_log("Error crítico en SessionEndpoint: " . $e->getMessage());
            $this->json(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor: ' . $e->getMessage()], 500);
        }
    }
}

$endpoint = new SessionEndpoint();
$endpoint->handleRequest();
