-- Verificación final del expediente de titulación y envío a Servicios Escolares (Administración Central).
-- Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

-- Un registro por trámite: lista de verificación (formato de integración de expediente) y su estado.
CREATE TABLE IF NOT EXISTS tit_expediente_final (
  tramite_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('en_integracion','verificado','enviado') NOT NULL DEFAULT 'en_integracion',
  marcas MEDIUMTEXT NOT NULL,
  observaciones VARCHAR(1000) NULL,
  verificado_por INT UNSIGNED NULL,
  verificado_en DATETIME NULL,
  enviado_por INT UNSIGNED NULL,
  enviado_en DATETIME NULL,
  lote_id BIGINT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tramite_id),
  KEY idx_tit_expfinal_estado (estado),
  CONSTRAINT fk_tit_expfinal_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada exportación del expediente (individual o en lote) que se envía a Servicios Escolares.
CREATE TABLE IF NOT EXISTS tit_expediente_lotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  folios VARCHAR(1000) NOT NULL DEFAULT '',
  huella CHAR(64) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
