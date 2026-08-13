# Fase 3 — Restricción de CORS (Prioridad ALTA)

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

### Verificación
- Verificar en devtools que el header `Access-Control-Allow-Origin` ya no es `*`
