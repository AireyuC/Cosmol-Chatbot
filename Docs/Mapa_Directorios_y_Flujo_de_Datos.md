# Mapa Integral de Directorios y Flujo de Entrada de Datos

**Proyecto:** Backend Chatbot WhatsApp — Cooperativa de Servicios Públicos Montero (COSMOL R.L.)  
**Versión:** 2.0 (Post-Reestructuración Modular)  
**Entorno Tecnológico:** PHP 7.3 Vanilla, Docker (PostgreSQL 16, n8n, Caddy), APIs REST Informix SAI.

---

## 1. Introducción y Filosofía Arquitectónica

El backend del chatbot de COSMOL está concebido como un **motor conversacional y transaccional desacoplado**. No dispone de una interfaz web tradicional para usuarios finales; en su lugar, atiende las peticiones HTTP orquestadas por **n8n** en representación de los socios que interactúan a través de **Meta WhatsApp Cloud API**.

### Principios Fundamentales:
1. **Arquitectura por Capas Limpias (Clean Layers):** Separación estricta entre presentación conversacional, lógica de negocio de dominio, acceso a datos e integraciones externas.
2. **Inversión de Dependencias (Service-Repository Pattern):** Los servicios de negocio interactúan únicamente a través de contratos (`Interfaces`). No conocen si la fuente subyacente es la base de datos PostgreSQL local (desarrollo/mocks) o los microservicios REST del sistema Informix SAI (producción).
3. **Máquina de Estados Finita (FSM):** Cada interacción se rige por un estado de sesión persistido en base de datos (`chat_session`), garantizando que la conversación recuerde el progreso del socio (ej. captura secuencial de GPS, foto y glosa).
4. **Resiliencia con Buffer Local:** Las métricas y eventos de auditoría destinados al software administrativo (`COSMOL-Reportes`) cuentan con un buffer local en PostgreSQL (`cola_reportes`) para que el chatbot jamás se congele ni pierda estadísticas si el servidor receptor experimenta caídas o latencia.

---

## 2. Esquema Completo de Directorios y Archivos

