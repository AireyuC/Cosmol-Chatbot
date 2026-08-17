# Fase 5 — Logging Estructurado (Prioridad MEDIA)

**Problema actual:** Los errores se registran con `error_log("texto plano")`,
difícil de filtrar y analizar en producción.

**Solución:** Crear un `Logger` que escriba JSON estructurado en un archivo de logs
dedicado. Cada línea es un JSON con `timestamp`, `level`, `message` y `context`.

### Archivos afectados

#### [NEW] `app/Core/Logger.php`

```php
namespace App\Core;

class Logger {
    private static string $logFile = '/var/log/cosmol_api.log';

    public static function error(string $message, array $context = []): void {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void {
        $entry = json_encode([
            'timestamp' => date('c'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        ]);
        file_put_contents(self::$logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
```

#### [MODIFY] [socio.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/socio.php) / [reclamos.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/reclamos.php) / [factura.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/factura.php)
Reemplazar todos los `error_log("...")` por `Logger::error(...)` con contexto.

```php
// Antes:
error_log("Error crítico en SocioEndpoint: " . $e->getMessage());

// Después:
Logger::error("Error crítico en SocioEndpoint", [
    'exception' => $e->getMessage(),
    'codigo_socio' => $codigo_socio,
    'action' => $action,
]);
```

#### [MODIFY] `dockerfile`
Asegurar que el directorio `/var/log` sea escribible por el proceso de Apache/PHP.

### Verificación
- Verificar que los errores aparecen en `/var/log/cosmol_api.log` en formato JSON
ook 