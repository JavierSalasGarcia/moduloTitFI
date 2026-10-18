-- Titulación: configuración editable de formatos, documentos y cotejo de originales.

-- Cambios a formatos oficiales (plantilla reemplazada y/o coordenadas editadas) y formatos añadidos.
CREATE TABLE IF NOT EXISTS tit_formatos_cfg (
  clave VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  personalizado TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  nombre VARCHAR(200) NULL,
  firma VARCHAR(200) NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidades VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NULL,
  plantilla_ruta VARCHAR(300) NULL,
  spec MEDIUMTEXT NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave),
  CONSTRAINT fk_tit_fmt_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustes por documento. modalidad '*' vale para todas las opciones; NULL en una columna hereda del catálogo.
CREATE TABLE IF NOT EXISTS tit_docs_cfg (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  activo TINYINT(1) NULL,
  fisico TINYINT(1) NULL,
  cotejo TINYINT(1) NULL,
  firma_cotejo ENUM('sello','pades') NULL,
  nombre VARCHAR(250) NULL,
  nota VARCHAR(500) NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_docs_cfg (modalidad, clave),
  CONSTRAINT fk_tit_dcfg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos o entregas añadidos por la administración a una etapa.
CREATE TABLE IF NOT EXISTS tit_docs_extra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '*',
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(250) NOT NULL,
  nota VARCHAR(500) NULL,
  condicion VARCHAR(300) NULL,
  formato VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  solo_fisico TINYINT(1) NOT NULL DEFAULT 0,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_docs_extra (modalidad, etapa, clave),
  CONSTRAINT fk_tit_dex_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_config_bitacora (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  accion VARCHAR(60) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_cfgbit (creado_en),
  CONSTRAINT fk_tit_cfgbit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cotejo del original contra el PDF subido, con sello o firma PAdES de quien coteja.
ALTER TABLE tit_documentos
  ADD COLUMN cotejo_tipo ENUM('sello','pades') NULL AFTER revisado_en,
  ADD COLUMN cotejo_por INT UNSIGNED NULL AFTER cotejo_tipo,
  ADD COLUMN cotejo_en DATETIME NULL AFTER cotejo_por,
  ADD COLUMN cotejo_hash CHAR(64) NULL AFTER cotejo_en,
  ADD COLUMN cotejo_ruta VARCHAR(500) NULL AFTER cotejo_hash,
  ADD CONSTRAINT fk_tit_doc_cotejo FOREIGN KEY (cotejo_por) REFERENCES usuarios(id);
