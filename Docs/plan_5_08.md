# Plan de Implementación del Backend API (COSMOL)

SOLO PARA BACKEND CHICHICO - FABIAN nada que ver aireyu

Este documento detalla la ruta paso a paso para crear los archivos PHP necesarios para el backend del Chatbot COSMOL, respetando la arquitectura modular y el patrón repositorio.

## Consideraciones Iniciales
- **Lenguaje:** PHP 7.3 (Vanilla PHP).
- **Patrón de Diseño:** Repositorio (para facilitar la migración de MySQL a Informix).
- **Entorno de Desarrollo:** Docker (n8n + PHP) y Base de Datos (MySQL mock de SAI en desarrollo, Informix en producción).

---

## Fases de Implementación (Ruta Paso a Paso)

### Fase 1: Configuración Base y Conexión a Base de Datos
El primer paso es establecer los cimientos del proyecto, incluyendo el enrutamiento básico y la conexión a la base de datos (con soporte para MySQL e Informix).

1. **Crear archivo de configuración principal:**
   - **Archivo:** `app/Config/database.php` o `app/Config/env.php`
   - **Propósito:** Almacenar las credenciales de conexión a la base de datos y variables de entorno (MySQL para desarrollo, Informix para producción).

2. **Crear la clase de conexión a la Base de Datos:**
   - **Archivo:** `app/Core/Database.php`
   - **Propósito:** Implementar un patrón Singleton para gestionar la conexión PDO. Deberá leer la configuración y conectarse al motor de base de datos correspondiente (XAMPP/MySQL en Fase 1, Informix en Fase 4).

3. **Crear el controlador base (Opcional pero recomendado):**
   - **Archivo:** `app/Core/Controller.php`
   - **Propósito:** Proveer métodos comunes para responder en formato JSON, capturar peticiones HTTP, y manejar errores generales.

### Fase 2: Implementación del Módulo de Socios (Autenticación y Consultas)
Este módulo se encargará de validar al asociado (Fricción Cero) y proveer datos de su cuenta.

1. **Crear la Interfaz del Repositorio de Socios:**
   - **Archivo:** `app/Data/Interfaces/SocioRepositoryInterface.php`
   - **Propósito:** Definir los métodos que debe tener cualquier repositorio de socios (ej. `findByCodigo($codigo)`, `getDeuda($codigo)`).

2. **Crear el Repositorio de Socios para MySQL (Desarrollo):**
   - **Archivo:** `app/Data/Repositories/MySQL/SocioRepository.php`
   - **Propósito:** Implementar la interfaz usando consultas SQL para MySQL (simulando la base de datos SAI).

3. **Crear el Caso de Uso / Lógica de Negocio (Módulo):**
   - **Archivo:** `app/Modules/Socio/SocioService.php`
   - **Propósito:** Contener la lógica de negocio. Recibe el repositorio por inyección de dependencias y orquesta la validación del socio y la obtención de deudas.

4. **Crear el Endpoint (Punto de entrada HTTP):**
   - **Archivo:** `public/api/socio.php`
   - **Propósito:** Recibir la petición POST/GET de n8n, instanciar el repositorio y el servicio, y devolver la respuesta en formato JSON.

### Fase 3: Implementación del Módulo de Reclamos
Este módulo permitirá registrar los reclamos técnicos y extraer la ubicación desde la base de datos.

1. **Crear la Interfaz del Repositorio de Reclamos:**
   - **Archivo:** `app/Data/Interfaces/ReclamoRepositoryInterface.php`
   - **Propósito:** Definir métodos como `createReclamo($data)`.

2. **Crear el Repositorio de Reclamos para MySQL (Desarrollo):**
   - **Archivo:** `app/Data/Repositories/MySQL/ReclamoRepository.php`
   - **Propósito:** Implementar la inserción de reclamos en la base de datos local.

3. **Crear el Caso de Uso / Lógica de Negocio:**
   - **Archivo:** `app/Modules/Reclamo/ReclamoService.php`
   - **Propósito:** Validar los datos del reclamo, cruzar información con el módulo de socios (para obtener la ubicación de la base de datos) y registrar el reclamo.

4. **Crear el Endpoint (Punto de entrada HTTP):**
   - **Archivo:** `public/api/reclamos.php`
   - **Propósito:** Recibir el webhook de n8n con los datos del flow de reclamos, procesarlo a través del servicio, y retornar la confirmación.

### Fase 4: Autoloading y Utilidades (Opcional pero muy recomendado)
Dado que estamos usando Vanilla PHP, un autoloader evitará tener decenas de `require_once`.

1. **Crear un autoloader básico (PSR-4):**
   - **Archivo:** `app/Core/Autoloader.php` (o usar Composer si se decide incorporar en el futuro).
   - **Propósito:** Cargar automáticamente las clases de `app/` cuando sean instanciadas en los archivos de `public/`.

2. **Inicializador global:**
   - **Archivo:** `app/bootstrap.php`
   - **Propósito:** Cargar el autoloader y configuraciones globales para ser incluido al inicio de `public/api/socio.php` y `public/api/reclamos.php`.

### Fase 5 (Futura): Migración a Informix
Cuando llegue el Sprint 4:

1. **Crear el Repositorio de Informix:**
   - **Archivos:** `app/Data/Repositories/Informix/SocioRepository.php` y `app/Data/Repositories/Informix/ReclamoRepository.php`.
   - **Propósito:** Replicar la funcionalidad usando la sintaxis y drivers de Informix (`pdo_informix`).

2. **Ajustar inyección en endpoints:**
   - Cambiar en `public/api/*.php` el repositorio instanciado (de MySQL a Informix) basado en una variable de entorno.

---

## Preguntas Abiertas

> [!IMPORTANT]
> **Por favor revisa estas preguntas antes de proceder:**
> 1. ¿Deseas que implementemos un autoloader sencillo en PHP puro (`spl_autoload_register`) o prefieres que utilicemos Composer (creando un `composer.json`) para manejar la carga de clases (PSR-4)? Composer suele ser el estándar, incluso para Vanilla PHP.
> 2. En `public/api/`, ¿crearemos archivos independientes por endpoint (ej. `socio.php`, `reclamos.php` como menciona el `AGENTS.md`) o prefieres un único punto de entrada (ej. `index.php`) que enrute las peticiones? Según el documento base, se usarán archivos separados. Confirmar si mantenemos esto.
> 3. ¿Quieres que prepare también un archivo `docker-compose.yml` para levantar PHP y n8n localmente en este momento, o nos enfocamos 100% en el código PHP primero?

## Plan de Ejecución
Una vez apruebes esta ruta y me indiques las respuestas a las preguntas abiertas, comenzaré a generar los archivos uno por uno sin ejecutar comandos de ejecución, solo creando el código estructurado en la carpeta correspondiente.