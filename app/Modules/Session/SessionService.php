<?php

declare(strict_types=1);

namespace App\Modules\Session;

use App\Data\Interfaces\SessionRepositoryInterface;
use App\Integrations\WhatsApp\WhatsAppSenderService;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;
use App\Core\Logger;

class SessionService
{
    /**
     * @var SessionRepositoryInterface
     */
    private $repository;

    /**
     * @var WhatsAppSenderService|null
     */
    private $whatsAppSender;

    private const MAX_ATTEMPTS = 3; // Máximo de intentos fallidos antes de bloquear al usuario
    private const INACTIVE_TIMEOUT_SECONDS = 600; // 10 minutos (600s) de inactividad antes de cerrar sesión
    private const BLOCKED_TIMEOUT_SECONDS = 300; // 5 minutos de bloqueo por intentos fallidos

    public function __construct(
        SessionRepositoryInterface $repository,
        ?WhatsAppSenderService $whatsAppSender = null
    ) {
        $this->repository = $repository;
        $this->whatsAppSender = $whatsAppSender;
    }

    public function processSessionState(string $telefono, string $message): array
    {
        $session = $this->repository->getSession($telefono);
        
        if (!$session) {
            $this->repository->saveSession($telefono, null, 'AWAITING_CODE', 0);
            return $this->buildResponse('AWAITING_CODE', null, null, 0);
        }

        $estado = $session['estado_actual'];
        $codigoSocio = $session['codigo_socio'] ? (int)$session['codigo_socio'] : null;
        $intentos = (int)$session['intentos_fallidos'];
        $contextData = isset($session['context_data']) ? json_decode($session['context_data'], true) : null;
        
        $ultimaInteraccion = strtotime($session['ultima_interaccion']);
        $ahora = time();
        $tiempoTranscurrido = $ahora - $ultimaInteraccion;

        if ($estado === 'BLOCKED') {
            if ($tiempoTranscurrido > self::BLOCKED_TIMEOUT_SECONDS) {
                $this->repository->resetSession($telefono);
                return $this->buildResponse('AWAITING_CODE', null, 'Tu bloqueo ha expirado. Por favor, envía tu código de socio.', 0);
            }
            // Aún bloqueado, ignorar (dejamos "en visto")
            return $this->buildResponse('BLOCKED', null, null, $intentos);
        }

        // Fail-safe reactivo de inactividad: si pasaron más de 10 min, cerramos sesión de inmediato
        if ($tiempoTranscurrido > self::INACTIVE_TIMEOUT_SECONDS && $estado !== 'AWAITING_CODE') {
            $this->repository->resetSession($telefono);
            return $this->buildResponse('AWAITING_CODE', null, null, 0);
        }

        // Renovar la última interacción con cada acción/mensaje recibido para reiniciar el cronómetro a cero
        if ($estado !== 'AWAITING_CODE') {
            $this->repository->touchSession($telefono);
        }

        // Devolver el estado actual tal cual está
        return $this->buildResponse($estado, $codigoSocio, null, $intentos, $contextData);
    }

    public function updateSession(string $telefono, ?int $codigoSocio, string $nuevoEstado, int $intentos, ?array $contextData = null): bool
    {
        if ($intentos >= self::MAX_ATTEMPTS) {
            $nuevoEstado = 'BLOCKED';
        }
        
        $contextString = $contextData !== null ? json_encode($contextData) : null;
        return $this->repository->saveSession($telefono, $codigoSocio, $nuevoEstado, $intentos, $contextString);
    }
    
    public function resetSession(string $telefono): bool
    {
        return $this->repository->resetSession($telefono);
    }

    /**
     * Identifica las sesiones que superaron los 10 minutos de inactividad,
     * envía la notificación proactiva de expiración por WhatsApp y las resetea en base de datos.
     *
     * @param int $segundosInactividad Tiempo en segundos (por defecto 600 = 10 min)
     * @return array Resumen y lista de sesiones expiradas
     */
    public function expirarSesionesInactivas(int $segundosInactividad = self::INACTIVE_TIMEOUT_SECONDS): array
    {
        $sesiones = $this->repository->getExpiredSessions($segundosInactividad);
        $expiradas = [];
        $notificadasCount = 0;

        $plantillaExpiracion = PlantillaSistema::sesionExpirada();

        foreach ($sesiones as $sesion) {
            $telefono = (string)$sesion['telefono_whatsapp'];

            // 1. Despacho proactivo a Meta WhatsApp si el sender está configurado
            $enviado = false;
            if ($this->whatsAppSender !== null) {
                $enviado = $this->whatsAppSender->enviarMensaje($telefono, $plantillaExpiracion);
                if ($enviado) {
                    $notificadasCount++;
                }
            }

            // 2. Transición atómica en base de datos a AWAITING_CODE con intentos en 0
            $this->repository->resetSession($telefono);

            $expiradas[] = [
                'telefono' => $telefono,
                'codigo_socio' => $sesion['codigo_socio'] ?? null,
                'estado_anterior' => $sesion['estado_actual'],
                'notificado' => $enviado,
                'whatsapp_payload' => $plantillaExpiracion
            ];
        }

        if (count($expiradas) > 0) {
            Logger::info("SessionService::expirarSesionesInactivas", [
                'total_expiradas' => count($expiradas),
                'notificadas' => $notificadasCount
            ]);
        }

        return [
            'total' => count($expiradas),
            'notificadas' => $notificadasCount,
            'expiradas' => $expiradas
        ];
    }

    private function buildResponse(string $estado, ?int $codigo, ?string $mensaje, int $intentos = 0, ?array $contextData = null): array
    {
        $response = [
            'status' => 'success',
            'estado_actual' => $estado,
            'codigo_socio' => $codigo,
            'intentos' => $intentos,
            'context_data' => $contextData
        ];

        if ($mensaje !== null) {
            $response['mensaje'] = $mensaje;
        }

        return $response;
    }
}
