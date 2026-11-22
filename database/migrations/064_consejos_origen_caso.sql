-- Consejos: los casos de una reunión son los mismos para el Consejo Académico y para el de Gobierno. «origen» dice ante qué Consejo se
-- presentó por primera vez: los casos creados cuando el Académico ya terminó (p. ej. asuntos generales nuevos) nacen en el de Gobierno y no
-- salen en el acta ni en la sesión del Académico. Repetible: solo agrega lo que falta.
SET NAMES utf8mb4;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE consejos_asuntos ADD COLUMN origen ENUM(''academico'',''gobierno'') NOT NULL DEFAULT ''academico'' AFTER numero', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consejos_asuntos' AND COLUMN_NAME = 'origen');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Casos que ya traen acuerdo del Consejo de Gobierno pero nunca tuvieron decisión ni acuerdo del Académico: se presentaron en el de Gobierno.
UPDATE consejos_asuntos SET origen = 'gobierno'
 WHERE origen = 'academico' AND aval_academico = 'pendiente' AND COALESCE(acuerdo_academico, '') = '' AND COALESCE(acuerdo_gobierno, '') <> '';
