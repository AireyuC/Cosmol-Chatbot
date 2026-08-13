# Cambios de Consistencia entre Fases (1–4)

Bitácora de los cambios aplicados para conectar correctamente los archivos de las Fases 1 a 4 del backend COSMOL y garantizar consistencia entre documentación, código y base de datos.

- **Fecha de generación:** 2026-08-11
- **Responsable:** Fabian Cuellar (pcuellar366@gmail.com)
- **Estado de las fases:** Fases 1-4 Completadas | Fase 5 Futura (Sprint 4)

---

## 1. Estándares adoptados (fuente de verdad)

Antes de revisar cualquier archivo, ten presentes estos acuerdos del equipo:

| Estándar | Valor |
|---|---|
| Entorno de pruebas | **Solo Docker** (PHP + n8n + MySQL 5.7 en `docker-compose.yml`). Sin XAMPP. |
| Código del asociado | `codigo_socio` (BD, repos, endpoints y docs). |
| Nombres de tablas | Singular: `socio` y `reclamo`. |
| Esquema BD canónico | `database/init.sql`. |
| Formato de respuesta JSON | Unificado: `{ success, message, data }`. |
| Tipado estricto | `declare(strict_types=1)` en todos los archivos PHP. |
| Arranque de endpoints | `require_once ../../app/bootstrap.php` (autoloader manual PSR-4, Fase 4). |
| Navegación de docs | `indice_maestro.md` es el punto central / fuente de verdad. |

---

## 2. Tabla de cambios realizados

> Las columnas **Fecha** y **Autor** se repiten para que cada fila tenga trazabilidad. Si un cambio pertenece a más de una fase, se anota la fase principal.

| Fecha | Autor | Fase | Archivo | Cambio realizado |
|---|---|---|---|---|
| 2026-08-11 | Fabian Cuellar | 1 | `database/init.sql` | Corregido error de sintaxis (falta coma tras `fecha_creacion`), eliminado `CREATE DATABASE`/`USE` redundantes y la nota sobre tablas "socios". |
| 2026-08-11 | Fabian Cuellar | 2 | `app/Data/Repositories/MySQL/SocioRepository.php` | Renombrado `cod_socio` → `codigo_socio`; `FROM socios` → `FROM socio`; el SELECT ahora incluye `direccion` (requerida por el módulo de Reclamos). |
| 2026-08-11 | Fabian Cuellar | 2 | `app/Data/Interfaces/SocioRepositoryInterface.php` | Parámetro `codigo_socio`; agregado `@todo` para `getDeuda()` y `getHistorialFacturas()` (Consultas de Cuenta, pendiente). |
| 2026-08-11 | Fabian Cuellar | 2 | `app/Modules/Socio/SocioService.php` | Esquema de respuesta unificado `{ success, message, data }`. |
| 2026-08-11 | Fabian Cuellar | 2 | `public/api/socio.php` | Parámetro de entrada `codigo_socio` (GET y POST); reemplazados los `require_once` manuales por `bootstrap.php`; respuesta con `success`. |
| 2026-08-11 | Fabian Cuellar | 3 | `app/Data/Repositories/MySQL/ReclamoRepository.php` | `INSERT INTO reclamos` / `FROM reclamos` → `reclamo` (singular); `declare(strict_types=1)`. |
| 2026-08-11 | Fabian Cuellar | 3 | `app/Data/Interfaces/ReclamoRepositoryInterface.php` | `declare(strict_types=1)`. |
| 2026-08-11 | Fabian Cuellar | 3 | `app/Modules/Reclamo/ReclamoService.php` | Dirección del socio ahora sale de la BD (sin GPS); respuesta unificada `{ success, message, data }`; `declare(strict_types=1)`; `error_log` al fallar. |
| 2026-08-11 | Fabian Cuellar | 3 | `public/api/reclamos.php` | Reemplazados los `require_once` manuales por `bootstrap.php`; guard `getBody() ?? []`; respuesta con `success`. |
| 2026-08-11 | Fabian Cuellar | 4 | `app/Core/Controller.php` | `declare(strict_types=1)`; `handleError()` ahora emite `{ success, message, data }`. |
| 2026-08-11 | Fabian Cuellar | 1 | `app/Config/database.php` | Defaults de `getenv()` alineados al entorno Docker (`db`, `cosmol_db`, `cosmol`, `cosmol12345`). |
| 2026-08-11 | Fabian Cuellar | — | `.env` | `DB_HOST=db` y `DB_ROOT_PASSWORD` (requerido por `docker-compose.yml`). |
| 2026-08-11 | Fabian Cuellar | — | `AGENTS.md` | Actualizado: desarrollo local con MySQL 5.7 en contenedor Docker; eliminada toda referencia a XAMPP. |
| 2026-08-11 | Fabian Cuellar | 1–3 | `fase1_config_base.md`, `fase2_modulo_socios.md`, `fase3_modulo_reclamos.md` | Actualizados a los estándares: `codigo_socio`, tablas `socio`/`reclamo`, esquema JSON unificado, sin XAMPP, nota deuda/historial pendiente. |
| 2026-08-11 | Fabian Cuellar | — | `indice_maestro.md` | Fases 1–4 marcadas como ✅ Completadas; agregado aviso de pendiente (Consultas de Cuenta). |
| 2026-08-11 | Fabian Cuellar | — | `Plan_dockerizacion.md` | Nota de documento histórico; referencia MySQL sin XAMPP; aclarado que no se usa Composer ni front controller. |
| 2026-08-11 | Fabian Cuellar | — | `Docs/ESTRUCTURA.md` | Nota de documento histórico/superado; apunta a `indice_maestro.md` como fuente de verdad. |
| 2026-08-11 | Fabian Cuellar | — | `Docs/plan_5_08.md` (raíz) | Consolidado: se eliminó el duplicado raíz; las respuestas a las preguntas abiertas se preservaron en `Docs/chichico-fabian/plan_5_08.md`. |

