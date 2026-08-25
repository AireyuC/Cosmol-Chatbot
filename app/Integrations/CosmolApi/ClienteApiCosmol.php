<?php

declare(strict_types=1);

namespace App\Integrations\CosmolApi;

use Exception;


class ClienteApiCosmol
{
    private $baseUrl;

    public function __construct()
    {
        
        $this->baseUrl = defined('COSMOL_API_URL') ? COSMOL_API_URL : '';
    }

    /**
     * Obtiene los datos del socio desde la API de Cosmol.
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
     * Obtiene el historial de facturas pagadas de un socio consultando /api-consultas/socios/{cod}/historial-facturas
     *
     * @param string $codSocio
     * @return array|null
     * @throws Exception
     */
    public function obtenerHistorialFacturas(string $codSocio): ?array
    {
        $endpoint = "/api-consultas/socios/" . urlencode($codSocio) . "/historial-facturas";
        return $this->hacerPeticion($endpoint);
    }

    /**
     * Registra una solicitud de reconexión
     *
     * @param string $codSocio
     * @param array $payload
     * @return array|null
     * @throws Exception
     */
    public function registrarReconexion(string $codSocio, array $payload): ?array
    {
        $endpoint = "/api-consultas/socios/" . urlencode($codSocio) . "/reconexion";
        return $this->hacerPeticion($endpoint, 'POST', $payload);
    }

    /**
     * @param string $endpoint
     * @param string $method
     * @param array|null $body
     * @return array|null
     * @throws Exception
     */
    private function hacerPeticion(string $endpoint, string $method = 'GET', ?array $body = null): ?array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                $jsonData = json_encode($body);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData)
                ]);
            }
        }
        
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
