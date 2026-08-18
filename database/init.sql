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


CREATE TABLE IF NOT EXISTS factura(
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_socio INT NOT NULL,
    periodo VARCHAR(20) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_emision DATE,
    fecha_vencimiento DATE,
    CONSTRAINT fk_factura_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

CREATE TABLE IF NOT EXISTS chat_session(
    telefono_whatsapp VARCHAR(20) PRIMARY KEY,
    codigo_socio INT NULL,
    estado_actual VARCHAR(50) DEFAULT 'AWAITING_CODE',
    intentos_fallidos INT DEFAULT 0,
    ultima_interaccion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    -- No usamos FOREIGN KEY hacia socio local porque los socios vienen de la API de COSMOL (Informix)
);

-- DATOS DE PRUEBA

-- Insertamos un socio de prueba (Código: 267657)
INSERT IGNORE INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion) 
VALUES (267657, '1234567', 'Juan', 'Pérez', '59170000000', 'Av. Prueba 123', 1);

-- Insertamos facturas para este socio
-- Una pagada (Histórico)
INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Mayo-2026', 95.50, 'PAGADA', '2026-05-01', '2026-05-15');

-- Dos pendientes (Deuda actual)
INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Junio-2026', 107.60, 'PENDIENTE', '2026-06-01', '2026-06-15');

INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Julio-2026', 114.30, 'PENDIENTE', '2026-07-01', '2026-07-15');