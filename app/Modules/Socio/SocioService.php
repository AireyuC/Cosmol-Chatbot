<?php

declare(strict_types=1);

namespace App\Modules\Socio;

use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Class SocioService
 * 
 * Contiene la lógica de negocio relacionada con los Socios.
 */
class SocioService
{
    /**
     * @var SocioRepositoryInterface
     */
    private $socioRepository;

    /**
     * SocioService constructor.
     *
     * @param SocioRepositoryInterface $socioRepository Inyección de la dependencia del repositorio.
     */
    public function __construct(SocioRepositoryInterface $socioRepository)
    {
        $this->socioRepository = $socioRepository;
    }

    /**
     * Valida la existencia de un socio por su código y devuelve sus datos formateados.
     *
     * @param string $cod_socio El código fijo a validar.
     * @return array Arreglo estandarizado con el resultado de la operación.
     */
    public function validarSocio(string $cod_socio): array
    {
        // Limpiamos el código ingresado (ej. quitamos espacios)
        $cod_socio = trim($cod_socio);

        if (empty($cod_socio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.'
            ];
        }

        if (!is_numeric($cod_socio)) {
            return [
                'status' => 'not_found',
                'mensaje' => 'El valor ingresado no es un número.',
                'datos_socio' => null
            ];
        }

        // Delegamos la búsqueda al repositorio
        $socioData = $this->socioRepository->findByCodigo($cod_socio);

        if ($socioData) {
            // Limpiar campos devueltos por la API
            if (isset($socioData['NOMBRE'])) {
                $socioData['NOMBRE'] = trim($socioData['NOMBRE']);
            }
            if (isset($socioData['DIRECCION'])) {
                $socioData['DIRECCION'] = trim($socioData['DIRECCION']);
            }

            $nombreSocio = $socioData['NOMBRE'];

            return [
                'status' => 'success',
                'mensaje' => 'Socio encontrado exitosamente.',
                'datos_socio' => [
                    'nombre' => $nombreSocio,
                    'direccion' => $socioData['DIRECCION'] ?? ''
                ]
            ];
        } else {
            return [
                'status' => 'not_found',
                'mensaje' => 'No se encontró un asociado con el código proporcionado.',
                'datos_socio' => null
            ];
        }
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
            $totalSuma = 0.0;
            $listaDeudas = [];

            foreach ($deudas as $deuda) {
                $monto = isset($deuda['MONTOTOTAL']) ? (float) $deuda['MONTOTOTAL'] : 0.0;
                $totalSuma += $monto;

                // Limpiar textos de la factura si es necesario
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
        } else {
            return [
                'status' => 'error'
            ];
        }
    }
}
