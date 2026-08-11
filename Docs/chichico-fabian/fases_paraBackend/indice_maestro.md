# Índice Maestro y Documentación Consolidada — Plan Backend COSMOL

Este documento centraliza la documentación y estado de todas las fases del desarrollo del backend, sustituyendo los archivos individuales de los sprints anteriores (Fases 1 a 4) para simplificar la lectura.

---

## Estado Actual de las Fases

| Fase | Nombre | Estado |
|------|--------|--------|
| **Fase 1** | Configuración Base y BD | ✅ Completada |
| **Fase 2** | Módulo de Socios | ✅ Completada |
| **Fase 3** | Módulo de Reclamos | ✅ Completada |
| **Fase 4** | Autoloading y Utilidades | ✅ Completada |
| [**Fase 5**](fase5_migracion_informix.md) | Migración a Informix | ⏳ Futuro (Sprint 4) |

> [!NOTE]
> **Pendiente documentado:** la funcionalidad "Consultas de Cuenta" (`getDeuda()`, `getHistorialFacturas()`) del módulo de Socios queda como deuda técnica, marcada con `@todo` en `SocioRepositoryInterface.php`.

---

## Fases Completadas (Documentación)

### Fase 1 — Configuración Base y Conexión a Base de Datos
Establece la infraestructura invisible del backend: la configuración del entorno, la conexión a la base de datos y el controlador base.
- `.env`: Archivo de configuración puro con variables de entorno y credenciales.
- `app/Config/database.php`: Archivo de configuración que recibe los valores del `.env` y los almacena en constantes.
- `app/Core/Database.php`: Singleton que gestiona físicamente la conexión a la base de datos usando PDO. Reutiliza la conexión.
- `app/Core/Controller.php`: Clase base para endpoints con métodos comunes como `json()`, `getBody()`, y `handleError()`.

### Fase 2 — Módulo de Socios (Autenticación y Consultas)
Implementa el principio de "Fricción Cero" (validación solo con el Código de Asociado) utilizando el Patrón Repositorio.
- `app/Data/Interfaces/SocioRepositoryInterface.php`: Contrato que define métodos obligatorios (`findByCodigo`).
- `app/Data/Repositories/MySQL/SocioRepository.php`: Implementación concreta del repositorio para MySQL.
- `app/Modules/Socio/SocioService.php`: Capa de lógica de negocio que abstrae la fuente de datos.
- `public/api/socio.php`: Punto de entrada HTTP para la API de socios.

### Fase 3 — Módulo de Reclamos
Permite registrar reclamos (agua turbia, fuga, etc.) asociando la ubicación directamente desde la base de datos (sin pedir GPS al usuario).
- `app/Data/Interfaces/ReclamoRepositoryInterface.php`: Contrato para reclamos (`createReclamo`, `findByCodigoSocio`).
- `app/Data/Repositories/MySQL/ReclamoRepository.php`: Implementación concreta para insertar reclamos en MySQL.
- `app/Modules/Reclamo/ReclamoService.php`: Capa de lógica que valida datos y cruza información usando ambos repositorios (Reclamos y Socios).
- `public/api/reclamos.php`: Punto de entrada HTTP para la creación de reclamos.

### Fase 4 — Autoloading y Utilidades
Infraestructura de conveniencia que hace la vida del desarrollador más fácil y evita errores.
- `app/Core/Autoloader.php`: Implementa el estándar PSR-4 de forma manual (`spl_autoload_register`) para la carga automática de clases sin Composer.
- `app/bootstrap.php`: Iniciador global que carga el autoloader, la configuración de base de datos, maneja errores y establece headers globales (CORS).

---

## Árbol de archivos del proyecto completo

```text
cosmol-chatbot/
│
├── app/
│   ├── Config/
│   │   └── database.php              ← Fase 1: Credenciales y variables de entorno
│   │
│   ├── Core/
│   │   ├── Autoloader.php            ← Fase 4: PSR-4 class loader
│   │   ├── Controller.php            ← Fase 1: Métodos JSON comunes (base)
│   │   └── Database.php              ← Fase 1: Singleton de conexión PDO
│   │
│   ├── Data/
│   │   ├── Interfaces/
│   │   │   ├── SocioRepositoryInterface.php    ← Fase 2: Contrato de socios
│   │   │   └── ReclamoRepositoryInterface.php  ← Fase 3: Contrato de reclamos
│   │   │
│   │   └── Repositories/
│   │       ├── MySQL/
│   │       │   ├── SocioRepository.php         ← Fase 2: Implementación MySQL
│   │       │   └── ReclamoRepository.php       ← Fase 3: Implementación MySQL
│   │       │
│   │       └── Informix/
│   │           ├── SocioRepository.php         ← Fase 5: Implementación Informix
│   │           └── ReclamoRepository.php       ← Fase 5: Implementación Informix
│   │
│   ├── Modules/
│   │   ├── Socio/
│   │   │   └── SocioService.php      ← Fase 2: Lógica de negocio de socios
│   │   │
│   │   └── Reclamo/
│   │       └── ReclamoService.php    ← Fase 3: Lógica de negocio de reclamos
│   │
│   └── bootstrap.php                 ← Fase 4: Inicializador global del sistema
│
└── public/
    └── api/
        ├── socio.php                 ← Fase 2: Endpoint HTTP para socios
        └── reclamos.php              ← Fase 3: Endpoint HTTP para reclamos
```

---

## Decisiones de Arquitectura Tomadas (Preguntas Abiertas Resueltas)
- **Autoloader manual vs Composer**: Se usa un autoloader manual (`spl_autoload_register`) ya que no hay dependencias de terceros.
- **Endpoints separados vs Router**: Se mantienen archivos separados (`socio.php`, `reclamos.php`) para simplificar el debug.
- **Entorno Docker**: Se utiliza un `docker-compose.yml` local para levantar la API, MySQL y n8n, dejando XAMPP de lado.
