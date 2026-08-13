# Estructura y Arquitectura del Proyecto - Chatbot COSMOL (Backend)

Este documento describe la arquitectura y la organización de carpetas del backend en PHP. El objetivo es que cualquier integrante del equipo entienda el rol de cada capa y sepa dónde agregar o modificar código siguiendo los estándares definidos.

## Arquitectura: Capas (Service-Repository Pattern)

Al ser una API consumida exclusivamente por n8n (no hay Frontend HTML), el patrón **MVC clásico no aplica directamente**. En su lugar, el proyecto adopta una arquitectura basada en capas orientada al dominio (Controller → Service → Repository):

- **Controller (Endpoints)**: Archivos independientes en `public/api/` que reciben la petición HTTP de n8n, leen los parámetros y delegan la ejecución al Servicio.
- **Service (Capa de Negocio)**: Clases en `app/Modules/` que contienen las reglas del negocio (ej. validar si un reclamo procede, aplicar lógica de "fricción cero").
- **Repository (Capa de Datos)**: Clases en `app/Data/Repositories/` que se encargan de armar y ejecutar las sentencias SQL. 
- **Interfaces**: Contratos en `app/Data/Interfaces/` que garantizan que los repositorios sean intercambiables.

> [!IMPORTANT]
> **El valor de esta arquitectura:** El Servicio (`SocioService`) nunca sabe si está hablando con MySQL (entorno de desarrollo) o con IBM Informix (producción). Solo habla con una Interfaz. Esto permite la futura migración de base de datos sin tocar ni una línea de la lógica de negocio principal.

## Estructura de carpetas actual

```text
cosmol-chatbot/
├── app/
│   ├── Config/
│   │   └── database.php              ← Constantes de entorno y BD
│   ├── Core/
│   │   ├── Autoloader.php            ← Autocarga de clases (PSR-4 manual)
│   │   ├── Controller.php            ← Métodos base (json, getBody, handleError)
│   │   └── Database.php              ← Singleton de conexión PDO
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── SocioRepositoryInterface.php
│   │   │   └── ReclamoRepositoryInterface.php
│   │   └── Repositories/
│   │       ├── MySQL/
│   │       │   ├── SocioRepository.php
│   │       │   └── ReclamoRepository.php
│   │       └── Informix/             ← Fase 5 (Futura)
│   ├── Modules/
│   │   ├── Socio/
│   │   │   └── SocioService.php
│   │   └── Reclamo/
│   │       └── ReclamoService.php
│   └── bootstrap.php                 ← Inicializador global del sistema
│
├── public/
│   └── api/
│       ├── socio.php                 ← Endpoint independiente
│       └── reclamos.php              ← Endpoint independiente
│
├── database/
│   └── init.sql                      ← Esquema SQL canónico para Docker
├── docker-compose.yml                ← Orquestación (n8n, backend, mysql)
└── .env                              ← Variables de entorno (credenciales)
```

## Rol de cada directorio

| Carpeta / Archivo | Rol |
|---|---|
| `public/api/` | **Endpoints independientes.** A diferencia de un *front controller* (un solo `index.php` con router), aquí se usan archivos separados (ej. `socio.php`). Esto hace que la integración de webhooks con n8n sea explícita, directa y muy fácil de depurar. |
| `app/Core/` | **Infraestructura base.** El `Autoloader` evita los molestos `require_once` manuales a lo largo del código. El `Controller` estandariza el JSON de respuesta (`{ success, message, data }`), y `Database` maneja el singleton de la conexión PDO. |
| `app/Modules/*/` | **Lógica de negocio.** Aquí viven los Servicios. Tienen estrictamente prohibido acceder a la base de datos de manera directa; deben pedir la información a través de los Repositorios inyectados. |
| `app/Data/Interfaces/` | **Contratos.** Aseguran que métodos como `findByCodigo()` existan obligatoriamente en todos los repositorios que los implementen, sin importar el motor de BD. |
| `app/Data/Repositories/` | **Consultas SQL.** Separa las sentencias puras de MySQL (usadas en Docker para desarrollo y testing) de las sentencias Informix (producción). |
| `app/bootstrap.php` | **Carga inicial.** Archivo requerido por todos los endpoints para inicializar el Autoloader, cargar variables de entorno, y configurar headers (CORS). |

## Decisiones Técnicas Clave

1. **Sin frameworks pesados:** Desarrollo en PHP puro (`Vanilla PHP 7.3`) para asegurar máxima compatibilidad con el entorno de servidor local y alto rendimiento.
2. **Autoloader nativo sin Composer:** Se implementó `spl_autoload_register` siguiendo el estándar PSR-4 de manera nativa. Al no haber dependencias de terceros por el momento, esto mantiene el proyecto simple.
3. **Respuesta Estandarizada:** Todas las llamadas a la API devuelven una estructura JSON uniforme: `{"success": bool, "message": string, "data": array|null}`.
4. **Entorno Contenerizado:** El desarrollo local se realiza exclusivamente con Docker (PHP + n8n + MySQL 5.7). Se elimina la dependencia de herramientas como XAMPP, garantizando que el entorno de todos los desarrolladores sea idéntico.
