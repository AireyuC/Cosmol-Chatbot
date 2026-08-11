-- Script de inicialización para Docker MySQL
-- Este script se ejecutará automáticamente la primera vez que se levante el contenedor de base de datos.
-- Las tablas se crean dentro de la base definida en MYSQL_DATABASE (docker-compose / .env).

CREATE TABLE IF NOT EXISTS socio(
    codigo_socio INT PRIMARY KEY,
    ci VARCHAR(20) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion VARCHAR(255) DEFAULT 'Sin dirección',
    estado_conexion BIT(1) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS reclamo(
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_reclamo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    direccion VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    codigo_socio INT NOT NULL,
    CONSTRAINT fk_reclamo_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

INSERT INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion)
VALUES ("54321","9768156","Eduardo","Cuellar","77712345","Av.SiempreViva 742",1);

INSERT INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion)
VALUES ("12345","1234567","Juan","Perez","77712345","Av.SiempreViva 742",1);

INSERT INTO reclamo (tipo_reclamo, descripcion, direccion, codigo_socio)
VALUES ("Fuga de Agua","El tubo principal de la calle está roto y sale mucha agua", "Calle 742", "54321");

INSERT INTO reclamo (tipo_reclamo, descripcion, direccion, codigo_socio)
VALUES ("Fuga de Agua","El tubo principal de la calle está roto y sale mucha agua", "Calle 742", "12345");

