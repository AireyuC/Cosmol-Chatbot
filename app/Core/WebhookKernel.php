<?php

declare(strict_types=1);

namespace App\Core;

use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;
use Throwable;

/**
 * Orquestador Central (Kernel) del Webhook de WhatsApp.
 * Gestiona el ciclo de vida completo de la petición entrante desde n8n:
 * validación HTTP, guardia de mantenimiento, evaluación de sesión,
 * despacho por máquina de estados y respuesta JSON conforme a los contratos de Meta.
 */
class WebhookKernel extends Controller
{
    /**
     * Procesa la petición entrante del webhook.
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
            return;
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
            return;
        }

        try {
            $container = AppContainer::getInstance();

            // 1. Guardia de Mantenimiento Global (con antispam desacoplado)
            $maintenanceResponse = $container->getMaintenanceGuard()->check((string)$telefono);
            if ($maintenanceResponse !== null) {
                $this->json($maintenanceResponse, 200);
                return;
            }

            // 2. Consulta y evaluación del estado de la sesión
            $sessionService = $container->getSessionService();
            $sessionResult = $sessionService->processSessionState((string)$telefono, '');

            $estadoActual = $sessionResult['estado_actual'];
            $intentos = (int)($sessionResult['intentos'] ?? 0);
            $codigoSocio = $sessionResult['codigo_socio'] ?? null;
            $contextData = $sessionResult['context_data'] ?? [];
            $sysMessage = $sessionResult['mensaje'] ?? ($sessionResult['message'] ?? null);

            // 3. Ruteo hacia los Flow Handlers de la máquina de estados
            $router = $container->getFlowRouter();
            $whatsappPayload = $router->dispatch(
                $estadoActual,
                (string)$telefono,
                (string)$tipoMensaje,
                $contenido,
                $sysMessage,
                $intentos,
                $codigoSocio,
                $contextData
            );

            // 4. Respuesta a n8n manteniendo el contrato estricto
            $this->json([
                'status' => 'success',
                'estado' => $estadoActual,
                'whatsapp_payload' => $whatsappPayload
            ], 200);

        } catch (Throwable $e) {
            Logger::error('Error en WebhookKernel', [
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
