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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 5. Manejar peticiones previas de CORS (Preflight requests - OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 6. Rate Limiting: Proteger la API de abusos (Fase 4)
\App\Core\RateLimiter::check();
