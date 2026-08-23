# Aislamiento de Red Docker — API PHP sin Puerto Público

> **Tipo:** Plan de Implementación (Seguridad de Infraestructura)
> **Entorno:** Desarrollo y Producción
> **Archivo a modificar:** [`docker-compose.yml`](file:///c:/Proyectos/Cosmol-Chatbot/docker-compose.yml)
> **Estado:** Pendiente de implementar

---

## ¿Qué problema resuelve?

En el `docker-compose.yml` actual, el contenedor del backend PHP tiene esta configuración:

```yaml
backend:
  ports:
    - "8000:80"   # ← EXPONE el puerto 80 del contenedor al puerto 8000 del host
```

### ¿Qué significa "exponer un puerto"?

Cuando Docker mapea `"8000:80"`, le dice al sistema operativo del servidor:
> *"Cualquier conexión que llegue al puerto 8000 de esta máquina, redirígela al contenedor PHP."*

Esto significa que la API PHP es accesible **desde fuera del servidor**:

```
Internet
  │
  ├─→ :5678  → cosmol_n8n   (correcto, n8n necesita ser público para recibir Webhooks)
  │
  └─→ :8000  → cosmol_php_backend  ← PROBLEMA: la API PHP no debería ser accesible
                                      directamente desde Internet
```

Cualquier persona que conozca la IP del servidor y el puerto `8000` puede llamar a la API
directamente, **saltándose a n8n y el token de autenticación interna**.

> [!CAUTION]
> Aunque el token de la Fase 1 bloquea peticiones sin credenciales, si el token fuera comprometido
> (filtrado en logs, historial de bash, etc.), tener el puerto expuesto facilita enormemente los
> ataques. La defensa en capas significa que **cada capa debe ser independientemente segura**.

---

## La solución: Red Docker Interna

Docker permite crear redes virtuales privadas entre contenedores. Los contenedores en la misma
red pueden comunicarse entre sí usando sus nombres de servicio como hostname, pero **los
contenedores sin puerto expuesto no son accesibles desde el exterior**.

### Arquitectura objetivo

```
Internet
  │
  └─→ :5678  → cosmol_n8n ─(red interna cosmol_network)─→ cosmol_php_backend
                                                         ↘─→ cosmol_mysql

  ✅ n8n: accesible públicamente (necesita recibir Webhooks de Meta)
  🔒 PHP: solo accesible desde la red Docker interna (solo n8n puede llamarlo)
  🔒 MySQL: solo accesible desde la red Docker interna (solo PHP puede llamarlo)
```

---

## Cambios a aplicar en docker-compose.yml

### Estado actual (con vulnerabilidad)

```yaml
services:
  n8n:
    ports:
      - "5678:5678"
    # Sin declaración de red → Docker crea una red bridge por defecto compartida

  backend:
    ports:
      - "8000:80"   # ← Puerto expuesto al host (problema)
    # Sin declaración de red

  db:
    ports:
      - "3306:3306" # ← Puerto de MySQL expuesto al host (también un problema)
```

### Estado objetivo (con aislamiento)

```yaml
services:

  n8n:
    image: docker.n8n.io/n8nio/n8n:1
    container_name: cosmol_n8n
    ports:
      - "5678:5678"         # ✅ Puerto público necesario para recibir Webhooks de Meta
    networks:
      - cosmol_network      # ← Conectado a la red interna para hablar con PHP
    env_file:
      - .env
    volumes:
      - n8n_data:/home/node/.n8n
    depends_on:
      backend:
        condition: service_healthy

  backend:
    build:
      context: .
      dockerfile: dockerfile
    container_name: cosmol_php_backend
    # ✅ SIN "ports:" → el contenedor no es accesible desde fuera de Docker
    # Solo cosmol_n8n (en la misma red) puede llamar a http://cosmol_php_backend/
    networks:
      - cosmol_network      # ← Misma red que n8n para que se comuniquen
    env_file:
      - .env
    volumes:
      - .:/app
    healthcheck:
      test: ["CMD-SHELL", "php -r \"exit(@fsockopen('localhost', 80) ? 0 : 1);\""]
      interval: 10s
      timeout: 5s
      retries: 5
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:5.7
    container_name: cosmol_mysql
    # ✅ SIN "ports:" → MySQL no es accesible desde fuera de Docker
    # Solo cosmol_php_backend puede conectarse a "db:3306"
    networks:
      - cosmol_network      # ← Misma red que PHP
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
      - MYSQL_DATABASE=${DB_NAME}
      - MYSQL_USER=${DB_USER}
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

networks:
  cosmol_network:
    driver: bridge
    name: cosmol_internal_network

volumes:
  n8n_data:
    name: cosmol_n8n_data
  mysql_data:
    name: cosmol_mysql_data
```

---

## ¿Qué cambia en n8n al aplicar esto?

En los nodos HTTP Request de n8n, la URL de la API PHP pasa de apuntar al host a apuntar
directamente al nombre del contenedor por la red interna:

| Entorno | URL antes | URL después |
|---|---|---|
| Dev (con puerto expuesto) | `http://localhost:8000/api/socio.php` | `http://cosmol_php_backend/api/socio.php` |
| Prod | `http://servidor:8000/api/socio.php` | `http://cosmol_php_backend/api/socio.php` |

La URL con el nombre del contenedor funciona porque ambos están en `cosmol_network`.
Docker resuelve `cosmol_php_backend` como hostname automáticamente.

> [!NOTE]
> El nombre del hostname es el valor de `container_name:` en el servicio correspondiente.
> En este caso `cosmol_php_backend`, no `backend` (que es el nombre del servicio en compose).

---

## Consideración para el entorno de desarrollo

Al eliminar `"8000:80"` del bloque de `ports` sin perfil, ya no se puede llamar a la API
desde Postman, curl o el navegador en la máquina local directamente en modo producción.

**Decisión tomada: Opción A — Perfil de desarrollo (`--profile dev`) ✅**

Se utilizará un perfil de Docker Compose para que el puerto `8000` solo se exponga
cuando se levanta el entorno explícitamente en modo desarrollo, sin afectar la
configuración de producción:

```yaml
backend:
  ports:
    - "8000:80"   # Solo activo con: docker compose --profile dev up
  profiles:
    - dev
```

```bash
# Desarrollo (con puerto 8000 expuesto para Postman, curl, navegador)
docker compose --profile dev up

# Producción (sin perfil → sin puerto expuesto, aislamiento completo)
docker compose up
```

> [!NOTE]
> Con `--profile dev`, el puerto `8000` queda disponible en `http://localhost:8000`.
> Sin el flag, Docker ignora la sección `ports` del perfil y el contenedor
> solo es accesible desde dentro de `cosmol_network`.

### Opción B — Exec directo al contenedor (alternativa sin perfil)
Si no se quiere levantar con `--profile dev`, es posible llamar a PHP desde
dentro de la red ejecutando comandos en el contenedor de n8n:

```bash
# Entrar al contenedor de n8n y llamar a PHP desde dentro de la red interna
docker exec -it cosmol_n8n sh -c "wget -q -O- 'http://cosmol_php_backend/api/socio.php?cod_socio=1' --header='Authorization: Bearer TU_TOKEN'"
```

---

## Verificación

Después de aplicar el cambio:

```bash
# Reconstruir con la nueva configuración
docker compose down
docker compose up -d

# Verificar que el puerto 8000 ya NO responde desde el host
curl http://localhost:8000/api/socio.php
# Resultado esperado: Connection refused (no hay puerto expuesto)

# Verificar que n8n SÍ puede llamar a PHP por la red interna
docker exec -it cosmol_n8n sh -c "wget -q -O- http://cosmol_php_backend/api/socio.php?cod_socio=1"
# Resultado esperado: {"success":false,"message":"No autorizado.",...}  (401 por el token)
```

El `401` en la verificación es la respuesta correcta: confirma que PHP responde a n8n
por la red interna, pero sigue rechazando peticiones sin el token de la Fase 1.
