-- Consejos: documentos adjuntos con una breve descripción. De una solicitud (anexos que sube quien solicita) o de un caso (el oficio
-- original escaneado de lo que llegó sin pasar por la plataforma, y sus anexos). Al incorporar una solicitud, sus adjuntos pasan al caso.
-- Repetible.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consejos_adjuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id INT UNSIGNED NULL,
  asunto_id INT UNSIGNED NULL,
  tipo ENUM('oficio','anexo') NOT NULL DEFAULT 'anexo',   -- oficio = el oficio original escaneado, anexo = documento que lo acompaña
  descripcion VARCHAR(200) NOT NULL,                       -- de qué es el archivo: «Constancias de recursos humanos», «Anexo 1…»
  nombre VARCHAR(160) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  extension VARCHAR(8) NOT NULL,
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  subido_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cons_adj_solicitud (solicitud_id),
  KEY idx_cons_adj_asunto (asunto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
