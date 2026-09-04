# Guía de Integración: Chatbot WhatsApp hacia COSMOL-Reportes

Este documento es la **especificación técnica oficial** para la integración entre el backend del chatbot (**`Cosmol-Chatbot`**) y el sistema de administración web (**`COSMOL-Reportes`**).

El objetivo es centralizar en tiempo real el registro, auditoría y métricas de todas las consultas generadas por los socios a través de WhatsApp.

---

## 1. Arquitectura y Flujo de Comunicación

```text
┌─────────────────────────┐
│ Socio en WhatsApp       │
└────────────┬────────────┘
             │ (Mensajes interactivos, texto, ubicación, foto)
             ▼
┌─────────────────────────┐
│ Meta Cloud API / n8n    │
└────────────┬────────────┘
             │ (POST webhook)
             ▼
┌─────────────────────────────────────────────────────────┐
│ Cosmol-Chatbot (PHP 7.3)                                │
│                                                         │
│  1. Procesa negocio (Informix SAI)                      │
│  2. Responde al socio en WhatsApp                       │
│  3. Dispara evento de auditoría hacia COSMOL-Reportes   │
└────────────┬───────────────────────────────┬────────────┘
             │ (POST /api/consultas)         │ (Si API reportes cae)
             ▼                               ▼
┌─────────────────────────┐      ┌─────────────────────────┐
│ COSMOL-Reportes         │      │ Buffer Local Postgres   │
│ (Sistema Web Admin)     │      │ (tabla: cola_reportes)  │
└────────────┬────────────┘      └───────────┬─────────────┘
             │                               │ (Reintento auto)
             ▼                               └─────────► Sincroniza
┌─────────────────────────┐                              al volver
│ BD: cosmol_reportes     │                              online
│ (tabla: consulta)       │
└─────────────────────────┘
```

---

## 2. Mecanismo de Resiliencia: Buffer Local (Tolerancia a Caídas)

Para garantizar que el servicio de WhatsApp **nunca se interrumpa** y que **ninguna métrica se pierda**:

1. **Timeout Ultracorto:** El cliente HTTP en el chatbot (`ClienteApiReportes`) tiene un timeout de conexión de **1 segundo** y total de **2 segundos**.
2. **Buffer Local en PostgreSQL (`cola_reportes`):**
   - Si `COSMOL-Reportes` está apagado, en mantenimiento o responde con error HTTP (500, 502, timeout), el evento se inserta automáticamente en la tabla local `cola_reportes` del chatbot con estado `'PENDIENTE'`.
   - La tabla se crea de forma transparente y defensiva en la BD del chatbot (`chatbot_cosmol`).
3. **Vaciado Automático y Reintentos:**
   - Cada vez que el chatbot realiza un envío exitoso, aprovecha para vaciar hasta 5 registros pendientes del buffer.
   - De esta manera, cuando el servidor de reportes vuelve a estar en línea, los eventos acumulados se sincronizan progresivamente sin saturar la red.

---

## 3. Catálogo de Consultas de WhatsApp

En la base de datos de `COSMOL-Reportes`, la tabla `tipo_consulta` debe contar con las siguientes semillas:

| id_tipo | Nombre del Tipo | Descripción | Evento en WhatsApp |
|:---:|---|---|---|
| **1** | Autenticación / Acceso | Socio ingresa su código fijo y valida su identidad | Al validar código en `AuthFlowHandler` |
| **2** | Consulta de Deuda | Consulta de facturas pendientes y monto total | Al pulsar "Consultar Deuda" |
| **3** | Historial de Facturas | Consulta de últimas facturas pagadas | Al pulsar "Historial" |
| **4** | Registro de Reclamo | Finalización de ticket de reclamo técnico o comercial | Al enviar GPS + Foto + Glosa |
| **5** | Solicitud de Reconexión | Finalización de ticket de trámite de reconexión | Al enviar GPS + Tipo + Foto + Glosa |
| **6** | Información de Oficinas | Consulta de dirección central y horarios | Al pulsar "Oficinas y Horarios" |
| **7** | Derivación a Agente | Solicitud de contacto con un operador humano | Al pulsar "Hablar con un Agente" |

### Script SQL para `COSMOL-Reportes` (Ejecutar en la BD `cosmol_reportes`):

```sql
-- Inserción de semillas en tipo_consulta (ID explícito para homologar con el Chatbot)
INSERT INTO tipo_consulta (id_tipo, nombre, descripcion) VALUES
(1, 'Autenticación / Acceso', 'Socio valida su código fijo en el chatbot'),
(2, 'Consulta de Deuda', 'Consulta de facturas pendientes y saldo'),
(3, 'Historial de Facturas', 'Consulta de facturas pagadas anteriormente'),
(4, 'Registro de Reclamo', 'Ticket de reclamo por agua o alcantarillado registrado'),
(5, 'Solicitud de Reconexión', 'Ticket de trámite de reconexión registrado'),
(6, 'Información de Oficinas', 'Consulta de ubicación de oficina central y horarios de atención'),
(7, 'Derivación a Agente', 'Solicitud de atención con un operador humano')
ON CONFLICT (id_tipo) DO UPDATE 
SET nombre = EXCLUDED.nombre, descripcion = EXCLUDED.descripcion;

-- Ajustar la secuencia del serial para futuros registros
SELECT setval('tipo_consulta_id_tipo_seq', (SELECT MAX(id_tipo) FROM tipo_consulta));
```

