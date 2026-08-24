# Guia de Arquitectura en Capas - Chatbot COSMOL

> **Audiencia:** Desarrolladores del equipo que necesitan entender como esta organizado el backend.
> **Patron:** Controller - Service - Repository (adaptado a API pura sin Frontend)

---

## Por que capas y no todo en un solo archivo?

Imagina que alguien escribe todo el codigo en un solo `index.php`:
- La conexion a la base de datos
- Las reglas de negocio (cuantos intentos tiene un socio)
- La validacion del token
- La respuesta JSON

Eso es codigo "espagueti". Si manana cambia la base de datos, tocas todo. Si manana cambia la regla de negocio, tocas todo. Si hay un bug, no sabes en que parte buscarlo.

**La arquitectura en capas separa cada responsabilidad en su propio lugar.** Cada capa habla unicamente con la que esta justo debajo de ella - nunca salta capas.

---

## El flujo completo de una peticion

Cuando n8n envia un mensaje (por ejemplo, un socio escribe `"267657"` en WhatsApp):

```
n8n (WhatsApp)
     |  HTTP POST con X-Internal-Token
     v
+----------------------------------------------------+
|  0. bootstrap.php (Infraestructura)               |  Seguridad, CORS, Auth, RateLimiter
+--------------------+-------------------------------+
                     | si pasa la seguridad
                     v
+----------------------------------------------------+
|  1. Controller - public/api/                      |  "Que quieres hacer?"
|  webhook_whatsapp.php / reclamos.php              |
+--------------------+-------------------------------+
                     | delega la logica
                     v
+----------------------------------------------------+
|  2. Service - app/Modules/                        |  "Esta bien hacerlo?"
|  SocioService / ReclamoService / SessionService   |
+--------------------+-------------------------------+
                     | pide datos
                     v
+----------------------------------------------------+
|  3. Repository - app/Data/Repositories/           |  "Donde estan los datos?"
|  Postgres/* / Api/*                               |
+--------------------+-------------------------------+
                     | SQL o HTTP
                     v
             PostgreSQL / API SAI
```

---

## Capa 0 - Infraestructura: app/bootstrap.php

**Que es?** El portero de seguridad. Se ejecuta **antes** de cualquier logica, en todos los endpoints.

**Archivo:** `app/bootstrap.php`

### Que hace en orden?

```
1. Autoloader PSR-4     -> Carga todas las clases automaticamente
2. database.php         -> Carga las constantes del entorno (.env)
3. Error reporting      -> En desarrollo muestra errores; en produccion los oculta
4. Headers CORS         -> Permite que n8n llame a la API desde su contenedor
5. Preflight OPTIONS    -> Responde 200 y sale (sin llegar a Auth ni RateLimiter)
6. Auth::validateInternalToken()  -> Valida el header X-Internal-Token (401 si falla)
7. RateLimiter::check() -> Limite de 30 peticiones/minuto por IP (429 si supera)
```

> [!IMPORTANT]
> El orden es **inamovible**. Si pones `Auth` despues de `RateLimiter`, un atacante sin token valido agotaria el contador de peticiones. Si pones CORS despues de `Auth`, un preflight sin token recibiria `401` en lugar de `200`.

### Las clases de seguridad que usa

| Clase | Archivo | Que hace |
|---|---|---|
| `Auth` | `app/Core/Auth.php` | Valida el token `X-Internal-Token` con `hash_equals()` para prevenir timing attacks. Si falla: `401`. |
| `RateLimiter` | `app/Core/RateLimiter.php` | Maximo 30 peticiones por minuto por IP. Almacena contadores en `/tmp`. Si supera: `429 + Retry-After: 60`. |
| `Validator` | `app/Core/Validator.php` | Valida los inputs antes de que lleguen al Servicio. Usada desde los Controllers. |
| `Logger` | `app/Core/Logger.php` | Escribe errores como JSON estructurado en `/var/log/cosmol_api.log`. |

---

## Capa 1 - Controller: public/api/

**Que es?** El punto de entrada HTTP. Recibe la peticion de n8n y delega al Servicio.

