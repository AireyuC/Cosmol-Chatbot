# Plan de Seguridad y Buenas Prácticas — Chatbot COSMOL

Aplicar un conjunto de mejoras de seguridad y arquitectura sobre la API PHP existente,
organizadas en fases progresivas. Cada fase es independiente y funcional al terminar,
sin romper el sistema en ningún punto del camino.

> [!IMPORTANT]
> Todas las mejoras viven **exclusivamente en el backend PHP**. n8n no requiere cambios
> de código — solo se le agrega el header secreto en sus peticiones HTTP, lo cual se
> configura directamente en la UI de n8n.

---

## Fase 1 — Token de Autenticación Interna (Prioridad CRÍTICA)

**Problema actual:** Cualquiera que conozca la URL del backend puede llamar a
`/api/socio.php?codigo_socio=123` y obtener datos reales sin ninguna restricción.

**Solución:** Implementar un secreto compartido (`API_INTERNAL_TOKEN`) entre n8n y la API
PHP. n8n lo envía como header en cada petición HTTP; la API PHP lo valida antes de
responder. Si el token falta o es incorrecto → `401 Unauthorized`.

### Archivos afectados

#### [MODIFY] [database.php](file:///c:/Proyectos/Cosmol-Chatbot/app/Config/database.php)
Agregar la constante `API_INTERNAL_TOKEN` leyendo del `.env`.

```php
// Agregar al final del archivo:
define('API_INTERNAL_TOKEN', getenv('API_INTERNAL_TOKEN') ?: '');
```

#### [NEW] `app/Core/Auth.php`
Clase de autenticación con un único método estático `validateInternalToken()`.
Centraliza la validación para reutilizarla en cualquier endpoint sin duplicar código.

```php
namespace App\Core;

class Auth {
    public static function validateInternalToken(): void {
        $token = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
        $expected = defined('API_INTERNAL_TOKEN') ? API_INTERNAL_TOKEN : '';

        // Comparación segura contra timing attacks
        if (empty($expected) || !hash_equals($expected, $token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.', 'data' => null]);
            exit;
        }
    }
}
```

> [!NOTE]
> `hash_equals()` previene timing attacks: aunque el token sea incorrecto,
> el tiempo de comparación siempre es el mismo, imposibilitando ataques de fuerza
> bruta por medición de tiempo de respuesta.

#### [MODIFY] [bootstrap.php](file:///c:/Proyectos/Cosmol-Chatbot/app/bootstrap.php)
Llamar a `Auth::validateInternalToken()` **una sola vez** en el bootstrap, antes
de despachar cualquier endpoint. Así todos los endpoints quedan protegidos
automáticamente sin tocar cada `socio.php`, `reclamos.php`, etc.

#### [MODIFY] [.env](file:///c:/Proyectos/Cosmol-Chatbot/.env)
Agregar la variable con un valor generado (mínimo 32 caracteres aleatorios).

```
API_INTERNAL_TOKEN=genera-aqui-un-token-de-minimo-32-chars
```

#### [MODIFY] [env.example](file:///c:/Proyectos/Cosmol-Chatbot/env.example)
Agregar el placeholder del token para documentar la variable.

```
# Token secreto compartido entre n8n y la API PHP
# Generar con: openssl rand -hex 32
API_INTERNAL_TOKEN=your_super_secret_token_here
```

### Configuración en n8n (manual, sin código)
En cada nodo HTTP Request de n8n que llame a la API PHP:
- `Headers` → agregar `X-Internal-Token: <el mismo valor del .env>`

---

## Fase 2 — Validación y Sanitización de Inputs (Prioridad ALTA)

**Problema actual:** `codigo_socio`, `tipo_reclamo` y `descripcion` solo se verifican
como `!= null`, pero no se valida su formato. Un input malicioso podría explorar
el sistema con valores inesperados.

**Solución:** Crear una clase `Validator` que aplique reglas de formato estrictas
(regex, listas blancas, longitud máxima) antes de que cualquier dato llegue al
servicio de negocio.

### Archivos afectados

#### [NEW] `app/Core/Validator.php`
Clase con métodos estáticos de validación reutilizables.

```php
namespace App\Core;

class Validator {

    // codigo_socio: solo dígitos, 1-10 caracteres
    public static function codigoSocio(?string $value): bool {
        if ($value === null) return false;
        return (bool) preg_match('/^\d{1,10}$/', $value);
    }

    // tipo_reclamo: solo valores permitidos (lista blanca)
    public static function tipoReclamo(?string $value): bool {
        $allowed = ['agua_turbia', 'fuga', 'sin_servicio', 'presion_baja', 'otro'];
        return in_array($value, $allowed, true);
    }

    // descripcion: texto libre, max 500 caracteres, sin HTML
    public static function descripcion(?string $value): bool {
        if ($value === null || strlen($value) > 500) return false;
        return strip_tags($value) === $value; // No permite HTML
    }
}
```

#### [MODIFY] [socio.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/socio.php)
Reemplazar la validación `if ($codigo_socio === null)` por `Validator::codigoSocio()`.

#### [MODIFY] [reclamos.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/reclamos.php)
Aplicar los tres validadores antes del bloque `try`.

---

## Fase 3 — Restricción de CORS (Prioridad ALTA)