> [!NOTE]
> `app/bootstrap.php`, `app/Core/Autoloader.php` y `app/Core/Database.php` no se modificaron, pero **sí se conectaron** al flujo (los endpoints ahora pasan por `bootstrap.php`).

---

## 3. Instrucciones para revisar la consistencia

Sigue este checklist en orden para verificar que la conexión entre archivos sigue siendo correcta.

### 3.1 Verificar la base de datos (esquema ↔ repositorios)

1. Abre `database/init.sql` y confirma las tablas canónicas:
   - `socio` con: `codigo_socio`, `ci`, `nombre`, `apellido`, `telefono`, `direccion`, `estado_conexion`.
   - `reclamo` con: `id`, `codigo_socio`, `tipo_reclamo`, `descripcion`, `direccion`, `estado`, `fecha_creacion`.
2. Compara cada consulta SQL de `app/Data/Repositories/MySQL/*.php` contra ese esquema:
   - `SocioRepository::findByCodigo()` → usa `FROM socio`, columna `codigo_socio`, y selecciona `direccion`.
   - `ReclamoRepository::createReclamo()` / `findByCodigoSocio()` → usan `reclamo` (singular).
3. **Resultado esperado:** ningún nombre de tabla o columna difiere entre SQL y esquema.

### 3.2 Verificar que la Fase 4 está cableada

