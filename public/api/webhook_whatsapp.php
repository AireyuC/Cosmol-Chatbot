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
use App\Presentacion\PlantillasWhatsApp\PlantillaReclamos;

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

            $sessionResult = $sessionService->processSessionState((string)$telefono, '');
            $estadoActual = $sessionResult['estado_actual'];
            $intentos = $sessionResult['intentos'];
            $codigoSocio = $sessionResult['codigo_socio'];
            $contextData = $sessionResult['context_data'] ?? [];

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
                    } elseif ($contenido === 'MENU_RECLAMOS') {
                        $whatsappPayload = PlantillaReclamos::menuReclamos();
                    } elseif ($contenido === 'MENU_HISTORIAL') {
                        $historialResult = $socioService->obtenerHistorial((string)$codigoSocio);
                        if ($historialResult['status'] === 'success') {
                            $whatsappPayload = PlantillaFactura::historialFacturas(
                                (string)$codigoSocio,
                                $historialResult['facturas'],
                                $historialResult['cantidad']
                            );
                        } else {
                            $whatsappPayload = PlantillaSistema::textoSimple("Ocurrió un error al obtener el historial de facturas.");
                        }
                    } elseif ($contenido === 'MENU_RECONEXION') {
                        $sessionService->updateSession($telefono, $codigoSocio, 'AWAITING_RECONEXION_GPS', 0, []);
                        $whatsappPayload = PlantillaSocio::solicitarGpsReconexion();
                    } elseif ($contenido === 'MENU_OFICINAS') {
                        $whatsappPayload = PlantillaSocio::menuPrincipal(
                            (string)$codigoSocio, 
                            '', 
                            false, 
                            "🏗️ Esta opción está a la espera de formularios. Por favor seleccione otra opción:"
                        );
                    } elseif (in_array($contenido, [
                        'RECLAMO_AGUA_TURBIA',
                        'RECLAMO_FUGA',
                        'RECLAMO_REBALSE',
                        'RECLAMO_TRANCADO',
                        'RECLAMO_ESTADO'
                    ])) {
                        $whatsappPayload = PlantillaReclamos::menuReclamos("🏗️ Esta opción está a la espera de formularios. Por favor seleccione otra opción:");
                    } else {
                        $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio, '', true);
                    }
                } else {
                    // Si escribe texto en lugar de usar botones del menú
                    $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio, '', true);
                }
            } 
            elseif ($estadoActual === 'AWAITING_RECONEXION_GPS') {
                if ($tipoMensaje === 'location' && !empty($contenido)) {
                    // $contenido es un JSON con lat y long
                    $loc = json_decode($contenido, true);
                    $lat = $loc['latitude'] ?? '';
                    $lng = $loc['longitude'] ?? '';
                    
                    $contextData['coordenadas_gps'] = "{$lat}, {$lng}";
                    $sessionService->updateSession($telefono, $codigoSocio, 'AWAITING_RECONEXION_TYPE', 0, $contextData);
                    
                    $whatsappPayload = PlantillaSocio::menuTipoReconexion();
                } else {
                    $whatsappPayload = PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Debe usar la opción de adjuntar 📎 y seleccionar 'Ubicación' 📍.");
                }
            }
            elseif ($estadoActual === 'AWAITING_RECONEXION_TYPE') {
                if ($tipoMensaje === 'interactive' && strpos((string)$contenido, 'RECONEXION_TIPO_') === 0) {
                    $tipoId = (int)str_replace('RECONEXION_TIPO_', '', $contenido);
                    $contextData['id_tipo_reconexion'] = $tipoId;
                    
                    $sessionService->updateSession($telefono, $codigoSocio, 'AWAITING_RECONEXION_PHOTO', 0, $contextData);
                    $whatsappPayload = PlantillaSocio::mensajeTextoSimple("📸 *Fotografía requerida*\n\nPor favor, envíe una foto del medidor o del lugar donde se requiere la reconexión usando el icono de la cámara o galería de WhatsApp.");
                } else {
                    $whatsappPayload = PlantillaSocio::mensajeTextoSimple("❌ Opción inválida. Por favor, seleccione una opción de la lista enviada. 👇");
                }
            }
            elseif ($estadoActual === 'AWAITING_RECONEXION_PHOTO') {
                if ($tipoMensaje === 'image' && !empty($contenido)) {
                    // $contenido contiene el media_id enviado por WhatsApp
                    require_once __DIR__ . '/../../app/Integrations/WhatsApp/WhatsAppMediaService.php';
                    $mediaService = new \App\Integrations\WhatsApp\WhatsAppMediaService();
                    $fotoUrl = $mediaService->descargarYGuardar((string)$contenido, (string)$codigoSocio);
                    
                    $contextData['foto_url'] = $fotoUrl; // Guardar URL local en contexto
                    
                    $sessionService->updateSession($telefono, $codigoSocio, 'AWAITING_RECONEXION_GLOSA', 0, $contextData);
                    $whatsappPayload = PlantillaSocio::solicitarGlosaReconexion();
                } else {
                    $whatsappPayload = PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Por favor, adjunte una imagen 📸.");
                }
            }
            elseif ($estadoActual === 'AWAITING_RECONEXION_GLOSA') {
                if ($tipoMensaje === 'text') {
                    $glosa = trim((string)$contenido);
                    
                    $gps = $contextData['coordenadas_gps'] ?? '';
                    $tipoId = $contextData['id_tipo_reconexion'] ?? 1;
                    $fotoUrl = $contextData['foto_url'] ?? '';
                    
                    $resultadoReconexion = $socioService->solicitarReconexion((string)$codigoSocio, $gps, $tipoId, $glosa, $fotoUrl);
                    
                    if ($resultadoReconexion['status'] === 'success') {
                        $ticket = $resultadoReconexion['id_reconexion'];
                        $msg = "✅ *Solicitud de Reconexión registrada exitosamente.*\nSu número de ticket es: *#{$ticket}*.\n\nNuestros técnicos se pondrán en contacto pronto.";
                    } else {
                        $msg = "❌ Ocurrió un error al procesar su solicitud de reconexión. Por favor, intente más tarde.";
                    }
                    
                    // Volver al menú principal y limpiar el context_data (enviamos null o array vacio)
                    $sessionService->updateSession($telefono, $codigoSocio, 'MAIN_MENU', 0, []);
                    
                    // Respondemos con el mensaje y también le devolvemos el menú principal 
                    // Como n8n actualmente solo acepta un payload, enviaremos primero un texto 
                    // o podemos anexar el menú
                    $whatsappPayload = PlantillaSocio::menuPrincipal((string)$codigoSocio, '', false, $msg);

                } else {
                    $whatsappPayload = PlantillaSocio::mensajeTextoSimple("❌ Formato inválido. Por favor, escriba una descripción o glosa en texto.");
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
