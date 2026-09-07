# Documentación: Integración con API Externa de Cosmol

Este documento describe cómo el Chatbot se conecta con el sistema central de Cosmol para obtener datos reales de los socios y sus deudas.

## 1. Arquitectura (Backend For Frontend)

Para mantener el orquestador (n8n) limpio y escalable, se ha decidido utilizar la API en PHP como un intermediario o "Proxy". 

- **El Orquestador (n8n):** Se encarga únicamente de recibir los mensajes de WhatsApp y enviar respuestas. No realiza cálculos ni formateo de textos complejos.
- **El Cerebro (PHP):** Recibe las solicitudes de n8n, se conecta a la API externa de Cosmol, procesa los datos (limpia espacios, suma deudas, formatea moneda) y devuelve a n8n un JSON puro y listo para mostrar en WhatsApp.

## 2. Configuración y Variables de Entorno

La URL base de la API externa de Cosmol no está escrita directamente en el código para permitir cambios rápidos si el servidor cambia.

1. Se configura en el archivo `.env`:
   ```env
   COSMOL_API_URL=http://api.cosmol.com.bo
   ```
2. El archivo `app/Config/database.php` carga esta variable.
3. La clase `ClienteApiCosmol` (`app/Integrations/CosmolApi/ClienteApiCosmol.php`) utiliza esta URL para hacer las peticiones `cURL`.

## 3. Flujos y Endpoints Implementados

El sistema cuenta con dos flujos principales integrados al flujo de n8n (`03_Flujo_Centralizado_MVC.json`). Todo el tráfico converge en un solo controlador centralizado (`webhook_whatsapp.php`), el cual a su vez invoca los servicios correspondientes de cada módulo.

### A. Validación de Socio

Utilizado cuando el usuario ingresa su código fijo por primera vez para validar su identidad.

- **Servicio Interno:** `SocioService`
- **Controlador Frontal (n8n):** `http://backend:80/api/webhook_whatsapp.php`
- **Parámetros:** `cod_socio` (Ej. "2587")
- **API Externa Consultada:** `/api-consultas/socios/{cod_socio}`
- **Respuesta Exitosa (Ejemplo):**
  ```json
  {
    "status": "success",
    "mensaje": "Socio encontrado exitosamente.",
    "datos_socio": {
      "nombre": "FIGUEREDO MONZON ANGEL NATALIO",
      "direccion": "P. DIAZ 231 /CBBA. B"
    }
  }
  ```
  *(El campo `datos_socio.nombre` es utilizado por el Nodo 5 de n8n para saludar al cliente).*

### B. Consulta de Facturas/Deudas

Utilizado cuando el usuario selecciona la opción "Pagar Deuda" en el menú interactivo.

- **Servicio Interno:** `SocioService`
- **Controlador Frontal (n8n):** `http://backend:80/api/webhook_whatsapp.php`
- **Parámetros:** `cod_socio`
- **API Externa Consultada:** `/api-consultas/socios/{cod_socio}/deudas`
- **Respuesta Exitosa (Ejemplo):**
  ```json
  {
    "status": "success",
    "codigo_socio": "2587",
    "mensaje_texto": "El Código Fijo (2587) tiene 2 facturas impagas, cuyo monto total es 140,93 Bs.\nEl detalle es el siguiente:\n\n1. 7-2026, 72,53 Bs. (Pendiente)\n2. 8-2026, 68,40 Bs. (Pendiente)",
    "facturas_pendientes": [...],
    "total_deuda": 140.93
  }
  ```
  *(El campo `mensaje_texto` es inyectado directamente por el Nodo 9 de n8n en el mensaje final de WhatsApp junto con el link de pago).*

### C. Consulta de Estado de Solicitudes y Reclamos

Utilizado cuando el usuario selecciona la opción "Estado de Solicitudes" en el Menú Principal.

- **Servicios Internos:** `ReclamoService`, `ReconexionService`
- **Controlador Frontal (n8n):** `http://backend:80/api/webhook_whatsapp.php`
- **Acción Recibida:** `MENU_ESTADO_TRAMITES` (o alias legacy `RECLAMO_ESTADO`)
- **APIs Externas Consultadas:**
  1. `GET /api-consultas/socios/{cod_socio}/reclamos`
  2. `GET /api-consultas/socios/{cod_socio}/reconexiones`
- **Respuesta de la API Informix (Ejemplo Reclamos):**
  ```json
  {
    "estado": "exito",
    "mensaje": "Historial de reclamos recuperado con éxito",
    "datos": [
      {
        "id_reclamo": "6",
        "descripcion": "Fuga de agua",
        "estado": "PENDIENTE",
        "fecha_registro": "2026-09-01 12:58:56.334214"
      }
    ]
  }
  ```
- **Respuesta de la API Informix (Ejemplo Reconexiones):**
  ```json
  {
    "estado": "exito",
    "mensaje": "Historial de reconexiones recuperado con éxito",
    "datos": [
      {
        "id_reconexion": "24",
        "id_tipo_reconexion": 1,
        "estado": "PENDIENTE",
        "fecha_registro": "2026-09-03 15:52:29.523723"
      }
    ]
  }
  ```
- **Procesamiento en PHP:** La clase `PlantillaSocio::estadoSolicitudes()` consolida ambos historiales en una tarjeta visual para WhatsApp con badges de estado (🟡 PENDIENTE, 🟢 CONCLUIDO, etc.) y la retorna dentro del Menú Principal.

## 4. Estructura de Clases Creadas

Siguiendo principios de Código Limpio y Arquitectura en Capas:

- **Integración (`app/Integrations/CosmolApi/ClienteApiCosmol.php`):** Contiene la lógica pura de conexión HTTP cURL con la API externa de COSMOL.
- **Repositorios (`app/Data/Repositories/Api/`):** Implementan las interfaces del dominio (`SocioRepositoryInterface`, `ReclamoRepositoryInterface`, `ReconexionRepositoryInterface`) comunicándose con `ClienteApiCosmol`.
- **Servicios (`app/Modules/`):** Ejecutan las reglas de negocio (`SocioService`, `ReclamoService`, `ReconexionService`).
- **Presentación y Flujos (`app/Presentacion/`):** Handlers de sesión y generadores de payloads WhatsApp compatibles con Meta Cloud API.