1. Abre `public/api/socio.php` y `public/api/reclamos.php`.
2. La única carga de dependencias debe ser: `require_once __DIR__ . '/../../app/bootstrap.php';` (sin `require_once` manuales).
3. Confirma que el autoloader mapea `App\` → `app/` (`app/Core/Autoloader.php`) y que `bootstrap.php` carga `Autoloader.php` + `Config/database.php` antes que cualquier clase.
4. **Resultado esperado:** las clases (`App\Core\*`, `App\Data\*`, `App\Modules\*`) se resuelven sin `require_once` explícitos.

### 3.3 Verificar el esquema JSON unificado

1. Busca todos los `return [` dentro de `app/Modules/*/` y `app/Core/Controller.php`.
2. Todas las respuestas deben tener exactamente las claves: `success`, `message`, `data`.
3. `Controller::handleError()` y `Controller::json()` deben mantener ese mismo contrato.
4. **Resultado esperado:** `socio.php` y `reclamos.php` responden a n8n con el mismo formato base.

### 3.4 Verificar que no quedan nombres antiguos

Busca en `app/` y `public/` cualquier rastro de los nombres descartados:

```powershell
Select-String -Path (Get-ChildItem -Recurse -File -Include *.php -Path app, public).FullName -Pattern "cod_socio|FROM socios|INTO reclamos|FROM reclamos|codigo_fijo|'exito'|'mensaje'|'status'"
```

**Resultado esperado:** sin coincidencias.

### 3.5 Verificar `declare(strict_types=1)`

1. Confirma que todos los archivos PHP de `app/` y `public/api/` inician con `declare(strict_types=1);` después del `<?php`.

### 3.6 Verificar entorno y configuración (Docker)

1. `.env` debe tener: `DB_HOST=db`, `DB_ROOT_PASSWORD`, `DB_NAME=cosmol_db`, `DB_USER=cosmol`, `DB_PASSWORD=cosmol12345`.
2. `docker-compose.yml` debe usar `${DB_*}` desde `.env` (los servicios `n8n`, `backend` y `db`).
3. `app/Config/database.php` debe tener defaults que coincidan con Docker (`db`, `3306`, `cosmol_db`, `cosmol`).
4. **Resultado esperado:** `docker compose up` levanta sin variables vacías.

### 3.7 Pruebas en el contenedor (smoke test)

> Requiere Docker Desktop corriendo.

```powershell
# 1. Lint de todos los archivos PHP
docker run --rm -v "${PWD}:/app" -w /app php:7.3-cli php -l public/api/socio.php
docker run --rm -v "${PWD}:/app" -w /app php:7.3-cli php -l public/api/reclamos.php

# 2. Levantar el stack
docker compose up --build -d
docker compose ps        # los 3 servicios deben aparecer como "running"

# 3. Smoke test de la API (con la BD de prueba)
curl "http://localhost:8000/api/socio.php?codigo_socio=1"
curl -X POST http://localhost:8000/api/reclamos.php `
  -H "Content-Type: application/json" `
  -d '{"codigo_socio":"1","tipo_reclamo":"AGUA_TURBIA","descripcion":"El agua llega turbia"}'
```

**Resultado esperado:**
- `GET socio.php` devuelve `{ "success": ..., "message": ..., "data": ... }`.
- `POST reclamos.php` devuelve `{ "success": true, "message": "Reclamo registrado correctamente con el ticket #N.", "data": { "ticket_id": N } }`.

### 3.8 Verificar la documentación

1. `indice_maestro.md` debe ser la fuente de verdad y tener las Fases 1–4 como ✅ Completadas.
2. Los archivos `fase1/2/3` deben usar `codigo_socio` y tablas `socio`/`reclamo`.
3. `Plan_dockerizacion.md` y `Docs/ESTRUCTURA.md` deben tener la nota de "documento histórico/superado".
4. No debe existir `Docs/plan_5_08.md` duplicado en la raíz.

---

## 4. Pendientes documentados

| Pendiente | Estado |
|---|---|
| `SocioRepositoryInterface::getDeuda()` y `getHistorialFacturas()` (Consultas de Cuenta) | `@todo` en la interfaz; sin implementar. |
| Fase 5 — Migración a Informix (repositorios Informix + driver `pdo_informix`) | ⏳ Futuro (Sprint 4). |

---

## 5. Referencias

- Índice maestro de fases: `fases_paraBackend/indice_maestro.md`
- Fases: `fase1_config_base.md` · `fase2_modulo_socios.md` · `fase3_modulo_reclamos.md` · `fase4_autoloading.md` · `fase5_migracion_informix.md`
- Orquestación Docker: `Plan_dockerizacion.md` · `docker-compose.yml` · `dockerfile`
- Esquema de BD: `database/init.sql`
