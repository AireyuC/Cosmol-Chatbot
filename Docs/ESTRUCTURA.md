# Estructura y Arquitectura del Proyecto - Chatbot COSMOL (Backend)

Este documento describe la arquitectura y la organización de carpetas del backend en PHP. El objetivo es que cualquier integrante del equipo entienda el rol de cada capa y sepa dónde agregar o modificar código siguiendo los estándares definidos.

## Arquitectura: Capas (Service-Repository Pattern)

Al ser una API consumida exclusivamente por n8n (no hay Frontend HTML), el patrón **MVC clásico no aplica directamente**. En su lugar, el proyecto adopta una arquitectura basada en capas orientada al dominio (Controller → Service → Repository):

- **Controller (Endpoints)**: Archivos independientes en `public/api/` que reciben la petición HTTP de n8n, leen los parámetros y delegan la ejecución al Servicio.
- **Service (Capa de Negocio)**: Clases en `app/Modules/` que contienen las reglas del negocio (ej. validar si un reclamo procede, aplicar lógica de "fricción cero").
- **Repository (Capa de Datos)**: Clases en `app/Data/Repositories/` que se encargan de obtener y persistir datos. En desarrollo consultan PostgreSQL local; en producción hacen peticiones HTTP a las **APIs REST del sistema SAI** (Informix).
- **Interfaces**: Contratos en `app/Data/Interfaces/` que garantizan que los repositorios sean intercambiables.

> [!IMPORTANT]
> **El valor de esta arquitectura:** El Servicio (`SocioService`) nunca sabe si está hablando con MySQL (desarrollo) o con la API REST del SAI (producción). Solo habla con una Interfaz. Esto permite cambiar la fuente de datos sin tocar ni una línea de la lógica de negocio principal.

## Estructura de carpetas actual

```text
cosmol-chatbot/
├── app/
│   ├── Config/
│   │   └── database.php              ← Constantes de entorno y BD
│   ├── Core/
│   │   ├── AppContainer.php          ← Inyector Central / Service Container (Lazy-Loading)
│   │   ├── Auth.php                  ← [Seguridad] Token interno
│   │   ├── Autoloader.php            ← Autocarga de clases (PSR-4 manual)
│   │   ├── Controller.php            ← Métodos base (json, getBody, handleError)
│   │   ├── Database.php              ← Singleton de conexión PDO
│   │   ├── FeatureFlags.php          ← Feature flags y control de modo mantenimiento
│   │   ├── Logger.php                ← [Seguridad] Logging JSON estructurado
│   │   ├── MaintenanceGuard.php      ← Guardia de mantenimiento global y antispam
│   │   ├── RateLimiter.php           ← [Seguridad] Rate limiting por IP
│   │   ├── Validator.php             ← [Seguridad] Validación de inputs
│   │   └── WebhookKernel.php         ← Orquestador del ciclo de vida del webhook
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── ReclamoRepositoryInterface.php
│   │   │   ├── ReconexionRepositoryInterface.php
│   │   │   ├── ReportesRepositoryInterface.php
│   │   │   ├── SessionRepositoryInterface.php
│   │   │   └── SocioRepositoryInterface.php
│   │   └── Repositories/
│   │       ├── Api/
│   │       │   ├── ReclamoRepository.php
│   │       │   ├── ReconexionRepository.php
│   │       │   └── SocioRepository.php
│   │       └── Postgres/
│   │           ├── ReclamoRepository.php
│   │           ├── ReportesBufferRepository.php
│   │           ├── SessionRepository.php
│   │           └── SocioRepository.php
│   ├── Integrations/
│   │   ├── CosmolApi/
│   │   │   └── ClienteApiCosmol.php     ← Cliente HTTP para APIs del SAI (Informix)
│   │   ├── CosmolReportes/
│   │   │   └── ClienteApiReportes.php    ← Cliente HTTP métricas a COSMOL-Reportes
│   │   └── WhatsApp/
│   │       └── WhatsAppMediaService.php  ← Descarga y guardado local de medios de WhatsApp
│   ├── Modules/
│   │   ├── Audit/
│   │   │   └── ConsultaAuditService.php  ← Auditoría de eventos con buffer de contingencia
│   │   ├── Facturacion/                 ← Dominio de Facturación y deudas
│   │   ├── Reclamo/
│   │   │   └── ReclamoService.php       ← Lógica de reclamos técnicos
│   │   ├── Reconexion/
│   │   │   └── ReconexionService.php    ← Lógica de solicitudes de reconexión y mora
│   │   ├── Session/
│   │   │   └── SessionService.php       ← Máquina de estados y sesiones en BD
│   │   └── Socio/
│   │       └── SocioService.php         ← Validación de identidad del socio
│   ├── Presentacion/
│   │   ├── Flows/
│   │   │   ├── FlowRouter.php            ← Enrutador de la máquina de estados
│   │   │   ├── Manejadores/              ← Manejadores de cada estado de la sesión
│   │   │   │   ├── BaseFlowHandler.php       ← Clase abstracta base
│   │   │   │   ├── AuthFlowHandler.php       ← Flujo de bienvenida y autenticación
│   │   │   │   ├── MenuFlowHandler.php       ← Despachador del menú principal
│   │   │   │   ├── ReclamoFlowHandler.php    ← Flujo de reclamos (GPS, foto, glosa)
│   │   │   │   └── ReconexionFlowHandler.php ← Flujo de reconexiones (GPS, foto, glosa)
│   │   │   └── MenuActions/              ← Acciones modulares del menú principal
│   │   │       ├── PagarAction.php
│   │   │       ├── HistorialAction.php
│   │   │       ├── ReconexionAction.php
│   │   │       ├── ReclamoAction.php
│   │   │       ├── EstadoTramitesAction.php
│   │   │       └── InfoAction.php
│   │   └── PlantillasWhatsApp/
│   │       ├── PlantillaFactura.php     ← Formateadores de facturas y deudas
│   │       ├── PlantillaReclamo.php     ← Formateadores de reclamos
│   │       ├── PlantillaReconexion.php  ← Formateadores de reconexión
│   │       ├── PlantillaSistema.php     ← Mensajes de sistema, errores y mantenimiento
│   │       └── PlantillaSocio.php       ← Menú interactivo y estado de solicitudes
│   └── bootstrap.php                    ← Inicializador global del sistema
│
├── database/
│   └── init.sql                         ← Inicialización de PostgreSQL
│
├── Docs/                                ← Documentación general y técnica
│
├── public/
│   └── api/
│       ├── reclamos.php                 ← [Legacy / Obsoleto] Endpoint anterior
│       └── webhook_whatsapp.php         ← Controlador Central (Webhook para N8N)
│
├── .env                                 ← Configuración de entorno (no versionado)
├── AGENTS.md                            ← Reglas maestras y contexto para IA
├── docker-compose.yml                   ← Orquestación (n8n, backend, postgres)
├── dockerfile                           ← Configuración de PHP 7.3
└── env.example                          ← Plantilla de variables de entorno
```

