# Estructura del Proyecto - Chatbot COSMOL (Backend)

Este documento describe la organización de carpetas del backend en PHP y el rol de cada una. El objetivo es que cualquier integrante del equipo pueda ubicar rápidamente dónde debe agregar o modificar código.

## Por qué no usamos MVC clásico

Al no existir Frontend, el patrón **MVC tradicional** (Model-View-Controller) no aplica tal cual: no hay Vistas en formato HTML. Sin embargo, el concepto de "Vista" no desaparece del todo, sino que se transforma en la **serialización de la respuesta JSON**.

Por eso este proyecto adopta una variante orientada a APIs REST: **Controller → Service → Repository** (arquitectura en capas / Service Layer pattern). Es funcionalmente un MVC adaptado, donde:

- **Model** se divide en **Repository** (acceso a datos) y **Entity** (estructura del dato: Socio, Factura, Reclamo).
- **View** se reduce a una clase **Response** que arma el JSON estandarizado.
- **Controller** conserva su rol: recibe la petición HTTP, valida entrada y delega al Service.

Este enfoque es el que mejor encaja con el requisito de poder migrar de MySQL (Fase 1) a Informix 4GL (Fase 2) sin alterar la lógica de negocio, ya que el Service depende de una **interfaz** de repositorio y no de una implementación concreta.

## Estructura de carpetas

```
/cosmol-chatbot
├── public/
│   └── index.php              ← único punto de entrada (front controller)
│
├── app/
│   ├── Core/
│   │   ├── Router.php         ← enrutador propio (PHP puro)
│   │   ├── Request.php        ← wrapper de $_GET/$_POST/php://input
│   │   ├── Response.php       ← respuestas JSON estandarizadas
│   │   └── Autoloader.php     ← solo si no se usa Composer
│   │
│   ├── Config/
│   │   ├── database.php       ← config de conexión (MySQL / Informix)
│   │   └── env.php
│   │
│   ├── Modules/
│   │   ├── Clientes/
│   │   │   ├── Controllers/
│   │   │   │   └── ClienteController.php
│   │   │   ├── Services/
│   │   │   │   └── ClienteService.php
│   │   │   ├── Repositories/
│   │   │   │   ├── ClienteRepositoryInterface.php
│   │   │   │   └── ClienteRepositoryMySQL.php
│   │   │   └── Entities/
│   │   │       └── Socio.php
│   │   │
│   │   ├── Facturacion/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   └── Entities/
│   │   │
│   │   └── Reclamos/
│   │       ├── Controllers/
│   │       ├── Services/
│   │       ├── Repositories/
│   │       └── Entities/
│   │
│   └── Data/
│       └── Connection/
│           ├── MySQLConnection.php
│           └── InformixConnection.php   ← se agrega en Fase 2 (driver pdo_informix)
│
├── .htaccess                  ← redirige todo a public/index.php
├── .env
└── composer.json
```

## Rol de cada carpeta

| Carpeta | Rol |
|---|---|
| `public/index.php` | Punto único de entrada. Todo request pasa por aquí y el Router decide a qué Controller ir. Evita exponer archivos PHP sueltos como endpoints (ej. `socio.php`, `reclamos.php` directamente accesibles). |
| `Core/Router.php` | Define rutas tipo `POST /api/clientes/verificar` → `ClienteController@verificar`. |
| `Core/Request.php` | Estandariza la lectura de datos de entrada (query params, body JSON) sin depender directamente de superglobales en cada Controller. |
| `Core/Response.php` | Centraliza el formato de salida JSON (éxito, error, códigos HTTP) para que todos los endpoints respondan de manera consistente hacia n8n. |
| `Modules/*/Controllers/` | Solo reciben el request, validan datos de entrada y llaman al Service correspondiente. No acceden directamente a la base de datos. |
| `Modules/*/Services/` | Aquí vive la lógica de negocio (ej. "verificar código de socio antes de devolver facturas", "rechazar reconexión si la mora supera 2 meses"). |
| `Modules/*/Repositories/` (con interfaz) | Pieza clave para la migración MySQL → Informix. El Service depende de la **interfaz**, no de la implementación concreta. En Fase 2 solo se crea `ClienteRepositoryInformix.php` implementando la misma interfaz y se cambia la configuración, sin tocar Services ni Controllers. |
| `Modules/*/Entities/` | Clases simples que representan Socio, Factura y Reclamo (solo datos, sin lógica de conexión a BD). |
| `Data/Connection/` | Adaptadores de conexión física a cada motor de base de datos (MySQL en desarrollo, Informix vía `pdo_informix` en producción). |

## Recomendación: Composer solo para autoload

PHP 7.3 es compatible con Composer. Se recomienda usarlo únicamente para el **autoload PSR-4**, sin añadir ningún framework, para no violar la restricción de "PHP puro sin framework":

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Esto reemplaza la necesidad de un `Autoloader.php` manual y simplifica el mantenimiento del proyecto a medida que se agreguen nuevos módulos (Clientes, Facturación, Reclamos).

**Nota:** el esqueleto de carpetas y archivos NO debe crearse hasta que el responsable lo indique explícitamente.
