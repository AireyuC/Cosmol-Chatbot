# Guía de Despliegue en Servidor Linux (Pruebas / Producción)

Esta guía detalla los pasos exactos que debes seguir desde que te entregan el servidor Linux vacío, hasta que el bot de WhatsApp quede funcionando con su dominio y candado de seguridad (SSL).

## Fase 1: Preparar el Servidor Linux

Conéctate a tu servidor Linux (por SSH) y ejecuta los siguientes comandos para actualizar el sistema e instalar Docker.

```bash
# 1. Actualizar repositorios y paquetes
sudo apt update && sudo apt upgrade -y

# 2. Instalar Docker y Docker Compose (Script oficial de Docker)
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# 3. Darle permisos a tu usuario para usar docker sin escribir "sudo" siempre
sudo usermod -aG docker $USER
newgrp docker
```

## Fase 2: Mover el Código y Configurar

> [!IMPORTANT]  
> Asegúrate de haber hecho un `git push` de tu código local a un repositorio remoto (GitHub, GitLab, Bitbucket) antes de este paso.

```bash
# 1. Clonar tu repositorio en el servidor
git clone https://github.com/tu-usuario/Cosmol-Chatbot.git
cd Cosmol-Chatbot

# 2. Crear el archivo de entorno (.env)
cp .env.example .env
nano .env
```

Dentro del editor `nano`, configura tus variables reales. Las más importantes para cambiar en producción son:

- `WEBHOOK_URL=https://tu-dominio-cosmol.com/` *(Cambiar el ngrok por el dominio real que usará n8n)*
- `APP_ENV=production` *(Para optimizar el rendimiento de PHP)*
- `APP_DEBUG=false` *(Para ocultar errores de código a los usuarios)*
- `DB_PASSWORD=una_contraseña_muy_segura` *(Cambiar la de desarrollo)*
- `DB_ROOT_PASSWORD=otra_contraseña_fuerte` *(Cambiar la de desarrollo)*
- `WHATSAPP_TOKEN=...` *(Asegúrate de que tenga el token permanente de Meta)*
- `COSMOL_API_URL=...` *(Si la URL del sistema SAI cambia en producción)*

> [!NOTE]
> Recuerda que **NO** necesitas copiar el archivo `docker-compose.override.yml`. Al no tener este archivo, el puerto `8000` de PHP quedará aislado de forma segura.

## Fase 3: Conectar el Dominio (Caddy SSL)

El sistema ahora incluye **Caddy**, un proxy inverso moderno que solicita automáticamente los certificados SSL gratuitos de *Let's Encrypt*.

Lo único que debes hacer es:
1. En tu proveedor de dominios (GoDaddy, Cloudflare, etc.), crea un **Récord A** para tu dominio (ej. `api.cosmol.com.bo`) que apunte a la **IP Pública** de este servidor.
2. Abre los puertos **80 y 443** en el Firewall de tu servidor o panel de la nube.
3. Edita el archivo `.env` y asegúrate de que la variable `DOMAIN_NAME` tenga exactamente tu dominio (sin `http://`).

## Fase 4: ¡Encender los Motores!

Ya con el entorno listo y el dominio apuntando, enciende los contenedores de Docker:

```bash
docker compose up -d
```

> [!TIP]
> **Verificación SSL:** Ejecuta `docker compose logs -f caddy`. Deberías ver cómo Caddy contacta a Let's Encrypt y obtiene el certificado en unos pocos segundos. A partir de ahora, n8n estará disponible de forma 100% segura en `https://tu-dominio-cosmol.com`.

## Fase 5: Conectar con Meta

1. Ve a tu panel de **Meta for Developers**.
2. Cambia la "URL de devolución de llamada" de ngrok a tu nuevo dominio HTTPS: `https://tu-dominio-cosmol.com/webhook/whatsapp`
3. Ingresa cualquier palabra en el Token de Verificación y dale a "Verificar y guardar".


