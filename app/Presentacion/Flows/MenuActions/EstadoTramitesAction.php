<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Reclamo\ReclamoService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Consulta combinada de estado de reclamos técnicos y solicitudes de reconexión.
 */
class EstadoTramitesAction
{
    /**
     * @var ReclamoService
     */
    private $reclamoService;

    /**
     * @var ReconexionService
     */
    private $reconexionService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    public function __construct(
        ReclamoService $reclamoService,
        ReconexionService $reconexionService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->reclamoService = $reclamoService;
        $this->reconexionService = $reconexionService;
        $this->auditService = $auditService;
    }

    /**
     * Ejecuta la consulta de estados de reclamos y reconexiones.
     *
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array Payload interactivo de WhatsApp
     */
    public function execute($codigoSocio, string $codigoSocioStr, string $nombreSocio): array
    {
        if (!FeatureFlags::isEnabled('estado_tramites')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Estado de Solicitudes"));
        }

        if ($this->auditService !== null) {
            $this->auditService->registrarConsultaEstado((int)$codigoSocio, $nombreSocio);
        }

        $reclamos = $this->reclamoService->obtenerHistorialReclamos($codigoSocioStr);
        $reconexiones = $this->reconexionService->obtenerHistorialReconexiones($codigoSocioStr);

        return PlantillaSocio::estadoSolicitudes($codigoSocioStr, $nombreSocio, $reclamos, $reconexiones);
    }
}
