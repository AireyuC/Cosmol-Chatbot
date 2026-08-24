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
│   │   ├── Auth.php                  ← Autenticación por token interno
│   │   ├── Autoloader.php            ← Autocarga de clases (PSR-4 manual)
│   │   ├── Controller.php            ← Métodos base (json, getBody, handleError)
│   │   ├── Database.php              ← Singleton de conexión PDO
│   │   ├── Logger.php                ← Logging estructurado JSON
│   │   ├── RateLimiter.php           ← Limitador de velocidad por IP
│   │   └── Validator.php             ← Validación de inputs
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── SocioRepositoryInterface.php
│   │   │   ├── ReclamoRepositoryInterface.php
│   │   │   └── SessionRepositoryInterface.php
│   │   └── Repositories/
│   │       ├── Api/
│   │       │   └── SocioRepository.php
│   │       └── Postgres/
│   │           ├── ReclamoRepository.php
│   │           ├── SessionRepository.php
│   │           └── SocioRepository.php
│   ├── Modules/
│   │   ├── Socio/
│   │   │   └── SocioService.php
│   │   ├── Reclamo/
│   │   │   └── ReclamoService.php
│   │   └── Session/
│   │       └── SessionService.php
│   ├── Presentacion/
│   │   └── PlantillasWhatsApp/       ← Ensamblado de payloads para WhatsApp
│   │       ├── PlantillaFactura.php
│   │       ├── PlantillaSistema.php
│   │       └── PlantillaSocio.php
│   └── bootstrap.php                 ← Inicializador global del sistema
│
├── public/
│   └── api/
│       ├── reclamos.php              ← Endpoint independiente de reclamos
│       └── webhook_whatsapp.php      ← Controlador Frontal Centralizado (n8n)
│
├── database/
│   └── init.sql                      ← Inicialización ANSI SQL de PostgreSQL 16
├── dockerfile                        ← Configuración de PHP 7.3
├── docker-compose.yml                ← Orquestación (n8n, backend, postgres)
└── .env                              ← Configuración de entorno
```

## Rol de cada directorio

| Carpeta / Archivo | Rol |
|---|---|
| `public/api/` | **Endpoints.** Todo el flujo del chatbot de n8n se procesa a través de `webhook_whatsapp.php` (máquina de estados y plantillas) y `reclamos.php` (registro directo de reclamos). |
| `app/Core/` | **Infraestructura base.** El `Autoloader` evita los molestos `require_once` manuales a lo largo del código. El `Controller` estandariza el JSON de respuesta (`{ success, message, data }`), `Database` maneja el singleton de la conexión PDO, y `Auth`/`RateLimiter`/`Validator`/`Logger` aseguran el pipeline de seguridad. |
| `app/Modules/*/` | **Lógica de negocio.** Aquí viven los Servicios (`SocioService`, `ReclamoService`, `SessionService`). Tienen estrictamente prohibido acceder a la base de datos de manera directa; deben pedir la información a través de los Repositorios inyectados. |
| `app/Presentacion/PlantillasWhatsApp/` | **Formateo visual de WhatsApp.** Genera las estructuras JSON interactivas (botones, listas, textos de facturación con Multipago y errores de sistema) para n8n. |
| `app/Data/Interfaces/` | **Contratos de Repositorios.** Aquí se define *qué* deben hacer los repositorios, no *cómo* lo hacen. Esto es vital para cambiar entre PostgreSQL y las APIs del SAI. |
| `app/Data/Repositories/` | **Capa de datos intercambiable.** `Postgres/` contiene queries SQL para el entorno de desarrollo local (Docker). `Api/` contiene clientes HTTP que consumen las APIs REST proporcionadas por el servidor Informix del sistema SAI en producción. |
| `app/bootstrap.php` | **Carga inicial.** Archivo requerido por todos los endpoints para inicializar el Autoloader, cargar variables de entorno, emitir headers CORS y ejecutar la cadena de seguridad (`Auth` + `RateLimiter`). |

## Decisiones Técnicas Clave

1. **Sin frameworks pesados:** Desarrollo en PHP puro (`Vanilla PHP 7.3`) para asegurar máxima compatibilidad con el entorno de servidor local y alto rendimiento.
2. **Autoloader nativo sin Composer:** Se implementó `spl_autoload_register` siguiendo el estándar PSR-4 de manera nativa. Al no haber dependencias de terceros por el momento, esto mantiene el proyecto simple.
3. **Respuesta Estandarizada:** Todas las llamadas a la API devuelven una estructura JSON uniforme: `{"success": bool, "message": string, "data": array|null}`.
4. **Entorno Contenerizado:** El desarrollo local se realiza exclusivamente con Docker (PHP + n8n + PostgreSQL 16). Se elimina la dependencia de herramientas como XAMPP, garantizando que el entorno de todos los desarrolladores sea idéntico.
