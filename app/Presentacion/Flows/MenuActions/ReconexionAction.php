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

    public function __construct(
        SessionService $sessionService,
        ReconexionService $reconexionService,
        FacturacionService $facturacionService
    ) {
        $this->sessionService = $sessionService;
        $this->reconexionService = $reconexionService;
        $this->facturacionService = $facturacionService;
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

        // 1. Validar si ya tiene reconexión pendiente
        if ($this->reconexionService->tieneReconexionPendiente($codigoSocioStr)) {
            $mensaje = PlantillaReconexion::reconexionPendiente();
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 2. Validar si supera el límite de mora (más de 2 facturas)
        $deudasResult = $this->facturacionService->obtenerDeudas($codigoSocioStr);
        $cantidadDeudas = ($deudasResult['status'] ?? '') === 'success' ? $deudasResult['cantidad_facturas'] : 0;

        if ($cantidadDeudas > 2) {
            $mensaje = PlantillaReconexion::deudaExcedida($cantidadDeudas);
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 3. Iniciar flujo de reconexión preservando nombre_socio en contextData
        $contextReconexion = ['nombre_socio' => $nombreSocio];
        $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GPS', 0, $contextReconexion);
        return PlantillaReconexion::solicitarGps();
    }
}
