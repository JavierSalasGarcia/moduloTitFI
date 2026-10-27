-- Historial de constancias: de dónde salió el título del trabajo y, si cambió, cuál fue el del protocolo. Repetible.
--  titulo_origen: '' (capturado a mano o del libro), 'manual', 'sistema_anterior', 'revision' (título del trabajo escrito en la solicitud
--  de revisión de la plataforma, el definitivo) o 'protocolo' (solo se encontró el del protocolo: puede diferir del definitivo).
--  titulo_protocolo: título que tenía el protocolo cuando es distinto del definitivo (informativo).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN titulo_origen VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''' AFTER titulo, ADD COLUMN titulo_protocolo VARCHAR(500) NOT NULL DEFAULT '''' AFTER titulo_origen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'titulo_origen');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Los títulos que trajo la importación del sistema de Titulación anterior (migración 025).
UPDATE tit_historico SET titulo_origen = 'sistema_anterior' WHERE hoja = 'SISTEMA ANTERIOR' AND titulo <> '' AND titulo_origen = '';
