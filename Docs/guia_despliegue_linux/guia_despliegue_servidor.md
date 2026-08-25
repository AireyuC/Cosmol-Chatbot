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

Dentro del archivo `.env`, configura tus variables reales. 

> [!NOTE]
> Recuerda que **NO** necesitas copiar el archivo `docker-compose.override.yml`. Al no tener este archivo, el puerto `8000` de PHP quedará aislado de forma segura.

## Fase 3: Conectar el Dominio (Proxy Externo)

Como el servidor ya cuenta con un dominio y certificado HTTPS administrado por tu proveedor (o departamento de TI), **no necesitas instalar Nginx ni Certbot**.

Lo único que debes hacer es indicar a la persona encargada de la red (o configurar en tu panel de control) que todo el tráfico web entrante a ese dominio debe ser redirigido internamente al **Puerto 5678** (que es donde escucha el contenedor de n8n).

## Fase 4: ¡Encender los Motores!

Ya con el entorno listo, enciende los contenedores de Docker. (Asegúrate de estar en la carpeta de tu proyecto `Cosmol-Chatbot`):

```bash
docker compose up -d
```

> [!TIP]
> **Verificación:** Ejecuta `docker ps`. Deberías ver tus 3 contenedores (`cosmol_n8n`, `cosmol_postgres` y `cosmol_backend`) corriendo, pero notarás que `cosmol_backend` **no expone** el puerto 8000 hacia `0.0.0.0`, logrando nuestra meta de seguridad y aislamiento.

## Fase 5: Conectar con Meta

1. Ve a tu panel de **Meta for Developers**.
2. Cambia la "URL de devolución de llamada" de ngrok a tu dominio oficial provisto por el servidor: `https://tu-dominio-cosmol.com/webhook/whatsapp`
3. Ingresa cualquier palabra en el Token de Verificación y dale a "Verificar y guardar".

¡Felicidades! 🎉 Tu bot ahora vive en su propio servidor y está listo para recibir mensajes 24/7 sin depender de tu computadora ni de ngrok.
