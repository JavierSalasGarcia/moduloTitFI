-- Titulación: firma electrónica de asesores (y otros actores sin cuenta) mediante enlace personal por correo.
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

ALTER TABLE tit_documentos
  ADD COLUMN pendiente_firmas TINYINT(1) NOT NULL DEFAULT 0 AFTER subdir_ruta;

INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
('titulacion_max_mb', '5', 'Titulación: tamaño máximo en MB de cada PDF que sube el pasante.');
