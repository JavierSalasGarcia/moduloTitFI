-- Oficios para justificar faltas de alumnos (Coordinadores de Docencia y Subdirección Académica). Repetible.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS jus_oficios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  firmante VARCHAR(40) CHARACTER SET ascii NOT NULL,
  numero VARCHAR(80) NOT NULL DEFAULT '',
  fecha DATE NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  destinatario VARCHAR(200) NOT NULL DEFAULT '',
  saludo VARCHAR(200) NOT NULL DEFAULT '',
  cuerpo TEXT NOT NULL,
  periodo VARCHAR(250) NOT NULL DEFAULT '',
  motivo VARCHAR(250) NOT NULL DEFAULT '',
  alumnos TEXT NOT NULL,
  firmante_nombre VARCHAR(200) NOT NULL,
  firmante_cargo VARCHAR(250) NOT NULL,
  archivo VARCHAR(255) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jus_usuario (usuario_id, creado_en),
  KEY idx_jus_firmante (firmante, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jus_adjuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficio_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(200) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_jus_adj (oficio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
