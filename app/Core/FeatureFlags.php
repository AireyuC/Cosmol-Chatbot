<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Gestor de Interruptores de Funcionalidad (Feature Flags) y Modo Mantenimiento.
 * Permite activar o desactivar módulos del chatbot y controlar el modo mantenimiento global
 * mediante variables de entorno (.env) o configuración.
 */
class FeatureFlags
{
    /**
     * Mapeo de módulos del sistema hacia sus variables de entorno correspondientes.
     */
    private const MODULE_ENV_MAP = [
        'deuda'            => 'FEATURE_CONSULTAR_DEUDA',
        'consultar_deuda'  => 'FEATURE_CONSULTAR_DEUDA',
        'reconexion'       => 'FEATURE_RECONEXION',
        'historial'        => 'FEATURE_HISTORIAL',
        'reclamos'         => 'FEATURE_RECLAMOS',
        'estado_tramites'  => 'FEATURE_ESTADO_TRAMITES',
        'estado_solicitudes' => 'FEATURE_ESTADO_TRAMITES',
        'oficinas'         => 'FEATURE_OFICINAS',
        'agente'           => 'FEATURE_AGENTE',
    ];

    /**
     * Evalúa si la sincronización y pusheo de datos a COSMOL-Reportes está habilitada.
     * Por defecto es true. Si se define en false (ej. durante mantenimiento de Reportes),
     * el sistema retiene los eventos en el buffer local sin emitir peticiones HTTP hacia afuera.
     */
    public static function isReportesSyncEnabled(): bool
    {
        return self::getEnvBool('REPORTES_SYNC_ENABLED', true);
    }

    /**
     * Evalúa si el modo de mantenimiento global está encendido.
     */
    public static function isMaintenanceMode(): bool
    {
        return self::getEnvBool('MAINTENANCE_MODE', false);
    }

    /**
     * Obtiene el número máximo de mensajes permitidos durante el mantenimiento antes de silenciar.
     */
    public static function getMaintenanceMaxAttempts(): int
    {
        $val = getenv('MAINTENANCE_MAX_ATTEMPTS');
        if ($val !== false && is_numeric($val) && (int)$val > 0) {
            return (int)$val;
        }
        return 4;
    }

    /**
     * Obtiene los minutos de espera por inactividad durante el modo mantenimiento (ej. 10 en dev, 20 en prod).
     */
    public static function getMaintenanceTimeoutMinutes(): int
    {
        $val = getenv('MAINTENANCE_TIMEOUT_MINUTES');
        if ($val !== false && is_numeric($val) && (int)$val > 0) {
            return (int)$val;
        }
        return 10;
    }

    /**
     * Obtiene los segundos de espera por inactividad durante el mantenimiento.
     */
    public static function getMaintenanceTimeoutSeconds(): int
    {
        return self::getMaintenanceTimeoutMinutes() * 60;
    }

    /**
     * Evalúa si un módulo específico está habilitado.
     * Si no está definido en el entorno, por defecto retorna true.
     */
    public static function isEnabled(string $module): bool
    {
        $moduleKey = strtolower(trim($module));
        $envKey = self::MODULE_ENV_MAP[$moduleKey] ?? null;

        if ($envKey === null) {
            return true;
        }

        return self::getEnvBool($envKey, true);
    }

    /**
     * Lee una variable de entorno y la convierte de manera estricta a booleano.
     */
    private static function getEnvBool(string $envKey, bool $default): bool
    {
        $val = getenv($envKey);
        if ($val === false || $val === null || $val === '') {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }
}
