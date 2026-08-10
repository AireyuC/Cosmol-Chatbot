# Plan de Dockerización — Chatbot COSMOL

Contenerización completa del entorno de desarrollo del backend, separando cada servicio en su propio contenedor y orquestándolos con Docker Compose. La meta es que con un solo comando (`docker compose up`) el entorno quede listo para programar.

---

## Información del entorno actual

| Herramienta | Versión instalada |
|---|---|
| Docker Engine | 29.6.2 |
| Docker Compose | v5.3.1 |
| PHP (destino) | 7.3 |
| n8n (destino) | última estable compatible |
| MySQL (dev) | la que empaqueta XAMPP 3.2.2 → **MySQL 5.7.44** |

---

## Información adicional requerida

> [!IMPORTANT]
> Antes de ejecutar necesito que me confirmes los siguientes datos:

1. **Puerto local para PHP-FPM / Apache**: ¿El contenedor PHP debe responder en el puerto `8080` o prefieres otro (ej. `80`, `8000`)?
2. **Credenciales MySQL**: ¿Quieres que defina unas credenciales de desarrollo genéricas (usuario `cosmol`, contraseña `secret`, DB `cosmol_dev`) o tienes unas credenciales preferidas?
3. **Volumen de datos MySQL**: ¿Quieres que los datos de la BD persistan en un volumen Docker (recomendado) o que se reinicien con cada `docker compose down -v`?
4. **n8n con autenticación**: n8n en desarrollo puede correr sin login (modo `basic auth` o sin auth). ¿Activas usuario/contraseña desde el inicio o no por ahora?
5. **ngrok**: ¿Quieres que el contenedor de ngrok también sea parte del `compose` inicial (hay imagen oficial), o lo manejarás fuera de Docker por separado?

---

## Servicios a contenerizar

```
┌──────────────────────────────────────────────────────────┐
│                   docker-compose.yml                     │
│                                                          │
│  ┌──────────┐   ┌──────────┐   ┌──────────┐             │
│  │  php-api │   │   n8n    │   │  mysql   │             │
│  │ PHP 7.3  │◄──│  latest  │   │  5.7.44  │             │
│  │ + Apache │   │ :5678    │   │  :3306   │             │
│  │ :8080    │   └──────────┘   └──────────┘             │
│  └─────┬────┘         ▲              ▲                   │
│        └──────────────┴──────────────┘                   │
│                  red interna                             │
└──────────────────────────────────────────────────────────┘
```

---

## Cambios propuestos

### Archivos raíz del proyecto

#### [NEW] `docker-compose.yml`
Orquestador principal. Declara 3 servicios:
- **`php-api`**: imagen `php:7.3-apache`, monta el código del proyecto, expone el puerto definido.
- **`n8n`**: imagen `n8nio/n8n:latest`, persistencia en volumen, expone `:5678`.
- **`mysql`**: imagen `mysql:5.7`, configura DB/usuario/contraseña con variables de entorno, persistencia en volumen.

Todos los servicios estarán en la misma red interna `cosmol-network` para comunicarse por nombre de servicio (ej. `mysql:3306`).

#### [NEW] `Dockerfile`
Dockerfile para el contenedor PHP:
- `FROM php:7.3-apache`
- Habilita `mod_rewrite` de Apache (necesario para que `.htaccess` redirija todo a `public/index.php`)
- Instala extensiones: `pdo`, `pdo_mysql`, `mysqli`
- Copia la configuración de VirtualHost que apunta el `DocumentRoot` a `/var/www/html/public`
- Instala Composer (para PSR-4 autoload según `ESTRUCTURA.md`)

#### [NEW] `.env`
Variables de entorno del proyecto (nunca commiteado con valores reales):
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `N8N_PORT`, `PHP_PORT`
- `APP_ENV=development`

#### [NEW] `.env.example`
Plantilla de `.env` con valores de ejemplo, sí commiteada en Git como referencia.

#### [MODIFY] `.gitignore`
Añadir `.env` y los volúmenes de datos Docker si se guardan localmente.

---

### Configuración de Apache / PHP

#### [NEW] `docker/php/vhost.conf`
Configuración del VirtualHost de Apache dentro del contenedor:
- `DocumentRoot /var/www/html/public`
- `AllowOverride All` para que el `.htaccess` funcione
- Logs de acceso y error activos

#### [NEW] `.htaccess` (en raíz del proyecto)
Redirige toda petición al `public/index.php` (front controller).

---

## Plan de ejecución paso a paso

- `[ ]` **Paso 1 — `.env` y `.env.example`**: Crear ambos archivos con las variables del entorno.
- `[ ]` **Paso 2 — `Dockerfile`**: Construir la imagen PHP 7.3 + Apache con las extensiones necesarias y Composer.
- `[ ]` **Paso 3 — `docker/php/vhost.conf`**: Configurar Apache para que apunte a `/public`.
- `[ ]` **Paso 4 — `.htaccess`**: Añadir el front controller redirect en raíz y en `public/`.
- `[ ]` **Paso 5 — `docker-compose.yml`**: Declarar los 3 servicios, la red interna y los volúmenes con las variables del `.env`.
- `[ ]` **Paso 6 — `.gitignore`**: Actualizar para excluir `.env` y datos locales de Docker.
- `[ ]` **Paso 7 — Build y verificación**: Ejecutar `docker compose up --build` y validar que los 3 servicios corran.
- `[ ]` **Paso 8 — Smoke test**: Hacer una petición HTTP al contenedor PHP y verificar que n8n responde en `:5678`.

---

## Plan de verificación

### Automático
```powershell
docker compose up --build -d
docker compose ps          # los 3 servicios deben aparecer como "running"
docker compose logs php-api
```

### Manual
- Abrir `http://localhost:8080` → debe responder (error 404 esperado, pero desde PHP, no de Apache).
- Abrir `http://localhost:5678` → debe mostrar la UI de n8n.
- Ejecutar `docker compose exec php-api php -v` → debe mostrar `PHP 7.3.x`.
- Ejecutar `docker compose exec php-api php -m | findstr pdo` → debe listar `pdo`, `pdo_mysql`.

---

## Notas sobre la migración a Informix (Fase 2)

> [!NOTE]
> En Fase 2 se añadirá al `Dockerfile` la compilación del driver `pdo_informix`. La arquitectura con interfaz de repositorio (`ClienteRepositoryInterface`) garantiza que ese cambio sea quirúrgico y no afecte a Services ni Controllers.
