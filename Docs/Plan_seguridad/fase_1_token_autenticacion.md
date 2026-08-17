# Fase 1 — Token de Autenticación Interna (Prioridad CRÍTICA)

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

### Verificación
- Llamar a `/api/socio.php?codigo_socio=1` sin el header → respuesta `401`
- Llamar con el header correcto → respuesta normal `200`/`404`




