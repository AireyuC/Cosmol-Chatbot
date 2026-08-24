# Estructura y Arquitectura del Proyecto - Chatbot COSMOL (Backend)

Este documento describe la arquitectura y la organización de carpetas del backend en PHP. El objetivo es que cualquier integrante del equipo entienda el rol de cada capa y sepa dónde agregar o modificar código siguiendo los estándares definidos.

## Arquitectura: Capas (Service-Repository Pattern)

Al ser una API consumida exclusivamente por n8n (no hay Frontend HTML), el patrón **MVC clásico no aplica directamente**. En su lugar, el proyecto adopta una arquitectura basada en capas orientada al dominio (Controller → Service → Repository):

- **Controller (Endpoints)**: Archivos independientes en `public/api/` que reciben la petición HTTP de n8n, leen los parámetros y delegan la ejecución al Servicio.
- **Service (Capa de Negocio)**: Clases en `app/Modules/` que contienen las reglas del negocio (ej. validar si un reclamo procede, aplicar lógica de "fricción cero").
- **Repository (Capa de Datos)**: Clases en `app/Data/Repositories/` que se encargan de obtener y persistir datos. En desarrollo consultan PostgreSQL local; en producción hacen peticiones HTTP a las **APIs REST del sistema SAI** (Informix).
- **Interfaces**: Contratos en `app/Data/Interfaces/` que garantizan que los repositorios sean intercambiables.

> [!IMPORTANT]
> **El valor de esta arquitectura:** El Servicio (`SocioService`) nunca sabe si está hablando con PostgreSQL (desarrollo) o con la API REST del SAI (producción). Solo habla con una Interfaz. Esto permite cambiar la fuente de datos sin tocar ni una línea de la lógica de negocio principal.

## Estructura de carpetas actual

```text
cosmol-chatbot/
├── app/
│   ├── Config/
│   │   └── database.php              ← Constantes de entorno y BD
│   ├── Core/
│   │   ├── Auth.php                  ← [Seguridad] Token interno
│   │   ├── Autoloader.php            ← Autocarga de clases (PSR-4 manual)
│   │   ├── Controller.php            ← Métodos base (json, getBody, handleError)
│   │   ├── Database.php              ← Singleton de conexión PDO
│   │   ├── Logger.php                ← [Seguridad] Logging JSON estructurado
│   │   ├── RateLimiter.php           ← [Seguridad] Rate limiting por IP
│   │   └── Validator.php             ← [Seguridad] Validación de inputs
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── ReclamoRepositoryInterface.php
│   │   │   ├── SessionRepositoryInterface.php
│   │   │   └── SocioRepositoryInterface.php
│   │   └── Repositories/
│   │       ├── Api/
│   │       │   └── SocioRepository.php
│   │       └── Postgres/
│   │           ├── ReclamoRepository.php
│   │           ├── SessionRepository.php
│   │           └── SocioRepository.php
│   ├── Integrations/
│   │   └── CosmolApi/
│   │       └── ClienteApiCosmol.php  ← Cliente HTTP para consumir APIs del SAI
│   ├── Modules/
│   │   ├── Facturacion/
│   │   ├── Reclamo/
│   │   │   └── ReclamoService.php
│   │   ├── Session/
│   │   │   └── SessionService.php
│   │   └── Socio/
│   │       └── SocioService.php
│   ├── Presentacion/
│   │   └── PlantillasWhatsApp/
│   │       ├── PlantillaFactura.php  ← Formateadores de texto para WhatsApp
│   │       ├── PlantillaSistema.php
│   │       └── PlantillaSocio.php
│   └── bootstrap.php                 ← Inicializador global del sistema
│
├── database/
│   └── init.sql                      ← Inicialización de PostgreSQL
│
├── Docs/                             ← Documentación general y técnica
│
├── public/
│   └── api/
│       ├── reclamos.php              ← Endpoint independiente
│       └── webhook_whatsapp.php      ← Controlador Central (Webhook para N8N)
│
├── .env                              ← Configuración de entorno (no versionado)
├── AGENTS.md                         ← Reglas maestras y contexto para IA
├── docker-compose.yml                ← Orquestación (n8n, backend, postgres)
├── dockerfile                        ← Configuración de PHP 7.3
└── env.example                       ← Plantilla de variables de entorno
```

## Rol de cada directorio

| Carpeta / Archivo | Rol |
|---|---|
| `public/api/` | **Endpoints.** Todo el flujo del chatbot de N8N se procesa a través del `webhook_whatsapp.php` que orquesta la máquina de estados y las llamadas a los servicios. |
| `app/Core/` | **Infraestructura base.** El `Autoloader` evita los molestos `require_once` manuales a lo largo del código. El `Controller` estandariza el JSON de respuesta (`{ success, message, data }`), y `Database` maneja el singleton de la conexión PDO. También se incluyen las clases de Seguridad (Auth, Validator, Logger, RateLimiter). |
| `app/Integrations/` | **Integraciones Externas.** Contiene los clientes (ej. `ClienteApiCosmol.php`) que manejan la comunicación HTTP directa con sistemas externos, como la API REST del SAI en producción. |
| `app/Modules/*/` | **Lógica de negocio.** Aquí viven los Servicios. Tienen estrictamente prohibido acceder a la base de datos de manera directa; deben pedir la información a través de los Repositorios inyectados. |
| `app/Presentacion/` | **Lógica de presentación.** Contiene las plantillas de WhatsApp encargadas de tomar los datos "crudos" del negocio y formatearlos con emojis, negritas y saltos de línea listos para enviar al usuario. |
| `app/Data/Interfaces/` | **Contratos de Repositorios.** Aquí se define *qué* deben hacer los repositorios, no *cómo* lo hacen. Esto es vital para cambiar entre PostgreSQL y las APIs del SAI. |
| `app/Data/Repositories/` | **Capa de datos intercambiable.** `Postgres/` contiene queries SQL para el entorno de desarrollo local (Docker). `Api/` contiene clientes HTTP que consumen las APIs REST proporcionadas por el servidor Informix del sistema SAI en producción. |
| `app/bootstrap.php` | **Carga inicial.** Archivo requerido por todos los endpoints para inicializar el Autoloader, cargar variables de entorno, emitir headers CORS y ejecutar la cadena de seguridad (`Auth` + `RateLimiter`). |

## Decisiones Técnicas Clave

1. **Sin frameworks pesados:** Desarrollo en PHP puro (`Vanilla PHP 7.3`) para asegurar máxima compatibilidad con el entorno de servidor local y alto rendimiento.
2. **Autoloader nativo sin Composer:** Se implementó `spl_autoload_register` siguiendo el estándar PSR-4 de manera nativa. Al no haber dependencias de terceros por el momento, esto mantiene el proyecto simple.
3. **Respuesta Estandarizada:** Todas las llamadas a la API devuelven una estructura JSON uniforme: `{"success": bool, "message": string, "data": array|null}`.
4. **Entorno Contenerizado:** El desarrollo local se realiza exclusivamente con Docker (PHP + n8n + PostgreSQL 16). Se elimina la dependencia de herramientas como XAMPP, garantizando que el entorno de todos los desarrolladores sea idéntico.
