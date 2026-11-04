# Documentación Arquitectónica — Chatbot COSMOL

> **AVISO PARA AGENTES DE IA:** Este archivo es la fuente de verdad arquitectónica, técnica y operativa de este repositorio. Léelo completo antes de analizar, generar, modificar o sugerir código.
> **Regla de Oro:** El agente DEBE consultar y solicitar confirmación explícita al usuario antes de ejecutar cualquier acción que no esté contemplada en este documento, y **SIEMPRE antes de borrar o modificar cualquier archivo del proyecto**.

---

## 1. Descripción del Proyecto y Objetivos

Sistema de atención automatizada al asociado de la **Cooperativa COSMOL R.L.** mediante WhatsApp, diseñado bajo el principio de **fricción cero**: el asociado interactúa y se autentica ingresando únicamente su **Código de Asociado (Código Fijo)**, sin contraseñas ni formularios complejos.

### 1.1 Alcance del Sistema
* **Backend Puro e Integraciones:** Este repositorio no contiene frontend administrativo ni portales web para el usuario final. Toda la interacción del socio ocurre a través de los mensajes y menús de WhatsApp orquestados por **n8n** y procesados por el backend en **PHP 7.3**.
* **Modelo Económico y de Conversación:** Solo se gestionan **conversaciones iniciadas por el usuario** (*User-Initiated* dentro de la ventana de 24 horas de Meta), con costo $0.00 USD para COSMOL. **No se envían notificaciones push ni mensajes masivos iniciados por el sistema** a los socios para evitar cobros por conversación iniciada por negocio (*Business-Initiated*).
* **Evolución del Desarrollo:** Las fases y módulos centrales (autenticación, consultas de deuda, pagos por enlace, captura de reclamos con georreferenciación y reconexiones) se encuentran completamente operativos en código, adaptándose dinámicamente según las necesidades operativas de la Cooperativa.

---

## 2. Stack Tecnológico — Restricciones Estrictas

| Componente | Tecnología | Notas y Restricciones |
|---|---|---|
| **Backend API** | **PHP 7.3 (Vanilla)** | **Estricto.** NUNCA usar sintaxis o funciones exclusivas de PHP 7.4+ u 8.x (ej. constructor property promotion, union types, `match`, `str_contains`, tipos en propiedades de clase, etc.). |
| **Servidor Web Interno** | Apache | Empaquetado dentro del contenedor de PHP (`php:7.3-apache`). |
| **Proxy Inverso & SSL** | **Caddy** | Certificados SSL automáticos (HTTPS), enrutamiento de n8n, servicio de `/uploads/*` y reenvío de `COSMOL-Reportes`. |
| **Orquestador (Middleware)** | **n8n (v1 Self-Hosted)** | Recibe el webhook de Meta, ejecuta confirmaciones de lectura / typing y traslada el payload al backend PHP. |
| **Base de Datos (Pruebas)** | **PostgreSQL 16-alpine** | Simulación local y de servidor de pruebas (Mock del SAI bajo estándar ANSI SQL). Contenedor `cosmol_postgres`. |
| **Base de Datos (Producción)** | **APIs REST del Sistema SAI (IBM Informix)** | En expectativa de conexión/migración. El backend está desacoplado mediante repositorios para conectarse vía API o exportar datos directamente. |
| **Contenedores** | **Docker / docker-compose** | Todo el entorno corre contenerizado en un servidor de pruebas Ubuntu Server. No instalar servicios directamente en el host. |

---

## 3. Infraestructura, Redes y Despliegue

Todo el entorno se encuentra desplegado y corriendo en un servidor de pruebas con **Ubuntu Server**, con IP asignada y el dominio oficial configurado.

### 3.1 Servicios en `docker-compose.yml`
1. **`caddy` (`cosmol_caddy`):** Puertos expuestos al host: `80`, `443` y `8081`.
   * Enruta el dominio `https://chatbot.cosmol.com.bo` directamente al contenedor `cosmol_n8n:5678`.
   * Sirve directamente las imágenes estáticas subidas en `/uploads/*` (`./public/uploads`).
   * Enruta el puerto `8081` (`https://chatbot.cosmol.com.bo:8081`) hacia el software hermano `COSMOL-Reportes` (`host.docker.internal:8082`).