**Problema actual:** `Access-Control-Allow-Origin: *` permite que cualquier dominio
llame a la API. Aunque n8n y PHP viven en la misma red Docker, es una mala práctica.

**Solución:** Restringir el origen permitido a la IP/hostname del contenedor n8n
dentro de la red Docker interna. En producción se usará el dominio real.

### Archivos afectados

#### [MODIFY] [database.php](file:///c:/Proyectos/Cosmol-Chatbot/app/Config/database.php)
Agregar la constante `ALLOWED_ORIGIN`.

```php
define('ALLOWED_ORIGIN', getenv('ALLOWED_ORIGIN') ?: 'http://cosmol_n8n:5678');
```

#### [MODIFY] [bootstrap.php](file:///c:/Proyectos/Cosmol-Chatbot/app/bootstrap.php)
Reemplazar el `*` por la constante dinámica.

```php
// Antes:
header('Access-Control-Allow-Origin: *');

// Después:
$origin = defined('ALLOWED_ORIGIN') ? ALLOWED_ORIGIN : '';
header("Access-Control-Allow-Origin: {$origin}");
```

#### [MODIFY] [.env](file:///c:/Proyectos/Cosmol-Chatbot/.env) / [env.example](file:///c:/Proyectos/Cosmol-Chatbot/env.example)
```
# Origen permitido para CORS (nombre del contenedor n8n en la red Docker)
# Dev: http://cosmol_n8n:5678 | Prod: https://tudominio.com
ALLOWED_ORIGIN=http://cosmol_n8n:5678
```

> [!NOTE]
> Al vivir dentro de la misma red Docker (`services:` en `docker-compose.yml`),
> n8n puede resolver `cosmol_n8n` como hostname directamente. No se necesita IP.

---

## Fase 4 — Rate Limiting (Prioridad MEDIA)

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

---

## Fase 5 — Logging Estructurado (Prioridad MEDIA)

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

---

## Fase 6 — Hardening del `.env` y Separación dev/prod (Prioridad MEDIA)

**Problema actual:** El `.env` actual mezcla configuración de desarrollo y producción.
`COSMOL_API_URL` apunta a producción real mientras `APP_ENV=development`.
`env.example` no documenta las nuevas variables de seguridad.

**Solución:** Limpiar y documentar bien el `.env` y el `env.example`. No crear archivos
separados (para mantener la arquitectura Docker actual), sino establecer convenciones
claras.

### Archivos afectados

#### [MODIFY] [.env](file:///c:/Proyectos/Cosmol-Chatbot/.env)
- Agregar `API_INTERNAL_TOKEN` con valor real generado
- Agregar `ALLOWED_ORIGIN`
- Verificar que `APP_ENV=development` y `COSMOL_API_URL=` (vacío) durante desarrollo
- Separar con comentarios claros cada sección

#### [MODIFY] [env.example](file:///c:/Proyectos/Cosmol-Chatbot/env.example)
- Documentar **todas** las variables (incluyendo las nuevas de Fases 1, 2 y 3)
- Agregar instrucciones de cómo generar el token (`openssl rand -hex 32`)
- Agregar comentarios indicando qué cambiar en producción vs desarrollo

---

## Resumen de Fases

| # | Fase | Archivos nuevos | Archivos modificados | Prioridad |
|---|------|----------------|---------------------|-----------|
| 1 | Token Autenticación Interna | `Auth.php` | `database.php`, `bootstrap.php`, `.env`, `env.example` | 🔴 Crítica |
| 2 | Validación de Inputs | `Validator.php` | `socio.php`, `reclamos.php` | 🔴 Alta |
| 3 | Restricción CORS | — | `database.php`, `bootstrap.php`, `.env`, `env.example` | 🔴 Alta |
| 4 | Rate Limiting | `RateLimiter.php` | `bootstrap.php` | 🟡 Media |
| 5 | Logging Estructurado | `Logger.php` | `socio.php`, `reclamos.php`, `factura.php`, `dockerfile` | 🟡 Media |
| 6 | Hardening `.env` / docs | — | `.env`, `env.example` | 🟡 Media |

---

## Orden de ejecución recomendado en `bootstrap.php`

Después de las mejoras, el orden de ejecución en el bootstrap será:

```
1. Cargar Autoloader
2. Cargar Config (database.php → constantes del .env)
3. Configurar error_reporting según APP_ENV
4. ← [FASE 1] Auth::validateInternalToken()   → 401 si falla
5. ← [FASE 4] RateLimiter::check()            → 429 si supera límite
6. Setear headers CORS con ALLOWED_ORIGIN     ← [FASE 3]
7. Manejar preflight OPTIONS
8. Despachar al endpoint correspondiente
```

---

## Verificación por Fase

### Fase 1
- Llamar a `/api/socio.php?codigo_socio=1` sin el header → respuesta `401`
- Llamar con el header correcto → respuesta normal `200`/`404`

### Fase 2
- Enviar `codigo_socio=abc!@#` → respuesta `400` con mensaje de validación
- Enviar `tipo_reclamo=hacking` → respuesta `400` con mensaje de validación

### Fase 3
- Verificar en devtools que el header `Access-Control-Allow-Origin` ya no es `*`

### Fase 4
- Hacer 31 peticiones seguidas desde la misma IP → la 31ª responde `429`

### Fase 5
- Verificar que los errores aparecen en `/var/log/cosmol_api.log` en formato JSON
