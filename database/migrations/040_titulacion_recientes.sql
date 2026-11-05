-- Registros del departamento de Titulación que se llevaban en Excel (2015-2026): solicitudes sin trabajo escrito, protocolos
-- recibidos, control de oficios, «artículo 5», actas de aplazamiento y las actas más recientes del libro. Repetible: cada
-- tabla tiene una huella única y se usa INSERT IGNORE; en tit_historico solo se agregan actas que no existen y se llenan vacíos.
CREATE TABLE IF NOT EXISTS tit_solicitudes_historicas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  siglas VARCHAR(20) NOT NULL DEFAULT '',
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  tipo VARCHAR(20) NOT NULL DEFAULT '',
  tipo_origen VARCHAR(40) NOT NULL DEFAULT '',
  fecha_solicitud DATE NULL,
  fecha_programada DATE NULL,
  vez VARCHAR(10) NOT NULL DEFAULT '',
  nivel VARCHAR(12) NOT NULL DEFAULT '',
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  lote VARCHAR(80) NOT NULL DEFAULT '',
  observaciones VARCHAR(200) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_solh_huella (huella), KEY idx_tit_solh_cuenta (cuenta), KEY idx_tit_solh_fecha (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_protocolos_recibidos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidad_origen VARCHAR(60) NOT NULL DEFAULT '',
  fecha_entrega DATE NULL,
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  incorporada VARCHAR(30) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_protr_huella (huella), KEY idx_tit_protr_cuenta (cuenta), KEY idx_tit_protr_fecha (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_oficios_control (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  serie CHAR(4) CHARACTER SET ascii NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NULL,
  dirigido VARCHAR(200) NOT NULL DEFAULT '',
  asunto VARCHAR(200) NOT NULL DEFAULT '',
  tipo VARCHAR(30) NOT NULL DEFAULT 'otro',
  persona VARCHAR(160) NOT NULL DEFAULT '',
  tomado_por VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_ofc_num (serie, numero), KEY idx_tit_ofc_fecha (fecha), KEY idx_tit_ofc_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_articulo5 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  plan VARCHAR(20) NOT NULL DEFAULT '',
  fecha_evaluacion DATE NULL,
  fecha_articulo5 DATE NULL,
  oficio VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_art5_huella (huella), KEY idx_tit_art5_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_actas_aplazamiento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consecutivo INT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  fecha DATE NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_aplaz_cons (consecutivo), KEY idx_tit_aplaz_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

