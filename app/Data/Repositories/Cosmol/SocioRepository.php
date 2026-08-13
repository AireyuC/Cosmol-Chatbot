<?php

declare(strict_types=1);

namespace App\Data\Repositories\Cosmol;

use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Class SocioRepository (Cosmol)
 *
 * Implementación del repositorio que consume la API REST externa de COSMOL
 * (api.cosmol.com.bo) en lugar de una base de datos local.
 * Usada en desarrollo/pruebas cuando COSMOL_API_URL esté definida en el .env.
 */
class SocioRepository implements SocioRepositoryInterface
{
    /**
     * URL base de la API externa (ej. https://api.cosmol.com.bo/api-consultas)
     * @var string
     */
    private string $baseUrl;

    /**
     * Token de autenticación Bearer (opcional).
     * Si COSMOL_API_TOKEN está vacío en el .env, no se envía cabecera Authorization.
     * @var string
     */
    private string $apiToken;

    public function __construct()
    {
        // Se leen las constantes definidas en Config/database.php desde el .env
        $this->baseUrl = rtrim(defined('COSMOL_API_URL') ? COSMOL_API_URL : '', '/');
        $this->apiToken = defined('COSMOL_API_TOKEN') ? COSMOL_API_TOKEN : '';
    }

    /**
     * Busca un socio por su código fijo consultando la API externa.
     * Endpoint: GET /socios/{codigo_socio}
     *
     * @param string $codigo_socio El código fijo del socio a buscar.
     * @return array|null Datos básicos del socio o null si no existe / falla.
     */
    public function findByCodigo(string $codigo_socio): ?array
    {
        $url = "{$this->baseUrl}/socios/" . urlencode($codigo_socio);
        $response = $this->get($url);

        if ($response === null) {
            return null;
        }

        // La API devuelve los datos directamente o anidados. Adaptamos según la respuesta real.
        // Si la API devuelve un array con los datos del socio en la raíz, lo retornamos tal cual.
        // Si los devuelve en una clave 'data', ajustar aquí.
        return $response;
    }

    /**
     * Consulta las deudas/facturas pendientes de un socio.
     * Endpoint: GET /socios/{codigo_socio}/deudas
     *
     * @param string $codigo_socio El código fijo del socio.
     * @return array|null Array con las deudas o null si falla.
     */
    public function getDeuda(string $codigo_socio): ?array
    {
        $url = "{$this->baseUrl}/socios/" . urlencode($codigo_socio) . "/deudas";
        $response = $this->get($url);

        if ($response === null) {
            return null;
        }

        return $response;
    }

    /**
     * Realiza una petición GET a la API externa usando cURL.
     *
     * @param string $url URL completa del endpoint a consultar.
     * @return array|null La respuesta JSON decodificada o null si hay error.
     */
    private function get(string $url): ?array
    {
        $ch = curl_init();

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        // Si hay token configurado, lo añadimos como Bearer
        if (!empty($this->apiToken)) {
            $headers[] = "Authorization: Bearer {$this->apiToken}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,       // Tiempo máximo de espera: 10 segundos
            CURLOPT_CONNECTTIMEOUT => 5,        // Tiempo máximo de conexión: 5 segundos
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,     // Verificar SSL en producción. Cambiar a false sólo para pruebas locales.
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $body       = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("SocioRepository[Cosmol] cURL error en GET {$url}: {$curlError}");
            return null;
        }

        if ($httpCode === 404) {
            // Socio no encontrado: es un caso válido, no un error de sistema
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("SocioRepository[Cosmol] HTTP {$httpCode} en GET {$url}. Body: {$body}");
            return null;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("SocioRepository[Cosmol] JSON inválido en GET {$url}: " . json_last_error_msg());
            return null;
        }

        return $data;
    }
}
