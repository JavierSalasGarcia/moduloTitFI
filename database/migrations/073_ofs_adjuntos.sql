-- Anexos de los oficios de la Subdirección Académica: viajan con el oficio y se ven en su ficha; NO se unen al PDF que se firma.
-- Los archivos viven en storage/oficios_sub/adjuntos/ (nunca en public/). Repetible.
CREATE TABLE IF NOT EXISTS ofs_adjuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficio_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(200) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  sha256 CHAR(64) NOT NULL,
  bytes INT UNSIGNED NOT NULL,
  subido_por INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ofs_adj_oficio (oficio_id),
  CONSTRAINT fk_ofs_adj_oficio FOREIGN KEY (oficio_id) REFERENCES ofs_oficios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