```text
Cosmol-Chatbot/
├── .env                                       # Variables de entorno reales (no versionado)
├── .env.example                               # Plantilla limpia de variables de entorno
├── .gitignore                                 # Reglas de exclusión para Git
├── AGENTS.md                                  # Documento maestro de reglas arquitectónicas
├── Caddyfile                                  # Configuración de proxy inverso, SSL y archivos estáticos
├── docker-compose.yml                         # Orquestador multi-contenedor (caddy, backend, db, n8n)
├── dockerfile                                 # Construcción de la imagen PHP 7.3 Apache + extensiones
│
├── Docs/                                      # Documentación técnica y guías operativas
│   ├── Arquitectura_en_capas/                 # Guías de transición de arquitectura
│   ├── fases_paraBackend/                     # Registro de fases de desarrollo
│   ├── guia_despliegue_linux/                 # Manuales de puesta en producción
│   ├── Plan_seguridad/                        # Documentación de CORS, tokens y rate limiting
│   ├── plan_n8n/                              # Workflows y flujos de n8n
│   ├── ESTRUCTURA.md                          # Resumen conceptual de módulos
│   ├── Integracion_API_Externa.md             # Contrato de integración Informix SAI
│   ├── Integracion_COSMOL_Reportes.md         # Contrato con el sistema de Reportes
│   ├── documentacion_tecnica_oficial.md       # Memoria técnica completa
│   └── Mapa_Directorios_y_Flujo_de_Datos.md   # [ESTE DOCUMENTO] Mapa y flujo general
│
├── database/                                  # Scripts de infraestructura de base de datos
│   └── init.sql                               # DDL de PostgreSQL: tablas socio, factura, chat_session, cola_reportes
│
├── public/                                    # Raíz pública expuesta en el servidor web Apache
│   ├── index.php                              # Redirección por defecto
│   ├── api/
│   │   ├── reclamos.php                       # [Legacy] Endpoint previo de reclamos
│   │   └── webhook_whatsapp.php               # FRONT CONTROLLER: Punto único de entrada desde n8n
│   └── uploads/                               # Directorio de fotos descargadas de WhatsApp (servidas por Caddy)
│       ├── reclamos/                          # Fotos de reclamos técnicos organizadas por código de socio
│       └── reconexiones/                      # Fotos de comprobantes/medidores de reconexión
│
├── scripts/                                   # Utilidades de mantenimiento en línea de comandos (CLI)
│   └── test_reportes.php                      # Diagnóstico en vivo de la API de Reportes y vaciado de cola
│
└── app/                                       # NÚCLEO DE LA APLICACIÓN (Lógica y Dominio)
    ├── bootstrap.php                          # Inicializador global (Autoloader, Config, CORS, Auth token)
    │
    ├── Config/                                # Ajustes de configuración
    │   └── database.php                       # Carga y definición de constantes desde variables de entorno
    │
    ├── Core/                                  # Componentes transversales y de infraestructura
    │   ├── AppContainer.php                   # Contenedor de Inyección de Dependencias (Lazy Loading IoC)
    │   ├── Auth.php                           # Validación del token interno X-Internal-Token
    │   ├── Autoloader.php                     # Autocargador PSR-4 nativo (sin dependencias de Composer)
    │   ├── Controller.php                     # Controlador base con formateo de respuestas JSON
    │   ├── Database.php                       # Singleton de conexión PDO para PostgreSQL / Informix
    │   ├── FeatureFlags.php                   # Conmutadores de características y modo mantenimiento global
    │   ├── Logger.php                         # Registro de logs estructurados en JSON (/var/log/cosmol_api.log)
    │   ├── MaintenanceGuard.php               # Filtro de escudo para modo mantenimiento y antispam
    │   ├── RateLimiter.php                    # Limitador de tasa de peticiones por IP
    │   └── WebhookKernel.php                  # Orquestador maestro del ciclo de vida de la petición HTTP
    │
    ├── Data/                                  # Capa de Acceso a Datos (Data Access Layer)
    │   ├── Interfaces/                        # Contratos de repositorios (desacoplamiento total)
    │   │   ├── ReclamoRepositoryInterface.php
    │   │   ├── ReconexionRepositoryInterface.php
    │   │   ├── ReportesRepositoryInterface.php
    │   │   ├── SessionRepositoryInterface.php
    │   │   └── SocioRepositoryInterface.php
    │   │
    │   └── Repositories/                      # Implementaciones concretas de acceso a datos
    │       ├── Api/                           # Repositorios de producción (consumen APIs REST Informix)
    │       │   ├── ReclamoRepository.php
    │       │   ├── ReconexionRepository.php
    │       │   └── SocioRepository.php
    │       └── Postgres/                      # Repositorios para PostgreSQL local
    │           ├── ReportesBufferRepository.php # Buffer de contingencia (tabla cola_reportes)
    │           └── SessionRepository.php        # Persistencia de conversaciones (tabla chat_session)
    │
    ├── Integrations/                          # Clientes de Servicios Externos
    │   ├── CosmolApi/
    │   │   └── ClienteApiCosmol.php           # Cliente HTTP cURL hacia microservicios Informix SAI
    │   ├── CosmolReportes/
    │   │   └── ClienteApiReportes.php         # Cliente HTTP cURL hacia la API de COSMOL-Reportes
    │   └── WhatsApp/
    │       └── WhatsAppMediaService.php       # Cliente Meta Graph API para descarga de imágenes adjuntas
    │
    ├── Modules/                               # Capa de Dominio y Lógica de Negocio
    │   ├── Audit/
    │   │   └── ConsultaAuditService.php       # Gestión de métricas con buffer local y reintentos automáticos
    │   ├── Facturacion/
    │   │   └── FacturacionService.php         # Lógica de estados de cuenta, facturas pendientes e historial
    │   ├── Reclamo/
    │   │   └── ReclamoService.php             # Reglas de registro y consulta de reclamos
    │   ├── Reconexion/
    │   │   └── ReconexionService.php          # Reglas de reconexión (validación de mora máxima <= 2 facturas)
    │   ├── Session/
    │   │   └── SessionService.php             # Control de transiciones de estados y expiración por inactividad
    │   └── Socio/
    │       └── SocioService.php               # Identificación y validación de socios por código fijo
    │
    └── Presentacion/                          # Capa de Interacción y Presentación WhatsApp
        ├── Flows/                             # Orquestación de la Máquina de Estados
        │   ├── FlowRouter.php                 # Enrutador principal: despacha la petición según estado actual
        │   │
        │   ├── Manejadores/                   # Flow Handlers especializados por cada estado
        │   │   ├── BaseFlowHandler.php        # Funcionalidad común entre manejadores
        │   │   ├── AuthFlowHandler.php        # Estado AWAITING_CODE (ingreso y validación de código fijo)
        │   │   ├── MenuFlowHandler.php        # Estado MAIN_MENU (despacha opciones interactivas)
        │   │   ├── ReclamoFlowHandler.php     # Estados AWAITING_RECLAMO_* (GPS -> Foto -> Glosa)
        │   │   └── ReconexionFlowHandler.php  # Estados AWAITING_RECONEXION_* (GPS -> Foto -> Glosa)
        │   │
        │   └── MenuActions/                   # Acciones desacopladas del Menú Principal
        │       ├── PagarAction.php            # Consulta de deudas y enlace de pago Multipago
        │       ├── HistorialAction.php        # Listado de facturas pagadas anteriormente
        │       ├── ReconexionAction.php       # Inicio de trámite de reconexión o aviso de mora
        │       ├── ReclamoAction.php          # Despliegue de submenú de tipos de reclamo
        │       ├── EstadoTramitesAction.php   # Seguimiento a tickets de reclamos y reconexiones
        │       └── InfoAction.php             # Ubicaciones, horarios y derivación con operador
        │
        └── PlantillasWhatsApp/                # Formateadores de Mensajería Interactiva de Meta
            ├── PlantillaFactura.php           # Tarjetas de deudas y listados de facturas
            ├── PlantillaReclamo.php           # Solicitudes de GPS, foto y confirmación de ticket
            ├── PlantillaReconexion.php        # Advertencias de mora y solicitud de datos
            ├── PlantillaSistema.php           # Mensajes de error, bienvenida y aviso de mantenimiento
            └── PlantillaSocio.php             # Menú interactivo con botones y listas desplegables
```