**Regla de oro:** El Controller **no contiene logica de negocio**. No decide si un socio es valido, no calcula totales de deudas. Solo:
1. Lee los parametros de la peticion
2. Llama al Servicio
3. Devuelve JSON

### Archivos

#### public/api/webhook_whatsapp.php - Controlador Central

Es el **corazon del chatbot**. Recibe todos los mensajes de WhatsApp a traves de n8n e implementa la **maquina de estados** del chatbot:

| Estado | Que significa? |
|---|---|
| `AWAITING_CODE` | El usuario aun no ha enviado su codigo de socio. |
| `MAIN_MENU` | El usuario esta autenticado y viendo el menu principal. |
| `BLOCKED` | El usuario supero los intentos maximos. Bloqueado por 5 minutos. |

**Como fluye el codigo internamente?**
```php
// 1. Recibe el mensaje de n8n (telefono, contenido, tipo)
// 2. Llama a SessionService para saber el estado actual de ese numero
// 3. Segun el estado, decide que plantilla enviar
// 4. Actualiza el estado en la BD
// 5. Responde siempre 200 OK a n8n (nunca rompe el flujo HTTP)
```

#### public/api/reclamos.php - Endpoint de Reclamos

Endpoint especializado para recibir `POST` con los datos de un reclamo nuevo.

```php
// Lo que hace:
// 1. Valida que el metodo sea POST
// 2. Lee el body JSON (getBody())
// 3. Valida inputs con Validator::codigoSocio() y Validator::tipoReclamo()
// 4. Instancia los repositorios e inyecta en ReclamoService
// 5. Llama a $service->registrarReclamo(...)
// 6. Responde 200 si exito, 400 si falla de validacion
```

### La clase base Controller

Todos los Controllers heredan de `app/Core/Controller.php`, que provee 3 metodos reutilizables:

| Metodo | Que hace |
|---|---|
| `json(array $data, int $status)` | Serializa el array como JSON, setea el HTTP status y termina el script. |
| `getBody(): ?array` | Lee el body de la peticion POST (`php://input`) y lo decodifica como JSON. |
| `handleError(string $msg, int $status)` | Llama a `json()` con `success: false` y el mensaje de error. |

---

## Capa 2 - Service: app/Modules/

**Que es?** El cerebro. Contiene todas las reglas de negocio de COSMOL. Es la unica capa que "piensa".

**Regla de oro:** El Service **no sabe nada de bases de datos ni de HTTP**. No tiene `PDO`, no tiene `curl`. Solo habla con Repositorios a traves de sus Interfaces.

### Archivos

#### app/Modules/Socio/SocioService.php

Gestiona toda la logica de los socios.

| Metodo | Logica de negocio que aplica |
|---|---|
| `validarSocio(string $cod)` | Limpia el codigo con `trim()`. Rechaza si no es numerico. Delega la busqueda al Repositorio. Limpia los datos devueltos (ej. `trim()` en el nombre). |
| `consultarDeuda(string $cod)` | Valida que el codigo no este vacio. Pide las deudas al Repositorio. |
| `obtenerDeudas(string $cod)` | Obtiene la lista de deudas, itera calculando el total acumulado, redondea a 2 decimales y devuelve un resumen con `cantidad_facturas` y `total_deuda`. |

**Como recibe el Repositorio?** Por inyeccion de dependencias en el constructor:
```php
// El Controller crea el repositorio y se lo pasa al Servicio
$socioRepo = new SocioRepository($pdo);           // <- Repositorio concreto
$service = new SocioService($socioRepo);          // <- Inyeccion
$service->validarSocio('267657');                 // <- El servicio lo usa sin saber su tipo
```

#### app/Modules/Reclamo/ReclamoService.php

Gestiona el registro de reclamos tecnicos (agua turbia, fuga, etc.).

**Logica clave:** La direccion del reclamo **no la da el usuario** por GPS. El Servicio la extrae automaticamente de los datos del socio en la base de datos. Asi se cumple el requisito de "ubicacion desde la BD" del AGENTS.md.

#### app/Modules/Session/SessionService.php

Gestiona el estado de la conversacion de cada numero de WhatsApp.

