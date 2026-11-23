-- Oficios de la Subdirección dirigidos a profesores: cada oficio firmado queda en «Avisos y eventos → Oficios» de sus destinatarios.
--  · ofs_destinatarios: además de personas hay GRUPOS (todos los profesores, tiempo completo, medio tiempo, asignatura, técnicos
--    académicos y profesores de cada programa). Una persona puede quedar enlazada a su registro del directorio de profesores (profesor_id)
--    o a su cuenta (usuario_id); el directorio se enlaza solo con la cuenta cuando el profesor se registra, así que el oficio le aparece
--    aunque se haya registrado después de que se firmó.
--  · ofs_oficio_destinos: a quién le corresponde ver cada oficio firmado (se llena al firmar).
-- Repetible.
SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE ofs_destinatarios ADD COLUMN tipo ENUM('persona','grupo') NOT NULL DEFAULT 'persona' AFTER dependencia", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ofs_destinatarios' AND COLUMN_NAME = 'tipo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE ofs_destinatarios ADD COLUMN grupo VARCHAR(40) NULL AFTER tipo", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ofs_destinatarios' AND COLUMN_NAME = 'grupo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE ofs_destinatarios ADD COLUMN usuario_id INT UNSIGNED NULL AFTER grupo", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ofs_destinatarios' AND COLUMN_NAME = 'usuario_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE ofs_destinatarios ADD COLUMN profesor_id INT UNSIGNED NULL AFTER usuario_id", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ofs_destinatarios' AND COLUMN_NAME = 'profesor_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS ofs_oficio_destinos (
  oficio_id INT UNSIGNED NOT NULL,
  tipo ENUM('usuario','profesor','grupo') NOT NULL,
  ref VARCHAR(40) NOT NULL,          -- id de usuario, id del directorio de profesores o clave del grupo
  PRIMARY KEY (oficio_id, tipo, ref),
  KEY idx_ofs_destino (tipo, ref),
  CONSTRAINT fk_ofs_destino FOREIGN KEY (oficio_id) REFERENCES ofs_oficios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Grupos que se pueden elegir como destinatarios de una circular.
INSERT IGNORE INTO ofs_destinatarios (nombre, cargo, dependencia, tipo, grupo) VALUES
  ('Profesoras y Profesores de la Facultad de Ingeniería', '', 'Universidad Autónoma del Estado de México', 'grupo', 'profesores'),
  ('Profesoras y Profesores de Tiempo Completo', '', 'Facultad de Ingeniería', 'grupo', 'ptc'),
  ('Profesoras y Profesores de Medio Tiempo', '', 'Facultad de Ingeniería', 'grupo', 'medio_tiempo'),
  ('Profesoras y Profesores de Asignatura', '', 'Facultad de Ingeniería', 'grupo', 'asignatura'),
  ('Técnicas y Técnicos Académicos', '', 'Facultad de Ingeniería', 'grupo', 'tecnico_academico');

-- Profesores de cada programa educativo.
INSERT IGNORE INTO ofs_destinatarios (nombre, cargo, dependencia, tipo, grupo)
SELECT CONCAT('Profesoras y Profesores de ', p.nombre), '', 'Facultad de Ingeniería', 'grupo', CONCAT('programa:', p.id) FROM programas p;
