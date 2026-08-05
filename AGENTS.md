# AGENTS.md — Cosmol Chatbot

> **Documento base de contexto.** El agente DEBE leer este archivo al iniciar cada sesión y seguirlo durante todo el desarrollo del proyecto. Es la fuente de verdad del contexto, los objetivos y las reglas de comportamiento.

---

## 1. Propósito del proyecto
Chatbot de WhatsApp para una empresa que administra y provee los servicios de agua potable y alcantarillado sanitario.

**Objetivo central:** atención al cliente y resolución de consultas de forma automatizada a través de WhatsApp.

---

## 2. Objetivos inmutables (para no desviarse)
El agente debe trabajar únicamente en función de estos objetivos:

Responder consultas del cliente mediante consumo de API:
- Cuantas facturas debe pagar (pendientes).
- El precio de cada factura.
- Ubicación de la empresa.

**Regla inviolable:** toda consulta que involucre datos de un cliente exige que el chatbot (vía la API) pida y verifique el código de socio en la base de datos antes de devolver cualquier información. Nunca se responden datos de un cliente sin verificar previamente su código de socio.

---

## 3. Stack Tecnológico y División del Equipo
El equipo consta de 3 desarrolladores. El trabajo está dividido, por lo que este agente se enfocará única y exclusivamente en el Backend. Las configuraciones de Meta y webhooks son gestionadas por otro miembro del equipo a través de n8n.

- **Backend / API:** PHP vanilla (Estrictamente versión 7.3).
- **Entorno de Desarrollo:** XAMPP versión 3.2.2 (compatible con PHP 7.3).
- **Testeo de API:** Postman.
- **Orquestación de flujos (externo al agente):** n8n.
- **Frontend / Dashboard:** Descartado (no se desarrollará interfaz gráfica).

### 3.1 Conexión de datos
- **Fase 1 — Desarrollo / Pruebas:** los datos de clientes y facturas se consultan en una base de datos local de XAMPP (MySQL). Se crearán modelos de prueba para las entidades principales (Socio y Facturas vinculadas).
- **Fase 2 — Producción:** la conexión migrará a la base de datos oficial de la empresa en Informix 4GL.
- **Diseño modular obligatorio:** la capa de datos debe estar abstraída (patrón repositorio / interfaces) de modo que se pueda cambiar de MySQL a Informix 4GL sin alterar la lógica de negocio de los endpoints.

---

## 4. Arquitectura / Estructura Modular (API REST)
El proyecto será una API REST escalable en PHP puro. Patrón: MVC organizado por módulos (enfocado en Controladores de API y Servicios).

```plaintext
/app
  /application
    /Modules
      /Clientes      -> servicios de consulta, verificación de socio y endpoints de API
      /Facturacion   -> consulta de facturas pendientes, precios y endpoints de API
      /Data          -> capa de datos multi-BD (adaptadores MySQL / Informix)
      /Core          -> router API, autoload (PSR-4), helpers, respuestas JSON
      /Config        -> conexión a BD y variables de entorno
```

Buenas prácticas:
- Autoloading con PSR-4.
- Router propio (PHP puro sin framework) diseñado para recibir y responder únicamente JSON.
- Capa de servicios por módulo.
- Configuración centralizada y secrets fuera del código.

> Nota: el esqueleto de carpetas NO debe crearse hasta que el responsable lo indique explícitamente.

---

## 5. Flujo funcional de la API (Orquestación con n8n)
- **Rol de n8n:** n8n recibe el Webhook de WhatsApp, maneja el árbol de conversación con el usuario y hace peticiones HTTP a nuestra API en PHP.
- **Rol de PHP (Nuestro enfoque):** Procesar las peticiones HTTP de n8n, interactuar con la base de datos y devolver respuestas en formato JSON.

**Flujo de consulta de facturas:**
1. n8n hace una petición POST/GET a nuestro endpoint enviando el `codigo_socio`.
2. PHP verifica el código en la base de datos (Fase 1: MySQL/XAMPP).
3. Si es válido, PHP devuelve un JSON con las facturas pendientes y precios.
4. Si no existe, PHP devuelve un JSON con el error correspondiente (ej. 404 Not Found), y n8n se encarga de informar al cliente.

**Ubicación de la empresa:** endpoint público que devuelve la información sin requerir validación.

---

## 6. Comportamiento y reglas del agente
1. **Leer SIEMPRE** este AGENTS.md al iniciar cada sesión antes de hacer cualquier tarea.
2. **Mantener la arquitectura modular**; no mezclar la lógica de un módulo en otro.
3. **No desviarse del objetivo:** centrarse estrictamente en el desarrollo Backend (API REST en PHP 7.3) para responder a n8n. No generar código de webhooks directos para Meta.
4. **Seguridad:** jamás exponer credenciales ni claves en código o commits.
5. **Consistencia:** aplicar buenas prácticas, estructurar respuestas estandarizadas en JSON y mantener el código escalable.
6. **Actualizar este archivo** cuando cambie el contexto o el estado del proyecto.
7. **No crear la estructura de carpetas** ni archivos fuera de lo solicitado sin indicación expresa del responsable.

---

## 7. PENDIENTES / por definir
- Definir la estructura exacta de los modelos de prueba (Socio y Factura) para MySQL.
- Parámetros de conexión de la base de datos oficial Informix 4GL (host, puerto, credenciales, driver ODBC).
- Definición de la ubicación de la empresa y otras variables de entorno (plantillas) para los endpoints.
- Estructura y formato de las respuestas JSON que n8n espera recibir de nuestra API.