**Constantes de negocio definidas aqui:**
```php
private const MAX_ATTEMPTS = 200;               // Intentos antes de bloquear
private const INACTIVE_TIMEOUT_SECONDS = 60;    // 1 min de inactividad -> reset
private const BLOCKED_TIMEOUT_SECONDS = 300;    // 5 min bloqueado -> desbloqueo auto
```

| Metodo | Logica de negocio que aplica |
|---|---|
| `processSessionState(telefono, mensaje)` | Si no existe sesion: la crea. Si esta BLOQUEADO y pasaron 5 min: resetea. Si hay inactividad de 60s: resetea. Si todo esta bien: devuelve el estado actual. |
| `updateSession(telefono, codigo, estado, intentos)` | Antes de guardar, verifica si `$intentos >= MAX_ATTEMPTS`. Si si, fuerza `estado = 'BLOCKED'`. |
| `resetSession(telefono)` | Limpia el codigo del socio, vuelve a `AWAITING_CODE` y pone intentos a 0. |

#### app/Modules/Facturacion/ _(Reservado - Futuro Sprint 3)_

Carpeta creada y reservada para el modulo de facturacion. Aun vacia.

---

## Capa 3 - Repository: app/Data/

**Que es?** El unico lugar donde existe SQL o llamadas HTTP externas. Sabe como encontrar y guardar datos, pero no sabe que hacer con ellos.

Esta capa tiene dos partes: las **Interfaces** (el contrato) y las **Implementaciones** (el codigo real).

### 3.1 Interfaces: app/Data/Interfaces/

Son los contratos que garantizan que el Servicio pueda usar cualquier implementacion sin cambiar su codigo. Definen el **que** (metodos), no el **como** (SQL o HTTP).

| Interfaz | Metodos que define |
|---|---|
| `SocioRepositoryInterface` | `findByCodigo(string): ?array` - `findDeudasByCodigo(string): ?array` |
| `ReclamoRepositoryInterface` | `createReclamo(array): int` - `findByCodigoSocio(string): array` |
| `SessionRepositoryInterface` | `getSession(string): ?array` - `saveSession(...): bool` - `resetSession(string): bool` |

### 3.2 Implementaciones Postgres: app/Data/Repositories/Postgres/

Usadas en **desarrollo local**. Hablan con PostgreSQL 16 a traves de PDO.

#### Postgres/SocioRepository.php

```sql
-- findByCodigo(): consulta preparada contra inyeccion SQL
SELECT codigo_socio AS cod_socio, nombre, ci, telefono
FROM socio WHERE codigo_socio = :cod_socio LIMIT 1
```
> `findDeudasByCodigo()` retorna `null` directamente. Los datos reales de deudas viven en el SAI, no en la BD local.

#### Postgres/ReclamoRepository.php

```sql
-- createReclamo(): inserta y devuelve el ID generado (ANSI SQL con RETURNING)
INSERT INTO reclamo (codigo_socio, tipo_reclamo, descripcion, direccion, estado, fecha_creacion)
VALUES (:codigo_socio, :tipo, :descripcion, :direccion, 'PENDIENTE', CURRENT_TIMESTAMP)
RETURNING id
```

Nota: se usa `CURRENT_TIMESTAMP` (ANSI SQL) en lugar de `NOW()` (exclusivo de PostgreSQL/MySQL) para garantizar portabilidad a Informix en el Sprint 4.

#### Postgres/SessionRepository.php

Usa el patron `SELECT -> INSERT/UPDATE` (portabilidad ANSI) en lugar de `ON DUPLICATE KEY UPDATE` (exclusivo de MySQL).

### 3.3 Implementacion API Externa: app/Data/Repositories/Api/

#### Api/SocioRepository.php

Implementa la misma `SocioRepositoryInterface` pero en lugar de SQL, usa `ClienteApiCosmol` para hacer peticiones HTTP al servidor SAI real.

---

## Capa de Integracion: app/Integrations/CosmolApi/

**Que es?** Un cliente HTTP especializado que sabe hablar con la API externa de COSMOL (servidor SAI / Informix).

### ClienteApiCosmol.php

