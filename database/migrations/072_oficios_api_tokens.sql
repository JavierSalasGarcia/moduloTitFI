-- Tokens para subir oficios al archivo de «Oficios enviados» desde una sesión de VS Code (POST /api/oficios).
-- Solo se guarda la huella SHA-256 del token; el token en claro se muestra una única vez al crearlo.
CREATE TABLE IF NOT EXISTS oficios_api_tokens (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  nombre varchar(80) NOT NULL,
  token_hash char(64) NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  vence_en date NOT NULL,
  ultimo_uso timestamp NULL DEFAULT NULL,
  revocado_en timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_oficios_token (token_hash),
  KEY idx_oficios_token_usuario (usuario_id),
  CONSTRAINT fk_oficios_token_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
