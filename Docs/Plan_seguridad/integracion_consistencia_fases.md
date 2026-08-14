# Auditoría de Consistencia — Integración de Fases de Seguridad

> **Fecha de revisión:** 2026-08-14
> **Revisado por:** Antigravity (Agente de desarrollo)
> **Contexto:** Validación cruzada entre el trabajo de Chichico (Fases 1, 3 y 5) y el trabajo de Fabián (Fases 2 y 4) antes de fusionar ambas ramas.

---

## 1. Alcance de la Revisión

Se analizaron los siguientes archivos de documentación y código real:

| Documento / Archivo | Autor | Rol |
|---|---|---|
| `Docs/Plan_seguridad/Detalle_fases_Chichico.md` | Chichico | Descripción de implementación Fases 1, 3 y 5 |
| `Docs/Plan_seguridad/fase_3_restriccion_cors.md` | Chichico | Especificación técnica Fase 3 |
| `Docs/chichico-fabian/fases_paraBackend/fase5_migracion_informix.md` | Chichico/Fabián | Especificación futura Fase 5 (Sprint 4) |
| `Docs/Plan_seguridad/fase_2_validacion_inputs.md` | Fabián | Especificación técnica Fase 2 |
| `Docs/Plan_seguridad/fase_4_rate_limiting.md` | Fabián | Especificación técnica Fase 4 |
| `app/bootstrap.php` | Chichico | Punto central de integración |
| `app/Core/RateLimiter.php` | Fabián | Implementación Rate Limiter |
| `app/Core/Controller.php` | Compartido | Clase base de endpoints |
| `public/api/socio.php` | Compartido | Endpoint de socios |
| `public/api/reclamos.php` | Compartido | Endpoint de reclamos |

---

## 2. Resultado del Análisis — Problemas Encontrados

Se detectaron **2 problemas**: uno crítico y uno de consistencia menor.

---

### 🔴 Problema 1 (Crítico) — `RateLimiter` existía pero nunca se ejecutaba

**Archivo afectado:** `app/bootstrap.php`

**Descripción:**
El archivo `app/Core/RateLimiter.php` estaba correctamente implementado por Fabián (Fase 4), pero
**nunca se invocaba** desde `bootstrap.php`. Al terminar el bootstrap solo con
`Auth::validateInternalToken()`, el rate limiter quedaba como código muerto — existente pero inactivo.

**Impacto:**
Un atacante con acceso al token podía hacer scraping ilimitado de códigos de socio sin ser bloqueado.

**Corrección aplicada:**

```diff
  // app/bootstrap.php
  use App\Core\Auth;
  Auth::validateInternalToken();

+ // [FASE 4 — Seguridad] Rate Limiting por IP.
+ // Se ejecuta DESPUÉS del Auth para no consumir el contador con peticiones no autenticadas.
+ use App\Core\RateLimiter;
+ RateLimiter::check();
```

---

### 🟡 Problema 2 (Consistencia) — Contrato de respuesta de error no uniforme en `socio.php`

**Archivo afectado:** `public/api/socio.php`

**Descripción:**
Las capas de seguridad (`Auth` → 401, `RateLimiter` → 429) y el método base `Controller::handleError()`
emiten respuestas de error con la estructura:

```json
{ "success": false, "message": "...", "data": null }
```

Sin embargo, los errores de `socio.php` (validación 400 y excepción 500) usaban directamente
`$this->json()` con la clave `status` en lugar de `success`:

```json
{ "status": "error", "message": "..." }
```

Esto significaba que n8n recibía **dos contratos distintos** según en qué capa fallara la petición,
lo que podría causar fallos silenciosos en el flujo de decisión del orquestador.

**Corrección aplicada:**

```diff
  // Error de validación (400)
- $this->json(['status' => 'error', 'message' => 'El parámetro cod_socio es inválido...'], 400);
+ $this->json(['success' => false, 'message' => 'El parámetro cod_socio es inválido o no fue proporcionado.', 'data' => null], 400);

  // Error de excepción (500)
- $this->json(['status' => 'error', 'message' => 'Ocurrió un error interno en el servidor'], 500);
+ $this->json(['success' => false, 'message' => 'Error interno en el servidor.', 'data' => null], 500);
```

