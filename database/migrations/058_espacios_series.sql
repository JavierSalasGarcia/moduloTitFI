-- Auditorios y salas: reservas periódicas (clases, cursos). Una serie agrupa las sesiones semanales de un mismo evento;
-- cada sesión sigue siendo una reserva propia (se puede cancelar sola, desde una fecha en adelante o completa). Repetible.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS esp_series (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  espacio_id INT UNSIGNED NOT NULL,
  dias VARCHAR(20) NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  responsable VARCHAR(160) NOT NULL,
  responsable_email VARCHAR(120) NULL,
  registrado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_esp_serie_espacio FOREIGN KEY (espacio_id) REFERENCES esp_espacios(id),
  CONSTRAINT fk_esp_serie_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE esp_reservas ADD COLUMN serie_id BIGINT UNSIGNED NULL AFTER espacio_id, ADD KEY idx_esp_res_serie (serie_id, fecha)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'esp_reservas' AND COLUMN_NAME = 'serie_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
