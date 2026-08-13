<?php

declare(strict_types=1);

namespace App\Data\Repositories\Api;

use App\Data\Interfaces\SocioRepositoryInterface;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use Exception;

class RepositorioSocioApi implements SocioRepositoryInterface
{
    private $clienteApi;

    public function __construct(ClienteApiCosmol $clienteApi)
    {
        $this->clienteApi = $clienteApi;
    }

    /**
     * Busca un socio por su código fijo utilizando la API externa.
     *
     * @param string $cod_socio El código fijo del socio a buscar.
     * @return array|null Retorna un array asociativo con los datos del socio, o null si no.
     */
    public function findByCodigo(string $cod_socio): ?array
    {
        try {
            $respuesta = $this->clienteApi->obtenerSocio($cod_socio);

            // Verificamos si la respuesta indica éxito
            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return $respuesta['datos'] ?? null;
            }

            return null;
        } catch (Exception $e) {
            // Loguear el error internamente si es necesario
            error_log("RepositorioSocioApi::findByCodigo error - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca las deudas pendientes de un socio por su código fijo utilizando la API externa.
     *
     * @param string $cod_socio El código fijo del socio.
     * @return array|null Retorna un array con el listado de deudas, o null si no hay datos.
     */
    public function findDeudasByCodigo(string $cod_socio): ?array
    {
        try {
            $respuesta = $this->clienteApi->obtenerDeudasSocio($cod_socio);

            // Verificamos si la respuesta indica éxito
            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return $respuesta['datos'] ?? [];
            }

            return null;
        } catch (Exception $e) {
            // Loguear el error internamente si es necesario
            error_log("RepositorioSocioApi::findDeudasByCodigo error - " . $e->getMessage());
            return null;
        }
    }
}