Lee la URL base desde la constante `COSMOL_API_URL` del `.env` y expone metodos de alto nivel:

| Metodo | Endpoint que llama |
|---|---|
| `obtenerSocio(string $codSocio)` | `GET /api-consultas/socios/{cod}` |
| `obtenerDeudasSocio(string $codSocio)` | `GET /api-consultas/socios/{cod}/deudas` |

Maneja los errores de red con Excepciones (curl falla, HTTP 4xx/5xx, respuesta no-JSON).

> **Por que esta separado del Repositorio?**
> Porque el cliente HTTP es un detalle de infraestructura. El Repositorio sabe *que pedir*, el Cliente sabe *como pedirlo* (timeouts, headers, errores de red). Si manana la API del SAI cambia, solo cambias `ClienteApiCosmol.php`.

---

## Capa de Presentacion: app/Presentacion/PlantillasWhatsApp/

**Que es?** La "Vista" adaptada a WhatsApp. Toma los datos del Servicio y los convierte en mensajes formateados con emojis, negritas y saltos de linea.

| Archivo | Que formatea? |
|---|---|
| `PlantillaSocio.php` | Bienvenida, menu principal, error de codigo invalido, mensaje de bloqueo. |
| `PlantillaFactura.php` | Lista de facturas pendientes, monto total de deuda, enlace de pago. |
| `PlantillaSistema.php` | Mensajes de error del sistema, reinicio de sesion, mensajes de inactividad. |

---

## Regla de dependencias (la mas importante)

```
Controller  ->  puede usar  ->  Service, Validator, Logger
Service     ->  puede usar  ->  Repository (solo a traves de Interface)
Repository  ->  puede usar  ->  PDO, ClienteApiCosmol
```

**Lo que NUNCA debe ocurrir:**
- Un Service haciendo `$pdo->query(...)` directamente (debe usar un Repositorio)
- Un Repository con logica de negocio (si lo tiene, deberia estar en el Service)
- Un Controller con `if ($totalDeuda > X)` (esa regla pertenece al Service)

---

## Resumen visual de archivos por capa

```
INFRAESTRUCTURA
  app/bootstrap.php
  app/Core/Autoloader.php
  app/Core/Auth.php           <- Seguridad: Token
  app/Core/RateLimiter.php    <- Seguridad: Rate limit
  app/Core/Validator.php      <- Seguridad: Inputs
  app/Core/Logger.php         <- Seguridad: Logs
  app/Core/Controller.php     <- Base de todos los endpoints
  app/Core/Database.php       <- Singleton PDO

CAPA 1: CONTROLLERS
  public/api/webhook_whatsapp.php    <- Controlador principal (maquina de estados)
  public/api/reclamos.php            <- Endpoint de reclamos

CAPA 2: SERVICES (NEGOCIO)
  app/Modules/Socio/SocioService.php
  app/Modules/Reclamo/ReclamoService.php
  app/Modules/Session/SessionService.php
  app/Modules/Facturacion/           <- Reservado (futuro Sprint 3)

CAPA 3: INTERFACES (CONTRATOS)
  app/Data/Interfaces/SocioRepositoryInterface.php
  app/Data/Interfaces/ReclamoRepositoryInterface.php
  app/Data/Interfaces/SessionRepositoryInterface.php

CAPA 3: REPOSITORIES (DATOS)
  app/Data/Repositories/Postgres/SocioRepository.php    <- dev: SQL PostgreSQL
  app/Data/Repositories/Postgres/ReclamoRepository.php  <- dev: SQL PostgreSQL
  app/Data/Repositories/Postgres/SessionRepository.php  <- dev: SQL PostgreSQL
  app/Data/Repositories/Api/SocioRepository.php         <- prod: HTTP al SAI

INTEGRACION EXTERNA
  app/Integrations/CosmolApi/ClienteApiCosmol.php       <- curl hacia API SAI

PRESENTACION
  app/Presentacion/PlantillasWhatsApp/PlantillaSocio.php
  app/Presentacion/PlantillasWhatsApp/PlantillaFactura.php
  app/Presentacion/PlantillasWhatsApp/PlantillaSistema.php
```
