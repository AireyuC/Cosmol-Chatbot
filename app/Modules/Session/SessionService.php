<?php

declare(strict_types=1);

namespace App\Modules\Session;

use App\Data\Interfaces\SessionRepositoryInterface;

class SessionService
{
    private SessionRepositoryInterface $repository;
    private const MAX_ATTEMPTS = 200;
    private const INACTIVE_TIMEOUT_SECONDS = 60; // 1 minuto
    private const BLOCKED_TIMEOUT_SECONDS = 300; // 5 minutos

    public function __construct(SessionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function processSessionState(string $telefono, string $message): array
    {
        $session = $this->repository->getSession($telefono);
        
        if (!$session) {
            // No existe sesión, inicializamos
            $this->repository->saveSession($telefono, null, 'AWAITING_CODE', 0);
            return $this->buildResponse('AWAITING_CODE', null, 'Hola, bienvenido. Por favor envía tu código de socio válido.');
        }

        $estado = $session['estado_actual'];
        $codigoSocio = $session['codigo_socio'] ? (int)$session['codigo_socio'] : null;
        $intentos = (int)$session['intentos_fallidos'];
        $ultimaInteraccion = strtotime($session['ultima_interaccion']);
        $ahora = time();
        $tiempoTranscurrido = $ahora - $ultimaInteraccion;

        // Comprobaciones de Timeout
        if ($estado === 'BLOCKED') {
            if ($tiempoTranscurrido > self::BLOCKED_TIMEOUT_SECONDS) {
                // Desbloqueo automático tras 5 min
                $this->repository->resetSession($telefono);
                return $this->buildResponse('AWAITING_CODE', null, 'Tu bloqueo ha expirado. Por favor, envía tu código de socio.');
            }
            // Aún bloqueado, ignorar (dejamos "en visto")
            return $this->buildResponse('BLOCKED', null, null);
        }

        if ($tiempoTranscurrido > self::INACTIVE_TIMEOUT_SECONDS) {
            // Reset por inactividad
            $this->repository->resetSession($telefono);
            return $this->buildResponse('AWAITING_CODE', null, 'Sesión expirada por inactividad. Por favor, envía tu código de socio.');
        }

        // Devolver el estado actual tal cual está (sin cambiarlo aquí, eso lo decide n8n)
        return $this->buildResponse($estado, $codigoSocio, null, $intentos);
    }

    public function updateSession(string $telefono, ?int $codigoSocio, string $nuevoEstado, int $intentos): bool
    {
        // Validar si el intento llega a max attempts para bloquear
        if ($intentos >= self::MAX_ATTEMPTS) {
            $nuevoEstado = 'BLOCKED';
        }
        return $this->repository->saveSession($telefono, $codigoSocio, $nuevoEstado, $intentos);
    }
    
    public function resetSession(string $telefono): bool
    {
        return $this->repository->resetSession($telefono);
    }

    private function buildResponse(string $estado, ?int $codigo, ?string $mensaje, int $intentos = 0): array
    {
        $response = [
            'status' => 'success',
            'estado_actual' => $estado,
            'codigo_socio' => $codigo,
            'intentos' => $intentos
        ];

        if ($mensaje !== null) {
            $response['mensaje'] = $mensaje;
            $response['whatsapp_payload'] = [
                'type' => 'text',
                'text' => [
                    'body' => $mensaje
                ]
            ];
        }

        return $response;
    }
}