> [!NOTE]
> `reclamos.php` **no tenía este problema** porque ya utilizaba `$this->handleError()` del
> Controller base, el cual emite la estructura correcta internamente.

---

## 3. Estado Final de Integración (Post-corrección)

### 3.1 Flujo de ejecución en `bootstrap.php`

El orden canónico quedó implementado correctamente en el código real:

```
1. Autoloader PSR-4
2. Config global (database.php → constantes + .env)
3. Error reporting según APP_ENV
4. Headers CORS dinámicos con ALLOWED_ORIGIN   ← Fase 3 (Chichico)
5. Preflight OPTIONS → 200 OK y exit
6. Auth::validateInternalToken()               ← Fase 1 (Chichico)
7. RateLimiter::check()                        ← Fase 4 (Fabián) ✅ CORREGIDO
```

### 3.2 Contrato de respuesta de error unificado

Todas las capas del sistema emiten ahora la misma estructura para errores:

| Capa | HTTP | Contrato |
|---|---|---|
| `Auth::validateInternalToken()` | 401 | `{ "success": false, "message": "...", "data": null }` |
| `RateLimiter::check()` | 429 | `{ "success": false, "message": "...", "data": null }` |
| `Controller::handleError()` | 4xx/5xx | `{ "success": false, "message": "...", "data": null }` |
| `socio.php` — validación | 400 | `{ "success": false, "message": "...", "data": null }` ✅ |
| `socio.php` — excepción | 500 | `{ "success": false, "message": "...", "data": null }` ✅ |
| `reclamos.php` — validación | 400 | `{ "success": false, "message": "...", "data": null }` ✅ |
| `reclamos.php` — excepción | 500 | `{ "success": false, "message": "...", "data": null }` ✅ |

### 3.3 Archivos `app/Core/` — Sin conflictos de integración

| Archivo | Autor | Estado |
|---|---|---|
| `app/Core/Auth.php` | Chichico | ✅ Independiente |
| `app/Core/Logger.php` | Chichico | ✅ Independiente |
| `app/Core/Validator.php` | Fabián | ✅ Independiente |
| `app/Core/RateLimiter.php` | Fabián | ✅ Independiente + **Activado** |

### 3.4 Fase 5 (Migración Informix) — Sin impacto en fases actuales

La documentación de la Fase 5 (`fase5_migracion_informix.md`) describe trabajo futuro (Sprint 4).
Su arquitectura de repositorios intercambiables **no genera ningún conflicto** con las fases de
seguridad actuales (1, 2, 3, 4). El `bootstrap.php`, `Auth`, `Logger`, `Validator` y `RateLimiter`
no requieren cambios para la migración a Informix.

---

## 4. Checklist de Verificación

Antes de hacer el merge final de las ramas, ejecutar las siguientes pruebas manuales:

| # | Escenario | Petición | Respuesta esperada |
|---|---|---|---|
| 1 | Sin token | `GET /api/socio.php?cod_socio=123` | `401` + `{"success": false, ...}` |
| 2 | Con token, `cod_socio` inválido | `GET ...?cod_socio=abc!@#` | `400` + `{"success": false, ...}` |
| 3 | Con token, `cod_socio` válido | `GET ...?cod_socio=12345` | `200` + resultado del servicio |
| 4 | 31 peticiones seguidas con token | loop de POST | La 31ª devuelve `429` + `{"success": false, ...}` |
| 5 | Petición preflight CORS | `OPTIONS` sin token | `200 OK` sin ejecutar Auth ni RateLimiter |
| 6 | Header CORS | devtools del navegador | `Access-Control-Allow-Origin: http://cosmol_n8n:5678` (no `*`) |
| 7 | `tipo_reclamo` no permitido | POST `tipo_reclamo=hacking` | `400` + `{"success": false, ...}` |
| 8 | `descripcion` con HTML | POST `descripcion=<script>` | `400` + `{"success": false, ...}` |
