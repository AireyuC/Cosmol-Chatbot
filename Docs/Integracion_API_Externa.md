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

El sistema cuenta con dos flujos principales integrados al flujo de n8n (`02_Flujo_Interactivo_COSMOL.json`). Ambos endpoints soportan la recepción de datos tanto en formato JSON como en `x-www-form-urlencoded` (Form Data), garantizando compatibilidad nativa con n8n.

### A. Validación de Socio (`socio.php`)

Utilizado cuando el usuario ingresa su código fijo por primera vez para validar su identidad.

- **URL Interna (n8n):** `http://backend:80/api/socio.php`
- **Método:** `POST`
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

### B. Consulta de Facturas/Deudas (`factura.php`)

Utilizado cuando el usuario selecciona la opción "Pagar Deuda" en el menú interactivo.

- **URL Interna (n8n):** `http://backend:80/api/factura.php`
- **Método:** `POST`
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

## 4. Estructura de Clases Creadas

Siguiendo principios de Código Limpio, la integración se dividió en las siguientes capas:

- **Integración (`app/Integrations/CosmolApi/ClienteApiCosmol.php`):** Contiene la lógica pura de conexión (cURL, timeouts, validación JSON).
- **Repositorio (`app/Data/Repositories/Api/RepositorioSocioApi.php`):** Implementa `SocioRepositoryInterface` pero obtiene los datos desde la API (a través de `ClienteApiCosmol`) en lugar de MySQL/Informix.
- **Servicio (`app/Modules/Socio/SocioService.php`):** Ejecuta las reglas de negocio. Aquí se calculan las sumas, se limpian los espacios sobrantes de los nombres entregados por la API y se redactan los mensajes pre-formateados para WhatsApp.
