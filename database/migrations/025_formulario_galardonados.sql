-- Formularios con evidencias (archivo .zip o enlace), varios registros por correo y Excel con el formato de quien
-- lo solicita. Primer uso: Memorándum DEP/06/2026 (alumnado, personas egresadas y profesorado galardonados en 2026).
-- Repetible: solo crea o cambia lo que falta y no borra datos.

-- 1 = una misma persona (correo) puede enviar varios registros, por ejemplo uno por reconocimiento.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_formularios ADD COLUMN varios_por_correo TINYINT(1) NOT NULL DEFAULT 0 AFTER agrupar_por', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_formularios' AND COLUMN_NAME = 'varios_por_correo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Formato del Excel (JSON): hojas filtradas por un campo, secciones por otro y columnas con las etiquetas del anexo.
-- NULL = el Excel genérico (una hoja por grupo con todas las columnas).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_formularios ADD COLUMN excel MEDIUMTEXT NULL AFTER varios_por_correo', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_formularios' AND COLUMN_NAME = 'excel');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Cuenta de Traza FI con la que se envió el registro (NULL = sin sesión).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_respuestas ADD COLUMN usuario_id INT UNSIGNED NULL AFTER datos', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_respuestas' AND COLUMN_NAME = 'usuario_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- El correo deja de ser único por formulario: la regla de un registro por correo la aplica la aplicación cuando
-- varios_por_correo = 0. Se crea primero el índice simple para que la llave foránea siga teniendo uno.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_respuestas ADD KEY idx_frm_email (formulario_id, email)', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_respuestas' AND INDEX_NAME = 'idx_frm_email');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE frm_respuestas DROP INDEX uq_frm_email', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_respuestas' AND INDEX_NAME = 'uq_frm_email');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Memorándum DEP/06/2026: galardonados 2026 para el Segundo Informe de Actividades de la Rectora. Cada persona
-- registra su propio reconocimiento (uno por envío); lo consulta la Subdirección Académica; abierto hasta el 31-dic.
INSERT IGNORE INTO frm_formularios (slug, titulo, agrupar_por, varios_por_correo, estado, cierra_en, acceso, descripcion, campos, excel) VALUES
('galardonados-2026', 'Alumnado, personas egresadas y profesorado galardonados en 2026', 'tipo_persona', 1, 'abierto', '2026-12-31 23:59:59', 'subdirector',
'La Dirección de Estudios Profesionales de la UAEMEX (Memorándum DEP/06/2026) solicita la relación del alumnado y de las personas egresadas de licenciatura que durante 2026 hayan resultado ganadores en certámenes del conocimiento, y del personal académico que haya obtenido reconocimientos o galardones, de carácter internacional, nacional o estatal, otorgados por instancias externas a la UAEMEX. La información se integrará al texto y anexo estadístico del Segundo Informe de Actividades de la Rectora.

Si eres alumna, alumno, persona egresada o profesor(a) de la Facultad de Ingeniería y obtuviste alguno de estos reconocimientos en 2026, regístralo aquí. Haz un registro por cada reconocimiento. La fecha de entrega a la DEP es el 12 de noviembre de 2026; si obtienes un reconocimiento después, regístralo en cuanto lo tengas (hasta el 31 de diciembre de 2026).

La evidencia debe incluir la documentación probatoria debidamente oficializada (constancia, diploma o reconocimiento) y, si la tienes, una fotografía tuya con el galardón para su posible incorporación al informe. Súbela en un solo archivo .zip o compártela como un enlace a una carpeta (Google Drive, OneDrive u otro) visible para cualquier persona con el enlace.',
'[{"k":"tipo_persona","label":"Eres","tipo":"select","req":true,"opciones":{"alumno":"Alumno/a","egresado":"Egresado/a","profesor":"Personal académico (profesor/a)"}},
{"k":"nombre","label":"Nombre completo (nombre[s], apellido paterno y apellido materno)","tipo":"texto","req":true,"max":160,"mayusculas":true,"de_cuenta":"nombre"},
{"k":"email","label":"Correo electrónico","tipo":"email","req":true,"max":160,"de_cuenta":"email"},
{"k":"espacio","label":"Espacio académico","tipo":"texto","req":true,"max":160,"mayusculas":true,"defecto":"FACULTAD DE INGENIERÍA"},
{"k":"programa","label":"Programa educativo (alumnado: el que cursas o cursaste; profesorado: los programas en que impartes docencia)","tipo":"texto","req":true,"max":250},
{"k":"caracter","label":"Carácter del certamen o reconocimiento","tipo":"select","req":true,"opciones":{"internacional":"Internacional","nacional":"Nacional","estatal":"Estatal"}},
{"k":"evento","label":"Nombre oficial del evento, certamen o reconocimiento","tipo":"texto","req":true,"max":250},
{"k":"organizador","label":"Organizadores o institución que lo otorga (externa a la UAEMEX)","tipo":"texto","req":true,"max":250},
{"k":"resultado","label":"Lugar, premio o distinción obtenida","tipo":"texto","req":true,"max":250},
{"k":"fecha","label":"Fecha en que lo obtuviste","tipo":"fecha","req":true,"min":"2026-01-01","max_fecha":"2026-12-31"},
{"k":"notas","label":"Notas para interpretar correctamente los datos (opcional)","tipo":"texto","req":false,"max":500},
{"k":"evidencia","label":"Evidencia","tipo":"evidencia","req":true,"max_mb":25},
{"k":"conf_a","label":"La evidencia incluye la documentación probatoria oficializada (constancia, diploma o reconocimiento) del galardón que registro.","tipo":"confirmar","req":true},
{"k":"conf_b","label":"El reconocimiento lo otorgó una instancia externa a la UAEMEX y lo obtuve durante 2026.","tipo":"confirmar","req":true}]',
'[{"hoja":"Alumnado y egresados","titulo":"Alumnado y personas egresadas de licenciatura ganadores en certámenes del conocimiento 2026","filtro":{"tipo_persona":["alumno","egresado"]},
  "secciones":{"campo":"caracter","titulos":{"internacional":"Certámenes internacionales","nacional":"Certámenes nacionales","estatal":"Certámenes estatales"}},
  "columnas":[["Nombre[s] de alumnos / egresados (nombre[s], apellido paterno y apellido materno)","nombre"],["Precisar si es alumno/a o egresado/a","tipo_persona"],["Espacio académico","espacio"],["Nombre oficial del Evento","evento"],["Organizadores (externos a la UAEMex)","organizador"],["Lugar o premio obtenido","resultado"],["Programa educativo","programa"],["Fecha","fecha"],["Correo electrónico","email"],["Evidencia","evidencia"],["Notas","notas"]]},
 {"hoja":"Profesorado","titulo":"Personal académico galardonada o galardonado 2026","filtro":{"tipo_persona":["profesor"]},
  "secciones":{"campo":"caracter","titulos":{"internacional":"Galardones internacionales","nacional":"Galardones nacionales","estatal":"Galardones estatales"}},
  "columnas":[["Nombre[s] de profesores (nombre[s], apellido paterno y apellido materno)","nombre"],["Espacio académico","espacio"],["Programa(s) educativo(s) en que imparte docencia","programa"],["Concepto (premio, reconocimiento o distinción que obtuvo)",["evento","resultado"]],["Institución que lo otorga (externa a la UAEMex)","organizador"],["Fecha","fecha"],["Correo electrónico","email"],["Evidencia","evidencia"],["Notas","notas"]]}]');
