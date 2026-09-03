<?php

declare(strict_types=1);

namespace App\Data\Repositories\Api;

use App\Data\Interfaces\ReclamoRepositoryInterface;
use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Core\Logger;
use Exception;

class ReclamoRepository implements ReclamoRepositoryInterface
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
     * Registra un reclamo de un socio ante la API de Informix.
     *
     * @param string $codigoSocio
     * @param array $payload
     * @return array|null
     */
    public function registrarReclamo(string $codigoSocio, array $payload): ?array
    {
        try {
            $respuesta = $this->clienteApi->registrarReclamo($codigoSocio, $payload);

            if (isset($respuesta['estado']) && $respuesta['estado'] === 'exito') {
                return (isset($respuesta['datos']) && is_array($respuesta['datos'])) 
                    ? $this->trimDatos($respuesta['datos']) 
                    : [];
            }

            return null;
        } catch (Exception $e) {
            Logger::error("ReclamoRepository::registrarReclamo error", [
                'codigo_socio' => $codigoSocio,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
}
