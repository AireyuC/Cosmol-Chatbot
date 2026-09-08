<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Modules\Session\SessionService;
use App\Modules\Audit\ConsultaAuditService;

/**
 * Clase base abstracta para todos los Flow Handlers de la máquina de estados.
 * Centraliza las dependencias transversales (sesión y auditoría) y métodos de transición.
 */
abstract class BaseFlowHandler
{
    /**
     * @var SessionService
     * @var ConsultaAuditService|null
     */
    protected $sessionService;
    protected $auditService;

    public function __construct(
        SessionService $sessionService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->auditService = $auditService;
    }

    /**
     * Actualiza el estado de la sesión del usuario.
     *
     * @param string $telefono
     * @param int|null $codigoSocio
     * @param string $nextState
     * @param int $intentos
     * @param array $contextData
     */
    protected function transitionState(
        string $telefono,
        ?int $codigoSocio,
        string $nextState,
        int $intentos = 0,
        array $contextData = []
    ): void {
        $this->sessionService->updateSession($telefono, $codigoSocio, $nextState, $intentos, $contextData);
    }

    /**
     * Reinicia completamente la sesión del usuario (vuelve a AWAITING_CODE).
     *
     * @param string $telefono
     */
    protected function resetSession(string $telefono): void
    {
        $this->sessionService->resetSession($telefono);
    }

    /**
     * Ejecuta una operación de auditoría si el servicio está disponible.
     *
     * @param callable $callback Función receptora de ConsultaAuditService
     */
    protected function logAudit(callable $callback): void
    {
        if ($this->auditService !== null) {
            $callback($this->auditService);
        }
    }
}
