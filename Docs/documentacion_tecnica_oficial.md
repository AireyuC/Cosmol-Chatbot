# Documentación Técnica Oficial — Chatbot COSMOL

> **Versión:** 2.0.0 (Actualización Integral)  
> **Fecha de emisión:** Septiembre 2026  
> **Estado:** Operativo en Servidor de Pruebas (Ubuntu Server)  
> **Clasificación:** Documento Técnico Maestro — Uso Exclusivo del Equipo de Desarrollo

---

## Tabla de Contenidos

1. [Materia 1: Propósito del Sistema y Modelo Operativo](#1-materia-1-propósito-del-sistema-y-modelo-operativo)
   - 1.1 [Filosofía de Fricción Cero](#11-filosofía-de-fricción-cero)
   - 1.2 [Modelo Económico y de Mensajería](#12-modelo-económico-y-de-mensajería)
   - 1.3 [Topología de Componentes y Flujo General](#13-topología-de-componentes-y-flujo-general)
2. [Materia 2: Infraestructura, Redes y Despliegue (Docker + Caddy)](#2-materia-2-infraestructura-redes-y-despliegue-docker--caddy)
   - 2.1 [Entorno de Ejecución (Ubuntu Server)](#21-entorno-de-ejecución-ubuntu-server)
   - 2.2 [Caddy: Proxy Inverso y Certificados SSL Automáticos](#22-caddy-proxy-inverso-y-certificados-ssl-automáticos)
   - 2.3 [Topología de Contenedores y Red Interna](#23-topología-de-contenedores-y-red-interna)
   - 2.4 [Configuración de Entorno (.env)](#24-configuración-de-entorno-env)
3. [Materia 3: Arquitectura del Software Backend (PHP 7.3 Vanilla)](#3-materia-3-arquitectura-del-software-backend-php-73-vanilla)
   - 3.1 [Estructura Modular en Capas](#31-estructura-modular-en-capas)
   - 3.2 [Endpoints Públicos Oficiales](#32-endpoints-públicos-oficiales)
   - 3.3 [Ciclo de Vida de la Petición y WebhookKernel](#33-ciclo-de-vida-de-la-petición-y-webhookkernel)
   - 3.4 [Contenedor de Inversión de Control (AppContainer)](#34-contenedor-de-inversión-de-control-appcontainer)
   - 3.5 [Máquina de Estados de Sesión (FSM)](#35-máquina-de-estados-de-sesión-fsm)
   - 3.6 [Enrutamiento por Flujos y Acciones de Menú](#36-enrutamiento-por-flujos-y-acciones-de-menú)
   - 3.7 [Plantillas Nativas de WhatsApp](#37-plantillas-nativas-de-whatsapp)
4. [Materia 4: Orquestación y Experiencia Visual en n8n](#4-materia-4-orquestación-y-experiencia-visual-en-n8n)
   - 4.1 [Pipeline Centralizado de Mensajería](#41-pipeline-centralizado-de-mensajería)
   - 4.2 [Nodos Estéticos en Meta Graph API v25.0](#42-nodos-estéticos-en-meta-graph-api-v250)
   - 4.3 [Estrategia de Tolerancia a Fallos (Resiliencia)](#43-estrategia-de-tolerancia-a-fallos-resiliencia)
5. [Materia 5: Políticas de Seguridad y Blindaje API](#5-materia-5-políticas-de-seguridad-y-blindaje-api)
   - 5.1 [Flujo Determinístico en bootstrap.php](#51-flujo-determinístico-en-bootstrapphp)
   - 5.2 [Autenticación por Token Interno](#52-autenticación-por-token-interno)
   - 5.3 [Rate Limiting y Mitigación de Spam](#53-rate-limiting-y-mitigación-de-spam)
   - 5.4 [Guardia de Mantenimiento (MaintenanceGuard)](#54-guardia-de-mantenimiento-maintenanceguard)
   - 5.5 [Sanitización de Datos y Logging Estructurado](#55-sanitización-de-datos-y-logging-estructurado)
6. [Materia 6: Integraciones y Persistencia de Datos](#6-materia-6-integraciones-y-persistencia-de-datos)
   - 6.1 [Persistencia Desacoplada: PostgreSQL Mock vs APIs SAI Informix](#61-persistencia-desacoplada-postgresql-mock-vs-apis-sai-informix)
   - 6.2 [Pasarelas de Pago Duales por URL](#62-pasarelas-de-pago-duales-por-url)
   - 6.3 [Gestión de Multimedia (WhatsAppMediaService)](#63-gestión-de-multimedia-whatsappmediaservice)
   - 6.4 [Sinergia con COSMOL-Reportes y Buffer de Contingencia](#64-sinergia-con-cosmol-reportes-y-buffer-de-contingencia)
7. [Materia 7: Ciclo de Vida del Proyecto y Roadmap Futuro](#7-materia-7-ciclo-de-vida-del-proyecto-y-roadmap-futuro)
   - 7.1 [Evolución Histórica y Estado de Desarrollo](#71-evolución-histórica-y-estado-de-desarrollo)
   - 7.2 [Evolución Técnica: Interacción con Personal Operativo](#72-evolución-técnica-interacción-con-personal-operativo)

---

## 1. Materia 1: Propósito del Sistema y Modelo Operativo

### 1.1 Filosofía de Fricción Cero
El **Chatbot COSMOL** es una plataforma de atención automatizada para los asociados de la **Cooperativa de Servicios Públicos Montero "COSMOL" R.L.** mediante WhatsApp. Su premisa fundamental es la **fricción cero**: el asociado se autentica y consulta sus servicios ingresando exclusivamente su **Código de Asociado (Código Fijo)**. No se requieren contraseñas, nombres de usuario ni descargas de aplicaciones adicionales.

### 1.2 Modelo Económico y de Mensajería
Para evitar costes operativos a la cooperativa, el sistema adopta una política estricta sobre la tarificación de Meta Cloud API:
* **Conversaciones Iniciadas por el Usuario (*User-Initiated*):** Toda la interacción ocurre dentro de la ventana de servicio de 24 horas abierta por el socio al escribir al bot. El costo para COSMOL por mensaje o sesión bajo este esquema es **$0.00 USD**.
* **Prohibición de Notificaciones Masivas Proactivas:** El bot **no realiza envíos masivos ni recordatorios push no solicitados** (*Business-Initiated*), eliminando el riesgo de facturaciones imprevistas por parte de Meta.

### 1.3 Topología de Componentes y Flujo General
El sistema opera desacoplado en cuatro capas:

```
┌─────────────────────────────────────────────────────────────────┐
│                        SOCIO / ASOCIADO                         │
│                    (WhatsApp en Smartphone)                     │
└───────────────────────────┬─────────────────────────────────────┘
                            │  Mensajes interactivos, GPS, Foto
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│             META WHATSAPP CLOUD API (v25.0)                     │
│               Webhooks POST HTTPS entrantes                     │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTPS (Puerto 443)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                   PROXY INVERSO: CADDY                          │
│     Certificados SSL automáticos (chatbot.cosmol.com.bo)        │
│          Enrutador hacia n8n y servidor de /uploads/*           │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTP interno (cosmol_network)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│               ORQUESTADOR: n8n (Self-Hosted)                    │
│   Filtro de Ruido → Check Azul → Typing → Despacho a Backend    │
└───────────────────────────┬─────────────────────────────────────┘
                            │  HTTP POST (Header: X-Internal-Token)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│              BACKEND API: PHP 7.3 + Apache                      │
│      bootstrap → WebhookKernel → AppContainer → FlowRouter      │
└──────────────┬──────────────────────────────────┬───────────────┘
               │                                  │
               ▼                                  ▼
┌──────────────────────────────┐   ┌──────────────────────────────┐
│     PostgreSQL 16 (db)       │   │  APIs REST del Sistema SAI   │
│   Sesiones, Buffer Reportes  │   │  (IBM Informix en Producción)│
│    y Datos Mock de Pruebas   │   │     COSMOL_API_URL           │
└──────────────────────────────┘   └──────────────────────────────┘
```

---

## 2. Materia 2: Infraestructura, Redes y Despliegue (Docker + Caddy)

### 2.1 Entorno de Ejecución (Ubuntu Server)
El ecosistema completo se encuentra desplegado y operativo sobre un servidor con **Ubuntu Server**, configurado con IP asignada y el dominio institucional `chatbot.cosmol.com.bo`. Todos los componentes corren contenerizados bajo **Docker** y orquestados mediante **docker-compose**, eliminando dependencias instaladas en el sistema host.

### 2.2 Caddy: Proxy Inverso y Certificados SSL Automáticos
Se oficializa **Caddy** como servidor perimetral y proxy inverso del proyecto, sustituyendo implementaciones tradicionales de Nginx por su capacidad de emitir y renovar certificados HTTPS de forma 100% automatizada y gratuita.

**Archivo de configuración (`Caddyfile`):**
```caddyfile
{$DOMAIN_NAME:chatbot.cosmol.com.bo} {
    # Servir imágenes estáticas de reclamos/reconexiones desde /uploads
    handle_path /uploads/* {
        root * /var/www/html/uploads
        file_server
    }

    # Tráfico general hacia n8n
    handle {
        reverse_proxy cosmol_n8n:5678
    }
}

# Dashboard COSMOL-Reportes con SSL en puerto 8081
{$DOMAIN_NAME:chatbot.cosmol.com.bo}:8081 {
    reverse_proxy host.docker.internal:8082
}
```

### 2.3 Topología de Contenedores y Red Interna
Definidos en [`docker-compose.yml`](file:///d:/Cosmol-Chatbot/docker-compose.yml):
1. **`caddy` (`cosmol_caddy`):** Expone los puertos `80`, `443` y `8081` hacia el host.
2. **`n8n` (`cosmol_n8n`):** Corre sobre el puerto interno `5678`, aislado del exterior; solo recibe tráfico a través de Caddy.
3. **`backend` (`cosmol_backend`):** Contenedor `php:7.3-apache`. Completamente aislado en la red interna; procesa las peticiones enviadas por n8n.
4. **`db` (`cosmol_postgres`):** Base de datos `postgres:16-alpine`. Almacena sesiones, buffer de contingencia y tablas mock ANSI SQL. Solo expone el puerto `127.0.0.1:5433:5432` localmente para administración con herramientas de base de datos.
5. **Red Interna:** Todos los contenedores se comunican a través del bridge `cosmol_internal_network` (`cosmol_network`).

### 2.4 Configuración de Entorno (.env)
Las credenciales y variables se centralizan en el archivo `.env`:
* **n8n:** `N8N_PROXY_HOPS=1`, `GENERIC_TIMEZONE=America/La_Paz`.
* **Caddy:** `DOMAIN_NAME=chatbot.cosmol.com.bo`.
* **Seguridad:** `API_INTERNAL_TOKEN` (token compartido n8n ↔ PHP), `ALLOWED_ORIGIN=http://cosmol_n8n:5678`.
* **Meta:** `WHATSAPP_TOKEN` (token permanente de usuario del sistema para descarga de multimedia).
* **Integraciones:** `COSMOL_API_URL` (para el SAI Informix) y `REPORTES_API_URL` junto con `REPORTES_SYNC_ENABLED=true` para `COSMOL-Reportes`.

---

## 3. Materia 3: Arquitectura del Software Backend (PHP 7.3 Vanilla)

### 3.1 Estructura Modular en Capas
El backend está estructurado bajo estándares estrictos de separación de responsabilidades y compatibilidad absoluta con **PHP 7.3**:

```text
app/
├── Config/                   ← Constantes y base de datos (database.php)
├── Core/                     ← Núcleo del framework interno
│   ├── AppContainer.php      ← Contenedor de Inversión de Control (IoC/DI)
│   ├── Auth.php              ← Verificación del token secreto
│   ├── Autoloader.php        ← Autoload PSR-4 nativo
│   ├── Controller.php        ← Respuestas JSON estandarizadas
│   ├── Database.php          ← Singleton PDO (PostgreSQL / ANSI SQL)
│   ├── FeatureFlags.php      ← Control de activación de características
│   ├── Logger.php            ← Registro de logs diarios en JSON
│   ├── RateLimiter.php       ← Control de peticiones concurrentes
│   ├── Validator.php         ← Sanitización y validación de tipos
│   └── WebhookKernel.php     ← Orquestador del ciclo de vida HTTP
│
├── Data/                     ← Capa de Datos y Persistencia
│   ├── Interfaces/           ← Contratos (ISocioRepository, IReclamoRepository, etc.)
│   └── Repositories/
│       ├── Postgres/         ← Acceso a PostgreSQL de pruebas
│       └── Api/              ← Acceso a APIs REST del SAI (Informix)
│
├── Modules/                  ← Lógica de Dominio y Negocio
│   ├── Audit/                ← ConsultaAuditService (Buffer hacia Reportes)
│   ├── Facturacion/          ← FacturacionService (Cálculo de deuda y facturas)
│   ├── Reclamo/              ← ReclamoService (Registro técnico con GPS/Foto)
│   ├── Reconexion/           ← ReconexionService (Evaluación de mora ≤ 2 meses)
│   ├── Session/              ← SessionService y MaintenanceGuard
│   └── Socio/                ← SocioService (Identificación por Código Fijo)
│
├── Presentacion/             ← Presentación y Flujos WhatsApp
│   ├── Flows/
│   │   ├── FlowRouter.php    ← Ruteador por Máquina de Estados
│   │   ├── Manejadores/      ← Handlers de estados (Auth, Menu, Reclamo, Reconexion)
│   │   └── MenuActions/      ← Acciones de menú (Pagar, Historial, Reclamo, etc.)
│   └── PlantillasWhatsApp/   ← Constructores de JSON conformes a Meta
│
└── Integrations/             ← Conectores de Servicios Externos
    ├── CosmolApi/            ← ClienteApiCosmol (Hacia SAI Informix)
    ├── CosmolReportes/       ← ClienteApiReportes (Hacia COSMOL-Reportes)
    └── WhatsApp/             ← WhatsAppMediaService (Descarga de fotos Graph API)
```

### 3.2 Endpoints Públicos Oficiales
El directorio `public/api/` expone **únicamente dos endpoints** autorizados:

1. **[`webhook_whatsapp.php`](file:///d:/Cosmol-Chatbot/public/api/webhook_whatsapp.php):**
   * **Consumidor:** Orquestador n8n.
   * **Método:** `POST`.
   * **Payload esperado:**
     ```json
     {
       "telefono": "5917XXXXXXX",
       "tipo_mensaje": "text|interactive|location|image",
       "contenido": "texto_o_payload_estructurado"
     }
     ```
   * **Función:** Invoca `WebhookKernel->handle()` para procesar la interacción conversacional del socio.

2. **[`cron_flush_reportes.php`](file:///d:/Cosmol-Chatbot/public/api/cron_flush_reportes.php):**
   * **Consumidor:** Tarea programada en n8n (Schedule Trigger) o cron interno cada 2-5 minutos.
   * **Función:** Vacía los registros acumulados en la cola de contingencia local (`cola_reportes`) enviándolos por lotes hacia `COSMOL-Reportes`.

### 3.3 Ciclo de Vida de la Petición y WebhookKernel
El flujo de procesamiento al recibir una solicitud desde n8n sigue las siguientes etapas:
1. `bootstrap.php` ejecuta verificaciones perimetrales (CORS, token interno, rate limit).
2. `WebhookKernel` valida los parámetros entrantes (`telefono`, `tipo_mensaje`, `contenido`).
3. Evalúa si el sistema se encuentra en modo mantenimiento mediante `MaintenanceGuard`.
4. Consulta el estado de sesión del socio en la base de datos vía `SessionService`.
5. Delega la ejecución a `FlowRouter`, el cual dispara el manejador o acción correspondiente.
6. Emite una respuesta JSON HTTP 200 conteniendo el bloque `whatsapp_payload` listo para ser despachado a Meta por n8n.

### 3.4 Contenedor de Inversión de Control (AppContainer)
[`AppContainer`](file:///d:/Cosmol-Chatbot/app/Core/AppContainer.php) implementa un patrón Service Locator / Contenedor IoC con instanciación diferida (*Lazy Loading*). Esto permite desacoplar los servicios de su implementación concreta, resolviendo automáticamente las dependencias de clientes API y repositorios de datos.

### 3.5 Máquina de Estados de Sesión (FSM)
La interacción del socio no es efímera ni caótica; se controla mediante una Máquina de Estados persistida en la tabla `chat_session` de PostgreSQL:

| Estado | Significado / Espera del Bot | Siguiente Transición |
|---|---|---|
| `AWAITING_CODE` | Esperando ingreso del Código Fijo del socio. | `MAIN_MENU` (si es válido) o reintento. |
| `MAIN_MENU` | Socio autenticado en el menú principal interactivo. | Enrutamiento a la acción elegida. |
| `AWAITING_RECLAMO_GPS` | Solicita ubicación GPS nativa de WhatsApp. | `AWAITING_RECLAMO_PHOTO` |
| `AWAITING_RECLAMO_PHOTO` | Solicita fotografía del medidor/fuga. | `AWAITING_RECLAMO_GLOSA` |
| `AWAITING_RECLAMO_GLOSA` | Solicita referencias escritas del problema. | Registro final y retorno a `MAIN_MENU`. |
| `AWAITING_RECONEXION_GPS` | Solicita ubicación GPS para reconexión. | `AWAITING_RECONEXION_PHOTO` |
| `AWAITING_RECONEXION_PHOTO`| Solicita fotografía del medidor cerrado. | `AWAITING_RECONEXION_GLOSA` |
| `AWAITING_RECONEXION_GLOSA`| Solicita observaciones para el técnico. | Orden emitida y retorno a `MAIN_MENU`. |
| `BLOCKED` | Usuario temporalmente suspendido por 5 minutos tras 3 intentos fallidos de código. | `AWAITING_CODE` tras expiración. |

### 3.6 Enrutamiento por Flujos y Acciones de Menú
`FlowRouter` distribuye las opciones del menú principal hacia clases de acción independientes en `app/Presentacion/Flows/MenuActions/`:
* **`PagarAction`:** Consulta las facturas adeudadas y genera los enlaces a las pasarelas de pago.
* **`HistorialAction`:** Recupera el registro de facturas canceladas anteriormente.
* **`ReclamoAction`:** Da inicio al flujo guiado de captura técnica.
* **`ReconexionAction`:** Evalúa la mora actual (bloqueando si supera 2 facturas) e inicia el trámite.
* **`EstadoTramitesAction`:** Informa al socio sobre el estado de sus reclamos o reconexiones registradas.
* **`InfoAction`:** Provee información institucional, números de emergencia y horarios de atención.

### 3.7 Plantillas Nativas de WhatsApp
Ubicadas en `app/Presentacion/PlantillasWhatsApp/`, encapsulan las especificaciones de Meta para generar:
* Mensajes interactivos de lista con secciones desplegables (`list`).
* Mensajes con botones de respuesta rápida (`button`).
* Mensajes de texto con formato markdown oficial de WhatsApp (negritas, cursivas, listas).

---

## 4. Materia 4: Orquestación y Experiencia Visual en n8n

### 4.1 Pipeline Centralizado de Mensajería
El flujo maestro en n8n (`04_Flujo_Centralizado_MVC_Con_Estetica.json`) opera de forma lineal y centralizada:

```text
[Webhook Meta] ──► [Filtro de Ruido] ──► [Doble Check Azul] ──► [Indicador Escribiendo] ──► [Backend PHP] ──► [Enviar a Meta]
```

1. **Webhook:** Recibe el payload JSON entrante desde Meta Cloud API.
2. **Filtro de Ruido (IF):** Descarta de inmediato los acuses de estado (`sent`, `delivered`, `read`) y continúa solo con mensajes que contengan texto, interactivos, ubicación o medios.

### 4.2 Nodos Estéticos en Meta Graph API v25.0
Para garantizar una experiencia de usuario ágil y humana, n8n ejecuta de manera automática dos llamadas antes de contactar a PHP:
* **Doble Check Azul:** Envía `status: "read"` a Meta con el `message_id` recibido, confirmando la lectura al instante.
* **Indicador de Escritura:** Envía una petición `typing_indicator` con `action: "typing_on"`. El usuario observa el estado *"COSMOL está escribiendo..."* mientras PHP procesa la consulta en base de datos.
* **Desvanecimiento Automático:** En cuanto el nodo final entrega la respuesta del bot, la animación de escribiendo desaparece de forma nativa.

### 4.3 Estrategia de Tolerancia a Fallos (Resiliencia)
Ambos nodos estéticos están configurados con la directiva **`Continue on Fail: true`** (`onError: "continueRegularOutput"`) y un timeout de **2000 ms**. Si un socio tiene deshabilitada la confirmación de lectura en su privacidad personal o si Meta presenta lentitud de red, **el flujo no se bloquea** y continúa directamente hacia PHP.

---

## 5. Materia 5: Políticas de Seguridad y Blindaje API

### 5.1 Flujo Determinístico en bootstrap.php
El archivo [`app/bootstrap.php`](file:///d:/Cosmol-Chatbot/app/bootstrap.php) actúa como guardián perimetral antes de cualquier ejecución de controlador:

```text
1. Carga de Autoloader PSR-4
2. Configuración de Zona Horaria (America/La_Paz)
3. Directiva de Errores según APP_ENV (ocultos en producción, visibles en desarrollo)
4. Encabezados CORS dinámicos basados en ALLOWED_ORIGIN
5. Manejo de Preflight OPTIONS (Retorna 200 OK inmediato para preflights de navegadores/proxies)
6. Validación del Token Interno (Auth::validateInternalToken) -> 401 Unauthorized si es inválido
7. Evaluación de Límite de Peticiones (RateLimiter::check) -> 429 Too Many Requests si excede
```

### 5.2 Autenticación por Token Interno
La comunicación entre n8n y el backend PHP requiere de forma obligatoria la presencia del encabezado HTTP:
`X-Internal-Token: <API_INTERNAL_TOKEN>`
Cualquier intento de invocación externa directa que carezca de este token es interceptado y rechazado con código HTTP 401.

### 5.3 Rate Limiting y Mitigación de Spam
La clase `RateLimiter` controla la frecuencia de peticiones entrantes por dirección IP en ventanas de tiempo predeterminadas, previniendo saturación por bucles en n8n o ataques de denegación de servicio.

### 5.4 Guardia de Mantenimiento (MaintenanceGuard)
Permite suspender la atención del bot de manera global ante mantenimientos de servidores sin necesidad de detener los contenedores Docker. Cuenta con un filtro antispam desacoplado que responde una única vez con un mensaje institucional amigable y silencia los mensajes insistentes de un mismo usuario durante la ventana de mantenimiento.

### 5.5 Sanitización de Datos y Logging Estructurado
* Toda entrada es saneada mediante `Validator.php` para prevenir inyecciones.
* Todas las consultas a bases de datos emplean consultas preparadas PDO con vinculación estricta de parámetros (*prepared statements*).
* Los eventos y excepciones operativas se guardan en logs diarios con formato JSON estructurado en `app/logs/`, facilitando auditorías técnicas.

---

## 6. Materia 6: Integraciones y Persistencia de Datos

### 6.1 Persistencia Desacoplada: PostgreSQL Mock vs APIs SAI Informix
El sistema utiliza el **Patrón Repository** sustentado en interfaces formales ubicadas en `app/Data/Interfaces/`:
* **Entorno Actual (Pruebas):** Se utiliza `app/Data/Repositories/Postgres/` contra el contenedor `cosmol_postgres`. Las tablas siguen el estándar ANSI SQL:
  * `socio`: Registro de asociados, teléfonos y estados de conexión.
  * `reclamo`: Órdenes de reclamos técnicos registradas.
  * `factura`: Detalle de períodos, consumos y deudas pendientes.
  * `chat_session`: Persistencia del estado conversacional de los socios.
  * `cola_reportes`: Buffer local de contingencia para auditorías.
* **Producción (SAI Informix):** El equipo de desarrollo preparó `app/Data/Repositories/Api/`, consumiendo `ClienteApiCosmol`. Cuando COSMOL habilite los endpoints del SAI en producción, basta con definir `COSMOL_API_URL` en `.env` sin modificar la lógica interna del bot.

### 6.2 Pasarelas de Pago Duales por URL
Debido a convenios institucionales y normativas bancarias, los pagos se procesan de manera segura redirigiendo al socio mediante enlaces oficiales formateados dinámicamente en [`PlantillaFactura.php`](file:///d:/Cosmol-Chatbot/app/Presentacion/PlantillasWhatsApp/PlantillaFactura.php):
1. **Multipago:** `https://multipago.com/service/cosmol_payment/first`
2. **Pago al Paso:** `https://red.pagoalpaso247.net/servicio/cosmol`

### 6.3 Gestión de Multimedia (WhatsAppMediaService)
Cuando un socio adjunta una fotografía como evidencia de reclamo o reconexión:
1. Meta provee un identificador `media_id` en el webhook.
2. [`WhatsAppMediaService`](file:///d:/Cosmol-Chatbot/app/Integrations/WhatsApp/WhatsAppMediaService.php) consulta la Graph API v25.0 (`https://graph.facebook.com/v25.0/{media_id}`).
3. Obtiene la URL de descarga temporal autenticada con `WHATSAPP_TOKEN`.
4. Descarga los bytes del archivo y los guarda en el disco local: `public/uploads/reclamos/` o `public/uploads/reconexiones/`.
5. Caddy expone el directorio como recurso estático HTTPS (`https://chatbot.cosmol.com.bo/uploads/...`), permitiendo que el personal técnico visualice la foto desde cualquier navegador.

### 6.4 Sinergia con COSMOL-Reportes y Buffer de Contingencia
Cada consulta, reclamo o trámite procesado por el bot es reportado al sistema hermano **COSMOL-Reportes**:
* **Llamada Asíncrona:** `ClienteApiReportes` emite un evento HTTP POST a `REPORTES_API_URL` con timeout de 2 segundos.
* **Buffer Local de Resiliencia:** Si el servidor de reportes no responde o experimenta mantenimiento, la consulta se guarda en la tabla `cola_reportes` con estado `'PENDIENTE'`.
* **Vaciado Progresivo:** Al restablecerse la conexión, el endpoint `cron_flush_reportes.php` vacía los registros pendientes hacia la base central de reportes sin saturar el tráfico.

---

## 7. Materia 7: Ciclo de Vida del Proyecto y Roadmap Futuro

### 7.1 Evolución Histórica y Estado de Desarrollo
El proyecto evolucionó a través de hitos técnicos clave:
1. **Unificación Arquitectónica:** Migración del esquema preliminar hacia arquitectura en capas (Kernel → Contenedor IoC → Router → Repositorio).
2. **Estandarización de Base de Datos:** Adopción de PostgreSQL 16 con sintaxis ANSI SQL para asegurar portabilidad inmediata hacia Informix.
3. **Robustecimiento Perimetral:** Incorporación de Caddy con SSL automático, aislando n8n y PHP de la red pública.
4. **Estado Actual:** Todos los módulos del socio (validación de código, consulta de deudas, reclamos con GPS y foto, reconexiones con mora, y reportes) se encuentran **100% implementados y validados operativamente** en el servidor de pruebas.

### 7.2 Evolución Técnica: Interacción con Personal Operativo
Como fase complementaria a futuro, se contempla la posibilidad de habilitar **WhatsApp Flows** o canales directos de WhatsApp exclusivamente para el personal operativo (plomeros y supervisores de campo), permitiéndoles recibir alertas de reclamos asignados y concluir trabajos técnicos en tiempo real desde sus teléfonos móviles.
