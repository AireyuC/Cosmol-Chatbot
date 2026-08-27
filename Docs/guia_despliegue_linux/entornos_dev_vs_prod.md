# Entornos de Ejecución: Desarrollo (Dev) vs Producción (Prod)

Para mantener la seguridad al máximo y no mezclar herramientas de prueba con el entorno real de los usuarios, hemos separado conceptualmente la arquitectura en dos modos.

Es fundamental entender qué herramientas y puertos deben estar activos en cada entorno para evitar exponer datos sensibles o tener conflictos de red.

---

## 🛠️ 1. Modo Desarrollador (Development)

Este modo se utiliza **exclusivamente en tu computadora local** (Windows/Mac) mientras programas o creas nuevos flujos.

### Características Principales:
- **Orquestador (n8n):** El puerto `5678` se expone directamente a tu `localhost` para que puedas abrir la interfaz gráfica mientras diseñas los flujos.
- **Proxy Inverso:** **No se utiliza Caddy**. Se utiliza **ngrok** para crear un túnel temporal que permite a Meta enviarte mensajes desde internet hacia tu `localhost:5678`.
- **Backend (PHP):** El puerto `8000` se expone a tu máquina mediante el archivo `docker-compose.override.yml`. Esto te permite usar Postman para probar tu código PHP de forma aislada.
- **Base de Datos:** Usamos contraseñas débiles (ej. `devpassword`) por simplicidad y la BD se reinicia rápidamente.
- **Variables `.env`:**
  - `WEBHOOK_URL=http://tu-url-ngrok.ngrok-free.app/`
  - `APP_ENV=development`
  - `APP_DEBUG=true` (Muestra todos los errores de PHP en pantalla para depurar).
  - `DOMAIN_NAME=localhost` (Ignorado, no hay Caddy).

### ¿Cómo se ejecuta?
En desarrollo, Docker automáticamente combina el archivo base con tu archivo de sobreescritura local:
```bash
# Docker leerá docker-compose.yml + docker-compose.override.yml
docker compose up -d
```

---

## 🚀 2. Modo Producción (Production)

Este modo se utiliza en el **Servidor Linux remoto** que atiende a los asociados reales de COSMOL. Su prioridad absoluta es la seguridad, la resiliencia y el aislamiento.

### Características Principales:
- **Orquestador (n8n):** El puerto `5678` **NO** se expone a internet. Está bloqueado y completamente aislado dentro de la red interna de Docker (`cosmol_network`).
- **Proxy Inverso:** **Se utiliza Caddy**. Este componente es el único que da la cara a internet abriendo los puertos `80` y `443`. Se encarga de cifrar el tráfico con HTTPS (candado de seguridad) y pasarlo internamente a n8n.
- **Backend (PHP):** **Aislado al 100%**. No expone el puerto 8000.
- **Resiliencia Automática:** Todos los contenedores usan la regla `restart: unless-stopped`. Si el servidor se apaga o reinicia por error, el bot volverá a encenderse solo.
- **Variables `.env`:**
  - `WEBHOOK_URL=https://api.cosmol.com.bo/webhook/whatsapp`
  - `APP_ENV=production`
  - `APP_DEBUG=false` (Oculta información técnica si hay errores).
  - `DOMAIN_NAME=api.cosmol.com.bo`
  - Contraseñas fuertes en `DB_PASSWORD`.

### Recomendación Técnica para Separarlos:
Actualmente hemos añadido Caddy al archivo `docker-compose.yml` base. Para evitar que Caddy intente obtener certificados SSL en tu entorno de Desarrollo local (lo cual daría error porque en tu computadora no tienes un dominio real), se recomienda:

1. **En Desarrollo:** Comentar el bloque de Caddy en el `docker-compose.yml` o usar perfiles de Docker (ej. `--profile prod`).
2. **En Producción:** **No** subir ni utilizar el archivo `docker-compose.override.yml`, de esa forma se garantiza el aislamiento total de los puertos de PHP y la interfaz directa de n8n.

---

> [!CAUTION]
> **Regla de Oro:** Nunca uses Ngrok en el servidor de Producción y nunca intentes levantar Caddy en tu computadora local de desarrollo. Cada herramienta tiene su entorno designado.
