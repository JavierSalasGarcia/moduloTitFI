-- Consejos: historial de observaciones de la coordinación y respuestas de quien solicita (varias rondas) antes de que la solicitud
-- pase a la cartera del Consejo. Repetible.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consejos_solicitud_obs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id INT UNSIGNED NOT NULL,
  ronda SMALLINT UNSIGNED NOT NULL,
  tipo ENUM('observacion','respuesta') NOT NULL,
  autor_id INT UNSIGNED NOT NULL,
  texto VARCHAR(1000) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cons_obs_solicitud (solicitud_id, id),
  CONSTRAINT fk_cons_obs_solicitud FOREIGN KEY (solicitud_id) REFERENCES consejos_solicitudes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
