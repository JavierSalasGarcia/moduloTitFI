-- Titulación de licenciatura y evaluación de grado de posgrado: el pasante (o el egresado) puede dejar un trámite que
-- inició por error. Si nadie más intervino en él se borra; si hubo intervención de otras personas pasa al estado nuevo
-- «abandonado» (en pausa): no se borra nada y puede retomarse exactamente donde se quedó.
-- 'abandonado' es distinto de 'cancelado' (que conservan los trámites cancelados con la versión anterior).
-- Solo estructura: ningún dato de personas. Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

-- tit_tramites.estado: se añade 'abandonado'.
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE tit_tramites MODIFY COLUMN estado ENUM(''abierto'',''concluido'',''cancelado'',''abandonado'') NOT NULL DEFAULT ''abierto''', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_tramites' AND COLUMN_NAME = 'estado' AND COLUMN_TYPE NOT LIKE '%abandonado%');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- tit_tramites.abandonado_en: cuándo lo dejó el pasante la última vez (NULL si está activo o nunca se dejó).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_tramites ADD COLUMN abandonado_en DATETIME NULL AFTER estado', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_tramites' AND COLUMN_NAME = 'abandonado_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- tit_tramites.abandonos: cuántas veces se ha dejado en pausa (para el personal; no se muestra como conteo al pasante).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_tramites ADD COLUMN abandonos INT UNSIGNED NOT NULL DEFAULT 0 AFTER abandonado_en', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_tramites' AND COLUMN_NAME = 'abandonos');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- pos_tramites.estado: se añade 'abandonado'.
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE pos_tramites MODIFY COLUMN estado ENUM(''abierto'',''concluido'',''cancelado'',''abandonado'') NOT NULL DEFAULT ''abierto''', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_tramites' AND COLUMN_NAME = 'estado' AND COLUMN_TYPE NOT LIKE '%abandonado%');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE pos_tramites ADD COLUMN abandonado_en DATETIME NULL AFTER estado', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_tramites' AND COLUMN_NAME = 'abandonado_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE pos_tramites ADD COLUMN abandonos INT UNSIGNED NOT NULL DEFAULT 0 AFTER abandonado_en', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_tramites' AND COLUMN_NAME = 'abandonos');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
