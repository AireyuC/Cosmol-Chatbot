# Índice Maestro y Documentación Consolidada — Plan Backend COSMOL

Este documento centraliza el estado de todas las fases del desarrollo del backend y sirve como **registro de avances** del proyecto.

---

## Estado Actual de las Fases

| Fase | Nombre | Estado |
|------|--------|--------|
| **Fase 1** | Configuración Base y BD | ✅ Completada |
| **Fase 2** | Módulo de Socios | ✅ Completada |
| **Fase 3** | Módulo de Reclamos | ✅ Completada |
| **Fase 4** | Autoloading y Utilidades | ✅ Completada |
| **Migración PostgreSQL** | Migración MySQL → PostgreSQL 16 + ANSI SQL | ✅ Completada |
| **Seguridad API** | Hardening de API PHP e infraestructura Docker (7 fases) | ✅ Completada |
| [**Fase 5**](fase5_migracion_informix.md) | Migración a IBM Informix (APIs REST del SAI) | ⏳ Futuro (Sprint 4) |

> [!NOTE]
> **Deuda técnica documentada:** `getDeuda()` y `getHistorialFacturas()` del módulo Socios quedan pendientes, marcados con `@todo` en `SocioRepositoryInterface.php`.

---

## Fases Completadas

### Fase 1 — Configuración Base y Conexión a Base de Datos
Infraestructura base del backend: entorno, conexión a BD y controlador base.
- `app/Config/database.php` — constantes de entorno (ahora apunta a PostgreSQL).
- `app/Core/Database.php` — Singleton PDO con soporte multi-driver (`pgsql`, `mysql`, `informix`).
- `app/Core/Controller.php` — métodos `json()`, `getBody()`, `handleError()`.

### Fase 2 — Módulo de Socios (Autenticación y Consultas)
Implementa la autenticación "Fricción Cero" (solo con el Código de Asociado) usando el Patrón Repositorio.
- `app/Data/Interfaces/SocioRepositoryInterface.php` — contrato (`findByCodigo`, `@todo getDeuda`, `getHistorialFacturas`).
- `app/Data/Repositories/Postgres/SocioRepository.php` — implementación para desarrollo local.
- `app/Modules/Socio/SocioService.php` — lógica de negocio agnóstica a HTTP.
- `public/api/webhook_whatsapp.php` — controlador frontal único para N8N.

### Fase 3 — Módulo de Reclamos
Registro de reclamos (agua turbia, fuga, etc.) tomando la ubicación de la BD, sin GPS del usuario.
- `app/Data/Interfaces/ReclamoRepositoryInterface.php` — contrato (`createReclamo`, `findByCodigoSocio`).
- `app/Data/Repositories/Postgres/ReclamoRepository.php` — implementación con ANSI SQL (`CURRENT_TIMESTAMP`).
- `app/Modules/Reclamo/ReclamoService.php` — lógica de validación cruzando datos de socios.
- `public/api/reclamos.php` — endpoint HTTP.

### Fase 4 — Autoloading y Utilidades
- `app/Core/Autoloader.php` — PSR-4 manual con `spl_autoload_register`.
- `app/bootstrap.php` — inicializador global (Autoloader + config + headers + seguridad).

---

## Migración PostgreSQL ← plan_imple_Mdocker.md (ARCHIVADO)

> **Ejecutado:** 2026-08-21 | **Planeado en:** `Docs/chichico-fabian/plan_imple_Mdocker.md` (eliminado tras aplicación)

Migración completa de MySQL a PostgreSQL 16-alpine adoptando estándar ANSI SQL para garantizar futura portabilidad a IBM Informix.

**Archivos modificados:**

