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


insert into socio 
(codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion)
values
('267657', '1234567', 'Juan', 'Perez', '59170000000', 'Av. Prueba 123', 1); 

insert into reclamo
(codigo_socio, tipo_reclamo, descripcion, direccion, estado)
values
('267657', 'Agua turbia', 'El agua sale turbia', 'Av. Prueba 123', 'PENDIENTE');

insert into factura 
(codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento)
values
('267657', 'Enero-2025', 100, 'PENDIENTE', '2025-01-01', '2025-01-31');


