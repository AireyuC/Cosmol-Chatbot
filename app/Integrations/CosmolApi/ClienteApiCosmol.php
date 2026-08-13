<?php

declare(strict_types=1);

namespace App\Integrations\CosmolApi;

use Exception;

/**
 * Cliente HTTP para comunicarse con la API de Cosmol.
 */
class ClienteApiCosmol
{
    private $baseUrl;

    public function __construct()
    {
        // Obtiene la URL base definida en app/Config/database.php
        $this->baseUrl = defined('COSMOL_API_URL') ? COSMOL_API_URL : 'http://api.cosmol.com.bo';
    }

    /**
     * Valida que el socio exista consultando el endpoint /api-consultas/socios/{cod}
     *
     * @param string $codSocio
     * @return array|null
     * @throws Exception
     */
    public function obtenerSocio(string $codSocio): ?array
    {
        $endpoint = "/api-consultas/socios/" . urlencode($codSocio);
        return $this->hacerPeticion($endpoint);
    }

    /**
     * Obtiene el listado de deudas de un socio consultando /api-consultas/socios/{cod}/deudas
     *
     * @param string $codSocio
     * @return array|null
     * @throws Exception
     */
    public function obtenerDeudasSocio(string $codSocio): ?array
    {
        $endpoint = "/api-consultas/socios/" . urlencode($codSocio) . "/deudas";
        return $this->hacerPeticion($endpoint);
    }

    /**
     * Realiza la petición cURL.
     *
     * @param string $endpoint
     * @return array|null
     * @throws Exception
     */
    private function hacerPeticion(string $endpoint): ?array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Timeout de 10 segundos
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Error al conectar con la API de Cosmol: " . $error);
        }

        if ($httpCode >= 400 && $httpCode !== 404) {
            throw new Exception("La API de Cosmol respondió con error HTTP: " . $httpCode);
        }

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Respuesta inválida de la API de Cosmol (No es JSON).");
        }

        return $decoded;
    }
}
