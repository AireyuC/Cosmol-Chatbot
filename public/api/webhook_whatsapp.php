<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Database;
use App\Data\Repositories\Postgres\SessionRepository;
use App\Modules\Session\SessionService;

use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Data\Repositories\Api\SocioRepository;
use App\Modules\Socio\SocioService;

use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaFactura;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Controlador Central para el Chatbot de WhatsApp.
 * Orquesta la Máquina de Estados, los Servicios y las Vistas (Plantillas).
 */
class WebhookWhatsAppEndpoint extends Controller
{
    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        $inputRaw = file_get_contents('php://input');
        error_log("WEBHOOK RAW INPUT: " . $inputRaw);
        error_log("WEBHOOK POST: " . json_encode($_POST));
        $input = json_decode($inputRaw, true);
        
        $telefono = $_POST['telefono'] ?? ($input['telefono'] ?? null);
        $tipoMensaje = $_POST['tipo_mensaje'] ?? ($input['tipo_mensaje'] ?? null);
        $contenido = $_POST['contenido'] ?? ($input['contenido'] ?? null);

        if (!$telefono || !$tipoMensaje || $contenido === null) {
            $this->json([
                'status' => 'error', 
                'message' => 'Faltan parámetros obligatorios',
                'whatsapp_payload' => PlantillaSistema::textoSimple("Error interno: Faltan parámetros en la comunicación con el bot.")
            ], 200);
        }

        try {
            $sessionRepo = new SessionRepository();
            $sessionService = new SessionService($sessionRepo);

            $clienteApi = new ClienteApiCosmol();
            $socioRepo = new SocioRepository($clienteApi);
            $socioService = new SocioService($socioRepo);

            // Obtener el estado actual y procesar Timeouts automáticos
            $sessionResult = $sessionService->processSessionState((string)$telefono, '');
            $estadoActual = $sessionResult['estado_actual'];
            $intentos = $sessionResult['intentos'];
            $codigoSocio = $sessionResult['codigo_socio'];

            // Variable para almacenar el JSON visual final que enviaremos a n8n
            $whatsappPayload = null;


            if ($estadoActual === 'BLOCKED') {
                // Si sigue bloqueado (processSessionState ya evalúa el timeout de 5 min)
                // Si el timeout hubiera pasado, processSessionState devolvería AWAITING_CODE.
                // El usuario solicitó: "el bot no le contestara hasta que acabe los 5 minutos"
                $whatsappPayload = null;
            } 
            elseif ($estadoActual === 'AWAITING_CODE') {
                if ($tipoMensaje === 'text') {
                    $cod = trim((string)$contenido);
                    $esCodigoValido = false;
                    $validacion = null;

                    if (is_numeric($cod)) {
                        $validacion = $socioService->validarSocio($cod);
                        if ($validacion['status'] === 'success') {
                            $esCodigoValido = true;
                        }
                    }

                    if ($esCodigoValido) {
                        // Socio válido -> Actualizar estado a MAIN_MENU
                        $sessionService->updateSession($telefono, (int)$cod, 'MAIN_MENU', 0);
                        $nombreSocio = $validacion['datos_socio']['nombre'] ?? 'Socio';
                        $whatsappPayload = PlantillaSocio::menuPrincipal($cod, $nombreSocio);
                    } else {

                        $intentos++;
                        
                        if (!is_numeric($cod) && $intentos === 1) {
                            $whatsappPayload = PlantillaSocio::saludo();
                        } else {
                            $whatsappPayload = PlantillaSistema::codigoInvalido();
                        }

                        $sessionService->updateSession($telefono, null, 'AWAITING_CODE', $intentos);
                        
                        $nuevaSesion = $sessionService->processSessionState($telefono, '');
                        if ($nuevaSesion['estado_actual'] === 'BLOCKED') {
                            // En el MOMENTO en que se bloquea, SÍ le notificamos. Luego lo ignoraremos.
                            $whatsappPayload = PlantillaSistema::bloqueado();
                        }
                    }
                } else {
                    // Si manda un botón pero estamos esperando código, lo contamos como intento
                    $intentos++;
                    $sessionService->updateSession($telefono, null, 'AWAITING_CODE', $intentos);
                    $nuevaSesion = $sessionService->processSessionState($telefono, '');
                    if ($nuevaSesion['estado_actual'] === 'BLOCKED') {
                        $whatsappPayload = PlantillaSistema::bloqueado();
                    } else {
                        $whatsappPayload = PlantillaSistema::codigoInvalido();
                    }
                }
            } 
            elseif ($estadoActual === 'MAIN_MENU') {
                if ($tipoMensaje === 'interactive') {
                    if (strpos($contenido, 'MENU_PAGAR_') === 0) {
                        // El usuario quiere pagar
                        $partes = explode('_', $contenido);
                        $cod = $partes[2] ?? (string)$codigoSocio;
                        
                        $deudasResult = $socioService->obtenerDeudas($cod);
                        if ($deudasResult['status'] === 'success') {
                            $whatsappPayload = PlantillaFactura::listaDeudas(
                                $cod,
                                $deudasResult['cantidad_facturas'],
                                $deudasResult['total_deuda'],
                                $deudasResult['facturas_pendientes']
                            );
                        } else {
                            $whatsappPayload = PlantillaSistema::textoSimple("Ocurrió un error al obtener las deudas.");
                        }
                    } elseif ($contenido === 'MENU_AGENTE') {
                        $whatsappPayload = PlantillaSocio::redireccionAgente();
                    } elseif ($contenido === 'MENU_PRINCIPAL_VOLVER') {
                        $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio);
                    } elseif ($contenido === 'MENU_CAMBIAR_CODIGO') {
                        
                        $sessionService->resetSession($telefono);
                        $whatsappPayload = PlantillaSistema::textoSimple("Sesión cerrada. Por favor, ingresa tu nuevo código de socio.");
                    } else {
                        $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio, '', true);
                    }
                } else {
                    // Si escribe texto en lugar de usar botones del menú
                    $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio, '', true);
                }
            }

            // RESPUESTA AL N8N
            // Siempre respondemos 200 OK para no romper el flujo HTTP de n8n.
            $this->json([
                'status' => 'success',
                'estado' => $estadoActual,
                'whatsapp_payload' => $whatsappPayload
            ], 200);

        } catch (Exception $e) {
            \App\Core\Logger::error('Error en WebhookWhatsAppEndpoint', [
                'exception' => $e->getMessage(),
                'telefono' => $telefono ?? null
            ]);
            
            $this->json([
                'status' => 'error',
                'whatsapp_payload' => PlantillaSistema::textoSimple("Ocurrió un error interno en el servidor.")
            ], 200);
        }
    }
}

$endpoint = new WebhookWhatsAppEndpoint();
$endpoint->handleRequest();
