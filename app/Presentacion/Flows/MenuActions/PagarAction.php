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
 * Acción del menú principal: Consulta y pago de deudas pendientes.
 */
class PagarAction
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
     * Ejecuta la consulta de deudas del socio.
     *
     * @param string $accion ID del botón (ej. MENU_PAGAR_12345)
     * @param string $codigoSocioStr Código fijo del socio
     * @param string $nombreSocio Nombre del socio
     * @return array Payload interactivo de WhatsApp
     */
    public function execute(string $accion, string $codigoSocioStr, string $nombreSocio): array
    {
        if (!FeatureFlags::isEnabled('deuda')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Consulta de Deuda"));
        }

        $partes = explode('_', $accion);
        $cod = $partes[2] ?? $codigoSocioStr;

        if ($this->auditService !== null) {
            $this->auditService->registrarConsultaDeuda((int)$cod, $nombreSocio);
        }

        $deudasResult = $this->facturacionService->obtenerDeudas($cod);
        if (isset($deudasResult['status']) && $deudasResult['status'] === 'success') {
            return PlantillaFactura::listaDeudas(
                $cod,
                $deudasResult['cantidad_facturas'],
                $deudasResult['total_deuda'],
                $deudasResult['facturas_pendientes']
            );
        }

        return PlantillaSistema::textoSimple("Ocurrió un error al obtener las deudas.");
    }
}
