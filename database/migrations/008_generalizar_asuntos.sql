-- Compatibilidad si la migración 007 anterior (tablas sa_*) ya fue aplicada.
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expedientes')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes'),
  'RENAME TABLE sa_expedientes TO asunto_expedientes', 'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_accesos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_accesos'),
  'RENAME TABLE sa_expediente_accesos TO asunto_expediente_accesos', 'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_archivos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_archivos'),
  'RENAME TABLE sa_expediente_archivos TO asunto_expediente_archivos', 'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_eventos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_eventos'),
  'RENAME TABLE sa_expediente_eventos TO asunto_expediente_eventos', 'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes')
  AND NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes' AND column_name = 'oficina'),
  "ALTER TABLE asunto_expedientes ADD COLUMN oficina VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SA' AFTER id",
  'SELECT 1'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
