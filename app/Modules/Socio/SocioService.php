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
     * Busca los datos de un socio por su código fijo.
     *
     * @param string $cod_socio
     * @return array|null
     */
    public function findByCodigo(string $cod_socio): ?array
    {
        $cod_socio = trim($cod_socio);
        if (empty($cod_socio)) {
            return null;
        }
        return $this->socioRepository->findByCodigo($cod_socio);
    }
}

