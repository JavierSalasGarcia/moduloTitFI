-- Archivos de un aviso agrupados en secciones dentro de la misma página (p. ej. calendarios de Licenciatura y de Posgrado). Repetible.
SET NAMES utf8mb4;
SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE comunicado_adjuntos ADD COLUMN grupo VARCHAR(60) NOT NULL DEFAULT '' AFTER comunicado_id", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicado_adjuntos' AND COLUMN_NAME = 'grupo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
