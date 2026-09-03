<?php

declare(strict_types=1);

namespace App\Data\Repositories\Api;

use App\Data\Interfaces\ReconexionRepositoryInterface;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Core\Logger;
use Exception;

class ReconexionRepository implements ReconexionRepositoryInterface
{
    /**
     * @var ClienteApiCosmol
     */
    private $clienteApi;

    public function __construct(ClienteApiCosmol $clienteApi)
    {
        $this->clienteApi = $clienteApi;
    }

    private function trimDatos(array $datos): array
    {
        $limpio = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $limpio[$clave] = trim($valor);
            } elseif (is_array($valor)) {
                $limpio[$clave] = $this->trimDatos($valor);
            } else {
                $limpio[$clave] = $valor;
            }
        }
        return $limpio;
    }

    /**
     * Registra una solicitud de reconexión de un socio ante la API de Informix.
     *
     * @param string $codigoSocio
     * @param array $payload
     * @return array|null
     */
    public function registrarReconexion(string $codigoSocio, array $payload): ?array
    {
        try {
            $respuesta = $this->clienteApi->registrarReconexion($codigoSocio, $payload);

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) 
                    ? $this->trimDatos($respuesta['datos']) 
                    : [];
            }

            return null;
        } catch (Exception $e) {
            Logger::error("ReconexionRepository::registrarReconexion error", [
                'codigo_socio' => $codigoSocio,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtiene el historial de solicitudes de reconexión de un socio.
     *
     * @param string $codigoSocio
     * @return array|null
     */
    public function obtenerHistorialReconexiones(string $codigoSocio): ?array
    {
        try {
            $respuesta = $this->clienteApi->obtenerHistorialReconexiones($codigoSocio);

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                if (isset($respuesta['datos']) && is_array($respuesta['datos'])) {
                    if (isset($respuesta['datos'][0])) {
                        return array_map([$this, 'trimDatos'], $respuesta['datos']);
                    } else {
                        return $this->trimDatos($respuesta['datos']);
                    }
                }
                return [];
            }
            return null;
        } catch (Exception $e) {
            Logger::error("ReconexionRepository::obtenerHistorialReconexiones error", [
                'codigo_socio' => $codigoSocio,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
}
