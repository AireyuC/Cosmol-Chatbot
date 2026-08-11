# Documentación de Flujos en n8n - Chatbot COSMOL

Este documento detalla la arquitectura de los nodos dentro de n8n para el Chatbot de WhatsApp. Aquí centralizamos cómo n8n recibe, procesa, formatea y responde a los usuarios, manteniendo el backend de PHP puro.

## 1. Arquitectura Base del Flujo

El flujo se divide en 3 capas principales dentro del lienzo de n8n:

### Capa 1: Ingesta y Filtro
- **Webhook (POST):** Recibe absolutamente todo el tráfico entrante de Meta.
- **IF (Filtro de Ruido):** Descarta actualizaciones de estado (Leído, Entregado) y deja pasar solo los mensajes de texto o interacciones de botones.

### Capa 2: Enrutamiento y Lógica de Negocio (Switch)
- **Switch Node (El Cerebro):** Evalúa el mensaje entrante.
  - *Ruta 1 (Auth):* Si el usuario escribe un número (Código de Socio), n8n hace un HTTP Request a `/api/socio.php` para validarlo.
  - *Ruta 2 (Menú):* Si el usuario presiona un botón interactivo (Ej: Payload `MENU_DEUDA`), n8n dirige el flujo hacia la consulta de facturas.
  - *Ruta 3 (Reclamos):* Si el usuario selecciona el botón de reclamos, n8n dirige el flujo a capturar los datos del problema.

### Capa 3: Capa de Presentación (Formatters)
Para enviar botones, n8n no usa el campo de texto simple, sino que construye el JSON estricto de Meta mediante Nodos **Set**:

#### Estructura JSON de un Menú de Botones (Ejemplo)
En el nodo "Set Menú Principal", inyectamos el siguiente objeto JSON:
```json
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "{{Telefono_Destino}}",
  "type": "interactive",
  "interactive": {
    "type": "button",
    "body": {
      "text": "¡Hola! Bienvenido a COSMOL. ¿En qué podemos ayudarte hoy?"
    },
    "action": {
      "buttons": [
        {
          "type": "reply",
          "reply": {
            "id": "BTN_DEUDA",
            "title": "Ver Deuda"
          }
        },
        {
          "type": "reply",
          "reply": {
            "id": "BTN_PAGAR",
            "title": "Pagar Servicio"
          }
        },
        {
          "type": "reply",
          "reply": {
            "id": "BTN_RECLAMO",
            "title": "Reclamos"
          }
        }
      ]
    }
  }
}
```
*Nota:* Este JSON formateado se pasa a un Nodo HTTP Request final apuntando a Meta `https://graph.facebook.com/v20.0/.../messages`.

## 2. Gestión del Estado (Memoria del Bot)
Dado que n8n es "Stateless" por defecto (no recuerda lo que pasó en el mensaje anterior), utilizaremos la naturaleza de los **Botones Interactivos** de Meta. 

Cada vez que enviamos un botón, Meta nos devuelve el `id` oculto de ese botón (ej. `BTN_RECLAMO`) cuando el usuario lo presiona. El nodo Switch de n8n leerá ese ID para saber exactamente qué acción pidió el usuario, simulando una sesión sin necesidad de usar bases de datos complejas para recordar el contexto.
