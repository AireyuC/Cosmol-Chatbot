-- Script de inicialización para Docker MySQL
-- Este script se ejecutará automáticamente la primera vez que se levante el contenedor de base de datos.

CREATE DATABASE IF NOT EXISTS cosmol_db;
USE cosmol_db;

CREATE TABLE IF NOT EXISTS socio(
    codigo_socio INT PRIMARY KEY,
    ci VARCHAR(20) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    estado_conexion BIT(1) NOT NULL DEFAULT 0,
);

CREATE TABLE IF NOT EXISTS reclamo(
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_reclamo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    direccion VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'PENDIENTE',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    codigo_socio INT NOT NULL,
    CONSTRAINT fk_reclamo_socio FOREIGN KEY (codigo_socio) REFERENCES socio(codigo_socio)
);

-- (Nota: Si tu compañero tiene las tablas de la Fase 2 como "socios", puede agregarlas a este mismo archivo)
