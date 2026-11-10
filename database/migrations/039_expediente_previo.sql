-- Expediente de titulación en tres etapas: revisión del protocolo, examen previo y examen definitivo.
--  · tit_historico.fecha_previo / acta_previo: el examen previo del trabajo escrito (editable en el historial).
--  · tit_historico_protocolos.fecha_previo / acta_previo / historico_id: el protocolo se liga al registro de su examen (uno a uno).
-- Se cargan los exámenes previos del sistema anterior (respaldo del 24-03-2021) y se ligan los protocolos con su examen cuando coinciden
-- licenciatura, cuenta y fecha de examen; el resto se liga desde la pantalla «Registrar o completar». Repetible.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN fecha_previo DATE NULL AFTER fecha_protocolo, ADD COLUMN acta_previo INT UNSIGNED NULL AFTER fecha_previo', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'fecha_previo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico_protocolos ADD COLUMN fecha_previo DATE NULL AFTER fecha_aprobacion, ADD COLUMN acta_previo INT UNSIGNED NULL AFTER fecha_previo, ADD COLUMN historico_id BIGINT UNSIGNED NULL AFTER acta_previo, ADD UNIQUE KEY uq_histprot_historico (historico_id)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico_protocolos' AND COLUMN_NAME = 'fecha_previo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

DROP TEMPORARY TABLE IF EXISTS tmp_exp_ant;
CREATE TEMPORARY TABLE tmp_exp_ant (carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, cuenta VARCHAR(12) NOT NULL, fecha_aprobacion DATE NULL, fecha_examen DATE NULL, fecha_previo DATE NOT NULL, acta_previo INT UNSIGNED NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): aquí se cargaban datos personales (números de cuenta y fechas de examen).
-- El repositorio conserva solo la estructura. La carga original está, fuera del control de versiones, en
-- database/datos_privados/migraciones/039_expediente_previo.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.

-- Examen previo en los exámenes del historial (misma licenciatura, cuenta y fecha de examen).
UPDATE tit_historico h JOIN tmp_exp_ant t ON t.carrera = h.carrera AND t.cuenta = h.cuenta AND t.fecha_examen = h.fecha_examen
   SET h.fecha_previo = t.fecha_previo, h.acta_previo = t.acta_previo WHERE h.fecha_previo IS NULL;
-- Examen previo en los protocolos (misma licenciatura, cuenta y fecha de aprobación).
UPDATE tit_historico_protocolos p JOIN tmp_exp_ant t ON t.carrera = p.carrera AND t.cuenta = p.cuenta AND t.fecha_aprobacion = p.fecha_aprobacion
   SET p.fecha_previo = t.fecha_previo, p.acta_previo = t.acta_previo WHERE p.fecha_previo IS NULL;
-- Liga protocolo y examen cuando son inequívocos: misma licenciatura y cuenta, y el examen del sistema anterior coincide en fecha.
UPDATE IGNORE tit_historico_protocolos p
  JOIN tmp_exp_ant t ON t.carrera = p.carrera AND t.cuenta = p.cuenta AND t.fecha_aprobacion = p.fecha_aprobacion AND t.fecha_examen IS NOT NULL
  JOIN tit_historico h ON h.carrera = t.carrera AND h.cuenta = t.cuenta AND h.fecha_examen = t.fecha_examen
   SET p.historico_id = h.id WHERE p.historico_id IS NULL;
DROP TEMPORARY TABLE IF EXISTS tmp_exp_ant;