---

## 4. Especificación del Endpoint Receptor en `COSMOL-Reportes`

Para recibir los datos desde el chatbot, el proyecto `COSMOL-Reportes` debe exponer la siguiente API:

### Especificación HTTP
* **URL:** `POST /api/consultas`
* **Headers requeridos:**
  * `Content-Type: application/json`
  * `X-Reportes-Token: <valor_de_REPORTES_API_TOKEN>`
* **Cuerpo JSON entrante (Request Body):**
  ```json
  {
    "codigo_socio": 267657,
    "nombres": "Juan Pérez",
    "id_tipo": 3,
    "tipo_consulta": "Historial de Facturas",
    "fecha_consulta": "2026-09-04",
    "hora_consulta": "17:05:22"
  }
  ```

### Respuesta Esperada por el Chatbot
* **HTTP 200 OK** o **HTTP 201 Created**:
  ```json
  {
    "status": "success",
    "message": "Consulta registrada exitosamente",
    "id_consulta": 154
  }
  ```
* **HTTP 401 Unauthorized** si el token no coincide.
* **HTTP 400 Bad Request** si faltan campos obligatorios.

---

## 5. Implementación Paso a Paso en el Proyecto `COSMOL-Reportes`

Cuando trabajes en el repositorio `COSMOL-Reportes`, sigue estos pasos para completar la integración:

### Paso 5.1: Crear el Endpoint Receptor
En `app/Controllers/ConsultaApiController.php`:
```php
<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

class ConsultaApiController extends Controller
{
    public function registrar()
    {
        // 1. Validar Token de Seguridad
        $tokenEsperado = getenv('REPORTES_API_TOKEN') ?: 'cosmol_secret_token_123';
        $tokenRecibido = $_SERVER['HTTP_X_REPORTES_TOKEN'] ?? '';

        if ($tokenRecibido !== $tokenEsperado) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Token no autorizado']);
            exit;
        }

        // 2. Leer JSON del Chatbot
        $input = json_decode(file_get_contents('php://input'), true);

        $codigoSocio = isset($input['codigo_socio']) ? (int)$input['codigo_socio'] : 0;
        $nombres     = isset($input['nombres']) ? trim($input['nombres']) : 'Socio';
        $idTipo      = isset($input['id_tipo']) ? (int)$input['id_tipo'] : null;
        $fecha       = isset($input['fecha_consulta']) ? $input['fecha_consulta'] : date('Y-m-d');
        $hora        = isset($input['hora_consulta']) ? $input['hora_consulta'] : date('H:i:s');

        if ($codigoSocio <= 0 || !$idTipo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Parámetros obligatorios faltantes']);
            exit;
        }

        // 3. Insertar en la tabla consulta
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO consulta (codigo_socio, nombres, fecha_consulta, hora_consulta, id_tipo)
                VALUES (:codigo_socio, :nombres, :fecha_consulta, :hora_consulta, :id_tipo)
            ");

            $stmt->execute([
                ':codigo_socio'   => $codigoSocio,
                ':nombres'        => $nombres,
                ':fecha_consulta' => $fecha,
                ':hora_consulta'  => $hora,
                ':id_tipo'        => $idTipo
            ]);

            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Consulta registrada'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error interno al guardar']);
        }
        exit;
    }
}
```

### Paso 5.2: Registrar la Ruta en `COSMOL-Reportes/app/Config/routes.php`
En el bloque `'POST'`:
```php
'/api/consultas' => ['ConsultaApiController', 'registrar', []],
```

---

## 6. Variables de Entorno (`.env`)

### En `Cosmol-Chatbot`:
```env
# URL hacia la API de COSMOL-Reportes
# Si ambos corren en el mismo host Docker: http://cosmol_app:80/api
# Si corren en puertos de host local: http://localhost:8080/api
# En producción con dominio: https://reportes.cosmol.com.bo/api
REPORTES_API_URL=http://localhost:8080/api

# Token de autenticación interno compartido
REPORTES_API_TOKEN=cosmol_secret_token_reportes_2026
```

### En `COSMOL-Reportes`:
```env
# Mismo token definido en el chatbot
REPORTES_API_TOKEN=cosmol_secret_token_reportes_2026
```

---

## 7. Verificación de la Integración

1. **Prueba en Chatbot sin servidor de reportes encendido:**
   - Realizar una consulta en WhatsApp (ej. deudas).
   - Verificar que el bot responde de inmediato al usuario sin congelarse.
   - En la base de datos local del chatbot (`chatbot_cosmol`), verificar la tabla `cola_reportes`:
     ```sql
     SELECT * FROM cola_reportes WHERE estado = 'PENDIENTE';
     ```
     El registro estará guardado con el evento `CONSULTA_DEUDA`.
2. **Prueba con servidor de reportes encendido:**
   - Levantar `COSMOL-Reportes`.
   - Realizar otra interacción en WhatsApp.
   - El chatbot enviará la nueva consulta y vaciará automáticamente los pendientes de `cola_reportes`.
   - En `COSMOL-Reportes`, comprobar la tabla `consulta`:
     ```sql
     SELECT c.*, t.nombre AS tipo FROM consulta c JOIN tipo_consulta t ON c.id_tipo = t.id_tipo ORDER BY c.id_consulta DESC;
     ```
