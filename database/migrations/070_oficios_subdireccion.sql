-- Oficios de la Subdirección Académica: redacción por la asistente, número del contador único (ACAD/FI/{n}/{AAAA}, tres dígitos,
-- reinicio anual), PDF en hoja membretada con QR y firma del titular con su e.firma del SAT (en el navegador; la llave nunca se guarda).
-- Repetible.
SET NAMES utf8mb4;

-- Destinatarios que se reutilizan de un oficio a otro.
CREATE TABLE IF NOT EXISTS ofs_destinatarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(160) NOT NULL,
  cargo VARCHAR(255) NOT NULL DEFAULT '',
  dependencia VARCHAR(255) NOT NULL DEFAULT '',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ofs_dest (nombre, cargo, dependencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Cada oficio. El número se reserva al enviarlo a firma (borrador = sin número). Estados: borrador → en_firma → firmado; cancelado.
CREATE TABLE IF NOT EXISTS ofs_oficios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  estado ENUM('borrador','en_firma','firmado','cancelado') NOT NULL DEFAULT 'borrador',
  serie VARCHAR(20) NOT NULL DEFAULT 'subdireccion',
  anio SMALLINT UNSIGNED NULL,
  numero INT UNSIGNED NULL,
  numero_texto VARCHAR(40) NULL,
  asunto VARCHAR(250) NOT NULL,
  destinatarios TEXT NOT NULL,                  -- JSON: [{nombre, cargo, dependencia}]
  cuerpo_html MEDIUMTEXT NOT NULL,              -- HTML limpio del lienzo (párrafos, negritas, cursivas y tablas)
  fecha DATE NULL,
  codigo VARCHAR(32) NULL,                      -- código del QR de verificación
  creado_por INT UNSIGNED NOT NULL,
  enviado_en DATETIME NULL,
  observacion VARCHAR(500) NULL,                -- lo que pide el titular al devolverlo
  firmado_por INT UNSIGNED NULL,
  firmado_en DATETIME NULL,
  pdf_preparado VARCHAR(255) NULL,              -- PDF con el espacio de la firma, esperando la firma del navegador
  pdf_firmado VARCHAR(255) NULL,
  sha256 CHAR(64) NULL,                         -- huella del PDF firmado
  cert_serie VARCHAR(60) NULL,                  -- número de serie del certificado (nunca el RFC)
  cert_nombre VARCHAR(190) NULL,
  cert_emisor VARCHAR(255) NULL,
  cert_vigencia VARCHAR(80) NULL,
  cadena_estado VARCHAR(255) NULL,              -- «verificada contra los certificados del SAT cargados» / «no verificada…»
  motivo_cancelacion VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ofs_numero (serie, anio, numero),
  UNIQUE KEY uq_ofs_codigo (codigo),
  KEY idx_ofs_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS ofs_historial (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficio_id INT UNSIGNED NOT NULL,
  accion VARCHAR(30) NOT NULL,
  usuario_id INT UNSIGNED NULL,
  nota VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ofs_hist (oficio_id),
  CONSTRAINT fk_ofs_hist FOREIGN KEY (oficio_id) REFERENCES ofs_oficios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Certificados públicos de la cadena del SAT (raíz e intermedios) que carga la administración: sirven para verificar la cadena de las
-- firmas y se ofrecen para descargar en el kit de confianza. No son secretos.
CREATE TABLE IF NOT EXISTS ofs_confianza (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(190) NOT NULL,
  pem MEDIUMTEXT NOT NULL,
  huella CHAR(64) NOT NULL,
  subido_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ofs_conf (huella)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Contador único de la Subdirección: el último oficio de 2026 fue el 446 (el siguiente es el 447).
INSERT INTO tit_oficio_series (serie, anio, ultimo) VALUES ('subdireccion', 2026, 446)
  ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, 446);
