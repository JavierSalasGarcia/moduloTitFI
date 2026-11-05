-- Acceso de egresados sin correo institucional (docs/ACCESO_EGRESADOS.md).
-- Repetible: solo crea o cambia lo que falta y no borra datos.

-- Marca de la cuenta de egresado: rol «alumno» con alcance limitado al módulo de Titulación, correo personal validado
-- por el personal y vigencia de 24 meses sin actividad.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE usuarios ADD COLUMN egresado_externo TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN egresado_vence DATE NULL DEFAULT NULL, ADD COLUMN egresado_cuenta VARCHAR(12) NULL DEFAULT NULL, ADD COLUMN egresado_carrera VARCHAR(20) NULL DEFAULT NULL, ADD COLUMN egresado_validado_por INT UNSIGNED NULL DEFAULT NULL, ADD COLUMN egresado_validado_en DATETIME NULL DEFAULT NULL',
  'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'egresado_externo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Solicitudes de acceso: no son cuentas; nada se activa sin la aprobación del personal de Titulación.
CREATE TABLE IF NOT EXISTS egresado_solicitudes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  genero ENUM('masculino','femenino','no_binario') NOT NULL,
  carrera VARCHAR(20) NOT NULL,
  cuenta VARCHAR(12) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  estado ENUM('sin_verificar','requiere_docs','pendiente','aprobada','activa','rechazada') NOT NULL DEFAULT 'sin_verificar',
  prevalidacion ENUM('coincide','media','ninguna') DEFAULT NULL,
  prevalidacion_detalle VARCHAR(500) DEFAULT NULL,
  codigo_hash VARCHAR(255) DEFAULT NULL,
  codigo_expira DATETIME DEFAULT NULL,
  codigo_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  codigo_enviado DATETIME DEFAULT NULL,
  verificado_en DATETIME DEFAULT NULL,
  motivo VARCHAR(500) DEFAULT NULL,
  resuelto_por INT UNSIGNED DEFAULT NULL,
  resuelto_en DATETIME DEFAULT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  clave_expira DATETIME DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_egresado_token (token_hash),
  KEY idx_egresado_estado (estado, creado_en),
  KEY idx_egresado_cuenta (cuenta),
  KEY idx_egresado_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Documentos de identidad: viven en storage/ privado y se borran al resolver; queda el sello (huella y fechas).
CREATE TABLE IF NOT EXISTS egresado_documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id INT UNSIGNED NOT NULL,
  tipo ENUM('identificacion','comprobante') NOT NULL,
  ruta VARCHAR(255) DEFAULT NULL,
  nombre_original VARCHAR(160) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  tamano INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  subido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  borrado_en DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_egresado_doc_sol (solicitud_id),
  CONSTRAINT fk_egresado_doc_sol FOREIGN KEY (solicitud_id) REFERENCES egresado_solicitudes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
