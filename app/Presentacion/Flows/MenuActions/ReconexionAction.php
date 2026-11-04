<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Session\SessionService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Facturacion\FacturacionService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaReconexion;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Validación de reglas de mora e inicio de reconexión.
 */
class ReconexionAction
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ReconexionService
     */
    private $reconexionService;

    /**
     * @var FacturacionService
     */
    private $facturacionService;

    /**
     * @var \App\Modules\Audit\ConsultaAuditService|null
     */
    // --- CONTROLADORES DE NEGOCIO Y TIEMPO (ADMINISTRACIÓN DESDE CÓDIGO) ---
    public const MAX_FACTURAS_MORA          = 1;  // Máximo de facturas en mora permitidas (si adeuda > 1 se rechaza)
    public const MAX_RECONEXIONES_POR_SOCIO = 1;  // Límite diario de solicitudes por código de socio
    public const COOLDOWN_SECONDS           = 60; // Segundos mínimos entre solicitudes consecutivas

    public function __construct(
        SessionService $sessionService,
        ReconexionService $reconexionService,
        FacturacionService $facturacionService,
        ?\App\Modules\Audit\ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->reconexionService = $reconexionService;
        $this->facturacionService = $facturacionService;
        $this->auditService = $auditService;
    }

    /**
     * Evalúa las políticas de negocio e inicia el flujo de reconexión si procede.
     *
     * @param string $telefono
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array Payload interactivo de WhatsApp
     */
    public function execute(string $telefono, $codigoSocio, string $codigoSocioStr, string $nombreSocio): array
    {
        if (!FeatureFlags::isEnabled('reconexion')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Solicitud de Reconexión"));
        }

        // 0. Validar límite diario por código de socio (máximo 1 reconexión por día)
        if ($this->auditService !== null && !$this->auditService->puedeSolicitarReconexionSocio((int)$codigoSocio)) {
            $mensaje = PlantillaReconexion::advertenciaLimiteReconexiones();
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 0.1 Control de Cooldown en segundos (anti-spam / doble toque accidental)
        if ($this->auditService !== null) {
            $segundosRestantes = $this->auditService->obtenerSegundosRestantesCooldownReconexion($telefono);
            if ($segundosRestantes > 0) {
                $mensaje = PlantillaReconexion::advertenciaCooldownReconexion($segundosRestantes);
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
            }
        }

        // 1. Validar si ya tiene reconexión pendiente en el sistema técnico externo
        if ($this->reconexionService->tieneReconexionPendiente($codigoSocioStr)) {
            $mensaje = PlantillaReconexion::reconexionPendiente();
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 2. Validar si supera el límite de mora (máximo 1 factura permitida; > 1 rechaza)
        $deudasResult = $this->facturacionService->obtenerDeudas($codigoSocioStr);
        $cantidadDeudas = ($deudasResult['status'] ?? '') === 'success' ? $deudasResult['cantidad_facturas'] : 0;

        if ($cantidadDeudas > self::MAX_FACTURAS_MORA) {
            $mensaje = PlantillaReconexion::deudaExcedida($cantidadDeudas);
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 3. Iniciar flujo de reconexión preservando nombre_socio en contextData
        $contextReconexion = ['nombre_socio' => $nombreSocio];
        $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GPS', 0, $contextReconexion);
        return PlantillaReconexion::solicitarGps();
    }
}