## Rol de cada directorio

| Carpeta / Archivo | Rol |
|---|---|
| `public/api/` | **Endpoints.** Todo el flujo del chatbot de N8N se procesa a través de `webhook_whatsapp.php` que orquesta la máquina de estados y las llamadas a los Flow Handlers y Servicios. `reclamos.php` es un archivo legacy. |
| `app/Core/` | **Infraestructura base y seguridad.** El `Autoloader` (PSR-4 nativo), `Controller` base, `Database` (PDO singleton), `FeatureFlags` (mantenimiento y toggles de menú), `Auth` (token interno), `Validator`, `Logger` estructurado y `RateLimiter`. |
| `app/Integrations/` | **Integraciones Externas.** Clientes HTTP dedicados: `ClienteApiCosmol` (APIs SAI Informix), `ClienteApiReportes` (auditoría externa) y `WhatsAppMediaService` (descarga y guardado de archivos adjuntos). |
| `app/Modules/*/` | **Lógica de negocio dividida por dominios.** `Socio` (identidad), `Facturacion` (deudas e historial), `Reclamo` (quejas y reportes), `Reconexion` (solicitudes y reglas de mora), `Session` (persistencia del estado de conversación), y `Audit` (registro de métricas). |
| `app/Presentacion/Flows/` | **Flow Handlers (Máquina de estados).** Clases dedicadas a orquestar cada paso de la conversación según el estado actual de la sesión del socio (`Auth`, `Menu`, `Reclamo`, `Reconexion`). |
| `app/Presentacion/PlantillasWhatsApp/` | **Lógica de presentación.** Ensambla las cargas útiles (payloads) conformes al formato exacto de Meta WhatsApp Cloud API (botones, listas interactivas, textos). |
| `app/Data/Interfaces/` | **Contratos de Repositorios.** Contratos para garantizar la independencia entre la lógica de negocio y la fuente de datos subyacente. |
| `app/Data/Repositories/` | **Capa de datos intercambiable.** `Postgres/` contiene implementaciones para PostgreSQL (sesiones, buffer de reportes y mocks locales). `Api/` contiene implementaciones que consumen las APIs REST del sistema SAI (Informix). |
| `app/bootstrap.php` | **Carga inicial.** Archivo requerido por los endpoints para inicializar el Autoloader, variables de entorno, seguridad inicial y headers CORS. |

## Decisiones Técnicas Clave

1. **Sin frameworks pesados:** Desarrollo en PHP puro (`Vanilla PHP 7.3`) para asegurar máxima compatibilidad con el entorno de servidor local y alto rendimiento.
2. **Autoloader nativo sin Composer:** Se implementó `spl_autoload_register` siguiendo el estándar PSR-4 de manera nativa sin dependencias externas.
3. **Respuesta Estandarizada hacia n8n:** La API devuelve una estructura JSON uniforme con el payload listo para WhatsApp: `{"status": string, "estado": string, "whatsapp_payload": array|null}` (o `"modo": "mantenimiento"` si el bot está inhabilitado globalmente).
4. **Entorno Contenerizado:** El desarrollo local se realiza exclusivamente con Docker (PHP + n8n + PostgreSQL 16).
5. **Captura Obligatoria de Datos:** Para reclamos y reconexiones, el sistema solicita de forma secuencial: Ubicación GPS nativa de WhatsApp, Fotografía del lugar/medidor, y Glosa o descripción en texto.
