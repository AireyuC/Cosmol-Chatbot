# Restricción de Acceso con Nginx — Solo n8n puede llegar a PHP

> **Tipo:** Plan de Implementación (Seguridad de Producción)
> **Entorno:** Producción únicamente
> **Capa de defensa:** Servidor web (complementa el aislamiento Docker y el token PHP)
> **Estado:** ⏳ Pendiente de implementar (Sprint 4 — Producción)

---

## ¿Qué es Nginx y qué rol juega aquí?

**Nginx** es un servidor web y proxy inverso. En producción, su función en este proyecto es:

1. **Terminar SSL** — recibe conexiones HTTPS externas y las desencripta.
2. **Proxy inverso hacia n8n** — redirige las peticiones de Webhooks de Meta al contenedor n8n.
3. **Capa de firewall de aplicación** — puede bloquear accesos al backend PHP que no provengan
   de la red interna Docker.

```
Internet (HTTPS)
      │
      ▼
  [ Nginx ]  ← corre en el servidor host, escucha en :443
      │
      ├─ /webhook/* ─────────────────→  cosmol_n8n:5678  (reenvía Webhooks de Meta)
      │
      └─ /api/*  ←── Solo acepta desde la red Docker interna
                       → cosmol_php_backend:80
```

---

## El modelo de defensa en capas

Este archivo documenta la **tercera capa** de defensa, complementando las anteriores:

| # | Capa | Qué bloquea | Dónde vive |
|---|---|---|---|
| 1 | **Red Docker interna** | El puerto de PHP no es accesible desde Internet | `docker-compose.yml` |
| 2 | **Token interno (Fase 1)** | Peticiones sin el header `X-Internal-Token` correcto | `app/Core/Auth.php` |
| 3 | **Restricción Nginx** ← este doc | Peticiones al backend que no vengan de n8n, incluso dentro del servidor | `nginx.conf` |

> [!NOTE]
> Si Docker ya aísla la red (capa 1) y PHP ya valida el token (capa 2), ¿para qué agregar Nginx?
> Porque la defensa en capas significa que **si una capa falla, las otras siguen protegiendo**.
> Ejemplo: si alguien gana acceso SSH al servidor y lanza `curl` localmente, las capas 1 y 2
> podrían ser insuficientes. Nginx como proxy inverso añade una barrera adicional independiente.

---

## ¿Cómo funciona un proxy inverso?

Sin Nginx, el flujo en producción sería:

```
Meta Webhook → Internet → Servidor → Docker → n8n → (red interna) → PHP
```

Con Nginx como proxy inverso:

```
Meta Webhook → Internet → Nginx (:443) → n8n (:5678) → (red interna) → PHP
                                │
                                └─ Bloquea cualquier petición directa a PHP
                                   que no venga del contenedor n8n
```

Nginx actúa como "portero": decide qué pasa hacia adentro y desde dónde.

---

## Configuración de Nginx para producción

### Estructura de archivos a crear

```
/etc/nginx/
└── sites-available/
    └── cosmol.conf     ← este es el archivo de configuración
```

### Archivo `cosmol.conf` completo

```nginx
# ============================================================
# Servidor principal — Chatbot COSMOL (Producción)
# ============================================================

# Redirigir HTTP → HTTPS
server {
    listen 80;
    server_name tu-dominio.cosmol.com.bo;
    return 301 https://$host$request_uri;
}

# Servidor HTTPS principal
server {
    listen 443 ssl;
    server_name tu-dominio.cosmol.com.bo;

    # Certificados SSL (se obtienen con Let's Encrypt / Certbot)
    ssl_certificate     /etc/letsencrypt/live/tu-dominio.cosmol.com.bo/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.cosmol.com.bo/privkey.pem;

    # Protocolos seguros (deshabilitar TLS 1.0 y 1.1)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # ─────────────────────────────────────────────────────────
    # BLOQUE 1: Webhooks de Meta → n8n
    # Accesible públicamente (Meta necesita llamar a esta ruta)
    # ─────────────────────────────────────────────────────────
    location /webhook/ {
        proxy_pass         http://127.0.0.1:5678;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }

    # ─────────────────────────────────────────────────────────
    # BLOQUE 2: API PHP — RESTRINGIDA a la red Docker interna
    # ─────────────────────────────────────────────────────────
    location /api/ {
        # Definir el rango de IPs de la red Docker interna
        # La red bridge de Docker por defecto usa 172.17.0.0/16
        # La red personalizada "cosmol_network" puede usar otro rango.
        # Ver el rango real con: docker network inspect cosmol_internal_network

        allow 172.16.0.0/12;   # Rango típico de redes Docker internas
        allow 127.0.0.1;       # Loopback del host (para pruebas locales en el servidor)
        deny  all;              # Bloquear todo lo demás

        proxy_pass         http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
    }

    # ─────────────────────────────────────────────────────────
    # BLOQUE 3: Interfaz de n8n (opcional — solo si se quiere
    # acceder al panel de n8n por HTTPS desde el exterior)
    # Si no se necesita acceso externo al panel, eliminar este bloque.
    # ─────────────────────────────────────────────────────────
    location / {
        proxy_pass         http://127.0.0.1:5678;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade    $http_upgrade;
        proxy_set_header   Connection "upgrade";
        proxy_set_header   Host       $host;
    }
}
```

