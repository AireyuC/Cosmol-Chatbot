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
     * Obtiene y estandariza el historial de facturas pagadas.
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
            if (isset($historial['MES']) || isset($historial['MONTO'])) {
                $historial = [$historial];
            }

            $lista = [];
            foreach ($historial as $factura) {
                // Filtrar facturas impagas (ESTADO = 0 o FECHA es nula)
                if ((isset($factura['ESTADO']) && (string)$factura['ESTADO'] === '0') || empty($factura['FECHA'])) {
                    continue;
                }

                $lista[] = [
                    'periodo' => ($factura['MES'] ?? '') . '/' . ($factura['ANIO'] ?? ''),
                    'monto' => isset($factura['MONTO']) ? (float)$factura['MONTO'] : 0.0,
                    'fecha' => $factura['FECHA']
                ];
            }

            return [
                'status' => 'success',
                'codigo_socio' => $cod_socio,
                'cantidad' => count($lista),
                'facturas' => $lista
            ];
        }

        return [
            'status' => 'error',
            'message' => 'No se pudo obtener el historial'
        ];
    }
}
