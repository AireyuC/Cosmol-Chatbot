<?php

declare(strict_types=1);

require_once __DIR__ . '/Core/Autoloader.php';

require_once __DIR__ . '/Config/database.php';

// 3. Configurar el manejo de errores según el entorno
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // Modo Producción: Ocultamos errores para no exponer información sensible
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

header('Content-Type: application/json; charset=utf-8');
$origin = defined('ALLOWED_ORIGIN') ? ALLOWED_ORIGIN : '';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Internal-Token');

// 5. Manejar peticiones previas de CORS (Preflight requests - OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 6. [FASE 1 — Seguridad] Validar el token interno antes de procesar cualquier petición.
// Si el header X-Internal-Token no coincide con API_INTERNAL_TOKEN → responde 401 y muere.
use App\Core\Auth;
Auth::validateInternalToken();

// 7. [FASE 4 — Seguridad] Rate Limiting por IP.
// Se ejecuta DESPUÉS del Auth: los atacantes sin token válido ya fueron cortados (401) y no
// consumen el contador. Solo las peticiones autenticadas incrementan el rate limiter.
use App\Core\RateLimiter;
RateLimiter::check();

