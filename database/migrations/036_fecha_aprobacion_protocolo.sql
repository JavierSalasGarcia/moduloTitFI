-- Historial de constancias: fecha de aprobación del protocolo (último dictamen favorable de la Comisión de Evaluación), tomada del
-- sistema de Titulación anterior (respaldo del 24-03-2021: tablas timaecep y timaedef). Permite filtrar y ordenar el historial por esa
-- fecha y elegir fechas de examen a partir de ella. Repetible: nunca pisa una fecha ya capturada.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN fecha_protocolo DATE NULL AFTER fecha_examen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'fecha_protocolo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

DROP TEMPORARY TABLE IF EXISTS tmp_fecha_protocolo;
CREATE TEMPORARY TABLE tmp_fecha_protocolo (
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cuenta VARCHAR(12) NOT NULL,
  fecha_examen DATE NOT NULL,
  fecha_protocolo DATE NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): aquí se cargaban datos personales (números de cuenta y fechas de examen).
-- El repositorio conserva solo la estructura. La carga original está, fuera del control de versiones, en
-- database/datos_privados/migraciones/036_fecha_aprobacion_protocolo.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.

-- 1) misma licenciatura, cuenta y fecha de examen
UPDATE tit_historico h JOIN tmp_fecha_protocolo t ON t.carrera = h.carrera AND t.cuenta = h.cuenta AND t.fecha_examen = h.fecha_examen
   SET h.fecha_protocolo = t.fecha_protocolo WHERE h.fecha_protocolo IS NULL;
-- 2) si la fecha del libro difiere: misma licenciatura y cuenta, cuando son únicas en ambos lados
UPDATE tit_historico h
  JOIN (SELECT carrera, cuenta, MIN(fecha_protocolo) fecha_protocolo FROM tmp_fecha_protocolo GROUP BY carrera, cuenta HAVING COUNT(*) = 1) t ON t.carrera = h.carrera AND t.cuenta = h.cuenta
  JOIN (SELECT carrera, cuenta FROM tit_historico WHERE cuenta <> '' GROUP BY carrera, cuenta HAVING COUNT(*) = 1) u ON u.carrera = h.carrera AND u.cuenta = h.cuenta
   SET h.fecha_protocolo = t.fecha_protocolo WHERE h.fecha_protocolo IS NULL;
DROP TEMPORARY TABLE IF EXISTS tmp_fecha_protocolo;
-- 3) una aprobación posterior al examen es imposible: cruce dudoso, se descarta
UPDATE tit_historico SET fecha_protocolo = NULL WHERE fecha_protocolo > fecha_examen;
