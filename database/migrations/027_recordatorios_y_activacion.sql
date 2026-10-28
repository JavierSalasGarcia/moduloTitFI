-- Recordatorios en la bandeja de Traza FI de los eventos agregados al calendario (un día y una hora antes, y la víspera
-- de la fecha límite) y activación de cuentas existentes desde el registro (sin duplicar a la persona).
-- Repetible: solo crea lo que falta y no borra datos.

SET NAMES utf8mb4;

-- Un recordatorio por persona, evento y momento: así no se repite aunque la persona entre varias veces.
CREATE TABLE IF NOT EXISTS evento_recordatorios (
  evento_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  momento ENUM('dia','hora','limite') NOT NULL,
  enviado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (evento_id, usuario_id, momento),
  KEY idx_evento_recordatorios_usuario (usuario_id),
  CONSTRAINT fk_evento_recordatorios_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  CONSTRAINT fk_evento_recordatorios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Quien se registra con un correo que ya tiene cuenta (alta de Titulación o de la administración) la activa con un
-- código enviado a ese correo, en vez de crear otra. Aquí espera la contraseña elegida hasta que confirma el código.
CREATE TABLE IF NOT EXISTS cuenta_activaciones (
  usuario_id INT UNSIGNED NOT NULL,
  codigo_hash VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  grado VARCHAR(30) NULL,
  genero VARCHAR(20) NULL,
  expira_en DATETIME NOT NULL,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  enviado_en DATETIME NOT NULL,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_cuenta_activaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respuestas de formularios enviadas sin sesión con el correo de una cuenta existente: quedan ligadas a esa cuenta.
UPDATE frm_respuestas r JOIN usuarios u ON u.email COLLATE utf8mb4_unicode_ci = r.email SET r.usuario_id = u.id WHERE r.usuario_id IS NULL;
