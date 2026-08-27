<?php

declare(strict_types=1);

namespace App\Data\Repositories\Api;

use App\Data\Interfaces\SocioRepositoryInterface;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use Exception;

class SocioRepository implements SocioRepositoryInterface
{
    private $clienteApi;

    public function __construct(ClienteApiCosmol $clienteApi)
    {
        $this->clienteApi = $clienteApi;
    }

    private function trimDatos(array $datos): array
    {
        $limpios = [];
        foreach ($datos as $key => $value) {
            if (is_array($value)) {
                $limpios[$key] = $this->trimDatos($value);
            } elseif (is_string($value)) {
                $limpios[$key] = trim($value);
            } else {
                $limpios[$key] = $value;
            }
        }
        return $limpios;
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

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) ? $this->trimDatos($respuesta['datos']) : null;
            }

            return null;
        } catch (Exception $e) {
            \App\Core\Logger::error("RepositorioSocioApi::findByCodigo error", ['exception' => $e->getMessage()]);
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

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) ? $this->trimDatos($respuesta['datos']) : [];
            }

            return null;
        } catch (Exception $e) {
            \App\Core\Logger::error("RepositorioSocioApi::findDeudasByCodigo error", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Busca el historial de facturas pagadas de un socio por su código fijo utilizando la API externa.
     *
     * @param string $cod_socio El código fijo del socio.
     * @return array|null Retorna un array con el historial de facturas, o null si no hay datos.
     */
    public function findHistorialByCodigo(string $cod_socio): ?array
    {
        try {
            $respuesta = $this->clienteApi->obtenerHistorialFacturas($cod_socio);

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) ? $this->trimDatos($respuesta['datos']) : [];
            }

            return null;
        } catch (Exception $e) {
            \App\Core\Logger::error("RepositorioSocioApi::findHistorialByCodigo error", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Registra una solicitud de reconexión de un socio.
     *
     * @param string $cod_socio El código fijo del socio.
     * @param array $payload Los datos del formulario de reconexión.
     * @return array|null Retorna la respuesta de la API o null si falla.
     */
    public function registrarReconexion(string $cod_socio, array $payload): ?array
    {
        try {
            $respuesta = $this->clienteApi->registrarReconexion($cod_socio, $payload);

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) ? $this->trimDatos($respuesta['datos']) : [];
            }

            return null;
        } catch (Exception $e) {
            \App\Core\Logger::error("RepositorioSocioApi::registrarReconexion error", ['exception' => $e->getMessage()]);
            return null;
        }
    }
}