2. **`n8n` (`cosmol_n8n`):** Aislado dentro de la red Docker; no expone puertos públicos directos (solo a través de Caddy).
3. **`backend` (`cosmol_backend`):** PHP 7.3 Apache. Aislado dentro de la red interna. Procesa los eventos en `public/api/webhook_whatsapp.php`.
4. **`db` (`cosmol_postgres`):** Base de datos PostgreSQL de pruebas. Puerto local expuesto solo para administración (`127.0.0.1:5433:5432`).

### 3.2 Red Interna
Todos los contenedores se comunican a través del bridge interno `cosmol_internal_network` (`cosmol_network`).

---

## 4. Canal de Comunicación: Meta WhatsApp Cloud API

* **Estado de la Cuenta:** Actualmente operando con número y entorno de pruebas (sandbox), a la espera de la resolución de verificación empresarial oficial por parte de Meta.
* **Paradigma de Interacción:** Mensajes interactivos nativos generados desde el backend PHP (List Messages con secciones, Quick Reply Buttons, y plantillas de texto formateadas).
* **WhatsApp Flows:** Pospuesto como una posible evolución futura para interacciones con personal operativo interno; no se utiliza en el flujo del socio.
* **Nodos Estéticos en n8n (Experiencia Visual):**
  * **Doble Check Azul:** Envío inmediato de `status: "read"` a Meta al recibir el mensaje.
  * **Indicador "Escribiendo...":** Envío de `typing_indicator` con `action: "typing_on"`.
  * **Regla de Resiliencia:** Ambos nodos deben tener **`Continue on Fail: true`** y timeouts de 2-3 segundos en n8n para garantizar que ningún micro-corte o configuración de privacidad del socio interrumpa el flujo del bot.

---

## 5. Reglas de Negocio y Módulos Operativos

### 5.1 Autenticación de Fricción Cero
* El asociado ingresa su Código Fijo (ej. `10245`).
* El sistema valida la existencia en base de datos y crea un estado de sesión en memoria/persistencia.

### 5.2 Consultas y Facturación
* Desglose de meses pendientes, importes de facturas, consumo en metros cúbicos y total a pagar.

### 5.3 Pasarelas de Pago Duales (por URL)
Debido a políticas institucionales y convenios bancarios, los pagos se canalizan exclusivamente vía enlaces externos seguros:
1. **Multipago:** `https://multipago.com/service/cosmol_payment/first`
2. **Pago al Paso:** `https://red.pagoalpaso247.net/servicio/cosmol`

### 5.4 Registro Obligatorio de Reclamos
Para suplir la falta de direcciones georreferenciadas en sistemas heredados, el bot **exige obligatoriamente tres datos** antes de registrar un reclamo técnico:
1. **Ubicación GPS:** Enviada nativamente desde WhatsApp (latitud y longitud).
2. **Fotografía de Evidencia:** Foto del medidor o del problema (almacenada localmente y servida por `/uploads/`).
3. **Glosa/Descripción:** Detalle en texto con referencias aportadas por el socio.

### 5.5 Reconexiones Automáticas
* **Regla Estricta:** Si el socio adeuda **más de 2 facturas en mora**, la solicitud de reconexión es **rechazada automáticamente**, indicándole que debe regularizar su deuda antes de solicitar la reconexión.
* Si cumple la condición (≤ 2 facturas), se registra la orden de reconexión con su respectiva geolocalización.

### 5.6 Guardia de Mantenimiento y Anti-Spam
* `App\Modules\Session\MaintenanceGuard`: Permite pausar la atención del bot de manera global ante contingencias de red sin apagar los contenedores, respondiendo con un mensaje institucional amigable y previniendo saturación por spam.

---

## 6. Integración de Datos (PostgreSQL Mock vs SAI Informix)

El sistema implementa el **Patrón Repository** con interfaces estrictas en `app/Data/Interfaces/`:
* **Entorno Actual (Pruebas):** Implementaciones en `app/Data/Repositories/Postgres/` contra la base de datos PostgreSQL (`cosmol_postgres`) usando estándar ANSI SQL.
* **Integración Futura (SAI Informix):** Implementaciones en `app/Data/Repositories/Api/` para consumir los endpoints REST del SAI una vez provistos por el equipo de sistemas de COSMOL.
* **Transición Transparente:** La inyección de dependencias en `AppContainer` permite alternar entre PostgreSQL y la API externa mediante variables de entorno en `.env` sin alterar controladores ni plantillas.

---

## 7. Sinergia con el Sistema Hermano "COSMOL-Reportes"

El Chatbot se conecta de forma directa y asíncrona con el sistema **COSMOL-Reportes** para alimentar el módulo de estadísticas y la bandeja de trabajos pendientes de los operadores:

