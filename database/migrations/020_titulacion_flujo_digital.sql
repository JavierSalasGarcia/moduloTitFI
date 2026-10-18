-- Titulación: flujo digital (firma electrónica de la Subdirección, asistente, piloto automático,
-- firma de asesores por enlace de correo y límite de tamaño de PDF).
-- Consolida todo lo posterior a 019 y es repetible: puede ejecutarse aunque parte ya esté aplicada.

-- ---------------------------------------------------------------- Subdirección
CREATE TABLE IF NOT EXISTS tit_firmas_subdireccion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('documento','dictamen') NOT NULL DEFAULT 'documento',
  documento_id BIGINT UNSIGNED NULL,
  revision_id BIGINT UNSIGNED NULL,
  formato VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  descripcion VARCHAR(250) NOT NULL,
  caja VARCHAR(120) NULL,
  estado ENUM('pendiente','firmado','cancelado') NOT NULL DEFAULT 'pendiente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  programado_para DATETIME NULL,
  firmado_en DATETIME NULL,
  capacidad ENUM('personal','delegada','automatica') NULL,
  firmante_id INT UNSIGNED NULL,
  piloto_id INT UNSIGNED NULL,
  ruta_firmado VARCHAR(500) NULL,
  hash_firmado CHAR(64) NULL,
  PRIMARY KEY (id),
  KEY idx_tit_fs_estado (estado, programado_para),
  KEY idx_tit_fs_tramite (tramite_id),
  CONSTRAINT fk_tit_fs_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fs_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_fs_rev FOREIGN KEY (revision_id) REFERENCES tit_revisiones(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_fs_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_asistentes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(120) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_asistente (email),
  CONSTRAINT fk_tit_asist_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_piloto (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  retraso_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  formatos TEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  apagado_en DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_tit_piloto (activo, inicio, fin),
  CONSTRAINT fk_tit_piloto_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Firma de asesores por enlace
CREATE TABLE IF NOT EXISTS tit_firmas_externas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  documento_id BIGINT UNSIGNED NOT NULL,
  rol ENUM('asesor','coasesor') NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  email VARCHAR(120) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  vence DATE NOT NULL,
  caja VARCHAR(160) NULL,
  estado ENUM('pendiente','firmada','cancelada') NOT NULL DEFAULT 'pendiente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  firmada_en DATETIME NULL,
  firmante_id INT UNSIGNED NULL,
  hash_firmado CHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_fext_token (token_hash),
  KEY idx_tit_fext_doc (documento_id, estado),
  CONSTRAINT fk_tit_fext_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fext_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fext_usuario FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Columnas nuevas (solo si faltan)
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_firmas_subdireccion ADD COLUMN caja VARCHAR(120) NULL AFTER descripcion', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_firmas_subdireccion' AND COLUMN_NAME = 'caja');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_en DATETIME NULL AFTER cotejo_ruta', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_capacidad ENUM(''personal'',''delegada'',''automatica'') NULL AFTER subdir_en', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_capacidad');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_ruta VARCHAR(500) NULL AFTER subdir_capacidad', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_ruta');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN pendiente_firmas TINYINT(1) NOT NULL DEFAULT 0 AFTER subdir_ruta', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'pendiente_firmas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------- Ajustes
INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
('titulacion_subdirector_email', 'jsalasg@uaemex.mx', 'Titulación: correo de la cuenta de quien ocupa la Subdirección Académica (firma documentos y administra a su asistente y el piloto automático).'),
('titulacion_max_mb', '5', 'Titulación: tamaño máximo en MB de cada PDF que sube el pasante.');
