-- Identidad estable de las personas ante el cambio de dominio del correo institucional (@profesor.uaemex.mx y @alumno.uaemex.mx
-- pasan a @uaemex.mx). Repetible: solo crea o cambia lo que falta y no borra datos.
--
--  · La persona es la cuenta (usuarios.id) o, si aún no tiene, el profesor del directorio (profesores.id, que se identifica por su RFC,
--    un dato interno que nunca se muestra ni se pide al profesor). El correo es un atributo que puede cambiar.
--  · usuario_correos: todos los correos que la cuenta ha tenido y verificó (el vigente es usuarios.email). Se acepta cualquiera para
--    iniciar sesión, pero los códigos siempre se envían al vigente.
--  · cambio_correo: solicitud de cambio de correo con código enviado al correo nuevo.
--  · tit_constancias.usuario_id / profesor_id: la constancia pertenece a la persona, no a un correo; así no se pierde si el correo cambia.
--  · rrhh_administradores: personal de Recursos Humanos que sube el RFC (y el número de empleado) para desambiguar y enlaza cuentas.

CREATE TABLE IF NOT EXISTS usuario_correos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  email varchar(120) NOT NULL,
  principal tinyint(1) NOT NULL DEFAULT 0,
  origen enum('cuenta','cambio','recursos_humanos') NOT NULL DEFAULT 'cuenta',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_correo (email),
  KEY idx_usuario_correo_usuario (usuario_id),
  CONSTRAINT fk_usuario_correo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT IGNORE INTO usuario_correos (usuario_id, email, principal, origen) SELECT id, email, 1, 'cuenta' FROM usuarios;

CREATE TABLE IF NOT EXISTS cambio_correo (
  usuario_id int unsigned NOT NULL,
  email_nuevo varchar(120) NOT NULL,
  codigo_hash varchar(255) NOT NULL,
  expira_en datetime NOT NULL,
  intentos tinyint unsigned NOT NULL DEFAULT 0,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_cambio_correo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS rrhh_administradores (
  usuario_id int unsigned NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_rrhh_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Número de empleado: desambiguador opcional que sube Recursos Humanos (único cuando se conoce).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE profesores ADD COLUMN num_empleado varchar(12) NULL DEFAULT NULL AFTER rfc, ADD UNIQUE KEY uq_profesor_empleado (num_empleado)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profesores' AND COLUMN_NAME = 'num_empleado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Constancias por persona.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_constancias ADD COLUMN usuario_id int unsigned NULL DEFAULT NULL AFTER email, ADD COLUMN profesor_id int unsigned NULL DEFAULT NULL AFTER usuario_id, ADD KEY idx_tit_constancia_usuario (usuario_id), ADD KEY idx_tit_constancia_profesor (profesor_id)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND COLUMN_NAME = 'usuario_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Relleno desde el correo con que se emitieron (la cuenta, y el profesor del directorio por cualquiera de sus correos).
UPDATE tit_constancias c JOIN usuario_correos uc ON uc.email COLLATE utf8mb4_unicode_ci = c.email
SET c.usuario_id = uc.usuario_id WHERE c.usuario_id IS NULL;
UPDATE tit_constancias c JOIN profesor_correos pc ON pc.email COLLATE utf8mb4_unicode_ci = c.email
SET c.profesor_id = pc.profesor_id WHERE c.profesor_id IS NULL;
UPDATE tit_constancias c JOIN profesores p ON p.usuario_id = c.usuario_id
SET c.profesor_id = p.id WHERE c.profesor_id IS NULL AND c.usuario_id IS NOT NULL;
UPDATE tit_constancias c JOIN profesores p ON p.id = c.profesor_id
SET c.usuario_id = p.usuario_id WHERE c.usuario_id IS NULL AND p.usuario_id IS NOT NULL;
