-- Notificaciones push: un registro por dispositivo (navegador o app instalada) que la persona activó en «Mi perfil». Repetible.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS push_suscripciones (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  endpoint varchar(700) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  p256dh varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  auth varchar(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  agente varchar(200) NOT NULL DEFAULT '',
  fallos smallint unsigned NOT NULL DEFAULT 0,
  ultimo_envio datetime NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_endpoint (endpoint),
  KEY idx_push_usuario (usuario_id),
  CONSTRAINT fk_push_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
