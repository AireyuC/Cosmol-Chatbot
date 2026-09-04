<?php

declare(strict_types=1);

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'pgsql');
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'chatbot_cosmol');
define('DB_USER', getenv('DB_USER') ?: 'cosmol');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8');

// Entorno de la Aplicación
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN));

// API Externa
define('COSMOL_API_URL', getenv('COSMOL_API_URL') ?: '');

// Seguridad — Token interno compartido entre n8n y la API PHP (Fase 1)
define('API_INTERNAL_TOKEN', getenv('API_INTERNAL_TOKEN') ?: '');

// Seguridad — Origen permitido para CORS (Fase 3)
define('ALLOWED_ORIGIN', getenv('ALLOWED_ORIGIN') ?: '');

// Integración COSMOL-Reportes (Métricas de Consultas de WhatsApp)
define('REPORTES_API_URL', getenv('REPORTES_API_URL') ?: '');
define('REPORTES_API_TOKEN', getenv('REPORTES_API_TOKEN') ?: '');