---

## 3. Flujo Integral de Entrada de Datos (Paso a Paso)

El siguiente diagrama ilustra el recorrido exacto de un mensaje desde que el socio presiona "Enviar" en su celular hasta que recibe la respuesta interactiva:

```text
 ┌─────────────────┐
 │ Socio (WhatsApp)│
 └────────┬────────┘
          │ 1. Envía mensaje (Texto / Botón / Ubicación GPS / Foto)
          ▼
 ┌──────────────────────┐
 │ Meta Cloud API       │
 └────────┬─────────────┘
          │ 2. Webhook HTTPS público
          ▼
 ┌──────────────────────┐
 │ Servidor Web Caddy   │ ──(Si es /uploads/*)──► Entrega imagen estática
 └────────┬─────────────┘
          │ 3. Proxy reverso a cosmol_n8n:5678
          ▼
 ┌──────────────────────┐
 │ Orquestador n8n      │
 └────────┬─────────────┘
          │ 4. Petición HTTP POST interna con X-Internal-Token
          ▼
 ┌────────────────────────────────────────────────────────────────────────┐
 │ BACKEND PHP (cosmol_backend)                                           │
 │                                                                        │
 │ [bootstrap.php]                                                        │
 │   • Registra Autoloader PSR-4                                          │
 │   • Carga constantes desde .env (app/Config/database.php)              │
 │   • Valida token interno (Auth::validateInternalToken)                 │
 │   • Controla límite de tráfico (RateLimiter::check)                    │
 │                                                                        │
 │ [public/api/webhook_whatsapp.php]                                      │
 │   • Instancia WebhookKernel y delega ejecución                         │
 │                                                                        │
 │ [WebhookKernel::handle]                                                │
 │   • Lee payload JSON (telefono, tipo_mensaje, contenido)               │
 │   • Verifica MaintenanceGuard (¿Chatbot en mantenimiento?)             │
 │   • SessionService::processSessionState                                │
 │       - Lee/crea sesión en PostgreSQL (chat_session)                   │
 │       - ¿Expiró por inactividad (>5 min)? Resetea a AWAITING_CODE      │
 │       - Obtiene estado_actual y context_data                           │
 │                                                                        │
 │ [FlowRouter::dispatch]                                                 │
 │   • Evalúa el estado de la conversación y enruta:                      │
 │     ├── AWAITING_CODE          ──► AuthFlowHandler                     │
 │     ├── MAIN_MENU              ──► MenuFlowHandler (MenuActions)       │
 │     ├── AWAITING_RECLAMO_*     ──► ReclamoFlowHandler                  │
 │     └── AWAITING_RECONEXION_*  ──► ReconexionFlowHandler               │
 │                                                                        │
 │ [Capa de Negocio y Datos]                                              │
 │   • Servicios (Socio, Facturación, Reclamo, Reconexión)                │
 │   • Repositorios (Consultan Informix SAI vía ClienteApiCosmol)         │
 │   • MediaService (Descarga fotos de Meta y guarda en /uploads)         │
 │                                                                        │
 │ [Auditoría y Métricas]                                                 │
 │   • ConsultaAuditService::registrar(...)                               │
 │       - Envío inmediato a COSMOL-Reportes vía ClienteApiReportes       │
 │       - Si falla / timeout ➔ Almacena en PostgreSQL (cola_reportes)    │
 │       - Si éxito ➔ Sincroniza hasta 5 registros pendientes del buffer  │
 │                                                                        │
 │ [Plantillas WhatsApp]                                                  │
 │   • Construye JSON interactivo de Meta (Botones, Listas, Textos)       │
 └────────┬───────────────────────────────────────────────────────────────┘
          │ 5. Responde HTTP 200 con { status: "success", whatsapp_payload: { ... } }
          ▼
 ┌──────────────────────┐
 │ Orquestador n8n      │
 └────────┬─────────────┘
          │ 6. Dispara nodo "WhatsApp Send Message" a Meta Cloud API
          ▼
 ┌──────────────────────┐
 │ Meta Cloud API       │
 └────────┬─────────────┘
          │ 7. Entrega mensaje interactivo al dispositivo
          ▼
 ┌─────────────────┐
 │ Socio (WhatsApp)│
 └─────────────────┘
```

