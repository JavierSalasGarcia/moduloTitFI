-- Encuesta privada SF/1500/2026: programación estimada de viajes a competencias internacionales en 2027.
-- La contestan profesores mediante un enlace no listado, aunque no tengan cuenta. Los registros los consultan
-- administración, Dirección y Subdirección Académica. Repetible y sin borrar respuestas.

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_formularios ADD COLUMN requiere_rol VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER acceso', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_formularios' AND COLUMN_NAME = 'requiere_rol');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

INSERT INTO frm_formularios
  (slug, titulo, agrupar_por, varios_por_correo, estado, cierra_en, acceso, requiere_rol, descripcion, campos)
VALUES
('programacion-viajes-internacionales-2027-sf1500-q7m4k9x2',
 'Programación estimada de viajes a competencias internacionales · 2027',
 'pais', 1, 'abierto', '2026-11-06 13:00:00', 'director,subdirector', NULL,
 'En atención al oficio SF/1500/2026 de la Secretaría de Finanzas, registra los viajes contemplados para que alumnas y alumnos participen en competencias internacionales durante 2027.

Haz un envío por cada viaje o competencia. Las fechas, la duración y las cantidades pueden ser estimadas. Si contemplas varios viajes, al terminar usa nuevamente el mismo enlace. Fecha límite: 6 de noviembre de 2026.

La información sólo podrá ser consultada por la administración de TRAZAFI, la Dirección y la Subdirección Académica. No necesitas una cuenta para responder; te recomendamos registrarte o iniciar sesión para que TRAZAFI complete automáticamente tu nombre y correo.',
'[{"k":"conf_profesor","label":"Declaro que soy profesor(a) y que la información corresponde a viajes académicos contemplados por la Facultad.","tipo":"confirmar","req":true},
{"k":"nombre","label":"Profesor(a) responsable","tipo":"texto","req":true,"max":160,"de_cuenta":"nombre"},
{"k":"email","label":"Correo institucional","tipo":"email","req":true,"max":160,"de_cuenta":"email"},
{"k":"competencia","label":"Nombre de la competencia internacional","tipo":"texto","req":true,"max":250,"ayuda":"Nombre oficial o provisional"},
{"k":"ciudad","label":"Ciudad de destino","tipo":"texto","req":true,"max":140},
{"k":"pais","label":"País de destino","tipo":"texto","req":true,"max":140,"mayusculas":true},
{"k":"fecha_salida","label":"Fecha estimada de salida","tipo":"fecha","req":true,"min":"2027-01-01","max_fecha":"2027-12-31"},
{"k":"fecha_regreso","label":"Fecha estimada de regreso","tipo":"fecha","req":true,"min":"2027-01-01","max_fecha":"2027-12-31","no_antes_de":"fecha_salida","no_antes_label":"Fecha estimada de salida"},
{"k":"duracion_dias","label":"Duración estimada del viaje (días)","tipo":"numero","req":true,"min":1,"max_num":366},
{"k":"alumnos","label":"Número aproximado de alumnos participantes","tipo":"numero","req":true,"min":1,"max_num":10000},
{"k":"profesores","label":"Número aproximado de profesores participantes","tipo":"numero","req":true,"min":0,"max_num":1000},
{"k":"edad_min","label":"Edad mínima estimada de participantes","tipo":"numero","req":true,"min":1,"max_num":100},
{"k":"edad_max","label":"Edad máxima estimada de participantes","tipo":"numero","req":true,"min":1,"max_num":100,"no_antes_de":"edad_min","no_antes_label":"Edad mínima estimada"},
{"k":"vehiculos_cantidad","label":"Cantidad de vehículos institucionales requeridos","tipo":"numero","req":true,"min":0,"max_num":100},
{"k":"vehiculos_tipo","label":"Tipo de vehículos institucionales requeridos","tipo":"textarea","req":true,"max":800,"ayuda":"Ej. 1 autobús de 40 plazas y 1 camioneta"},
{"k":"observaciones","label":"Observaciones o supuestos de la estimación (opcional)","tipo":"textarea","req":false,"max":1500}]')
ON DUPLICATE KEY UPDATE
  titulo = VALUES(titulo), agrupar_por = VALUES(agrupar_por), varios_por_correo = VALUES(varios_por_correo),
  descripcion = VALUES(descripcion), campos = VALUES(campos), requiere_rol = VALUES(requiere_rol);

-- Se conserva el estado. Este cambio sí fija el cierre solicitado y desactiva el requisito de cuenta.
UPDATE frm_formularios
SET cierra_en = '2026-11-06 13:00:00', requiere_rol = NULL
WHERE slug = 'programacion-viajes-internacionales-2027-sf1500-q7m4k9x2';

UPDATE frm_formularios
SET acceso = 'director,subdirector'
WHERE slug = 'programacion-viajes-internacionales-2027-sf1500-q7m4k9x2' AND (acceso IS NULL OR acceso = '');
