-- Titulación: revisión de protocolos por comisiones de cada licenciatura.

-- Estructura: integrantes de cada comisión (la carrera es la clave de Catalogo::carreras()).
CREATE TABLE IF NOT EXISTS tit_comision_miembros (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  email VARCHAR(120) NOT NULL,
  rol ENUM('encargado','miembro') NOT NULL DEFAULT 'miembro',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_miembro (carrera, email),
  KEY idx_tit_miembro_email (email),
  CONSTRAINT fk_tit_miembro_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada vez que el pasante sube su protocolo se abre una ronda de revisión (1ª, 2ª…).
CREATE TABLE IF NOT EXISTS tit_revisiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  numero SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  documento_id BIGINT UNSIGNED NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  estado ENUM('recibida','en_comision','en_evaluacion','por_dictaminar','dictaminada','cancelada') NOT NULL DEFAULT 'recibida',
  fecha_recepcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviada_por INT UNSIGNED NULL,
  enviada_en DATETIME NULL,
  evaluacion_en DATETIME NULL,
  dictamen ENUM('aprobado','no_aprobado') NULL,
  requiere_coord TINYINT(1) NULL,
  observaciones TEXT NULL,
  dictaminado_por INT UNSIGNED NULL,
  dictaminado_en DATETIME NULL,
  firma_tipo ENUM('sello','pades') NULL,
  dictamen_ruta VARCHAR(500) NULL,
  dictamen_hash CHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_rev_ronda (tramite_id, numero),
  KEY idx_tit_rev_estado (carrera, estado),
  CONSTRAINT fk_tit_rev_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_rev_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_rev_envia FOREIGN KEY (enviada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_rev_dictamina FOREIGN KEY (dictaminado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluador asignado a una ronda. El token del enlace del correo se guarda solo como huella.
CREATE TABLE IF NOT EXISTS tit_revision_asignaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  revision_id BIGINT UNSIGNED NOT NULL,
  miembro_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_vence DATE NOT NULL,
  estado ENUM('pendiente','borrador','firmada','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_limite DATE NOT NULL,
  asignada_por INT UNSIGNED NULL,
  asignada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  propuesta ENUM('aprobado','no_aprobado') NULL,
  requiere_coord TINYINT(1) NULL,
  forma TEXT NULL,
  fondo TEXT NULL,
  firmada_en DATETIME NULL,
  firma_tipo ENUM('sello','pades') NULL,
  firmante_id INT UNSIGNED NULL,
  pdf_ruta VARCHAR(500) NULL,
  pdf_hash CHAR(64) NULL,
  recordatorios SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_recordatorio DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_asig_token (token_hash),
  UNIQUE KEY uq_tit_asig_miembro (revision_id, miembro_id),
  CONSTRAINT fk_tit_asig_rev FOREIGN KEY (revision_id) REFERENCES tit_revisiones(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_asig_miembro FOREIGN KEY (miembro_id) REFERENCES tit_comision_miembros(id),
  CONSTRAINT fk_tit_asig_asigna FOREIGN KEY (asignada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_asig_firma FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de correos enviados (sin cuerpo: los mensajes contienen enlaces personales).
CREATE TABLE IF NOT EXISTS tit_correos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  destinatario VARCHAR(120) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  tramite_id BIGINT UNSIGNED NULL,
  estado ENUM('enviado','fallido','no_configurado') NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_correos (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
('titulacion_dias_revision', '7', 'Titulación: días hábiles que tiene cada evaluador para revisar un protocolo.'),
('titulacion_dias_aviso', '2', 'Titulación: faltando estos días hábiles el semáforo de la revisión pasa a ámbar.'),
('titulacion_firma_revisores', 'sello', 'Titulación: firma de las revisiones de protocolo (sello o pades).'),
('titulacion_dias_inhabiles', '', 'Titulación: fechas no laborables (AAAA-MM-DD, una por línea) que no cuentan como días hábiles.'),
('titulacion_token_gracia', '14', 'Titulación: días extra tras la fecha límite en que sigue vigente el enlace del correo.');
