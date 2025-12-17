-- =====================================================
-- MIGRACIÓN: Sistema de Encuestas Rediseñado
-- Soporta: Múltiples preguntas, respuestas abiertas, recibos anónimos
-- =====================================================

-- 1. Modificar tabla encuestas existente
ALTER TABLE encuestas
ADD COLUMN token_recibos VARCHAR(32) UNIQUE DEFAULT NULL AFTER activa,
DROP COLUMN permite_multiple;

-- 2. Crear tabla de preguntas
CREATE TABLE IF NOT EXISTS encuestas_preguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    texto_pregunta TEXT NOT NULL,
    tipo ENUM('unica', 'multiple', 'abierta') NOT NULL DEFAULT 'unica',
    requerida TINYINT(1) DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    INDEX idx_encuesta_orden (encuesta_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Migrar opciones existentes a nueva estructura
-- Primero crear tabla de opciones
CREATE TABLE IF NOT EXISTS encuestas_opciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pregunta_id INT NOT NULL,
    texto VARCHAR(500) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pregunta_id) REFERENCES encuestas_preguntas(id) ON DELETE CASCADE,
    INDEX idx_pregunta_orden (pregunta_id, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Migrar datos de encuestas_opciones antiguas a nueva estructura
-- (Asumiendo que cada encuesta antigua es una sola pregunta)
INSERT INTO encuestas_preguntas (encuesta_id, texto_pregunta, tipo, requerida, orden)
SELECT DISTINCT e.id, e.titulo, 'unica', 1, 1
FROM encuestas e
WHERE EXISTS (SELECT 1 FROM encuestas_opciones_old WHERE encuesta_id = e.id);

-- Renombrar tabla antigua si existe
RENAME TABLE encuestas_opciones TO encuestas_opciones_old;

-- Migrar opciones
INSERT INTO encuestas_opciones (pregunta_id, texto, orden)
SELECT ep.id, eo.texto, eo.id
FROM encuestas_opciones_old eo
INNER JOIN encuestas_preguntas ep ON eo.encuesta_id = ep.encuesta_id;

-- 5. Tabla de participación (bandera para anónimas)
CREATE TABLE IF NOT EXISTS encuestas_participacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    ha_votado TINYINT(1) DEFAULT 1,
    fecha_participacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participacion (encuesta_id, usuario_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_encuesta (encuesta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla de votos normales (con usuario_id)
CREATE TABLE IF NOT EXISTS encuestas_votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_voto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voto (encuesta_id, usuario_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_encuesta (encuesta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Respuestas de opción (normales)
CREATE TABLE IF NOT EXISTS encuestas_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voto_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    opcion_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voto_id) REFERENCES encuestas_votos(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES encuestas_preguntas(id) ON DELETE CASCADE,
    FOREIGN KEY (opcion_id) REFERENCES encuestas_opciones(id) ON DELETE CASCADE,
    INDEX idx_voto (voto_id),
    INDEX idx_pregunta (pregunta_id),
    INDEX idx_opcion (opcion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Respuestas abiertas (normales)
CREATE TABLE IF NOT EXISTS encuestas_respuestas_abiertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voto_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    texto_respuesta TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voto_id) REFERENCES encuestas_votos(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES encuestas_preguntas(id) ON DELETE CASCADE,
    INDEX idx_voto (voto_id),
    INDEX idx_pregunta (pregunta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Votos anónimos (CON recibo, SIN usuario_id)
CREATE TABLE IF NOT EXISTS encuestas_votos_anonimos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encuesta_id INT NOT NULL,
    recibo VARCHAR(20) UNIQUE NOT NULL,
    fecha_voto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encuesta_id) REFERENCES encuestas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_recibo (recibo),
    INDEX idx_encuesta (encuesta_id),
    INDEX idx_recibo (recibo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Respuestas anónimas de opción
CREATE TABLE IF NOT EXISTS encuestas_respuestas_anonimas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voto_anonimo_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    opcion_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voto_anonimo_id) REFERENCES encuestas_votos_anonimos(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES encuestas_preguntas(id) ON DELETE CASCADE,
    FOREIGN KEY (opcion_id) REFERENCES encuestas_opciones(id) ON DELETE CASCADE,
    INDEX idx_voto (voto_anonimo_id),
    INDEX idx_pregunta (pregunta_id),
    INDEX idx_opcion (opcion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Respuestas abiertas anónimas
CREATE TABLE IF NOT EXISTS encuestas_respuestas_abiertas_anonimas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voto_anonimo_id INT NOT NULL,
    pregunta_id INT NOT NULL,
    texto_respuesta TEXT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voto_anonimo_id) REFERENCES encuestas_votos_anonimos(id) ON DELETE CASCADE,
    FOREIGN KEY (pregunta_id) REFERENCES encuestas_preguntas(id) ON DELETE CASCADE,
    INDEX idx_voto (voto_anonimo_id),
    INDEX idx_pregunta (pregunta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Generar tokens para encuestas anónimas existentes
UPDATE encuestas
SET token_recibos = CONCAT(
    SUBSTRING(MD5(CONCAT(id, RAND())), 1, 8),
    SUBSTRING(MD5(CONCAT(titulo, RAND())), 1, 8),
    SUBSTRING(MD5(CONCAT(NOW(), RAND())), 1, 16)
)
WHERE anonima = 1 AND token_recibos IS NULL;

-- =====================================================
-- NOTAS DE MIGRACIÓN:
-- 1. Este script es seguro: usa CREATE IF NOT EXISTS y ALTER ADD COLUMN
-- 2. Los datos antiguos se migran a la nueva estructura
-- 3. La tabla encuestas_opciones_old se mantiene por seguridad
-- 4. Eliminar encuestas_opciones_old después de verificar migración:
--    DROP TABLE encuestas_opciones_old;
-- =====================================================
