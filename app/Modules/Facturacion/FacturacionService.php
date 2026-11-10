<?php

declare(strict_types=1);

namespace App\Modules\Facturacion;

use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Class FacturacionService
 * 
 * Contiene la lógica de negocio relacionada con facturación, deudas e historial de consumo/pagos.
 */
class FacturacionService
{
    /**
     * @var SocioRepositoryInterface
     */
    private $socioRepository;

    /**
     * FacturacionService constructor.
     *
     * @param SocioRepositoryInterface $socioRepository Inyección del repositorio de datos.
     */
    public function __construct(SocioRepositoryInterface $socioRepository)
    {
        $this->socioRepository = $socioRepository;
    }

    /**
     * Consulta las deudas/facturas pendientes de un socio.
     *
     * @param string $codigo_socio El código fijo del socio.
     * @return array Arreglo estandarizado con las deudas o un mensaje de error.
     */
    public function consultarDeuda(string $codigo_socio): array
    {
        $codigo_socio = trim($codigo_socio);

        if (empty($codigo_socio)) {
            return [
                'success' => false,
                'message' => 'El código de socio es requerido.',
                'data' => null
            ];
        }

        $deudaData = $this->socioRepository->findDeudasByCodigo($codigo_socio);

        if ($deudaData === null) {
            return [
                'status' => 'not_found',
                'mensaje' => 'No se encontró un asociado con el código proporcionado.',
                'datos_socio' => null
            ];
        }

        return [
            'success' => true,
            'message' => 'Deudas consultadas exitosamente.',
            'data' => $deudaData
        ];
    }

    /**
     * Obtiene el listado de deudas pendientes y calcula el monto total.
     *
     * @param string $cod_socio El código fijo.
     * @return array Arreglo estandarizado con el resultado y cálculos.
     */
    public function obtenerDeudas(string $cod_socio): array
    {
        $cod_socio = trim($cod_socio);

        if (empty($cod_socio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.'
            ];
        }

        $deudas = $this->socioRepository->findDeudasByCodigo($cod_socio);

        if ($deudas !== null) {
            // Si la API devuelve un solo registro, viene como array asociativo. Lo envolvemos en una lista.
            if (isset($deudas['NROFACTURA']) || isset($deudas['MONTOTOTAL'])) {
                $deudas = [$deudas];
            }

            $totalSuma = 0.0;
            $listaDeudas = [];

            foreach ($deudas as $deuda) {
                $monto = isset($deuda['MONTOTOTAL']) ? (float) $deuda['MONTOTOTAL'] : 0.0;
                $totalSuma += $monto;

                $razonSocial = isset($deuda['RAZONSOCIAL']) ? trim($deuda['RAZONSOCIAL']) : '';

                $listaDeudas[] = [
                    'factura' => $deuda['NROFACTURA'] ?? '',
                    'periodo' => ($deuda['NMES'] ?? '') . '-' . ($deuda['ANIO'] ?? ''),
                    'monto' => $monto,
                    'razon_social' => $razonSocial
                ];
            }

            $cantidadFacturas = count($listaDeudas);
            $totalRedondeado = round($totalSuma, 2);

            return [
                'status' => 'success',
                'codigo_socio' => $cod_socio,
                'cantidad_facturas' => $cantidadFacturas,
                'facturas_pendientes' => $listaDeudas,
                'total_deuda' => $totalRedondeado
            ];
        }

        return [
            'status' => 'error'
        ];
    }

    /**
     * Obtiene y estandariza el historial de facturas (hasta 12 periodos).
     * Incluye facturas pagadas, impagas y vencidas según la regla de negocio:
     * - Las 2 primeras facturas impagas se etiquetan como "Impaga".
     * - A partir de la 3ª factura impaga, se etiquetan como "Vencida".
     * - Las facturas canceladas se etiquetan como "Pagada".
     *
     * @param string $cod_socio El código fijo.
     * @return array Arreglo estandarizado con el resultado.
     */
    public function obtenerHistorial(string $cod_socio): array
    {
        $cod_socio = trim($cod_socio);

        if (empty($cod_socio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.'
            ];
        }

        $historial = $this->socioRepository->findHistorialByCodigo($cod_socio);

        if ($historial !== null) {
            // Si la API devuelve un solo registro, viene como array asociativo. Lo envolvemos en una lista.
            if (isset($historial['MES']) || isset($historial['MONTO']) || isset($historial['periodo'])) {
                $historial = [$historial];
            }

            $lista = [];
            $contadorImpagas = 0;

            foreach ($historial as $factura) {
                // Determinar si está pagada o impaga
                $esPagada = false;
                $estadoRaw = isset($factura['ESTADO']) ? (string)$factura['ESTADO'] : (isset($factura['estado']) ? (string)$factura['estado'] : '');
                $fechaPago = $factura['FECHA'] ?? ($factura['fecha'] ?? null);

                if (($estadoRaw === '1' || strtolower($estadoRaw) === 'pagada') || (!empty($fechaPago) && $estadoRaw !== '0')) {
                    $esPagada = true;
                }

                if ($esPagada) {
                    $estadoLabel = 'Pagada';
                } else {
                    $contadorImpagas++;
                    // Regla de negocio: las dos primeras impagas, las demás vencidas
                    $estadoLabel = ($contadorImpagas <= 2) ? 'Impaga' : 'Vencida';
                }

                // Construcción limpia del periodo (ej. 8/2026)
                if (isset($factura['periodo']) && !empty($factura['periodo'])) {
                    $periodo = (string)$factura['periodo'];
                } else {
                    $mes = (string)($factura['MES'] ?? ($factura['NMES'] ?? ''));
                    $anio = (string)($factura['ANIO'] ?? '');
                    $periodo = (!empty($mes) && !empty($anio)) ? "{$mes}/{$anio}" : ($mes ?: $anio);
                }

                $monto = isset($factura['MONTO']) ? (float)$factura['MONTO'] : (float)($factura['monto'] ?? 0.0);

                $lista[] = [
                    'periodo' => $periodo,
                    'monto' => $monto,
                    'estado' => $estadoLabel,
                    'fecha' => $fechaPago
                ];
            }

            return [
                'status' => 'success',
                'codigo_socio' => $cod_socio,
                'cantidad' => count($lista),
                'total_impagas' => $contadorImpagas,
                'facturas' => $lista
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No se pudo obtener el historial'
        ];
    }
}
