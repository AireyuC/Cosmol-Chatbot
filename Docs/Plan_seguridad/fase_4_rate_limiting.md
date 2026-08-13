# Fase 4 — Rate Limiting (Prioridad MEDIA)

**Problema actual:** Sin límite de peticiones, un atacante puede enumerar miles de
códigos de socio por segundo haciendo scraping de datos.

**Solución:** Implementar un rate limiter basado en archivos (sin dependencias externas,
compatible con PHP 7.3 puro). Máximo **30 peticiones por minuto** por IP de origen.

### Archivos afectados

#### [NEW] `app/Core/RateLimiter.php`
Usa el sistema de archivos (`/tmp`) como almacén de contadores por IP.
Cada archivo representa una IP y contiene el timestamp de la ventana y el contador.

```php
namespace App\Core;

class RateLimiter {
    private static int $maxRequests = 30;
    private static int $windowSeconds = 60;

    public static function check(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $safeIp = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', $ip);
        $file = sys_get_temp_dir() . "/rl_{$safeIp}.json";

        $data = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
        $now = time();

        if (!$data || ($now - $data['window_start']) >= self::$windowSeconds) {
            $data = ['window_start' => $now, 'count' => 0];
        }

        $data['count']++;

        if ($data['count'] > self::$maxRequests) {
            http_response_code(429);
            header('Retry-After: 60');
            echo json_encode([
                'success' => false,
                'message' => 'Demasiadas peticiones. Intenta en 60 segundos.',
                'data' => null
            ]);
            exit;
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
```

#### [MODIFY] [bootstrap.php](file:///c:/Proyectos/Cosmol-Chatbot/app/bootstrap.php)
Llamar a `RateLimiter::check()` después de la validación del token (Fase 1).

> [!NOTE]
> Solución sin Redis ni APCu para mantener compatibilidad con PHP 7.3 puro en Docker.
> Si en el futuro se agrega Redis al stack, se puede migrar el almacén sin cambiar
> la interfaz de `RateLimiter`.

### Verificación
- Hacer 31 peticiones seguidas desde la misma IP → la 31ª responde `429`
