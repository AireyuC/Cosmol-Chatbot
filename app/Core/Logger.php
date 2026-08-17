<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Clase de Logging Estructurado (Fase 5 — Plan de Seguridad)
 *
 * Registra eventos y errores de la API en formato JSON estructurado en un
 * archivo de logs dedicado (/var/log/cosmol_api.log).
 *
 * Cada entrada es una línea JSON independiente con timestamp ISO-8601,
 * nivel de severidad (ERROR, INFO), mensaje descriptivo y arreglo de contexto.
 *
 * Compatible con PHP 7.3 (sin propiedades con tipo).
 */
class Logger
{
    /** @var string Ruta por defecto al archivo de log en el contenedor Docker */
    private static $logFile = '/var/log/cosmol_api.log';

    /**
     * Registra un error crítico con su contexto.
     *
     * @param string $message Mensaje descriptivo del error
     * @param array $context Datos adicionales de contexto (excepciones, parámetros, etc.)
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Registra información de eventos normales con su contexto.
     *
     * @param string $message Mensaje informativo
     * @param array $context Datos adicionales de contexto
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Escribe la entrada formateada como JSON en el archivo de log.
     *
     * @param string $level Nivel del log ('ERROR', 'INFO')
     * @param string $message Mensaje del evento
     * @param array $context Contexto adicional
     * @return void
     */
    private static function write(string $level, string $message, array $context): void
    {
        $entry = json_encode([
            'timestamp' => date('c'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logPath = self::$logFile;
        $dir = dirname($logPath);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // Intenta escribir en /var/log/cosmol_api.log; si falla por permisos, hace fallback a /tmp
        if (@file_put_contents($logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            $fallbackFile = sys_get_temp_dir() . '/cosmol_api.log';
            @file_put_contents($fallbackFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