---

## Cómo obtener el rango de IP real de la red Docker

El rango `172.16.0.0/12` es el más común, pero puede variar. Para confirmar el rango exacto
de la red `cosmol_network` en producción:

```bash
# Inspeccionar la red Docker
docker network inspect cosmol_internal_network

# Buscar la sección "IPAM" → "Subnet" en la salida:
# "IPAM": {
#     "Config": [
#         {
#             "Subnet": "172.20.0.0/16",   ← este es el rango a usar en el allow
#             "Gateway": "172.20.0.1"
#         }
#     ]
# }
```

Luego en el `nginx.conf`, reemplazar el `allow` con el valor real:

```nginx
allow 172.20.0.0/16;   # ← rango real de cosmol_internal_network
deny  all;
```

---

## Pasos de instalación en el servidor de producción

```bash
# 1. Instalar Nginx
sudo apt update && sudo apt install -y nginx

# 2. Instalar Certbot para SSL gratuito con Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx

# 3. Copiar el archivo de configuración
sudo cp cosmol.conf /etc/nginx/sites-available/cosmol.conf

# 4. Activar el sitio
sudo ln -s /etc/nginx/sites-available/cosmol.conf /etc/nginx/sites-enabled/cosmol.conf

# 5. Obtener el certificado SSL (reemplazar con el dominio real)
sudo certbot --nginx -d tu-dominio.cosmol.com.bo

# 6. Verificar que la configuración no tiene errores de sintaxis
sudo nginx -t

# 7. Recargar Nginx
sudo systemctl reload nginx

# 8. Verificar que Nginx se inicia solo al reiniciar el servidor
sudo systemctl enable nginx
```

---

## Verificación de la restricción

### Desde Internet (debe fallar)

```bash
# Llamar directamente a /api/ desde fuera del servidor
curl https://tu-dominio.cosmol.com.bo/api/socio.php?cod_socio=1

# Respuesta esperada: 403 Forbidden
# Nginx bloqueó la petición antes de llegar al contenedor PHP
```

### Desde dentro del servidor (debe funcionar)

```bash
# Simular una petición que viene de la red Docker interna
curl -H "X-Internal-Token: [token_del_env]" \
     http://127.0.0.1:8000/api/socio.php?cod_socio=12345

# Respuesta esperada: 200 OK + datos del socio
```

### Webhooks de Meta (deben funcionar)

```bash
# Verificar que el webhook de Meta sigue llegando a n8n
curl -X POST https://tu-dominio.cosmol.com.bo/webhook/whatsapp \
     -H "Content-Type: application/json" \
     -d '{"test": "ping"}'

# Respuesta esperada: 200 OK desde n8n
```

---

## Resumen visual de la arquitectura en producción

```
┌─────────────────────────────────────────────────────────────────┐
│  SERVIDOR DE PRODUCCIÓN                                         │
│                                                                 │
│  ┌────────┐  :443 HTTPS                                         │
│  │ Nginx  │◄──────────── Meta Webhooks / Usuarios              │
│  └───┬────┘                                                     │
│      │                                                          │
│      ├── /webhook/* ──────────────────►  cosmol_n8n (:5678)    │
│      │                                        │                 │
│      └── /api/* (solo red interna) ←──────────┘                │
│                       │                                         │
│                       ▼                                         │
│            cosmol_php_backend (:80)                             │
│                       │                                         │
│                       ▼                                         │
│                cosmol_mysql (:3306)                             │
│                                                                 │
│  🔒 PHP y MySQL no tienen puertos expuestos al exterior        │
│  🔒 Nginx bloquea peticiones directas a /api/ desde Internet   │
│  🔒 Auth.php valida el token en cada petición aceptada         │
└─────────────────────────────────────────────────────────────────┘
```
