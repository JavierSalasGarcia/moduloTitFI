-- Avisos y eventos en una sola lista: tipo de aviso (aviso oficial, convocatoria, exámenes), consulta pública sin sesión,
-- archivos adjuntos por aviso (p. ej. un PDF de exámenes por carrera) y registro de lo que cada persona ya leyó (globito del menú).
-- Un aviso con categoria = 'Ocultar' es un borrador: solo lo ven quienes pueden gestionarlo. Repetible.
SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE comunicados ADD COLUMN clase ENUM('aviso','convocatoria','examenes') NOT NULL DEFAULT 'aviso' AFTER categoria", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'clase');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE comunicados ADD COLUMN publico TINYINT(1) NOT NULL DEFAULT 0 AFTER clase", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'publico');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS comunicado_adjuntos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  comunicado_id int unsigned NOT NULL,
  etiqueta varchar(120) NOT NULL,
  nombre varchar(200) NOT NULL,
  ruta varchar(255) NOT NULL,
  bytes int unsigned NOT NULL DEFAULT 0,
  orden smallint NOT NULL DEFAULT 0,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_adjunto_comunicado (comunicado_id, orden),
  CONSTRAINT fk_adjunto_comunicado FOREIGN KEY (comunicado_id) REFERENCES comunicados (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS aviso_lecturas (
  usuario_id int unsigned NOT NULL,
  tipo enum('comunicado','evento') NOT NULL,
  item_id int unsigned NOT NULL,
  leido_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id, tipo, item_id),
  CONSTRAINT fk_lectura_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
