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
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $token;

    public function __construct()
    {
        $this->baseUrl = defined('REPORTES_API_URL') ? (string)REPORTES_API_URL : '';
        $this->token = defined('REPORTES_API_TOKEN') ? (string)REPORTES_API_TOKEN : '';
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

        $url = rtrim($this->baseUrl, '/') . '/consultas';
        $jsonData = json_encode($payload);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ];

        if (!empty($this->token)) {
            $headers[] = 'X-Reportes-Token: ' . $this->token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);       // Timeout ultracorto para no bloquear WhatsApp
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

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
