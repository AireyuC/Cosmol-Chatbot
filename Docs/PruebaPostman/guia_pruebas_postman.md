# Guía de Pruebas de APIs con Postman

Esta guía detalla los pasos necesarios para probar las APIs del proyecto **COSMOL Chatbot** (`socio.php` y `reclamos.php`) de manera local utilizando Postman.

## 1. Requisitos Previos

Antes de ejecutar cualquier prueba en Postman, es indispensable asegurar que el entorno de desarrollo local esté funcionando correctamente.

### 1.1 Levantar el Entorno (Docker)
El proyecto utiliza Docker para emular el entorno de producción (PHP, Apache y MySQL).
1. Abre tu terminal.
2. Navega a la raíz del proyecto (`c:\Proyectos\Cosmol-Chatbot`).
3. Ejecuta el comando para levantar los contenedores en segundo plano:
   ```bash
   docker-compose up -d
   ```
4. Verifica que los contenedores `cosmol_php_backend` y `cosmol_mysql` estén corriendo.

### 1.2 Insertar Datos de Prueba (Mock Data)
Al levantar la base de datos por primera vez, las tablas (`socio` y `reclamo`) se crean vacías. Si intentas consultar un socio o crear un reclamo con la base de datos vacía, obtendrás errores (como el error `404 Socio no encontrado` o fallos de llave foránea).

Para insertar un socio de prueba, puedes ejecutar el siguiente comando SQL directamente en el contenedor de MySQL o utilizando un gestor de base de datos como DBeaver (conectado a `localhost:3306`):

**Comando rápido desde consola (Ajusta el password de root según tu `.env`):**
```bash
docker exec -it cosmol_mysql mysql -u root -p -D cosmol_db -e "INSERT INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion) VALUES (12345, '1234567', 'Juan', 'Pérez', '77712345', 'Av. Siempre Viva 742', 1) ON DUPLICATE KEY UPDATE codigo_socio=12345;"
```

**Si usas un cliente SQL, ejecuta esto:**
```sql
INSERT INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion) 
VALUES (12345, '1234567', 'Juan', 'Pérez', '77712345', 'Av. Siempre Viva 742', 1);
```

---

## 2. Probar API de Validación de Socio (`socio.php`)

Esta API verifica si un socio existe en la base de datos y retorna su información básica.

### Opción A: Usando el método GET
1. En Postman, haz clic en **New** > **HTTP Request**.
2. Método: **GET**.
3. URL: `http://localhost:8000/api/socio.php?codigo_socio=12345`
4. Presiona **Send**.

**Respuesta Esperada (200 OK):**
```json
{
    "success": true,
    "message": "Socio válido",
    "data": {
        "codigo_socio": 12345,
        "ci": "1234567",
        "nombre": "Juan Pérez",
        "telefono": "77712345",
        "direccion": "Av. Siempre Viva 742",
        "estado_conexion": true
    }
}
```

### Opción B: Usando el método POST (Recomendado para Webhooks)
1. Nuevo HTTP Request.
2. Método: **POST**.
3. URL: `http://localhost:8000/api/socio.php`
4. En la pestaña **Body**, selecciona **raw** y cambia el tipo a **JSON**.
5. Ingresa el payload:
   ```json
   {
       "codigo_socio": "12345"
   }
   ```
6. Presiona **Send**. Deberías obtener la misma respuesta que en GET.

---

## 3. Probar API de Registro de Reclamos (`reclamos.php`)

Esta API permite registrar un nuevo reclamo asociado a un socio existente. Solo admite peticiones por POST.

1. En Postman, crea un nuevo **HTTP Request**.
2. Método: **POST**.
3. URL: `http://localhost:8000/api/reclamos.php`
4. En la pestaña **Body**, selecciona **raw** y **JSON**.
5. Ingresa los datos del reclamo. **Importante:** El `codigo_socio` debe existir en la base de datos (por ejemplo, `12345`).
   ```json
   {
       "codigo_socio": "12345",
       "tipo_reclamo": "Fuga de Agua",
       "descripcion": "El tubo principal de la calle está roto y sale mucha agua"
   }
   ```
6. Presiona **Send**.

**Respuesta Esperada (200 OK):**
```json
{
    "success": true,
    "message": "Reclamo registrado exitosamente",
    "data": {
        "id_reclamo": 1
    }
}
```

## 💡 Consejos Adicionales
- **Guarda tus Requests:** Te recomendamos crear una *Collection* en Postman (ej. `COSMOL APIs`) y guardar allí estas peticiones para no tener que escribirlas cada vez.
- **Rutas sin barra al final:** Evita poner `/` al final de las URLs (ej. usa `/api/socio.php` y NO `/api/socio.php/`) para prevenir problemas de enrutamiento con Apache.
- **Troubleshooting (Problemas de Conexión):** Si Postman te devuelve un error `Connection Refused` o `404 Not Found`, confirma que tu contenedor `cosmol_php_backend` esté levantado y mapeado correctamente al puerto `8000` ejecutando `docker ps` en tu terminal.
