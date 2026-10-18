-- Módulo Titulación (licenciatura y, más adelante, posgrado).
-- Se aplica a mano en pruebas y producción; copiar la carpeta del módulo no ejecuta SQL.

CREATE TABLE IF NOT EXISTS tit_tramites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  nivel ENUM('licenciatura','posgrado') NOT NULL DEFAULT 'licenciatura',
  alumno_id INT UNSIGNED NOT NULL,
  tipo ENUM('sin_trabajo','con_trabajo') NOT NULL,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  variante VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  estado ENUM('abierto','concluido','cancelado') NOT NULL DEFAULT 'abierto',
  etapa_actual VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'datos',
  titulo VARCHAR(500) NULL,
  datos MEDIUMTEXT NOT NULL,
  resultado VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  responsable_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_folio (folio),
  KEY idx_tit_alumno (alumno_id, estado),
  KEY idx_tit_bandeja (estado, etapa_actual),
  KEY idx_tit_modalidad (modalidad),
  CONSTRAINT fk_tit_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_documentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  origen ENUM('alumno','personal') NOT NULL DEFAULT 'alumno',
  estado ENUM('en_revision','aprobado','rechazado','no_aplica','reemplazado') NOT NULL DEFAULT 'en_revision',
  ruta VARCHAR(500) NULL,
  nombre_original VARCHAR(255) NULL,
  subido_por INT UNSIGNED NOT NULL,
  comentario VARCHAR(1000) NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_doc_tramite (tramite_id, etapa, clave, estado),
  KEY idx_tit_doc_revision (estado, creado_en),
  CONSTRAINT fk_tit_doc_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_doc_subio FOREIGN KEY (subido_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_doc_reviso FOREIGN KEY (revisado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_hitos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  datos TEXT NOT NULL,
  completado_por INT UNSIGNED NOT NULL,
  completado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_hito (tramite_id, etapa),
  CONSTRAINT fk_tit_hito_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_hito_usuario FOREIGN KEY (completado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  visible_alumno TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_evento_tramite (tramite_id, creado_en),
  CONSTRAINT fk_tit_evento_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dato que se captura una sola vez y se imprime en todos los formatos (Ajustes, solo administración).
INSERT IGNORE INTO ajustes (clave, valor, descripcion)
VALUES ('titulacion_subdirector', 'DR. JAVIER SALAS GARCÍA', 'Titulación: nombre de quien ocupa la Subdirección Académica, tal como se imprime en los formatos (incluye grado, p. ej. DR. NOMBRE APELLIDOS).');
