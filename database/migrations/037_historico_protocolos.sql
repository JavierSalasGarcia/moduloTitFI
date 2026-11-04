-- Captura de protocolos revisados fuera del sistema (en papel): cada registro guarda al alumno, el título, la fecha de aprobación y los
-- revisores del protocolo; al generar sus constancias se crea un trámite histórico (concluido, sin cuenta de alumno) y se usa la misma
-- cola de firmas, búsqueda y QR. Repetible.
CREATE TABLE IF NOT EXISTS tit_historico_protocolos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  alumno VARCHAR(160) NOT NULL DEFAULT '',
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  titulo VARCHAR(500) NOT NULL DEFAULT '',
  director VARCHAR(160) NOT NULL DEFAULT '',
  fecha_inicio DATE NULL,
  fecha_aprobacion DATE NULL,
  revisor1 VARCHAR(160) NOT NULL DEFAULT '', revisor1_email VARCHAR(120) NOT NULL DEFAULT '',
  revisor2 VARCHAR(160) NOT NULL DEFAULT '', revisor2_email VARCHAR(120) NOT NULL DEFAULT '',
  revisor3 VARCHAR(160) NOT NULL DEFAULT '', revisor3_email VARCHAR(120) NOT NULL DEFAULT '',
  tramite_id BIGINT UNSIGNED NULL,
  generado_en DATETIME NULL,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_histprot_carrera (carrera, fecha_aprobacion),
  KEY idx_tit_histprot_tramite (tramite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
