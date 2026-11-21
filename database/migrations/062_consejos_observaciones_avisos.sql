-- Consejos: observación de la Subdirección por caso (sale como comentario de Word en el acta) y registro del aviso por correo
-- de cada oficio. Repetible: solo agrega lo que falta.
SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE consejos_asuntos ADD COLUMN observacion TEXT NULL AFTER nota_gobierno', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consejos_asuntos' AND COLUMN_NAME = 'observacion');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE consejos_asuntos ADD COLUMN observacion_autor VARCHAR(120) NULL AFTER observacion', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consejos_asuntos' AND COLUMN_NAME = 'observacion_autor');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE consejos_oficios ADD COLUMN avisado_en DATETIME NULL AFTER estado', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consejos_oficios' AND COLUMN_NAME = 'avisado_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