| Archivo | Cambio |
|---|---|
| [`dockerfile`](file:///c:/Proyectos/Cosmol-Chatbot/dockerfile) | `libpq-dev` + `pdo_pgsql` + `pdo_mysql` (dual driver) |
| [`docker-compose.yml`](file:///c:/Proyectos/Cosmol-Chatbot/docker-compose.yml) | Servicio `db` → `postgres:16-alpine`, volumen `postgres_data`, healthcheck `pg_isready` |
| [`.env`](file:///c:/Proyectos/Cosmol-Chatbot/.env) | `DB_DRIVER=pgsql`, `DB_PORT=5432`, `DB_NAME=chatbot_cosmol` |
| [`app/Config/database.php`](file:///c:/Proyectos/Cosmol-Chatbot/app/Config/database.php) | Defaults fallback apuntan a `pgsql` y `5432` |
| [`app/Core/Database.php`](file:///c:/Proyectos/Cosmol-Chatbot/app/Core/Database.php) | DSN para `pgsql` con `--client_encoding=UTF8`, soporte `informix` preparado |
| [`database/init.sql`](file:///c:/Proyectos/Cosmol-Chatbot/database/init.sql) | ANSI SQL: `SERIAL`, `BOOLEAN`, `TIMESTAMP`, `CURRENT_TIMESTAMP`, sin `AUTO_INCREMENT` ni `NOW()` |
| `SocioRepository.php` (Api) | No usa base de datos local, utiliza `ClienteApiCosmol` para el SAI |
| `SessionRepository.php` | Patrón ANSI `SELECT` + `INSERT`/`UPDATE` para persistencia de estados |

**Restricciones ANSI SQL activas** (portabilidad a Informix):
- ❌ `AUTO_INCREMENT` → usar `SERIAL`
- ❌ `NOW()` → usar `CURRENT_TIMESTAMP`
- ❌ `ON DUPLICATE KEY UPDATE`, `INSERT IGNORE`, `REPLACE INTO`
- ❌ `ON UPDATE CURRENT_TIMESTAMP` (se maneja a nivel de aplicación)
- ❌ Tipos exclusivos de Postgres: `JSONB`, arrays, `UUID` nativo

---

## Seguridad API + Infraestructura Docker

> **Ejecutado:** 2026-08-17–2026-08-21 | **Detalle completo:** [`Informacion_seguridad.md`](file:///c:/Proyectos/Cosmol-Chatbot/Docs/chichico-fabian/Plan_seguridad/Informacion_seguridad.md)

Hardening completo de la API PHP y su infraestructura Docker en 7 fases:

| Fase | Qué hace | Clase / Archivo clave |
|---|---|---|
| 1 — Token Interno | Valida `X-Internal-Token` entre n8n y PHP. `401` si falla. | `app/Core/Auth.php` |
| 2 — Validación de Inputs | Regex + lista blanca + sanitización antes de la capa de negocio. | `app/Core/Validator.php` |
| 3 — CORS Restrictivo | `Access-Control-Allow-Origin` solo al hostname de n8n. | `bootstrap.php` + `.env` |
| 4 — Rate Limiting | Máx. 30 peticiones/min por IP. `429` si se supera. | `app/Core/RateLimiter.php` |
| 5 — Logging Estructurado | JSON estructurado en `/var/log/cosmol_api.log`. | `app/Core/Logger.php` |
| 6 — Hardening `.env` | Convenciones dev/prod documentadas. Token regenerable. | `.env`, `env.example` |
| 7 — Aislamiento Docker | Backend sin puertos públicos. Perfil `dev` para desarrollo local. Red interna `cosmol_network`. | `docker-compose.yml` |

**Cómo conectan los servicios Docker:**
- `n8n` (puerto `5678`) → llama a `backend` vía red interna `cosmol_network` (`http://cosmol_backend/api/...`).
- `backend` → conecta a `db` (`cosmol_postgres:5432`) vía red interna.
- En desarrollo: `docker compose --profile dev up` expone `backend` en `localhost:8000`.
- En producción: solo `n8n` es accesible desde el exterior (a través del proxy inverso COSMOL con SSL).

---

> [!TIP]
> **Estructura y Arquitectura:** Para ver el árbol de archivos detallado y las decisiones de arquitectura, consulta el documento principal [ESTRUCTURA.md](../../ESTRUCTURA.md).
