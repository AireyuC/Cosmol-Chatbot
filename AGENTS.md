# AGENTS.md — Cosmol Chatbot

> **Documento base de contexto.** El agente DEBE leer este archivo al iniciar cada sesión y seguirlo durante todo el desarrollo del proyecto. Es la fuente de verdad del contexto, los objetivos y las reglas de comportamiento.

---

## 1. Propósito del proyecto

Chatbot de WhatsApp para una empresa que administra y provee los servicios de **agua potable y alcantarillado sanitario**.

**Objetivo central:** atención al cliente y resolución de consultas de forma automatizada a través de WhatsApp.

---

## 2. Objetivos inmutables (para no desviarse)

El agente debe trabajar únicamente en función de estos objetivos:

1. Responder los mensajes de los clientes que llegan vía WhatsApp.
2. Ofrecer un **menú de opciones** mediante plantillas interactivas creadas en Meta Developers.
3. Responder consultas del cliente:
   - **Cuantas facturas debe pagar** (pendientes).
   - **El precio de cada factura**.
   - **Ubicación de la empresa**.
4. **Regla inviolable:** toda consulta que involucre datos de un cliente exige que el chatbot **pida el código de socio** vinculado a la empresa y **verifique su existencia en la base de datos** antes de responder. **Nunca** se responden datos de un cliente sin verificar previamente su código de socio.

---

## 3. Stack Tecnológico (fijo)

- **Backend:** PHP puro.
- **Frontend / Dashboard:** HTML, CSS, Bootstrap.
- **Exposición de webhooks en desarrollo:** Ngrok.
- **Orquestación de flujos:** n8n.
- **Integración WhatsApp:** cuenta **Meta for Developers** conectada a la **API de WhatsApp** (webhooks, plantillas interactivas y mensajes).

### 3.1 Conexión de datos

- **Fase 1 — Desarrollo / Pruebas:** los datos de clientes y facturas se consultan en una **base de datos local de XAMPP (MySQL)** con datos de prueba.
- **Fase 2 — Producción (posterior a las pruebas):** la conexión migrará a la **base de datos oficial de la empresa en Informix 4GL**.
- **Diseño modular obligatorio:** la capa de datos debe estar **abstraída** (patrón repositorio / interfaces) de modo que se pueda cambiar de XAMPP/MySQL a Informix 4GL **sin alterar la lógica de negocio** del chatbot.
- **PENDIENTE:** definir los parámetros de conexión de la BD oficial (host, puerto, credenciales y driver ODBC de Informix) cuando se aproxime la migración a producción.

### 3.2 Versiones

- Se utilizarán las **versiones más recientes y estables de PHP y MySQL que incluye la instalación de XAMPP** del equipo de desarrollo (la última versión estable disponible en XAMPP actual).

---

## 4. Arquitectura / Estructura Modular

El proyecto debe ser **escalable** y seguir buenas prácticas. Patrón: **MVC organizado por módulos**.

```
/app
  /public
  /application
    /Modules
      /WhatsApp      -> controladores, servicios y webhook de WhatsApp
      /Clientes      -> servicios de consulta y verificación de socio
      /Facturacion   -> facturas pendientes y precios
      /n8n           -> integración con flujos de n8n
      /Data          -> capa de datos multi-BD (adaptadores MySQL / Informix)
      /Core          -> router, bootstrap, autoload (PSR-4), helpers
      /Config
```

**Buenas prácticas:**
- Autoloading con **PSR-4**.
- **Router propio** (PHP puro sin framework).
- Capa de **servicios** por módulo.
- **Configuración centralizada** y **secrets fuera del código** (nunca exponer tokens, claves o credenciales en el repositorio).

> Nota: el esqueleto de carpetas NO debe crearse hasta que el responsable lo indique explícitamente.

---

## 5. Flujo funcional del bot

- **Flujo de mensajes:** Webhook de WhatsApp (Meta) → n8n → PHP (validación y verificación) → respuesta al cliente.
- **Menú:** ofrece opciones mediante plantillas interactivas de Meta.
- **Consulta de facturas (paso a paso):**
  1. El cliente consulta (ej. cuántas facturas debe pagar, precios).
  2. El bot solicita el **código de socio**.
  3. PHP verifica el código en la base de datos (Fase 1: MySQL/XAMPP).
  4. Si el código es válido, responde con la información vinculada a esa cuenta.
  5. Si el código no existe, informa que no se encontró y no revela datos.
- **Ubicación de la empresa:** consulta pública, no requiere código de socio.

---

## 6. Comportamiento y reglas del agente

1. **Leer SIEMPRE** este `AGENTS.md` al iniciar cada sesión antes de hacer cualquier tarea.
2. **Mantener la arquitectura modular**; no mezclar la lógica de un módulo en otro.
3. **No desviarse del objetivo:** centrarse en consultas de facturas, verificación de socio y menú de WhatsApp.
4. **Seguridad:** manejar de forma segura los secrets; jamás exponer tokens de Meta, credenciales ni claves en código o commits.
5. **Consistencia:** aplicar buenas prácticas, seguir el patrón MVC por módulos y mantener el código escalable.
6. **Actualizar este archivo** cuando cambie el contexto o el estado del proyecto, para que el agente siempre tenga la información al día.
7. **No crear la estructura de carpetas** ni archivos fuera de lo solicitado sin indicación expresa del responsable.

---

## 7. PENDIENTES / por definir

- Parámetros de conexión de la base de datos oficial **Informix 4GL** (host, puerto, credenciales, driver ODBC).
- Especificación de la **API/sistema externo** de clientes y facturas (si aplica).
- Autenticación y verificación de **webhooks de Meta**.
- **Lista final de plantillas interactivas** creadas en Meta Developers.
- Datos de prueba para la base local de **XAMPP (MySQL)**.
- Definición de la **ubicación de la empresa** para la consulta pública.