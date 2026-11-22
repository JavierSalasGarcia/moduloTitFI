-- Consejos: solicitudes que redactan los profesores y coordinadores. Por acuerdo del H. Consejo de Gobierno (caso AG28 de la
-- reunión de noviembre de 2026) toda solicitud lleva el Vo. Bo. o de enterado de la Coordinación de Docencia o de la de Estudios
-- Avanzados; con él, la solicitud pasa a la siguiente reunión. Repetible: solo agrega lo que falta.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consejos_solicitudes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(24) NOT NULL DEFAULT '',
  solicitante_id INT UNSIGNED NOT NULL,
  grupo ENUM('profesor','alumno','general') NOT NULL,
  plantilla VARCHAR(40) NULL,
  solicitud_html MEDIUMTEXT NOT NULL,
  antecedentes_html MEDIUMTEXT NULL,
  profesores VARCHAR(500) NULL,                 -- ids de profesores mencionados, separados por coma
  coordinador_id INT UNSIGNED NOT NULL,         -- quien da el Vo. Bo. o el enterado
  coordinacion VARCHAR(200) NOT NULL,           -- «Coordinación de Docencia · …» / «Coordinación de Estudios Avanzados · …»
  estado ENUM('borrador','enviada','visto_bueno','devuelta','incorporada','cancelada') NOT NULL DEFAULT 'borrador',
  vobo_tipo ENUM('vobo','enterado') NULL,
  vobo_por INT UNSIGNED NULL,
  vobo_en DATETIME NULL,
  comentario VARCHAR(1000) NULL,                -- motivo de la devolución
  enviado_en DATETIME NULL,
  modo_solicitante VARCHAR(12) NULL,            -- modo de firma que regía al enviar: autografa | interna
  modo_vobo VARCHAR(12) NULL,
  oficio_ruta VARCHAR(255) NULL,                -- PDF membretado vigente (con los sellos internos, si los hay)
  escaneado_ruta VARCHAR(255) NULL,             -- oficio impreso con Vo. Bo. y firma autógrafa, escaneado
  escaneado_en DATETIME NULL,
  escaneado_por INT UNSIGNED NULL,
  reunion_id INT UNSIGNED NULL,
  asunto_id INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cons_sol_solicitante (solicitante_id, estado),
  KEY idx_cons_sol_coordinador (coordinador_id, estado),
  KEY idx_cons_sol_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE consejos_asuntos ADD COLUMN solicitud_id INT UNSIGNED NULL AFTER origen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consejos_asuntos' AND COLUMN_NAME = 'solicitud_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
