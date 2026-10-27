-- Roles múltiples por persona, cargos Titular/Asistente y avisos dirigidos a varios programas.
-- Repetible: solo crea o cambia lo que falta y no borra datos.

-- Cargo principal de la cuenta dentro de su departamento o coordinación (Titular de… / Asistente de…).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN cargo ENUM(''titular'',''asistente'') NULL DEFAULT NULL AFTER rol', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cargo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Roles adicionales: la persona conserva su rol principal en usuarios.rol y suma los que aquí se registren.
-- Ejemplo: profesor en IC e ISC (dos filas con programa) y, además, titular de una coordinación de docencia.
CREATE TABLE IF NOT EXISTS usuario_roles (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  rol enum('admin','titular','operativo','profesor','alumno','coordinador') NOT NULL,
  departamento_id smallint unsigned NOT NULL DEFAULT 0,
  programa_id smallint unsigned NOT NULL DEFAULT 0,
  cargo enum('titular','asistente') DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_rol (usuario_id, rol, departamento_id, programa_id),
  KEY idx_usuario_roles_rol (rol, departamento_id, programa_id),
  CONSTRAINT fk_usuario_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Avisos: a quién se dirigen (alumnos, profesores o todos) y a cuántos programas a la vez.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE comunicados ADD COLUMN destinatarios ENUM(''todos'',''alumno'',''profesor'') NOT NULL DEFAULT ''todos'' AFTER alcance', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'destinatarios');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS comunicado_programas (
  comunicado_id int unsigned NOT NULL,
  programa_id smallint unsigned NOT NULL,
  PRIMARY KEY (comunicado_id, programa_id),
  KEY idx_comunicado_programa (programa_id),
  CONSTRAINT fk_comunicado_programa_com FOREIGN KEY (comunicado_id) REFERENCES comunicados (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Los avisos ya publicados para un programa pasan a la nueva tabla.
INSERT IGNORE INTO comunicado_programas (comunicado_id, programa_id)
SELECT id, programa_id FROM comunicados WHERE alcance = 'programa' AND programa_id IS NOT NULL;

-- Concursos: calaveritas favoritas de cada persona (de ellas elige a su preferida al votar). No son el voto.
CREATE TABLE IF NOT EXISTS con_favoritos (
  concurso_id int unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  numero int unsigned NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (concurso_id, usuario_id, numero),
  CONSTRAINT fk_con_favorito_concurso FOREIGN KEY (concurso_id) REFERENCES con_concursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cargos: los antiguos departamentos «Titular de la …» se unifican con su dependencia y el cargo pasa a la cuenta.
-- Subdirección Académica, Subdirección Administrativa y Coordinación de Planeación (por clave, no por id).
UPDATE usuarios u
  JOIN departamentos o ON o.id = u.departamento_id
  JOIN departamentos n ON n.clave = REPLACE(o.clave, 'titular_de_la_', '')
   SET u.cargo = 'titular', u.departamento_id = n.id
 WHERE u.rol = 'titular'
   AND o.clave IN ('titular_de_la_subdireccion_academica', 'titular_de_la_subdireccion_administrativa', 'titular_de_la_coordinacion_de_planeacion');
UPDATE usuarios SET cargo = 'titular' WHERE cargo IS NULL AND rol = 'titular' AND departamento_id IS NOT NULL;
