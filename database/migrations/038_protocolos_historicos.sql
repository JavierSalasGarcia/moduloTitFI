-- Protocolos aprobados del sistema de Titulación anterior (respaldo del 24-03-2021) con todos sus revisores, para generar en lote
-- las constancias de «revisor del protocolo» desde Constancias → Captura de registros externos. Un registro por alumno (si el tema
-- fue de dos, ambos) con los revisores que dictaminaron en cualquier vuelta (hasta 3 en columnas; el resto en revisores_mas).
-- Solo carga los registros: no genera constancias. Repetible: no duplica (misma licenciatura, cuenta y fecha de aprobación).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico_protocolos ADD COLUMN revisores_mas TEXT NULL AFTER revisor3_email', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico_protocolos' AND COLUMN_NAME = 'revisores_mas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/038_protocolos_historicos.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