1. **Cliente de Reportes:** `App\Integrations\CosmolReportes\ClienteApiReportes` envía las consultas, reclamos y solicitudes de reconexión registradas por los socios.
2. **Cola de Resiliencia Local:** Si el sistema de reportes experimenta cortes o mantenimiento, las consultas se encolan en `ReportesBufferRepository` dentro de PostgreSQL.
3. **Despachador Programado (Flush):** El endpoint `public/api/cron_flush_reportes.php` es ejecutado periódicamente por n8n (Schedule Trigger) para vaciar los registros pendientes hacia la API de Reportes.
4. **Control Global:** Controlado por el feature flag `REPORTES_SYNC_ENABLED` en `.env`.

---

## 8. Arquitectura del Backend PHP (Estructura de Directorios)

El código fuente en `app/` sigue una arquitectura modular en capas desacopladas:

```
app/
├── Config/                   ← Variables de entorno y ajustes de BD/APIs
├── Core/                     ← Núcleo de la aplicación
│   ├── AppContainer.php      ← Contenedor de Inversión de Control (IoC/DI)
│   ├── Controller.php        ← Controlador base (respuestas JSON)
│   ├── Database.php          ← Conexión PDO singleton (PostgreSQL)
│   ├── FeatureFlags.php      ← Banderas de activación de características
│   ├── Logger.php            ← Registro de logs diarios
│   └── WebhookKernel.php     ← Orquestador y ciclo de vida de la petición
│
├── Data/                     ← Capa de Persistencia y Repositorios
│   ├── Interfaces/           ← Contratos (ISocioRepository, IReclamoRepository, etc.)
│   └── Repositories/
│       ├── Postgres/         ← Acceso a PostgreSQL de pruebas (ANSI SQL)
│       └── Api/              ← Acceso a APIs externas del SAI (Informix)
│
├── Modules/                  ← Lógica de Dominio y Servicios de Negocio
│   ├── Audit/                ← Auditoría y buffer hacia COSMOL-Reportes
│   ├── Facturacion/          ← Cálculo de facturas y deudas
│   ├── Reclamo/              ← Procesamiento y validación de reclamos
│   ├── Reconexion/           ← Evaluación de mora y órdenes de reconexión
│   ├── Session/              ← Máquina de estados del socio y MaintenanceGuard
│   └── Socio/                ← Búsqueda y validación de asociados
│
├── Presentacion/             ← Generación de Payloads para WhatsApp
│   ├── Flows/MenuActions/    ← Handlers de cada botón/opción del menú
│   └── PlantillasWhatsApp/   ← Plantillas JSON conformes a la Graph API de Meta
│
└── Integrations/             ← Conectores con sistemas externos
    └── CosmolReportes/       ← Cliente HTTP hacia la API de COSMOL-Reportes
```

---

## 9. Reglas Explícitas para el Agente de IA

- ❌ **Prohibido PHP 7.4+ / PHP 8.x:** Bajo ninguna circunstancia uses sintaxis incompatible con PHP 7.3.
- ❌ **Prohibido frameworks PHP o JS pesados:** El backend es PHP Vanilla estructurado. No sugerir Laravel, Symfony, React o Vue.
- ❌ **Prohibido saltarse la autorización interna:** Toda petición entrante desde n8n debe validar el header `X-Internal-Token`.
- ❌ **Prohibido exponer servicios innecesarios:** El backend PHP y la base de datos PostgreSQL no deben exponer puertos públicos al exterior en producción; todo el tráfico web entra exclusivamente por Caddy.
- ❌ **Prohibido alterar contratos de Meta:** Los payloads generados en `PlantillasWhatsApp` deben respetar estrictamente el formato JSON exigido por la WhatsApp Cloud API.
- ❌ **Prohibido modificar o borrar archivos sin confirmación:** Si una tarea requiere eliminar código, alterar esquemas de BD o modificar archivos existentes, **pregunta primero al usuario**.
- ✅ **Consultas preparadas obligatorias:** Todo acceso a base de datos debe usar PDO con *prepared statements*; jamás concatenar variables en sentencias SQL.
- ✅ **Mantener el desacoplamiento:** Si creas una nueva funcionalidad de datos, crea su interfaz en `app/Data/Interfaces/` y vincúlala a través de `AppContainer`.
- ✅ **Resiliencia ante fallos de APIs:** Cualquier llamada a `COSMOL-Reportes` o al `SAI` debe implementar timeouts cortos y manejo de excepciones mediante buffers locales para no interrumpir la experiencia del socio.
