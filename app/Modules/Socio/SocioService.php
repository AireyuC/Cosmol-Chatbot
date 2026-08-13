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
     * @param string $codigo_socio El código fijo a validar.
     * @return array Arreglo estandarizado con el resultado de la operación.
     */
    public function validarSocio(string $codigo_socio): array
    {
        // Limpiamos el código ingresado (ej. quitamos espacios)
        $codigo_socio = trim($codigo_socio);

        if (empty($codigo_socio)) {
            return [
                'success' => false,
                'message' => 'El código de socio es requerido.',
                'data' => null
            ];
        }

        // Delegamos la búsqueda al repositorio
        $socioData = $this->socioRepository->findByCodigo($codigo_socio);

        if ($socioData) {
            return [
                'success' => true,
                'message' => 'Socio encontrado exitosamente.',
                'data' => $socioData
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

        $deudaData = $this->socioRepository->getDeuda($codigo_socio);

        if ($deudaData === null) {
            return [
                'success' => false,
                'message' => 'No se pudo obtener información de deudas para este socio.',
                'data' => null
            ];
        }

        return [
            'success' => true,
            'message' => 'Deudas consultadas exitosamente.',
            'data' => $deudaData
        ];
    }
}
