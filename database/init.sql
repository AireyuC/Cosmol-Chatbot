
-- Esquema ANSI SQL para Desarrollo Local (PostgreSQL 16.14.1-alpine / Informix)

CREATE TABLE IF NOT EXISTS socio (
    codigo_socio INT PRIMARY KEY,
    ci VARCHAR(20) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion VARCHAR(255) DEFAULT 'Sin dirección',
    estado_conexion BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS reclamo (
    id SERIAL PRIMARY KEY,
    tipo_reclamo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(500),
    direccion VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    codigo_socio INT NOT NULL,
    CONSTRAINT fk_reclamo_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

CREATE TABLE IF NOT EXISTS factura (
    id SERIAL PRIMARY KEY,
    codigo_socio INT NOT NULL,
    periodo VARCHAR(20) NOT NULL,
    monto NUMERIC(10,2) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_emision DATE,
    fecha_vencimiento DATE,
    CONSTRAINT fk_factura_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

CREATE TABLE IF NOT EXISTS chat_session (
    telefono_whatsapp VARCHAR(20) PRIMARY KEY,
    codigo_socio INT NULL,
    estado_actual VARCHAR(50) DEFAULT 'AWAITING_CODE',
    intentos_fallidos INT DEFAULT 0,
    ultima_interaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    context_data TEXT NULL
);

-- Buffer local de consultas hacia COSMOL-Reportes (Cola de resiliencia ante caídas)
CREATE TABLE IF NOT EXISTS cola_reportes (
    id SERIAL PRIMARY KEY,
    codigo_socio INT NOT NULL,
    nombres VARCHAR(200) NOT NULL,
    id_tipo INT NOT NULL,
    tipo_consulta VARCHAR(100) NOT NULL,
    fecha_consulta DATE NOT NULL DEFAULT CURRENT_DATE,
    hora_consulta TIME NOT NULL DEFAULT CURRENT_TIME,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    intentos INT NOT NULL DEFAULT 0,
    ultimo_error TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


INSERT INTO socio (codigo_socio, ci, nombre, apellido, telefono, direccion, estado_conexion) 
VALUES (267657, '1234567', 'Juan', 'Pérez', '59170000000', 'Av. Prueba 123', TRUE);

INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Mayo-2026', 95.50, 'PAGADA', '2026-05-01', '2026-05-15');

INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Junio-2026', 107.60, 'PENDIENTE', '2026-06-01', '2026-06-15');

INSERT INTO factura (codigo_socio, periodo, monto, estado, fecha_emision, fecha_vencimiento) 
VALUES (267657, 'Julio-2026', 114.30, 'PENDIENTE', '2026-07-01', '2026-07-15');