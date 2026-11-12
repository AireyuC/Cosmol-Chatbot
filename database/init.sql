-- =============================================================================
-- ESQUEMA DE BASE DE DATOS — CHATBOT COSMOL R.L. (PostgreSQL 16)
-- =============================================================================
-- Este script se ejecuta automáticamente al inicializar el contenedor Docker.
--
-- ARQUITECTURA DE DATOS:
-- En producción, los datos de los asociados, facturación, deudas y trámites técnicos
-- se consultan directamente en tiempo real desde el sistema central SAI (Informix)
-- a través de APIs REST (COSMOL_API_URL).
--
-- Por lo tanto, NO SE REQUIERE INSERTAR SEEDS NI DATOS INICIALES.
-- Las tablas operativas del chatbot se pueblan de forma autónoma con el tráfico.
-- =============================================================================

-- 1. Máquina de estados conversacional y sesiones activas de WhatsApp
CREATE TABLE IF NOT EXISTS chat_session (
    telefono_whatsapp VARCHAR(20) PRIMARY KEY,
    codigo_socio INT NULL,
    estado_actual VARCHAR(50) DEFAULT 'AWAITING_CODE',
    intentos_fallidos INT DEFAULT 0,
    ultima_interaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    context_data TEXT NULL
);

-- Índice de alto rendimiento para escaneo de timeouts de inactividad (Crons 3 min y 10 min)
CREATE INDEX IF NOT EXISTS idx_chat_session_inactividad 
    ON chat_session (estado_actual, ultima_interaccion);

-- 2. Buffer local y cola de resiliencia hacia COSMOL-Reportes
-- Almacena auditoría, cuotas diarias (anti-spam) y garantiza entrega de datos ante caídas de red.
CREATE TABLE IF NOT EXISTS cola_reportes (
    id SERIAL PRIMARY KEY,
    codigo_socio INT NOT NULL,
    nombres VARCHAR(200) NOT NULL,
    telefono VARCHAR(30) NULL,
    id_tipo INT NOT NULL,
    tipo_consulta VARCHAR(100) NOT NULL,
    tipo_ubicacion VARCHAR(20) NULL,
    fecha_consulta DATE NOT NULL DEFAULT CURRENT_DATE,
    hora_consulta TIME NOT NULL DEFAULT CURRENT_TIME,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    intentos INT NOT NULL DEFAULT 0,
    ultimo_error TEXT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices optimizados para cola_reportes (Crons periódicos, límites de tasa y cooldowns)
CREATE INDEX IF NOT EXISTS idx_cola_reportes_pendientes 
    ON cola_reportes (estado, id) 
    WHERE estado = 'PENDIENTE';

CREATE INDEX IF NOT EXISTS idx_cola_reportes_audit_telefono 
    ON cola_reportes (fecha_consulta, telefono, id_tipo);

CREATE INDEX IF NOT EXISTS idx_cola_reportes_audit_socio 
    ON cola_reportes (fecha_consulta, codigo_socio, id_tipo);
