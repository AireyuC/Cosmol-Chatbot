<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clase de Autenticación Interna (Fase 1 — Plan de Seguridad)
 *
 * Valida que las peticiones a la API provengan exclusivamente de n8n
 * mediante un token secreto compartido enviado en el header HTTP.
 *
 * Se invoca una sola vez desde bootstrap.php, protegiendo automáticamente
 * todos los endpoints sin necesidad de modificar cada uno individualmente.
 */
class Auth
{
    /**
     * Valida el token interno enviado por n8n en el header X-Internal-Token.
     *
     * - Lee el header HTTP_X_INTERNAL_TOKEN desde $_SERVER (Apache convierte
     *   el header "X-Internal-Token" a "HTTP_X_INTERNAL_TOKEN" automáticamente).
     * - Lo compara contra API_INTERNAL_TOKEN (definida en Config/database.php).
     * - Usa hash_equals() para prevenir timing attacks: la comparación siempre
     *   toma el mismo tiempo sin importar cuántos caracteres coincidan.
     * - Si falla: responde 401 con JSON estándar y termina el script.
     *
     * @return void
     */
    public static function validateInternalToken(): void
    {
        $token = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
        $expected = defined('API_INTERNAL_TOKEN') ? API_INTERNAL_TOKEN : '';

        // Comparación segura contra timing attacks
        if (empty($expected) || !hash_equals($expected, $token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.', 'data' => null]);
            exit;
        }
    }
}
