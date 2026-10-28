-- Ingeniería Civil como programa educativo del catálogo (formularios, eventos, avisos, alumnos y archivo documental).
INSERT INTO programas (siglas, nombre, nivel, depto_id)
SELECT 'ICI', 'Ingeniería Civil', 'licenciatura', d.id
FROM departamentos d
WHERE d.clave = 'coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_civil'
  AND NOT EXISTS (SELECT 1 FROM programas WHERE siglas = 'ICI');
