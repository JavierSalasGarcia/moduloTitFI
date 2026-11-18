-- Enlace corto y no consecutivo para cada aviso (/aviso/{token}): evita que cambiando el número se lleguen a ver otros avisos. Repetible.
SET NAMES utf8mb4;
SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE comunicados ADD COLUMN token CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL AFTER id, ADD UNIQUE KEY uq_comunicado_token (token)", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
-- Los avisos sin token lo reciben la primera vez que se muestran (AvisoController::token).
