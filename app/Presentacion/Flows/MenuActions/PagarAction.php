<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Facturacion\FacturacionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaFactura;
use App\Presentacion\PlantillasWhatsApp\PlantillaPago;
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
    public function execute(string $accion, string $codigoSocioStr, string $nombreSocio, ?string $telefono = null): array
    {
        if (!FeatureFlags::isEnabled('deuda')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Consulta de Deuda"));
        }

        $partes = explode('_', $accion);
        $cod = $partes[2] ?? $codigoSocioStr;

        if ($this->auditService !== null) {
            $this->auditService->registrarConsultaDeuda((int)$cod, $nombreSocio, $telefono);
        }

        $deudasResult = $this->facturacionService->obtenerDeudas($cod);
        if (isset($deudasResult['status']) && $deudasResult['status'] === 'success') {
            $cantidadFacturas = (int)($deudasResult['cantidad_facturas'] ?? 0);
            $totalDeuda = (float)($deudasResult['total_deuda'] ?? 0.0);
            $facturas = $deudasResult['facturas_pendientes'] ?? [];

            // Si el socio no tiene deudas pendientes
            if ($cantidadFacturas === 0) {
                return PlantillaFactura::listaDeudas($cod, 0, 0.0, []);
            }

            // Si el módulo de Pago por QR y selección acumulativa está activo por Feature Flag
            if (FeatureFlags::isPagoQrEnabled()) {
                return PlantillaPago::selectorFacturas($cod, $facturas, $totalDeuda);
            }

            // Fallback: Flujo clásico con pasarelas web externas
            return PlantillaFactura::listaDeudas($cod, $cantidadFacturas, $totalDeuda, $facturas);
        }

        return PlantillaSistema::textoSimple("Ocurrió un error al obtener las deudas.");
    }

    /**
     * Procesa la selección de facturas acumuladas (1 factura, 2 facturas, todas)
     * y muestra el código QR de cobro correspondiente.
     *
     * @param string $accion Formato: PAGO_SELEC_{cantidad}_{codSocio}
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array
     */
    public function handleSeleccion(string $accion, string $codigoSocioStr, string $nombreSocio): array
    {
        $partes = explode('_', $accion);
        $cantSeleccionada = (int)($partes[2] ?? 1);
        $cod = $partes[3] ?? $codigoSocioStr;

        $deudasResult = $this->facturacionService->obtenerDeudas($cod);
        $facturas = $deudasResult['facturas_pendientes'] ?? [];

        if (empty($facturas)) {
            return PlantillaSocio::menuPrincipal($cod, $nombreSocio, false, "No se encontraron facturas pendientes para este asociado.");
        }

        $montoAcumulado = 0.0;
        $periodos = [];
        $limite = min($cantSeleccionada, count($facturas));

        for ($i = 0; $i < $limite; $i++) {
            $montoAcumulado += (float)($facturas[$i]['monto'] ?? 0.0);
            $periodos[] = $facturas[$i]['periodo'] ?? '';
        }

        return PlantillaPago::mostrarQrPago(
            $cod,
            $limite,
            $montoAcumulado,
            implode(', ', $periodos)
        );
    }

    /**
     * Simula la confirmación de pago exitoso (Alpha/Mock) y despliega el recibo digital.
     *
     * @param string $accion Formato: PAGO_CONFIRMAR_{cantidad}_{codSocio}
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array
     */
    public function handleConfirmacion(string $accion, string $codigoSocioStr, string $nombreSocio): array
    {
        $partes = explode('_', $accion);
        $cantSeleccionada = (int)($partes[2] ?? 1);
        $cod = $partes[3] ?? $codigoSocioStr;

        $deudasResult = $this->facturacionService->obtenerDeudas($cod);
        $facturas = $deudasResult['facturas_pendientes'] ?? [];

        $montoAcumulado = 0.0;
        $periodos = [];
        $limite = min($cantSeleccionada, count($facturas));

        for ($i = 0; $i < $limite; $i++) {
            $montoAcumulado += (float)($facturas[$i]['monto'] ?? 0.0);
            $periodos[] = $facturas[$i]['periodo'] ?? '';
        }

        if ($montoAcumulado <= 0.0) {
            $montoAcumulado = 150.00; // Valor fallback demostrativo para mock
            $periodos = ['Periodo Actual'];
        }

        return PlantillaPago::pagoExitoso(
            $cod,
            $nombreSocio,
            $limite,
            $montoAcumulado,
            implode(', ', $periodos)
        );
    }
}
