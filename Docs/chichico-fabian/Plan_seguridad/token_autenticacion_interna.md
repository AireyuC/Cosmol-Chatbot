# Autenticación Interna — Token entre n8n y la API PHP

> **Fase:** 1 del Plan de Seguridad
> **Prioridad:** 🔴 Crítica
> **Estado:** ✅ Implementado
> **Archivo clave:** [`app/Core/Auth.php`](file:///c:/Proyectos/Cosmol-Chatbot/app/Core/Auth.php)

---

## ¿Qué es esto y para qué sirve?

La API PHP del Chatbot COSMOL expone endpoints como `/api/socio.php` y `/api/reclamos.php`
que devuelven datos reales de asociados. Estos endpoints son accesibles por HTTP desde cualquier
lugar, lo que significa que **cualquier persona con la URL podría consultarlos directamente**.

El **token de autenticación interna** es la primera línea de defensa: un secreto que solo conocen
n8n (el orquestador) y la API PHP. Si una petición llega sin ese secreto, la API la rechaza
inmediatamente sin procesar nada.

---

## El problema original (sin token)

```
┌─────────────────────────────────────────────────────────────────┐
│                       INTERNET                                  │
│                                                                 │
│  Usuario legítimo   →  WhatsApp  →  n8n  →  API PHP  →  DB      │
│                                                                 │
│  Atacante malicioso → GET https://api.cosmol.com/api/socio.php  │
│                         ?cod_socio=12345                        │
│                       ← 200 OK + datos del socio                │
└─────────────────────────────────────────────────────────────────┘
```

Sin ninguna protección, un atacante podría:
- Escribir un script que enumere todos los códigos del `1` al `99999` y obtenga datos de cada socio.
- Acceder a información de facturas, deudas y reclamos sin ser un socio ni tener credenciales.
- Esto se conoce como **scraping** y es un ataque silencioso y difícil de detectar sin Rate Limiting.

---

## La solución: Secreto Compartido en Header HTTP

### ¿Cómo funciona?

El sistema funciona como una **contraseña privada entre dos piezas de software**:

```
┌──────┐   HTTP Request                          ┌──────────┐
│      │   + Header: X-Internal-Token: abc123... │          │
│  n8n │ ──────────────────────────────────────► │  API PHP │
│      │                                         │          │
└──────┘   ← 200 OK + datos                      └──────────┘

┌───────────┐   HTTP Request (sin header o header incorrecto)
│ Atacante  │ ─────────────────────────────────────────────► API PHP
└───────────┘                                               ← 401 Unauthorized 🔒
```

### Flujo detallado

1. **El token se genera una sola vez** con `openssl rand -hex 32` y se guarda en el `.env`.
2. **n8n envía el token** en el header `X-Internal-Token` en cada petición HTTP a la API.
3. **La API PHP recibe la petición** y antes de hacer cualquier cosa, extrae ese header.
4. **Compara el header** con el valor del `.env` usando `hash_equals()`.
5. **Si coinciden** → procesa la petición normalmente.
6. **Si no coinciden (o falta el header)** → responde `401 Unauthorized` y se detiene.

---

## Implementación técnica

### Dónde vive el token

El token se define en el `.env` y se carga como constante PHP en el arranque del sistema:

**`.env`**
```env
# Token secreto compartido entre n8n y la API PHP
# Generado con: openssl rand -hex 32
API_INTERNAL_TOKEN=c4b9d031e13f41249e0c90494fbdb96a2982d6b3fcb5962b9a715a6b0c2a71d0
```

**`app/Config/database.php`** — carga la constante PHP al iniciar:
```php
// Seguridad — Token interno compartido entre n8n y la API PHP (Fase 1)
define('API_INTERNAL_TOKEN', getenv('API_INTERNAL_TOKEN') ?: '');
```

---

### La clase Auth.php

[`app/Core/Auth.php`](file:///c:/Proyectos/Cosmol-Chatbot/app/Core/Auth.php) es la clase que
realiza la validación. Tiene un único método estático público:

```php
<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function validateInternalToken(): void
    {
        // 1. Leer el header enviado por n8n
        //    Apache convierte "X-Internal-Token" → "HTTP_X_INTERNAL_TOKEN" automáticamente
        $token    = $_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? '';

        // 2. Leer el valor esperado desde la constante del .env
        $expected = defined('API_INTERNAL_TOKEN') ? API_INTERNAL_TOKEN : '';

        // 3. Comparar de forma segura (ver sección sobre timing attacks)
        if (empty($expected) || !hash_equals($expected, $token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.', 'data' => null]);
            exit; // Detener todo. No se ejecuta nada más.
        }
    }
}
```

---

### Dónde se llama (bootstrap.php)

La validación se hace **una sola vez** en [`app/bootstrap.php`](file:///c:/Proyectos/Cosmol-Chatbot/app/bootstrap.php),
que es el punto de entrada compartido por **todos** los endpoints:

```php
// 6. [FASE 1 — Seguridad] Validar el token interno antes de procesar cualquier petición.
// Si el header X-Internal-Token no coincide con API_INTERNAL_TOKEN → responde 401 y muere.
use App\Core\Auth;
Auth::validateInternalToken();
```

Esto significa que **no hay que tocar cada endpoint individualmente**. Cualquier endpoint nuevo
que se cree en el futuro quedará protegido automáticamente por herencia del bootstrap.

---

### Configuración en n8n (sin escribir código)

En n8n, en cada nodo **HTTP Request** que apunte a la API PHP, se debe agregar el header:

| Campo | Valor |
|-------|-------|
| Header Name | `X-Internal-Token` |
| Header Value | El valor exacto de `API_INTERNAL_TOKEN` del `.env` |

```
Nodo HTTP Request en n8n:
┌─────────────────────────────────────────┐
│ URL: http://cosmol_php/api/socio.php    │
│                                         │
│ Headers:                                │
│   X-Internal-Token: c4b9d031e13f...     │
│                                         │
│ Method: GET                             │
└─────────────────────────────────────────┘
```

> [!IMPORTANT]
> El valor del header en n8n debe ser **idéntico al del `.env`**, incluyendo mayúsculas y minúsculas.
> Si son distintos, n8n recibirá `401` en todas sus peticiones.

---

## ¿Por qué `hash_equals()` y no `===`?

Esta es la parte de seguridad más sutil. Al comparar el token recibido con el esperado,
**no se usa el operador `===`** sino la función `hash_equals()`. ¿Por qué?

### El problema: Timing Attacks (ataques por medición de tiempo)

Un comparador normal (`===`) en PHP detiene la comparación en el primer carácter distinto:

```
Token esperado:  a b c d e f g h ...
Token atacante:  a b X ← STOP (tomó 3ms)

Token atacante:  X ← STOP (tomó 1ms)
```

Un atacante sofisticado puede enviar miles de variaciones del token y medir el tiempo de respuesta.
Si la respuesta tardó más, significa que más caracteres iniciales son correctos. Dígito a dígito,
puede adivinar el token sin nunca acertar directamente — solo midiendo tiempos de respuesta.

### La solución: Comparación de tiempo constante

`hash_equals()` **siempre tarda exactamente el mismo tiempo**, sin importar en qué carácter divergen
los strings:

```
Token esperado:  a b c d e f g h ...
Token atacante:  X ← sigue comparando hasta el final igualmente → STOP (siempre ~5ms)
```

Esto hace que la medición de tiempos no aporte información útil al atacante.

> [!NOTE]
> Esta protección es especialmente relevante en servidores con respuesta rápida (< 5ms).
> En latencias de red normales el efecto es menor, pero es una buena práctica defensiva siempre.

---

## ¿Se podría hacer de otra forma?

Sí. Existen otras capas donde se puede implementar esta protección. Aquí la comparación:

| Capa | Mecanismo | ¿Aplica? | Pros | Contras |
|------|-----------|----------|------|---------|
| **Backend PHP** ← actual | Header `X-Internal-Token` | ✅ Implementado | Portable, funciona en cualquier infra | Necesita `.env` bien protegido |
| **Red Docker** | API PHP sin puerto público expuesto | ✅ Complementario | Muy seguro a nivel OS | No protege si otro contenedor es comprometido |
| **Servidor web (Nginx/Apache)** | `allow/deny` por IP | ⚠️ Parcial | Sin tocar PHP | IPs de Docker son dinámicas; frágil |
| **JWT / OAuth2** | Token firmado con expiración | ❌ Overkill | Estándar industry | n8n debería renovar tokens; demasiada complejidad |
| **API Key en URL** | `?api_key=abc123` | ❌ No recomendado | Muy simple | El token queda visible en logs del servidor y en historial |

**La implementación actual (backend PHP + header HTTP) es la correcta** para la arquitectura de
COSMOL (n8n ↔ PHP en Docker, comunicación interna). Idealmente se combina con el aislamiento de
red Docker para tener dos capas independientes de protección.

---

## Cómo generar un nuevo token

Si se necesita rotar el token (recomendado cada cierto tiempo o ante sospecha de compromiso):

```bash
# En Linux / WSL / Git Bash
openssl rand -hex 32

# Ejemplo de salida:
# 7f3a9b2c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a
```

1. Reemplazar el valor en `.env` → `API_INTERNAL_TOKEN=nuevo_valor`
2. Actualizar el header en todos los nodos HTTP Request de n8n con el nuevo valor.
3. Reiniciar el contenedor PHP para que cargue el nuevo `.env`.

> [!CAUTION]
> Si se rota el token sin actualizar n8n primero, **todos los flujos de WhatsApp dejarán de funcionar**
> hasta que n8n tenga el nuevo valor. Hacer la rotación en una ventana de mantenimiento.

---

## Verificación rápida

Desde cualquier cliente HTTP (curl, Postman, Insomnia):

```bash
# ❌ Sin token → debe devolver 401
curl http://localhost:8080/api/socio.php?cod_socio=12345

# Respuesta esperada:
# HTTP/1.1 401 Unauthorized
# {"success":false,"message":"No autorizado.","data":null}

# ✅ Con token correcto → debe devolver 200 (o 404 si el socio no existe)
curl -H "X-Internal-Token: c4b9d031e13f41249e0c90494fbdb96a2982d6b3fcb5962b9a715a6b0c2a71d0" \
     http://localhost:8080/api/socio.php?cod_socio=12345

# Respuesta esperada:
# HTTP/1.1 200 OK
# {"success":true,"message":"Socio encontrado.","data":{...}}
```
