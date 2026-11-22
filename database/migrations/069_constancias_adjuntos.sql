-- Constancias: evidencia opcional que quien solicita adjunta a su constancia (y que el firmante puede pedir antes de firmar).
-- Los archivos viven en storage/constancias/adjuntos/ (nunca en public/) y se sirven con permiso. Repetible.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cst_adjuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id BIGINT UNSIGNED NOT NULL,
  descripcion VARCHAR(200) NOT NULL DEFAULT '',
  nombre VARCHAR(160) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  extension VARCHAR(8) NOT NULL,
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  subido_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cst_adj_solicitud (solicitud_id),
  CONSTRAINT fk_cst_adj_solicitud FOREIGN KEY (solicitud_id) REFERENCES cst_solicitudes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
