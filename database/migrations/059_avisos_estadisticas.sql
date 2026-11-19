-- Estadísticas de Avisos y eventos: aperturas (con sesión y de visitantes sin cuenta, sin guardar su identidad),
-- descargas de cada archivo y personas autorizadas a consultarlas además de admin, Subdirección Académica y coordinadores. Repetible.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS aviso_visitas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo ENUM('comunicado','evento') NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  visitante CHAR(16) CHARACTER SET ascii NULL,
  perfil VARCHAR(20) NOT NULL DEFAULT 'visitante',
  programa VARCHAR(12) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visita_item (tipo, item_id),
  KEY idx_visita_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS aviso_descargas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  comunicado_id INT UNSIGNED NOT NULL,
  adjunto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  visitante CHAR(16) CHARACTER SET ascii NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_descarga_aviso (comunicado_id, adjunto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS estadisticas_usuarios (
  usuario_id INT UNSIGNED NOT NULL,
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_estad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
