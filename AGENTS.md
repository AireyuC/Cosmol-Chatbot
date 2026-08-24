# Documentación Arquitectónica - Chatbot COSMOL
Documento base de contexto. El agente DEBE leer este archivo al iniciar cada sesión y seguirlo durante todo el desarrollo del proyecto. Es la fuente de verdad del contexto, los objetivos y las reglas de comportamiento, el agente DEBE preguntar antes de ejecutar cualquier accion que no este contemplada en este documento y tambien antes de borrar o modificar cualquier archivo del proyecto.

## 1. RESUMEN Y OBJETIVOS
- **Objetivo Principal:** Desarrollar un chatbot automatizado por WhatsApp para atención a los asociados de COSMOL, permitiendo consultas, pagos y registro de reclamos con fricción cero (estilo CRE).
- **Alcance del Sistema:** Sistema puramente **Backend** (API e integraciones). No habrá aplicación o portal web Frontend administrativo por el momento. Toda la interacción del cliente será a través de las plantillas y flujos de WhatsApp.

## 2. ARQUITECTURA Y TECNOLOGÍAS
- **Canal de Comunicación:** Meta WhatsApp Cloud API.
  - *Fase de Pruebas:* Se utilizará el numero de prueba que meta nos da para trabajar en sandbox, con esta cuenta podremos hacer pruebas de las funcionalidades. 
  - *Producción:* Se buscará la verificación empresarial para activar *WhatsApp Flows* de forma oficial.
- **Orquestador (Middleware):** n8n (Self-Hosted en Node.js).
- **Backend API:** PHP Puro (Vanilla PHP v7.3). Implementa medidas de seguridad estrictas (Token de Autenticación Interno, CORS restrictivo, Rate Limiting por IP y Validación/Sanitización de inputs) para su comunicación con n8n.
- **Infraestructura de Despliegue (Docker):** Version (la mas estable para los demas stacks) Se oficializa el uso de **Docker** para contenerizar tanto n8n como la API PHP, garantizando un entorno escalable e idéntico para producción.
  - **Red Interna (Aislamiento):** Ambos servicios corren bajo una red Docker interna (`cosmol_network`). El backend PHP no expone puertos públicos en producción.
  - **n8n Local y Puertos:** En desarrollo, n8n se ejecutará de manera local dentro de un contenedor, proporcionándole/exponiendo un puerto específico (ej. `5678`) para acceder a su interfaz gráfica y recibir peticiones del exterior.
  - **Pruebas (ngrok):** Para recibir los Webhooks de Meta **únicamente durante la fase de desarrollo**, se utilizará **ngrok** de manera temporal. Esto creará un túnel público HTTPS que apuntará al puerto local de n8n. ngrok **no se usará en producción**.
  - **Producción:** En el despliegue final se sustituirá ngrok por el **dominio o URL propio de la empresa COSMOL** con su respectivo certificado SSL (a través de un proxy inverso como Nginx o Apache), recibiendo las peticiones de Meta directamente al contenedor de n8n.
  - **API PHP:** Se levantará en su propio contenedor, accesible únicamente por n8n a través de la red interna. Solo expone puerto al host (`8000`) cuando se usa el perfil `dev` en desarrollo local.
- **Integración con Sistema SAI (Informix):**
  - **Producción:** No se realizará una conexión directa ni migración a Informix. En su lugar, se consumirán **APIs REST** proporcionadas por el servidor Informix del sistema SAI. La API PHP actuará como intermediaria, haciendo peticiones HTTP a estos endpoints externos.
  - **Desarrollo Local:** PostgreSQL 16-alpine (imagen `postgres:16-alpine`, actualmente versión 16.15) corriendo en contenedor Docker (servicio `db` del `docker-compose.yml`) utilizando el estándar ANSI SQL como base de datos simulada para pruebas locales (mock del sistema SAI y preparación para futura migración a Informix). Todo el entorno de desarrollo corre exclusivamente en Docker.

## 3. FUNCIONALIDADES PRINCIPALES (FASE 1)
1. **Autenticación Fricción Cero:** El asociado se valida ingresando únicamente su Código de Asociado / Código Fijo.
2. **Consultas de Cuenta:** Visualización rápida de historial de facturas, montos pendientes y estados de cuenta.
3. **Pagos Integrados:** Redirección simple a la pasarela de Multipago (`https://multipago.com/service/cosmol_payment/first`).
4. **Registro de Reclamos (Agua turbia, fugas, etc.):**
   - Uso de formularios (Flows) u opciones interactivas para capturar detalles del problema en un solo paso.
   - **Manejo de Ubicación:** Se **ignora** la captura de ubicación por GPS desde WhatsApp por el momento. La ubicación para la atención del reclamo se extraerá directamente de los **datos almacenados en la base de datos** (sistema SAI).
5. **Reconexiones Automáticas:** Evaluación de la antigüedad de la deuda (rechazo si la mora supera los 2 meses) y orden directa al sistema.

## 4. ESTRUCTURA DE MICROSERVICIOS Y FLUJO
1. **Webhook:** n8n recibe los mensajes de Meta WhatsApp.
2. **Decisión Lógica:** n8n evalúa el texto o la plantilla recibida.
3. **Consulta al Backend:** n8n hace una petición HTTP GET/POST a la API PHP (`/api/webhook_whatsapp.php`).
4. **Consulta de Datos:** La API PHP hace una petición a las **APIs REST del sistema SAI** (Informix) en producción, o a **PostgreSQL local** (mock con ANSI SQL) en desarrollo, y devuelve la respuesta formateada.
5. **Respuesta al Cliente:** n8n formatea la respuesta de la base de datos y envía el mensaje de WhatsApp.

## 5. FASES ÁGILES DE DESARROLLO (SPRINTS)
- **Sprint 1 (Setup y Mocks):** Configuración de Meta App, levantamiento del entorno Docker (PHP + n8n + PostgreSQL) y creación de Endpoints PHP simulados (Mocks con ANSI SQL).
- **Sprint 2 (Auth y Menú):** Flujo de bienvenida en n8n, conexión para validar socio y redirección a pasarela de pagos.
- **Sprint 3 (Módulo Reclamos):** Implementación de flujos para quejas técnicas y extracción de datos de ubicación del socio exclusivamente desde la BD.
- **Sprint 4 (Integración APIs SAI):** Sustitución de las llamadas al PostgreSQL local por las **APIs REST del sistema SAI** (Informix) proporcionadas por el equipo de producción. Validación end-to-end del flujo completo contra datos reales.