---

## 4. Descripción Detallada de las Fases del Flujo

### Fase 1: Recepción Externa y Proxy Reverso (Caddy + n8n)
* El usuario interactúa en WhatsApp. Meta envía un webhook con la estructura oficial a la URL pública segura configurada (`WEBHOOK_URL`).
* **Caddy:** Recibe el tráfico en los puertos `80` y `443`. Si la petición solicita un recurso bajo `/uploads/*`, Caddy entrega la imagen de inmediato desde el disco sin despertar a PHP ni a la base de datos. Para cualquier otra ruta, Caddy traslada el paquete hacia el contenedor `cosmol_n8n:5678`.
* **n8n:** Evalúa el tipo de mensaje recibido de Meta, extrae las variables básicas (`telefono`, `tipo_mensaje`, `contenido`) e inyecta la cabecera obligatoria de seguridad:
  `X-Internal-Token: <API_INTERNAL_TOKEN>`
  Inmediatamente despacha un `POST` interno hacia:
  `http://cosmol_backend:80/api/webhook_whatsapp.php`

---

### Fase 2: Blindaje de Seguridad y Carga de Entorno (`bootstrap.php`)
Antes de que cualquier lógica se ejecute:
1. `app/Core/Autoloader.php`: Registra la autocarga automática bajo el prefijo `App\`.
2. `app/Config/database.php`: Lee las credenciales de base de datos, URLs de servicios y tokens desde las variables inyectadas por Docker.
3. `app/Core/Auth.php`: Verifica la cabecera `X-Internal-Token` usando `hash_equals()`. Si el token falta o es incorrecto, corta la ejecución inmediatamente con `HTTP 401 Unauthorized`.
4. `app/Core/RateLimiter.php`: Inspecciona el número de solicitudes por segundo para evitar sobrecargas o ataques de denegación de servicio.

---

### Fase 3: Núcleo y Sesión (`WebhookKernel` + `SessionService`)
El archivo `public/api/webhook_whatsapp.php` contiene únicamente dos líneas y delega el control a `WebhookKernel->handle()`:
1. **Validación de Entradas:** Confirma la presencia obligatoria de teléfono y contenido.
2. **MaintenanceGuard:** Si el administrador activó el modo mantenimiento en `FeatureFlags`, el bot devuelve un mensaje de sistema o guarda silencio si el usuario insiste (mecanismo antispam).
3. **Control de Sesión (`SessionService`):**
   * Consulta la tabla `chat_session` en PostgreSQL por el número de teléfono.
   * **Detección de Expiración:** Si transcurrieron más de 5 minutos desde la última interacción, la sesión se reinicia automáticamente al estado `AWAITING_CODE`.
   * Recupera el `estado_actual`, el `codigo_socio` autenticado y los datos de contexto temporal (`context_data` en formato JSON).

---

### Fase 4: Despacho por Máquina de Estados (`FlowRouter`)
`FlowRouter` analiza el `estado_actual` recuperado de la base de datos y transfiere el control al manejador correspondiente:

| Estado Actual | Manejador Asignado | Acción que realiza |
|---|---|---|
| `AWAITING_CODE` | `AuthFlowHandler` | Solicita el código fijo del socio. Si el socio lo envía, consulta a `SocioService`. Si es válido, lo autentica, cambia el estado a `MAIN_MENU` y despliega la bienvenida. Si falla 3 veces, bloquea la sesión temporalmente (`BLOCKED`). |
| `MAIN_MENU` | `MenuFlowHandler` | Recibe la opción seleccionada por el socio en los botones o listas y delega a las clases de `MenuActions/` (`Pagar`, `Historial`, `Reclamo`, `Reconexion`, etc.). |
| `AWAITING_RECLAMO_*` | `ReclamoFlowHandler` | Orquesta la captura secuencial de 3 pasos: **1) GPS nativo**, **2) Foto de la falla** (descargada vía `WhatsAppMediaService`), y **3) Glosa explicativa**. Al finalizar, llama a `ReclamoService->registrarReclamo()` y retorna el ticket. |
| `AWAITING_RECONEXION_*` | `ReconexionFlowHandler` | Valida previamente que el socio no deba más de 2 facturas. Luego captura secuencialmente: **1) GPS**, **2) Foto del comprobante/medidor**, y **3) Glosa**. Registra la solicitud vía `ReconexionService` y confirma al socio. |
| `BLOCKED` | Silencio Total | Retorna `null`. El bot no responde para proteger el sistema ante intentos repetitivos de adivinación de códigos. |

---

### Fase 5: Auditoría Asíncrona y Resiliencia (`ConsultaAuditService`)
En cuanto se concreta una operación exitosa (consulta de historial, acceso, registro de reclamo o reconexión):
1. El servicio invoca `ConsultaAuditService->registrar(...)`.
2. `ClienteApiReportes` intenta enviar un `POST /api/consultas` al sistema administrativo `COSMOL-Reportes`.
3. **Si el servidor de Reportes responde con éxito (`200/201`):** Aprovecha la conexión abierta para tomar hasta 5 consultas rezagadas de la tabla `cola_reportes` y las despacha de inmediato.
4. **Si el servidor de Reportes no responde, da error o entra en timeout (>3s):** El evento se guarda localmente en PostgreSQL (`cola_reportes`) con estado `PENDIENTE` y el socio en WhatsApp **no sufre ninguna interrupción ni retraso**.

---

### Fase 6: Formato de Salida y Despacho Final
1. El Flow Handler utiliza las clases estáticas de `PlantillasWhatsApp/` (`PlantillaSocio`, `PlantillaFactura`, `PlantillaReclamo`, etc.) para estructurar el JSON oficial que Meta exige (mensajes de tipo `interactive`, listas con títulos y descripciones, botones de acción rápida).
2. `WebhookKernel` emite la respuesta final hacia n8n:
   ```json
   {
     "status": "success",
     "estado": "MAIN_MENU",
     "whatsapp_payload": { ... }
   }
   ```
3. n8n recibe este objeto y dispara el nodo final que envía el mensaje al teléfono del socio mediante la API oficial de WhatsApp.

---

## 5. Resumen de Responsabilidades por Directorio

| Carpeta | Rol Arquitectónico | ¿Qué contiene? |
|---|---|---|
| `public/api/` | **Controladores HTTP** | Punto de entrada expuesto a n8n (`webhook_whatsapp.php`). |
| `app/Core/` | **Infraestructura Base** | Inyector de dependencias (`AppContainer`), conexión a base de datos (`Database`), validadores y kernels. |
| `app/Config/` | **Configuración** | Constantes tomadas del archivo `.env`. |
| `app/Data/` | **Acceso a Datos** | Interfaces que aíslan al sistema de la base de datos física, repositorios PostgreSQL y clientes de API Informix. |
| `app/Integrations/` | **Servicios Externos** | Clientes cURL especializados para Informix SAI, COSMOL-Reportes y descarga de imágenes de Meta Graph API. |
| `app/Modules/` | **Lógica de Negocio** | Reglas puras del negocio: mora máxima, cálculo de deudas, validación de códigos de socio y buffer de auditoría. |
| `app/Presentacion/Flows/` | **Máquina de Estados** | Orquestación de qué responde el bot según en qué paso de la conversación se encuentre el socio. |
| `app/Presentacion/PlantillasWhatsApp/` | **Vistas de WhatsApp** | Diseños y estructuras JSON para botones, listas interactivas y textos con emojis según directrices de Meta. |
| `scripts/` | **Herramientas de Soporte** | Scripts PHP ejecutables por CLI para pruebas de integración y depuración. |
