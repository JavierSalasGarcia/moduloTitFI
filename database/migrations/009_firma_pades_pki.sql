-- Certificados individuales y firma remota PDF Advanced Electronic Signatures
-- (PAdES), estándar de firma digital incorporada al documento PDF.

CREATE TABLE IF NOT EXISTS firma_certificados (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int(10) unsigned NOT NULL,
  serie varchar(64) NOT NULL,
  sujeto varchar(500) NOT NULL,
  huella_sha256 char(64) NOT NULL,
  pfx_path varchar(500) NOT NULL COMMENT 'Ruta absoluta fuera de public; nunca se entrega al usuario',
  secreto_envuelto text NOT NULL COMMENT 'Contraseña del PKCS#12 cifrada por el servicio criptográfico',
  valido_desde datetime NOT NULL,
  valido_hasta datetime NOT NULL,
  estado enum('activo','revocado','expirado') NOT NULL DEFAULT 'activo',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  revocado_en timestamp NULL DEFAULT NULL,
  motivo_revocacion varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_cert_serie (serie),
  KEY idx_firma_cert_usuario_estado (usuario_id, estado),
  CONSTRAINT firma_certificados_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS firma_operaciones (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  solicitud_id int(10) unsigned NOT NULL,
  firmante_id int(10) unsigned NOT NULL,
  documento_id int(10) unsigned NOT NULL,
  certificado_id int(10) unsigned NOT NULL,
  campo_pdf varchar(100) NOT NULL,
  segundo_factor enum('totp','webauthn') NOT NULL,
  desafio_sha256 char(64) NOT NULL,
  entrada_sha256 char(64) NOT NULL,
  salida_sha256 char(64) NOT NULL,
  resultado enum('firmado','fallido') NOT NULL DEFAULT 'firmado',
  detalle varchar(500) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_operacion_doc_firmante (documento_id, firmante_id),
  KEY idx_firma_operacion_solicitud (solicitud_id),
  KEY idx_firma_operacion_certificado (certificado_id),
  CONSTRAINT firma_operaciones_solicitud_fk FOREIGN KEY (solicitud_id) REFERENCES firma_solicitudes (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_firmante_fk FOREIGN KEY (firmante_id) REFERENCES firma_firmantes (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_documento_fk FOREIGN KEY (documento_id) REFERENCES firma_documentos (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_certificado_fk FOREIGN KEY (certificado_id) REFERENCES firma_certificados (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS firma_segundo_factor_usos (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int(10) unsigned NOT NULL,
  codigo_hash char(64) NOT NULL,
  usado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_2fa_usuario_codigo (usuario_id, codigo_hash),
  KEY idx_firma_2fa_fecha (usado_en),
  CONSTRAINT firma_2fa_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

