<?php

declare(strict_types=1);

namespace App\Modules\Session;

use App\Data\Interfaces\SessionRepositoryInterface;

class SessionService
{
    /**
     * @var SessionRepositoryInterface
     */
    private $repository;
    private const MAX_ATTEMPTS = 200; // Maximo de intentos fallidos antes de bloquear al usuario
    private const INACTIVE_TIMEOUT_SECONDS = 60; // 60 segundos de inactividad antes de cerrar sesion
    private const BLOCKED_TIMEOUT_SECONDS = 300; // 5 minutos de bloqueo por intentos fallidos

    public function __construct(SessionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function processSessionState(string $telefono, string $message): array
    {
        $session = $this->repository->getSession($telefono);
        
        if (!$session) {
            $this->repository->saveSession($telefono, null, 'AWAITING_CODE', 0);
            return $this->buildResponse('AWAITING_CODE', null, 'Hola, bienvenido. Por favor envía tu código de socio válido.');
        }

        $estado = $session['estado_actual'];
        $codigoSocio = $session['codigo_socio'] ? (int)$session['codigo_socio'] : null;
        $intentos = (int)$session['intentos_fallidos'];
        $ultimaInteraccion = strtotime($session['ultima_interaccion']);
        $ahora = time();
        $tiempoTranscurrido = $ahora - $ultimaInteraccion;

        if ($estado === 'BLOCKED') {
            if ($tiempoTranscurrido > self::BLOCKED_TIMEOUT_SECONDS) {

                $this->repository->resetSession($telefono);
                return $this->buildResponse('AWAITING_CODE', null, 'Tu bloqueo ha expirado. Por favor, envía tu código de socio.');
            }
            // Aún bloqueado, ignorar (dejamos "en visto")
            return $this->buildResponse('BLOCKED', null, null);
        }

        if ($tiempoTranscurrido > self::INACTIVE_TIMEOUT_SECONDS) {
            
            $this->repository->resetSession($telefono);
            return $this->buildResponse('AWAITING_CODE', null, 'Sesión expirada por inactividad. Por favor, envía tu código de socio.');
        }

        // Devolver el estado actual tal cual está (sin cambiarlo aquí, eso lo decide n8n).
        return $this->buildResponse($estado, $codigoSocio, null, $intentos);
    }

    public function updateSession(string $telefono, ?int $codigoSocio, string $nuevoEstado, int $intentos): bool
    {

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
        }

        return $response;
    }
}
