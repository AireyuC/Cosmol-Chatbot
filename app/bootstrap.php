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
header('Access-Control-Allow-Origin: *'); // Habilita peticiones CORS (ej. llamadas desde n8n)
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 5. Manejar peticiones previas de CORS (Preflight requests - OPTIONS)
// Los navegadores o herramientas suelen mandar un 'OPTIONS' antes de la petición real
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
