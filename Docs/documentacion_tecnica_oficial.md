# Documentación Técnica Oficial — Chatbot COSMOL

> **Versión:** 1.0.0
> **Fecha de emisión:** 2026-08-21
> **Estado:** Operativo
> **Clasificación:** Documento Interno — Uso Exclusivo del Equipo de Desarrollo

---

## Tabla de Contenidos

1. [Arquitectura y Diseño del Sistema](#1-arquitectura-y-diseño-del-sistema)
   - 1.1 [Propósito y Alcance](#11-propósito-y-alcance)
   - 1.2 [Topología de Componentes](#12-topología-de-componentes)
   - 1.3 [Pila Tecnológica (Tech Stack)](#13-pila-tecnológica-tech-stack)
2. [Lógica del Orquestador (n8n)](#2-lógica-del-orquestador-n8n)
   - 2.1 [Arquitectura de Nodos y Flujos](#21-arquitectura-de-nodos-y-flujos)
   - 2.2 [Gestión de Sesión y Estado](#22-gestión-de-sesión-y-estado)
3. [Arquitectura del Backend (API PHP)](#3-arquitectura-del-backend-api-php)
   - 3.1 [Patrones de Diseño](#31-patrones-de-diseño)
   - 3.2 [Ciclo de Vida de la Petición](#32-ciclo-de-vida-de-la-petición)
   - 3.3 [Contratos de la API (Endpoints)](#33-contratos-de-la-api-endpoints)
4. [Integraciones y Persistencia de Datos](#4-integraciones-y-persistencia-de-datos)
   - 4.1 [Consumo de API Externa](#41-consumo-de-api-externa)
   - 4.2 [Base de Datos de Desarrollo](#42-base-de-datos-de-desarrollo)
   - 4.3 [Especificaciones de Producción](#43-especificaciones-de-producción)
5. [Políticas de Seguridad](#5-políticas-de-seguridad)
   - 5.1 [Autenticación de Servicios Internos](#51-autenticación-de-servicios-internos)
   - 5.2 [Mitigación de Vulnerabilidades](#52-mitigación-de-vulnerabilidades)
6. [Guía de Configuración y Desarrollo (Setup)](#6-guía-de-configuración-y-desarrollo-setup)
   - 6.1 [Requisitos Previos y Variables de Entorno](#61-requisitos-previos-y-variables-de-entorno)
   - 6.2 [Orquestación con Docker](#62-orquestación-con-docker)
   - 6.3 [Exposición Local (Webhooks)](#63-exposición-local-webhooks)

---

## 1. Arquitectura y Diseño del Sistema

### 1.1 Propósito y Alcance

El **Chatbot COSMOL** es una interfaz de atención automatizada al asociado, desplegada sobre el canal de mensajería WhatsApp. Su objetivo central es brindar acceso inmediato a los servicios de la Cooperativa COSMOL mediante un modelo de interacción conversacional de **fricción cero**: el asociado se identifica ingresando únicamente su Código de Asociado (Código Fijo), sin necesidad de contraseñas, formularios web ni intervención de un operador humano.

El alcance funcional del sistema comprende las siguientes capacidades:

- **Autenticación:** Validación de identidad del asociado a partir de su Código Fijo.
- **Consultas de Cuenta:** Visualización del historial de facturas, montos pendientes y estado de deuda.
- **Pagos Integrados:** Redirección directa a la pasarela de pagos Multipago (`https://multipago.com/service/cosmol_payment/first`).
- **Registro de Reclamos:** Captura estructurada de incidencias técnicas (agua turbia, fugas, sin servicio, presión baja) con resolución de ubicación desde los datos del sistema central, sin requerir GPS del usuario.
- **Reconexiones:** Evaluación de antigüedad de deuda y emisión de órdenes al sistema según las reglas de negocio establecidas.

> [!IMPORTANT]
> El sistema es un backend puro orientado a APIs e integraciones. No existe un portal web o aplicación frontend administrativo. La totalidad de la interacción con el usuario final se realiza a través de los flujos y plantillas de WhatsApp gestionadas por el orquestador n8n.

---

### 1.2 Topología de Componentes

El sistema opera como una cadena de microservicios con flujo de información bidireccional, compuesta por cuatro capas funcionales:

```
┌─────────────────────────────────────────────────────────────────┐
│                        USUARIO FINAL                            │
│                    (Asociado de COSMOL)                         │
└───────────────────────────┬─────────────────────────────────────┘
                            │  WhatsApp
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│             META WHATSAPP CLOUD API                             │
│         Graph API v20.0  ·  Webhooks POST                       │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTPS  (Webhook entrante)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│               ORQUESTADOR: n8n (Self-Hosted)                    │
│   Ingesta → Filtro → Switch → Formateadores → HTTP Request      │
│                 Contenedor: cosmol_n8n                          │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTP interno  (Red Docker: cosmol_network)
                            │  Header: X-Internal-Token
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND API: PHP 7.3 + Apache                      │
│     bootstrap → Auth → RateLimiter → Service → Repository      │
│               Contenedor: cosmol_php_backend                    │
└──────────────┬──────────────────────────────────┬──────────────┘
               │  PDO (red interna)               │  cURL / HTTP
               ▼                                  ▼
┌──────────────────────┐             ┌────────────────────────────┐
│  Base de Datos Local │             │  APIs REST del Sistema SAI │
│  PostgreSQL 16-alpine  │             │  (IBM Informix — Prod.)    │
│  Contenedor: db        │             │  COSMOL_API_URL            │
└──────────────────────┘             └────────────────────────────┘
```

El flujo canónico de una petición es el siguiente:

1. El asociado envía un mensaje o interactúa con un botón en WhatsApp.
2. Meta WhatsApp Cloud API reenvía el evento como un `POST` al Webhook de n8n.
3. n8n evalúa el mensaje entrante, filtra ruido y enruta la solicitud al backend PHP mediante una petición HTTP interna a la red Docker.
4. La API PHP autentica la petición, ejecuta la lógica de negocio y consulta la fuente de datos correspondiente (PostgreSQL local en desarrollo; APIs REST del SAI en producción).
5. La API PHP devuelve un JSON estructurado a n8n.
6. n8n formatea la respuesta y envía el mensaje final al asociado a través de la API de Meta.

---

### 1.3 Pila Tecnológica (Tech Stack)

| Capa | Tecnología | Versión | Rol |
|---|---|---|---|
| Canal de Comunicación | Meta WhatsApp Cloud API | Graph API v20.0 | Recepción y envío de mensajes de WhatsApp |
| Orquestador | n8n (Self-Hosted) | `docker.n8n.io/n8nio/n8n:1` | Gestión de flujos de conversación y webhooks |
| Backend | PHP (Vanilla) + Apache | 7.3 (`php:7.3-apache`) | Lógica de negocio y exposición de la API REST interna |
| Base de datos (desarrollo) | PostgreSQL | 16-alpine | Persistencia local simulada del sistema SAI (ANSI SQL) |
| Base de datos (producción) | IBM Informix (via APIs REST) | — | Sistema central SAI de COSMOL |
| Contenedorización | Docker + Docker Compose | Última estable | Orquestación de todos los servicios |
| Túnel de desarrollo | ngrok | Última estable | Exposición temporal del webhook local a Meta |
| Driver de BD (desarrollo) | `pdo_pgsql` | Nativo PHP 7.3 | Conexión PDO a PostgreSQL |
| Driver de BD (producción) | `curl` nativo | Nativo PHP | Cliente HTTP para las APIs REST del SAI |
| Pasarela de pagos | Multipago | — | Procesamiento de pagos de facturas |

**Extensiones PHP compiladas en el contenedor:**

```dockerfile
FROM php:7.3-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT /app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
```

> [!NOTE]
> El `DocumentRoot` de Apache apunta a `/app/public`, garantizando que el código fuente ubicado en `/app` nunca sea accesible directamente desde el exterior. Solo los archivos en `public/api/` son puntos de entrada HTTP válidos.

---

## 2. Lógica del Orquestador (n8n)

### 2.1 Arquitectura de Nodos y Flujos

El flujo de n8n está estructurado en tres capas lógicas independientes y secuenciales:

#### Capa 1 — Ingesta y Filtro

| Nodo | Tipo | Función |
|---|---|---|
| **Webhook (POST)** | Trigger | Recibe la totalidad del tráfico entrante de Meta WhatsApp Cloud API |
| **IF (Filtro de Ruido)** | Condición | Descarta actualizaciones de estado (`delivered`, `read`) y deja pasar únicamente mensajes de texto e interacciones de botones interactivos |

Esta capa garantiza que el flujo de enrutamiento solo procese eventos accionables, evitando ejecuciones innecesarias ante confirmaciones de entrega.

#### Capa 2 — Enrutamiento y Lógica de Negocio

El núcleo del flujo es el **Nodo Switch**, que evalúa el contenido del mensaje o el `id` del botón presionado y ramifica la ejecución en tres rutas:

| Ruta | Condición de Activación | Acción |
|---|---|---|
| **Ruta 1 — Autenticación** | El usuario escribe un valor numérico (Código de Socio) | n8n enruta hacia `webhook_whatsapp.php` para validar la identidad y generar la sesión |
| **Ruta 2 — Menú / Deuda** | El payload del botón es `MENU_PAGAR_...` | n8n enruta hacia `webhook_whatsapp.php` que consulta facturas y entrega el menú correspondiente |
| **Ruta 3 — Reclamos** | El payload del botón es `BTN_RECLAMO` | n8n captura los datos del problema y delega el registro a `/api/reclamos.php` |

#### Capa 3 — Capa de Presentación (Formateadores)

Para construir mensajes con botones interactivos, n8n utiliza **Nodos Set** que ensamblan el JSON estricto exigido por la API de Meta antes de ejecutar el `HTTP Request` final hacia `https://graph.facebook.com/v20.0/{phone_number_id}/messages`.

A continuación se muestra la estructura del menú principal con tres botones de acción:

```json
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "{{Telefono_Destino}}",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "body": { "text": "¡Hola! Bienvenido a COSMOL. ¿En qué podemos ayudarte hoy?" },
    "action": {
      "buttons": [
        { "type": "reply", "reply": { "id": "BTN_DEUDA",   "title": "Ver Deuda"      } },
        { "type": "reply", "reply": { "id": "BTN_PAGAR",   "title": "Pagar Servicio" } },
        { "type": "reply", "reply": { "id": "BTN_RECLAMO", "title": "Reclamos"       } }
      ]
    }
  }
}
```

---

### 2.2 Gestión de Sesión y Estado

n8n es inherentemente *stateless* entre ejecuciones: cada webhook recibido es procesado de forma aislada, sin memoria de la conversación previa. El sistema resuelve esta limitación mediante el mecanismo de **payloads de botones interactivos de Meta**.

**Principio de funcionamiento:**

Cuando n8n envía un mensaje con botones al usuario, cada botón lleva un campo `id` (el payload) definido en el Nodo Set de la capa de presentación. Cuando el usuario presiona un botón, Meta devuelve ese `id` oculto en la siguiente petición al webhook. El **Nodo Switch** extrae este identificador y lo usa como señal de estado para determinar el contexto de la conversación y enrutar la ejecución al flujo correspondiente.

**Ejemplo del ciclo completo:**

```
1. n8n envía menú → usuario ve botones: BTN_DEUDA, BTN_PAGAR, BTN_RECLAMO
2. Usuario presiona "Reclamos"
3. Meta envía a n8n: { "button": { "payload": "BTN_RECLAMO", ... } }
4. Switch evalúa payload === "BTN_RECLAMO" → activa Ruta 3 (Reclamos)
5. n8n ejecuta la lógica de captura de reclamo
```

Este mecanismo no requiere almacenamiento de sesión en base de datos ni cookies. El contexto de la conversación está codificado en los propios payloads que Meta devuelve, simplificando la arquitectura y eliminando dependencias de estado externas.

---

## 3. Arquitectura del Backend (API PHP)

### 3.1 Patrones de Diseño

El backend adopta una **arquitectura por capas** basada en el patrón **Service-Repository** (Controller → Service → Repository), adaptada a una API consumida exclusivamente por n8n sin frontend HTML.

**Estructura de directorios:**

```text
cosmol-chatbot/
├── app/
│   ├── Config/
│   │   └── database.php              ← Constantes de entorno y conexión a BD
│   ├── Core/
│   │   ├── Auth.php                  ← Validación del token interno (Fase 1 — Seguridad)
│   │   ├── Autoloader.php            ← Autocarga de clases PSR-4 (spl_autoload_register)
│   │   ├── Controller.php            ← Métodos base: json(), getBody(), handleError()
│   │   ├── Database.php              ← Singleton de conexión PDO (multi-driver)
│   │   ├── Logger.php                ← Logger estructurado JSON (/var/log/cosmol_api.log)
│   │   ├── RateLimiter.php           ← Rate limiting por IP (30 req/min)
│   │   └── Validator.php             ← Validación y sanitización de inputs
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── SocioRepositoryInterface.php
│   │   │   ├── ReclamoRepositoryInterface.php
│   │   │   └── SessionRepositoryInterface.php
│   │   └── Repositories/
│   │       ├── Postgres/
│   │       │   ├── SocioRepository.php
│   │       │   ├── ReclamoRepository.php
│   │       │   └── SessionRepository.php
│   │       ├── Api/
│   │       │   └── SocioRepository.php
│   │       └── SAI/                  ← Sprint 4: Repositorios HTTP hacia APIs REST del SAI
│   ├── Integrations/
│   │   └── CosmolApi/
│   │       └── ClienteApiCosmol.php  ← Cliente cURL para la API central de COSMOL
│   ├── Modules/
│   │   ├── Socio/
│   │   │   └── SocioService.php
│   │   ├── Reclamo/
│   │   │   └── ReclamoService.php
│   │   └── Session/
│   │       └── SessionService.php
│   ├── Presentacion/
│   │   └── PlantillasWhatsApp/       ← Ensambladores de payloads JSON para WhatsApp
│   │       ├── PlantillaFactura.php
│   │       ├── PlantillaSistema.php
│   │       └── PlantillaSocio.php
│   └── bootstrap.php                 ← Inicializador global del sistema
├── public/
│   └── api/
│       ├── reclamos.php              ← Registro de reclamos técnicos
│       └── webhook_whatsapp.php      ← Controlador Frontal Centralizado (Máquina de Estados)
├── database/
│   └── init.sql                      ← Esquema ANSI SQL canónico para Docker
├── docker-compose.yml
└── .env
```

**Responsabilidades de cada capa:**

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| **Controller (Endpoint)** | `public/api/*.php` | Recibe la petición HTTP de n8n, extrae parámetros, invoca al Service y devuelve el JSON de respuesta |
| **Service (Negocio)** | `app/Modules/*/` | Contiene las reglas de negocio. Tiene prohibición explícita de acceder a la BD directamente; delega a los Repositories |
| **Repository (Datos)** | `app/Data/Repositories/` | Obtiene y persiste datos. En desarrollo: SQL a PostgreSQL. En producción: HTTP a las APIs REST del SAI |
| **Interface (Contrato)** | `app/Data/Interfaces/` | Garantiza que todos los repositorios expongan los mismos métodos, independientemente del motor de datos |

> [!IMPORTANT]
> El valor arquitectónico clave reside en la **intercambiabilidad de repositorios**: el `SocioService` nunca conoce si está interactuando con PostgreSQL local o con las APIs REST del SAI. Solo habla con `SocioRepositoryInterface`. Este principio permite la migración completa a Informix (Sprint 4) sin modificar una sola línea de la lógica de negocio.

---

### 3.2 Ciclo de Vida de la Petición

Cada endpoint incluye como primera instrucción la carga de `app/bootstrap.php`, que actúa como **controlador frontal** e inicializador global. El orden de ejecución es determinístico e inamovible.

```php
<?php
declare(strict_types=1);

// Paso 1: Carga del autoloader PSR-4 manual
require_once __DIR__ . '/Core/Autoloader.php';

// Paso 2: Carga de configuración global y constantes
require_once __DIR__ . '/Config/database.php';

// Paso 3: Configuración de error reporting según el entorno
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// Paso 4: Emisión de headers CORS con origen restringido
header('Content-Type: application/json; charset=utf-8');
$origin = defined('ALLOWED_ORIGIN') ? ALLOWED_ORIGIN : '';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Internal-Token');

// Paso 5: Respuesta a solicitudes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Paso 6: Validación del token interno (Fase 1 — Seguridad)
use App\Core\Auth;
Auth::validateInternalToken();

// Paso 7: Rate Limiting por IP (Fase 4 — Seguridad)
use App\Core\RateLimiter;
RateLimiter::check();
```

**Justificación del orden de ejecución:**

- El `OPTIONS` preflight **debe preceder** a `Auth` y `RateLimiter` para no bloquear solicitudes preflight de CORS.
- `Auth` **debe preceder** a `RateLimiter`: los atacantes sin token válido reciben `401` sin consumir el contador de rate limiting.
- Los headers CORS se emiten **antes** de `RateLimiter` para que las respuestas `429` incluyan el header correcto y n8n no las interprete como fallos de CORS.

**Autoloader PSR-4:**

`app/Core/Autoloader.php` implementa `spl_autoload_register` de forma nativa, siguiendo el estándar PSR-4, sin dependencia de Composer. El namespace raíz `App\` se mapea al directorio `app/`, eliminando todos los `require_once` manuales.

---

### 3.3 Contratos de la API (Endpoints)

Todos los endpoints devuelven una estructura JSON estandarizada.

**Estructura de respuesta exitosa:**
```json
{ "success": true, "message": "Descripción.", "data": { "...": "..." } }
```

**Estructura de respuesta de error:**
```json
{ "success": false, "message": "Descripción del error.", "data": null }
```

**Catálogo de Endpoints:**

| Archivo | Método | URL Ejemplo Interna | Descripción |
|---|---|---|---|
| `webhook_whatsapp.php` | `POST` | `http://cosmol_php_backend/api/webhook_whatsapp.php` | Controlador frontal único (Máquina de Estados) que procesa todos los mensajes de WhatsApp |
| `reclamos.php` | `POST` | `http://cosmol_php_backend/api/reclamos.php` | Registro de reclamos técnicos y comerciales |

---

**`POST /api/socio.php` — Validación de Socio**

Procesa la totalidad de interacciones conversacionales de WhatsApp provenientes de n8n. Administra la sesión de usuario, timeouts, validación de socio, consulta de facturas y ensambla los payloads interactivos de WhatsApp (`whatsapp_payload`). Soporta `application/json` y `application/x-www-form-urlencoded`.

| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `telefono` | `string` | Sí | Número de WhatsApp del usuario (ej. `"59170000000"`) |
| `tipo_mensaje` | `string` | Sí | Tipo de evento: `"text"` o `"interactive"` |
| `contenido` | `string` | Sí | Texto ingresado (código fijo) o payload del botón presionado (ej. `"MENU_PAGAR_2587"`, `"MENU_AGENTE"`) |

Respuesta exitosa — Menú Principal generado (`HTTP 200 OK`):
```json
{
  "status": "success",
  "estado": "MAIN_MENU",
  "whatsapp_payload": {
    "type": "interactive",
    "interactive": {
      "type": "list",
      "header": { "type": "text", "text": "Menú Principal" },
      "body": { "text": "Su Código Fijo 267657 (JUAN PEREZ) ha sido validado.\n\n¿En qué puedo ayudarle? Por favor, haga clic en Mostrar Menú." },
      "footer": { "text": "COSMOL - Tu cooperativa" },
      "action": {
        "button": "Mostrar Menú",
        "sections": [
          {
            "title": "Opciones",
            "rows": [
              { "id": "MENU_PAGAR_267657", "title": "Pagar Deuda", "description": "Consultar y pagar tus facturas" },
              { "id": "MENU_AGENTE", "title": "Consultar con un agente", "description": "Soporte y registro de reclamos" },
              { "id": "MENU_CAMBIAR_CODIGO", "title": "Consultar otro Socio", "description": "Ingresar un código fijo diferente" }
            ]
          }
        ]
      }
    }
  }
}
```

Respuesta exitosa — Consulta de Deudas con enlace Multipago:
```json
{
  "status": "success",
  "estado": "MAIN_MENU",
  "whatsapp_payload": {
    "type": "interactive",
    "interactive": {
      "type": "list",
      "body": {
        "text": "El Código Fijo (267657) tiene 2 facturas impagas, cuyo monto total es 221,90 Bs.\nEl detalle es el siguiente:\n\n1. Junio-2026, 107,60 Bs. (Pendiente)\n2. Julio-2026, 114,30 Bs. (Pendiente)\n\n💳 *Link de pago seguro:*\nhttps://multipago.com/service/cosmol_payment/first\n\n¿Necesitas algún otro servicio? Por favor, usa el menú 👇"
      }
    }
  }
}
```

---

**`POST /api/reclamos.php` — Registro de Reclamos Técnicos y Comerciales**

Registra un nuevo reclamo en el sistema. La ubicación se obtiene automáticamente de los datos registrados del socio (no requiere GPS).

| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `codigo_socio` | `string` | Sí | Código numérico del asociado (1–10 dígitos) |
| `tipo_reclamo` | `string` | Sí | Lista blanca: `agua_turbia`, `fuga`, `sin_servicio`, `presion_baja`, `otro` |
| `descripcion` | `string` | No | Texto libre, máximo 500 caracteres, sin HTML |

Respuesta exitosa (`HTTP 200 OK`):
```json
{
  "success": true,
  "message": "Reclamo registrado correctamente con el ticket #42.",
  "data": { "ticket_id": 42 }
}
```

Respuesta — Validación fallida (`HTTP 400 Bad Request`):
```json
{
  "success": false,
  "message": "El campo tipo_reclamo es inválido o no permitido.",
  "data": null
}
```

**Códigos de respuesta HTTP:**

| Código | Condición |
|---|---|
| `200 OK` | Petición procesada correctamente |
| `400 Bad Request` | Parámetro ausente o con formato inválido |
| `401 Unauthorized` | Header `X-Internal-Token` ausente o incorrecto |
| `405 Method Not Allowed` | Método HTTP no soportado |
| `429 Too Many Requests` | Límite de 30 peticiones por minuto excedido |
| `500 Internal Server Error` | Error en el servidor o en la integración con el sistema SAI |

---

## 4. Integraciones y Persistencia de Datos

### 4.1 Consumo de API Externa

La clase `ClienteApiCosmol` (`app/Integrations/CosmolApi/ClienteApiCosmol.php`) actúa como cliente HTTP centralizado para la comunicación con el sistema central de COSMOL. Implementa el patrón **Backend for Frontend (BFF)**: recibe la solicitud de n8n, la traduce a la llamada HTTP correspondiente hacia la API externa, procesa la respuesta y devuelve un JSON normalizado.

**Métodos públicos:**

| Método | Endpoint externo consultado | Descripción |
|---|---|---|
| `obtenerSocio(string $codSocio)` | `GET /api-consultas/socios/{cod_socio}` | Datos de identificación del asociado |
| `obtenerDeudasSocio(string $codSocio)` | `GET /api-consultas/socios/{cod_socio}/deudas` | Listado de facturas impagas del asociado |

**Implementación del cliente cURL:**

```php
private function hacerPeticion(string $endpoint): ?array
{
    $url = rtrim($this->baseUrl, '/') . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Error al conectar con la API de Cosmol: " . $error);
    }
    if ($httpCode >= 400 && $httpCode !== 404) {
        throw new Exception("La API de Cosmol respondió con error HTTP: " . $httpCode);
    }
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Respuesta inválida de la API de Cosmol (No es JSON).");
    }
    return $decoded;
}
```

**Cadena de delegación para la integración:**

```
n8n → socio.php (Endpoint)
      └→ SocioService (lógica de negocio)
         └→ RepositorioSocioApi (implementa SocioRepositoryInterface)
            └→ ClienteApiCosmol (cliente cURL)
               └→ API Central COSMOL (COSMOL_API_URL/api-consultas/...)
```

La URL base se configura exclusivamente mediante la variable de entorno `COSMOL_API_URL` en `.env`, cargada como constante en `app/Config/database.php`. El código fuente no contiene ninguna URL codificada de manera fija.

---

### 4.2 Base de Datos de Desarrollo

El entorno de desarrollo local utiliza **PostgreSQL 16-alpine** como motor de base de datos simulada del sistema SAI. El esquema sigue estrictamente el estándar **ANSI SQL**, garantizando la máxima compatibilidad posible con IBM Informix en la migración a producción.

**Restricciones de modelado aplicadas:**

- Tipos permitidos: `VARCHAR(n)`, `INT`, `NUMERIC(10,2)`, `DATE`, `TIMESTAMP`, `BOOLEAN`, `SERIAL`
- Prohibido: `AUTO_INCREMENT`, `ON DUPLICATE KEY UPDATE`, `INSERT IGNORE`, `NOW()`, `ON UPDATE CURRENT_TIMESTAMP`
- Prohibido: Tipos exclusivos de PostgreSQL (`JSONB`, `UUID` nativo, arrays `TEXT[]`)

**Esquema de la base de datos (`database/init.sql`):**

```sql
CREATE TABLE IF NOT EXISTS socio (
    codigo_socio     INT          PRIMARY KEY,
    ci               VARCHAR(20)  NOT NULL,
    nombre           VARCHAR(80)  NOT NULL,
    apellido         VARCHAR(80)  NOT NULL,
    telefono         VARCHAR(20)  NOT NULL,
    direccion        VARCHAR(255) DEFAULT 'Sin dirección',
    estado_conexion  BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS reclamo (
    id               SERIAL       PRIMARY KEY,
    tipo_reclamo     VARCHAR(50)  NOT NULL,
    descripcion      VARCHAR(500),
    direccion        VARCHAR(255) NOT NULL,
    estado           VARCHAR(20)  DEFAULT 'PENDIENTE',
    fecha_creacion   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    codigo_socio     INT          NOT NULL,
    CONSTRAINT fk_reclamo_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

CREATE TABLE IF NOT EXISTS factura (
    id                SERIAL        PRIMARY KEY,
    codigo_socio      INT           NOT NULL,
    periodo           VARCHAR(20)   NOT NULL,
    monto             NUMERIC(10,2) NOT NULL,
    estado            VARCHAR(20)   DEFAULT 'PENDIENTE',
    fecha_emision     DATE,
    fecha_vencimiento DATE,
    CONSTRAINT fk_factura_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

CREATE TABLE IF NOT EXISTS chat_session (
    telefono_whatsapp  VARCHAR(20) PRIMARY KEY,
    codigo_socio       INT         NULL,
    estado_actual      VARCHAR(50) DEFAULT 'AWAITING_CODE',
    intentos_fallidos  INT         DEFAULT 0,
    ultima_interaccion TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);
```

**Resolución de DSN multi-driver en `app/Core/Database.php`:**

```php
if ($driver === 'pgsql' || $driver === 'postgres') {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};options='--client_encoding=UTF8'";
} elseif ($driver === 'informix') {
    $dsn = "informix:host={$host};service={$port};database={$dbName};";
} else {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
}
```

---

### 4.3 Especificaciones de Producción

En producción (Sprint 4), los repositorios locales (`app/Data/Repositories/Postgres/`) son reemplazados por sus equivalentes en `app/Data/Repositories/SAI/`, que implementan las mismas interfaces pero obtienen los datos mediante peticiones HTTP hacia las **APIs REST del sistema SAI** (servidor Informix).

**Principio de intercambiabilidad:**

```
SocioService → SocioRepositoryInterface (contrato inalterado)
                     │
                     ├── Postgres/SocioRepository  (desarrollo: SQL → PostgreSQL)
                     └── SAI/SocioRepository       (producción: HTTP → servidor Informix)
```

**Requisitos previos para habilitar la integración SAI:**

| Requisito | Descripción |
|---|---|
| URL base del SAI | Endpoint base de las APIs REST del servidor Informix |
| Token de autenticación | API Key o Bearer Token provisto por el equipo del SAI |
| Documentación de endpoints | Rutas, métodos y formatos de respuesta JSON del SAI |
| Validación de contratos | Compatibilidad de los datos del SAI con las interfaces existentes |

**Variables de entorno a actualizar para producción:**

```env
APP_ENV=production
COSMOL_API_URL=https://api.cosmol.com.bo/api-consultas/
COSMOL_API_TOKEN=tu_token_de_produccion
```

> [!NOTE]
> El cambio de repositorio en cada endpoint es el único ajuste de código necesario. Los servicios, `bootstrap.php`, el autoloader y los contratos de interfaz no requieren ninguna modificación durante la migración.

---

## 5. Políticas de Seguridad

### 5.1 Autenticación de Servicios Internos

El contenedor `cosmol_php_backend` está conectado exclusivamente a la red Docker interna `cosmol_network` y no expone puertos al host en modo producción. Para garantizar que solo n8n pueda invocar los endpoints dentro de la red, se implementa un **mecanismo de autenticación por token compartido**.

**Mecanismo:** El secreto `API_INTERNAL_TOKEN` es enviado por n8n en el header HTTP `X-Internal-Token` en cada petición hacia la API PHP.

**Implementación (`app/Core/Auth.php`):**

```php
namespace App\Core;

class Auth
{
    public static function validateInternalToken(): void
    {
        $token    = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';
        $expected = defined('API_INTERNAL_TOKEN') ? API_INTERNAL_TOKEN : '';

        if (empty($expected) || !hash_equals($expected, $token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.', 'data' => null]);
            exit;
        }
    }
}
```

**Flujo de autenticación:**

```
n8n (HTTP Request)
  Headers: X-Internal-Token: <valor de API_INTERNAL_TOKEN>
  |
  v
bootstrap.php → Auth::validateInternalToken()
  |-- Token ausente o incorrecto → HTTP 401 + {"success": false} → exit
  └-- Token válido → RateLimiter::check() → Endpoint
```

**Contrato de respuesta de error unificado:**

| Capa | HTTP | Respuesta |
|---|---|---|
| `Auth::validateInternalToken()` | 401 | `{ "success": false, "message": "No autorizado.", "data": null }` |
| `RateLimiter::check()` | 429 | `{ "success": false, "message": "Demasiadas peticiones. Intenta en 60 segundos.", "data": null }` |
| `Validator::*()` en endpoint | 400 | `{ "success": false, "message": "...", "data": null }` |
| `Controller::handleError()` | 4xx/5xx | `{ "success": false, "message": "...", "data": null }` |

---

### 5.2 Mitigación de Vulnerabilidades

El sistema implementa múltiples capas de defensa independientes:

#### Prevención de Timing Attacks

La comparación del `API_INTERNAL_TOKEN` utiliza `hash_equals()` en lugar del operador `===`. Esta función realiza la comparación en **tiempo constante**, independientemente de cuántos caracteres coincidan, impidiendo que un atacante deduzca el token correcto midiendo tiempos de respuesta.

```php
// Vulnerable: la comparacion se detiene en el primer caracter diferente
if ($token === $expected) { ... }

// Seguro: el tiempo de comparacion es siempre constante
if (!hash_equals($expected, $token)) { ... }
```

#### Rate Limiting por IP

`app/Core/RateLimiter.php` implementa un limitador de velocidad por IP sin dependencias externas, compatible con PHP 7.3 puro. El umbral es de **30 peticiones por minuto** por IP de origen. Al superarlo retorna `HTTP 429` con el header `Retry-After: 60`.

#### Validación y Sanitización de Inputs

`app/Core/Validator.php` aplica reglas estrictas sobre todos los parámetros antes de que lleguen a la capa de servicio:

```php
class Validator
{
    // Solo digitos, 1-10 caracteres
    public static function codigoSocio(?string $value): bool {
        return (bool) preg_match('/^\d{1,10}$/', $value ?? '');
    }

    // Lista blanca de valores permitidos
    public static function tipoReclamo(?string $value): bool {
        $allowed = ['agua_turbia', 'fuga', 'sin_servicio', 'presion_baja', 'otro'];
        return in_array($value, $allowed, true);
    }

    // Texto libre: max 500 caracteres, sin HTML
    public static function descripcion(?string $value): bool {
        if ($value === null || strlen($value) > 500) return false;
        return strip_tags($value) === $value;
    }
}
```

#### Restricción de CORS

El header `Access-Control-Allow-Origin` está restringido al hostname del contenedor n8n dentro de la red Docker. El valor comodín `*` no se utiliza en ningún entorno.

```php
$origin = defined('ALLOWED_ORIGIN') ? ALLOWED_ORIGIN : '';
header("Access-Control-Allow-Origin: {$origin}");
```

#### Aislamiento de Red Docker

```
Internet → :5678 → cosmol_n8n ─(cosmol_network)─→ cosmol_php_backend
                                                  └→ cosmol_postgres

n8n:        accesible públicamente (recibe Webhooks de Meta)
PHP:        aislado, solo accesible desde cosmol_network
PostgreSQL: aislado, solo accesible desde cosmol_network
```

#### Logging Estructurado

`app/Core/Logger.php` escribe cada evento en `/var/log/cosmol_api.log` en formato JSON por línea, con campos `timestamp`, `level`, `message` y `context`, facilitando el análisis y correlación de eventos en producción.

```php
Logger::error('Error critico en SocioEndpoint', [
    'exception'    => $e->getMessage(),
    'codigo_socio' => $cod_socio ?? null,
    'action'       => $action ?? null,
]);
```

---

## 6. Guía de Configuración y Desarrollo (Setup)

### 6.1 Requisitos Previos y Variables de Entorno

**Software requerido:**

- Docker Desktop (última versión estable, incluye Docker Compose)
- ngrok (para desarrollo con webhooks de Meta)
- Git

**Configuración inicial:**

```bash
cp env.example .env
# Editar .env con los valores reales del entorno
```

**Tabla de variables de entorno:**

| Variable | Descripción | Desarrollo | Producción |
|---|---|---|---|
| `N8N_HOST` | Host de escucha de n8n | `0.0.0.0` | `0.0.0.0` |
| `N8N_PORT` | Puerto de la interfaz de n8n | `5678` | `5678` |
| `N8N_PROTOCOL` | Protocolo de n8n | `http` | `https` |
| `NODE_ENV` | Entorno de Node.js | `production` | `production` |
| `GENERIC_TIMEZONE` | Zona horaria del sistema | `America/La_Paz` | `America/La_Paz` |
| `WEBHOOK_URL` | URL pública del webhook de n8n | URL de ngrok | Dominio real con SSL |
| `DB_DRIVER` | Driver de base de datos | `pgsql` | `pgsql` o `informix` |
| `DB_HOST` | Host de la BD | `db` (contenedor) | IP del servidor de producción |
| `DB_PORT` | Puerto de la BD | `5432` | Puerto del servidor de producción |
| `DB_NAME` | Nombre de la base de datos | `chatbot_cosmol` | Nombre en producción |
| `DB_USER` | Usuario de la BD | `cosmol` | Usuario de producción |
| `DB_PASSWORD` | Contraseña de la BD | `<secreto>` | `<secreto>` |
| `DB_ROOT_PASSWORD` | Contraseña del superusuario | `<secreto>` | `<secreto>` |
| `DB_CHARSET` | Charset de la conexión | `utf8` | `utf8` |
| `APP_ENV` | Entorno de la aplicación PHP | `development` | `production` |
| `APP_DEBUG` | Modo debug (expone errores) | `true` | `false` |
| `API_INTERNAL_TOKEN` | Token secreto compartido n8n ↔ PHP (mín. 64 hex chars) | `<generado>` | `<regenerar en deploy>` |
| `ALLOWED_ORIGIN` | Origen CORS permitido | `http://cosmol_n8n:5678` | `https://tu_dominio_n8n.com` |
| `COSMOL_API_URL` | URL base de la API del sistema SAI | vacío (usa PostgreSQL local) | `https://api.cosmol.com.bo` |
| `COSMOL_API_TOKEN` | Token hacia la API del SAI | vacío | Token provisto por el equipo SAI |

**Generación del `API_INTERNAL_TOKEN`:**

```bash
openssl rand -hex 32
```

> [!CAUTION]
> El archivo `.env` contiene credenciales sensibles y nunca debe subirse al repositorio de Git. Verificar que `.gitignore` lo incluye explícitamente. El `API_INTERNAL_TOKEN` debe regenerarse con `openssl rand -hex 32` en cada despliegue a producción.

---

### 6.2 Orquestación con Docker

**Servicios definidos en `docker-compose.yml`:**

| Servicio | Contenedor | Imagen | Puerto Expuesto |
|---|---|---|---|
| `n8n` | `cosmol_n8n` | `docker.n8n.io/n8nio/n8n:1` | `5678:5678` (público) |
| `backend` | `cosmol_php_backend` | `php:7.3-apache` (build local) | `8000:80` (solo perfil `dev`) |
| `db` | `cosmol_postgres` | `postgres:16-alpine` | Red interna (`5433:5432` en host dev) |

**Comandos esenciales:**

```bash
# Construir imagenes e iniciar todos los servicios en segundo plano
docker compose up -d --build

# Iniciar en modo desarrollo (expone puerto 8000 del backend)
docker compose --profile dev up -d --build

# Detener todos los servicios (preserva volumenes y datos)
docker compose down

# Detener y eliminar todos los volumenes (limpieza completa)
docker compose down -v

# Ver logs en tiempo real de todos los servicios
docker compose logs -f

# Ver logs del backend PHP
docker compose logs -f backend

# Ver logs de n8n
docker compose logs -f n8n

# Verificar el estado de salud de los contenedores
docker compose ps

# Reconstruir el contenedor del backend (tras cambios en el Dockerfile)
docker compose build backend && docker compose up -d backend

# Acceder a la consola del contenedor del backend
docker exec -it cosmol_php_backend bash

# Verificar conectividad interna n8n → backend PHP
docker exec -it cosmol_n8n sh -c "wget -q -O- http://cosmol_php_backend/api/socio.php"
```

**Flujo de inicio desde cero:**

```bash
# 1. Clonar el repositorio
git clone <url-repositorio>
cd cosmol-chatbot

# 2. Configurar el entorno
cp env.example .env
# Completar .env con los valores reales

# 3. Levantar el entorno de desarrollo
docker compose --profile dev up -d --build

# 4. Verificar estado de los servicios
docker compose ps
# Estado esperado: n8n (running), backend (healthy), db (healthy)

# 5. Acceder a la interfaz de n8n
# http://localhost:5678
```

---

### 6.3 Exposición Local (Webhooks)

Durante el desarrollo, Meta WhatsApp Cloud API requiere una URL pública HTTPS para enviar los eventos de webhook. Se utiliza **ngrok** para crear un túnel temporal que expone el puerto local de n8n a Internet.

**Paso 1 — Autenticar ngrok:**

```bash
ngrok authtoken <tu_auth_token>
```

**Paso 2 — Crear el túnel apuntando al puerto de n8n:**

```bash
ngrok http 5678
```

ngrok generará una URL pública similar a:

```
Forwarding  https://1234abcd.ngrok-free.app -> http://localhost:5678
```

**Paso 3 — Actualizar `WEBHOOK_URL` en `.env`:**

```env
WEBHOOK_URL=https://1234abcd.ngrok-free.app/
```

Reiniciar n8n para que adopte la nueva URL:

```bash
docker compose restart n8n
```

**Paso 4 — Configurar el webhook en Meta for Developers:**

1. Navegar a `Aplicación → WhatsApp → Configuración` en el panel de Meta for Developers.
2. En la sección **Webhooks**, establecer la URL de callback:
   `https://1234abcd.ngrok-free.app/webhook/whatsapp`
3. Ingresar el **Verify Token** configurado en n8n.
4. Suscribir el campo: `messages`.

> [!WARNING]
> Cada reinicio de ngrok sin URL fija genera una URL diferente, lo que requiere actualizar tanto `WEBHOOK_URL` en `.env` como la URL en el panel de Meta for Developers.
> En producción, ngrok es reemplazado por un dominio real con certificado SSL gestionado por un proxy inverso (Nginx o Apache), apuntando directamente al puerto `5678` del contenedor `cosmol_n8n`.

**Verificación del webhook desde la red Docker:**

```bash
docker exec -it cosmol_n8n sh -c \
  "wget -q -O- 'https://tu-url.ngrok-free.app/webhook/whatsapp?hub.mode=subscribe&hub.challenge=TEST&hub.verify_token=TU_TOKEN'"
# Resultado esperado: TEST  (el challenge devuelto confirma que el webhook responde)
```
