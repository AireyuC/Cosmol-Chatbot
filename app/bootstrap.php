<?php

declare(strict_types=1);

// 1. Cargar el autoloader (habilita el descubrimiento automático de clases mediante PSR-4)
require_once __DIR__ . '/Core/Autoloader.php';

// 2. Cargar la configuración de la aplicación y la base de datos (las constantes del .env)
require_once __DIR__ . '/Config/database.php';

// 3. Configurar el manejo de errores según el entorno
// APP_ENV se definió en Config/database.php leyendo del .env
if (defined('APP_ENV') && APP_ENV === 'development') {
    // Modo Desarrollo: Mostramos todos los errores para depurar
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    // Modo Producción: Ocultamos errores para no exponer información sensible
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 4. Configurar headers globales para los endpoints de nuestra API
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

