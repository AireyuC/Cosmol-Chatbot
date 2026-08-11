<?php

declare(strict_types=1);

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cosmol_db');
define('DB_USER', getenv('DB_USER') ?: 'cosmol');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Entorno de la Aplicación
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN));
