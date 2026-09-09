<?php

declare(strict_types=1);

namespace App\Integrations\CosmolReportes;

use App\Core\Logger;
use Exception;

/**
 * Cliente HTTP para enviar eventos de consultas al sistema COSMOL-Reportes.
 */
class ClienteApiReportes
{
    /**
     * @var string
     * @var string
     * @var bool
     * @var string|null
     */
    private $baseUrl;
    private $token;
    private $servidorOffline = false;
    private $ultimoError = null;

    public function __construct()
    {
        $this->baseUrl = defined('REPORTES_API_URL') ? (string)REPORTES_API_URL : '';
        $this->token = defined('REPORTES_API_TOKEN') ? (string)REPORTES_API_TOKEN : '';
    }

    public function estaServidorOffline(): bool
    {
        return $this->servidorOffline;
    }

    public function obtenerUltimoError(): ?string
    {
        return $this->ultimoError;
    }

    /**
     * Envía una consulta a la API de COSMOL-Reportes.
     *
     * @param array $payload Datos de la consulta
     * @return bool True si el servidor respondió 200 o 201, False ante error o timeout
     */
    public function enviarConsulta(array $payload): bool
    {
        if (empty($this->baseUrl)) {
            // Si no está configurada la URL, no intentamos conexión externa
            return false;
        }

        $baseUrl = rtrim($this->baseUrl, '/');
        if (substr($baseUrl, -14) === '/api/consultas' || substr($baseUrl, -10) === '/consultas') {
            $url = $baseUrl;
        } elseif (substr($baseUrl, -4) === '/api') {
            $url = $baseUrl . '/consultas';
        } else {
            $url = $baseUrl . '/api/consultas';
        }

        $jsonData = json_encode($payload);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
            'ngrok-skip-browser-warning: true'
        ];

        if (!empty($this->token)) {
            $headers[] = 'X-Reportes-Token: ' . $this->token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);       // Margen adecuado para túneles ngrok
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::warning("ClienteApiReportes: Timeout o error de conexión con COSMOL-Reportes", [
                'error' => $curlError,
                'url'   => $url
            ]);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        Logger::warning("ClienteApiReportes: COSMOL-Reportes respondió con error HTTP", [
            'http_code' => $httpCode,
            'response'  => $response
        ]);
        return false;
    }
}
