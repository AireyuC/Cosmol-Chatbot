<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Logger;
use App\Data\Repositories\Postgres\SessionRepository;
use App\Data\Repositories\Api\SocioRepository;
use App\Data\Repositories\Api\ReconexionRepository;
use App\Data\Repositories\Api\ReclamoRepository;
use App\Data\Repositories\Postgres\ReportesBufferRepository;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Integrations\CosmolReportes\ClienteApiReportes;
use App\Integrations\WhatsApp\WhatsAppMediaService;
use App\Modules\Session\SessionService;
use App\Modules\Socio\SocioService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Reclamo\ReclamoService;
use App\Modules\Audit\ConsultaAuditService;
use App\Presentacion\Flows\AuthFlowHandler;
use App\Presentacion\Flows\MenuFlowHandler;
use App\Presentacion\Flows\ReconexionFlowHandler;
use App\Presentacion\Flows\ReclamoFlowHandler;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Front Controller para el Webhook de WhatsApp consumido por n8n.
 * Despacha los eventos hacia los Flow Handlers correspondientes según el estado de la sesión
 * e integra el servicio de auditoría de métricas hacia COSMOL-Reportes con buffer de contingencia.
 */
class WebhookWhatsAppEndpoint extends Controller
{
    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        $inputRaw = file_get_contents('php://input');
        $input = json_decode((string)$inputRaw, true);

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
            // 1. Inicialización de Repositorios y Servicios Base
            $sessionRepo = new SessionRepository();
            $sessionService = new SessionService($sessionRepo);

            $clienteApi = new ClienteApiCosmol();
            $socioRepo = new SocioRepository($clienteApi);
            $reconexionRepo = new ReconexionRepository($clienteApi);
            $reclamoRepo = new ReclamoRepository($clienteApi);

            $socioService = new SocioService($socioRepo);
            $reconexionService = new ReconexionService($reconexionRepo, $socioRepo);
            $reclamoService = new ReclamoService($reclamoRepo, $socioRepo);
            $mediaService = new WhatsAppMediaService();

            // 2. Inicialización de Auditoría de Reportes (con Buffer de contingencia)
            $clienteReportes = new ClienteApiReportes();
            $reportesBufferRepo = new ReportesBufferRepository();
            $auditService = new ConsultaAuditService($clienteReportes, $reportesBufferRepo);

            // 3. Consulta y evaluación del estado de la sesión
            $sessionResult = $sessionService->processSessionState((string)$telefono, '');
            $estadoActual = $sessionResult['estado_actual'];
            $intentos = (int)($sessionResult['intentos'] ?? 0);
            $codigoSocio = $sessionResult['codigo_socio'] ?? null;
            $contextData = $sessionResult['context_data'] ?? [];
            $sysMessage = $sessionResult['message'] ?? null;

            $whatsappPayload = null;

            // 4. Ruteo hacia los Flow Handlers
            if ($sysMessage) {
                $whatsappPayload = PlantillaSistema::textoSimple($sysMessage);
            } elseif ($estadoActual === 'BLOCKED') {
                // Silencio durante el bloqueo de 5 minutos
                $whatsappPayload = null;
            } elseif ($estadoActual === 'AWAITING_CODE') {
                $authFlow = new AuthFlowHandler($sessionService, $socioService, $auditService);
                $whatsappPayload = $authFlow->handle((string)$telefono, (string)$tipoMensaje, $contenido, $intentos);
            } elseif ($estadoActual === 'MAIN_MENU') {
                $menuFlow = new MenuFlowHandler($sessionService, $socioService, $reconexionService, $auditService);
                $whatsappPayload = $menuFlow->handle((string)$telefono, (string)$tipoMensaje, $contenido, $codigoSocio, $contextData);
            } elseif (strpos($estadoActual, 'AWAITING_RECONEXION_') === 0) {
                $reconexionFlow = new ReconexionFlowHandler($sessionService, $reconexionService, $mediaService, $auditService);
                $whatsappPayload = $reconexionFlow->handle($estadoActual, (string)$telefono, (string)$tipoMensaje, $contenido, $codigoSocio, $contextData);
            } elseif (strpos($estadoActual, 'AWAITING_RECLAMO_') === 0) {
                $reclamoFlow = new ReclamoFlowHandler($sessionService, $reclamoService, $mediaService, $auditService);
                $whatsappPayload = $reclamoFlow->handle($estadoActual, (string)$telefono, (string)$tipoMensaje, $contenido, $codigoSocio, $contextData);
            }

            // 5. Respuesta a n8n manteniendo el contrato estricto
            $this->json([
                'status' => 'success',
                'estado' => $estadoActual,
                'whatsapp_payload' => $whatsappPayload
            ], 200);

        } catch (\Throwable $e) {
            Logger::error('Error en WebhookWhatsAppEndpoint', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
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
