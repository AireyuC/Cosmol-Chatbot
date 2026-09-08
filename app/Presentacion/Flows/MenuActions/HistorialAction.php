<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Facturacion\FacturacionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaFactura;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Consulta de historial de facturas pagadas.
 */
class HistorialAction
{
    /**
     * @var FacturacionService
     */
    private $facturacionService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    public function __construct(
        FacturacionService $facturacionService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->facturacionService = $facturacionService;
        $this->auditService = $auditService;
    }

    /**
     * Ejecuta la consulta del historial de pagos del socio.
     *
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array Payload interactivo de WhatsApp
     */
    public function execute($codigoSocio, string $codigoSocioStr, string $nombreSocio): array
    {
        if (!FeatureFlags::isEnabled('historial')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Historial de Facturas"));
        }

        if ($this->auditService !== null) {
            $this->auditService->registrarConsultaHistorial((int)$codigoSocio, $nombreSocio);
        }

        $historialResult = $this->facturacionService->obtenerHistorial($codigoSocioStr);
        if (isset($historialResult['status']) && $historialResult['status'] === 'success') {
            return PlantillaFactura::historialFacturas(
                $codigoSocioStr,
                $historialResult['facturas'],
                $historialResult['cantidad']
            );
        }

        return PlantillaSistema::textoSimple("Ocurrió un error al obtener el historial de facturas.");
    }
}
