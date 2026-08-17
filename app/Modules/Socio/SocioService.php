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

        // Delegamos la búsqueda al repositorio
        $socioData = $this->socioRepository->findByCodigo($cod_socio);

        if ($socioData) {
            // Limpiar campos devueltos por la API (quitar espacios en blanco sobrantes)
            if (isset($socioData['NOMBRE'])) {
                $socioData['NOMBRE'] = trim($socioData['NOMBRE']);
            }
            if (isset($socioData['DIRECCION'])) {
                $socioData['DIRECCION'] = trim($socioData['DIRECCION']);
            }

            return [
                'status' => 'success',
                'mensaje' => 'Socio encontrado exitosamente.',
                'datos_socio' => [
                    'nombre' => $socioData['NOMBRE'],
                    'direccion' => $socioData['DIRECCION'] ?? ''
                ]
            ];
        }

        return [
            'success' => false,
            'message' => 'No se encontró un asociado con el código proporcionado.',
            'data' => null
        ];
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

            $mensajeTexto = "";
            $cantidadFacturas = count($listaDeudas);
            $totalRedondeado = round($totalSuma, 2);
            $totalFormateado = number_format($totalRedondeado, 2, ',', '.');

            if ($cantidadFacturas > 0) {
                $mensajeTexto = "El Código Fijo ($cod_socio) tiene $cantidadFacturas facturas impagas, cuyo monto total es $totalFormateado Bs.\nEl detalle es el siguiente:\n\n";
                $contador = 1;
                foreach ($listaDeudas as $d) {
                    $montoF = number_format($d['monto'], 2, ',', '.');
                    $mensajeTexto .= "$contador. {$d['periodo']}, $montoF Bs. (Pendiente)\n";
                    $contador++;
                }
                $mensajeTexto = trim($mensajeTexto);
            } else {
                $mensajeTexto = "El Código Fijo ($cod_socio) no tiene deudas pendientes en este momento.";
            }

            return [
                'status' => 'success',
                'codigo_socio' => $cod_socio,
                'mensaje_texto' => $mensajeTexto,
                'facturas_pendientes' => $listaDeudas,
                'total_deuda' => $totalRedondeado
            ];
        } else {
            return [
                'status' => 'error',
                'mensaje_texto' => 'Ocurrió un error al obtener las deudas o no se encontró el socio.'
            ];
        }
    }
}
