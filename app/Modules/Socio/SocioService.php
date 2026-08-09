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
            return [
                'status' => 'success',
                'message' => 'Socio encontrado exitosamente.',
                'data' => $socioData
            ];
        } else {
            return [
                'status' => 'not_found',
                'message' => 'No se encontró un asociado con el código proporcionado.',
                'data' => null
            ];
        }
    }
}
