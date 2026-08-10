# Índice Maestro — Plan Backend COSMOL

Este documento es el punto de navegación central. Cada fase tiene su propio archivo con la explicación detallada de cada archivo PHP que se va a construir.

---

## Resumen de Fases

| Fase | Nombre | Archivos | Estado |
|------|--------|----------|--------|
| [Fase 1](fase1_config_base.md) | Configuración Base y BD | `database.php`, `Database.php`, `Controller.php` | 🔴 Por hacer |
| [Fase 2](fase2_modulo_socios.md) | Módulo de Socios | `SocioRepositoryInterface.php`, `SocioRepository.php` (MySQL), `SocioService.php`, `socio.php` | 🔴 Por hacer |
| [Fase 3](fase3_modulo_reclamos.md) | Módulo de Reclamos | `ReclamoRepositoryInterface.php`, `ReclamoRepository.php` (MySQL), `ReclamoService.php`, `reclamos.php` | 🔴 Por hacer |
| [Fase 4](fase4_autoloading.md) | Autoloading y Utilidades | `Autoloader.php`, `bootstrap.php` | 🔴 Por hacer |
| [Fase 5](fase5_migracion_informix.md) | Migración a Informix | `SocioRepository.php` (Informix), `ReclamoRepository.php` (Informix) | ⏳ Futuro (Sprint 4) |

---

## Árbol de archivos del proyecto completo

```
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
│   │       │   └── ReclamoRepository.php        ← Fase 3: Implementación MySQL
│   │       │
│   │       └── Informix/
│   │           ├── SocioRepository.php         ← Fase 5: Implementación Informix
│   │           └── ReclamoRepository.php        ← Fase 5: Implementación Informix
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

## Orden de construcción recomendado

> [!IMPORTANT]
> Construir en este orden evita dependencias rotas. Cada capa depende de la anterior.

```
1. database.php          → define las credenciales
2. Database.php          → usa las credenciales para conectar
3. Controller.php        → métodos base para todos los endpoints
4. Autoloader.php        → habilita el descubrimiento de clases
5. bootstrap.php         → une todo lo anterior en un único include
6. SocioRepositoryInterface.php  → define el contrato
7. SocioRepository.php (MySQL)   → implementa el contrato
8. SocioService.php              → usa el repositorio con lógica
9. socio.php (endpoint)          → expone el servicio vía HTTP
10. ReclamoRepositoryInterface.php → nuevo contrato
11. ReclamoRepository.php (MySQL)  → nueva implementación
12. ReclamoService.php             → lógica de reclamos
13. reclamos.php (endpoint)        → expone vía HTTP
```

---

## Preguntas abiertas del plan original

El documento `plan_5_08.md` menciona 3 preguntas pendientes de decisión:

### Pregunta 1: ¿Autoloader manual o Composer?
> ¿`spl_autoload_register` en PHP puro o Composer con `composer.json`?

**Respuesta:** Empezar con el autoloader manual (Fase 4) ya que no hay paquetes externos por instalar. Se puede migrar a Composer sin romper nada cuando sea necesario.

### Pregunta 2: ¿Endpoints separados o un único `index.php`?
> ¿`socio.php` + `reclamos.php` por separado o un router central?

**Respuesta:** Se mantienen archivos separados (`socio.php`, `reclamos.php`). Esto simplifica el debug y es más explícito para n8n.

### Pregunta 3: ¿`docker-compose.yml` ahora o después?
> ¿Preparar Docker en este momento o enfocarse 100% en el código PHP?

**Respuesta:** Enfocarse en el código PHP primero (Fases 1–4). El `docker-compose.yml` puede prepararse en paralelo o al final cuando los endpoints estén validados con XAMPP.

---

## Cómo avanzar

Cada vez que estés listo para implementar una fase:

1. Lee el documento de la fase correspondiente.
2. Confirma que entiendes qué hace cada archivo.
3. Pídele al agente que genere el código de ese archivo específico.
4. Verifica con XAMPP antes de pasar a la siguiente fase.
