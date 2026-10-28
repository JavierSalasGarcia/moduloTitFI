-- Formularios con evidencias (archivo .zip o enlace), varios registros por correo y Excel con el formato de quien
-- lo solicita. Primer uso: Memorándum DEP/06/2026 (alumnado, personas egresadas y profesorado galardonados en 2026).
-- Repetible: solo crea o cambia lo que falta y no borra datos.

SET NAMES utf8mb4;

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
-- Al volver a importar se actualizan el texto, los campos y el formato del Excel; el estado, el cierre y el acceso
-- se conservan como los haya dejado la administración.
INSERT INTO frm_formularios (slug, titulo, agrupar_por, varios_por_correo, estado, cierra_en, acceso, descripcion, campos, excel) VALUES
('galardonados-2026', '¿Ganaste un premio en 2026?', 'tipo_persona', 1, 'abierto', '2026-12-31 23:59:59', 'subdirector',
'Si eres alumna, alumno, egresada/o o profesor/a de la FI y en 2026 ganaste un premio, lugar o distinción internacional, nacional o estatal otorgado por una institución externa a la UAEMEX, regístralo: la Facultad lo dará a conocer en sus redes sociales y aparecerá en el Informe de Actividades de la Rectora.

Haz un registro por cada premio. Ten a la mano tu constancia o diploma (y una foto con tu reconocimiento, si la tienes) en un .zip o en una carpeta de Drive. Fecha límite: 12 de noviembre; si recibes un premio después, regístralo hasta el 31 de diciembre.',
'[{"k":"tipo_persona","label":"Eres","tipo":"select","req":true,"opciones":{"alumno":"Alumno/a","egresado":"Egresado/a","profesor":"Profesor/a"}},
{"k":"nombre","label":"Nombre completo","tipo":"texto","req":true,"max":160,"mayusculas":true,"de_cuenta":"nombre","ayuda":"Nombre(s) Apellido paterno Apellido materno"},
{"k":"email","label":"Correo electrónico","tipo":"email","req":true,"max":160,"de_cuenta":"email"},
{"k":"espacio","label":"Espacio académico","tipo":"fijo","valor":"FACULTAD DE INGENIERÍA"},
{"k":"programa","label":"Programa educativo","tipo":"texto","req":true,"max":250,"ayuda":"Profesores: en los que impartes clase"},
{"k":"caracter","label":"Alcance del premio","tipo":"select","req":true,"opciones":{"internacional":"Internacional","nacional":"Nacional","estatal":"Estatal"}},
{"k":"evento","label":"Nombre oficial del evento o reconocimiento","tipo":"texto","req":true,"max":250},
{"k":"organizador","label":"Quién lo otorga (externo a la UAEMEX)","tipo":"texto","req":true,"max":250},
{"k":"resultado","label":"Lugar o premio obtenido","tipo":"texto","req":true,"max":250},
{"k":"fecha","label":"Fecha","tipo":"fecha","req":true,"min":"2026-01-01","max_fecha":"2026-12-31"},
{"k":"notas","label":"Notas (opcional)","tipo":"texto","req":false,"max":500},
{"k":"evidencia","label":"Constancia o diploma (y foto, si la tienes)","tipo":"evidencia","req":true,"max_mb":25},
{"k":"conf_a","label":"Lo otorgó una institución externa a la UAEMEX en 2026.","tipo":"confirmar","req":true},
{"k":"redes","label":"Autorizo que la Facultad publique mi nombre, mi premio y mi foto en sus redes sociales.","tipo":"confirmar","req":false,"mostrar":true}]',
'[{"hoja":"Alumnado y egresados","titulo":"Alumnado y personas egresadas de licenciatura ganadores en certámenes del conocimiento 2026","filtro":{"tipo_persona":["alumno","egresado"]},
  "secciones":{"campo":"caracter","titulos":{"internacional":"Certámenes internacionales","nacional":"Certámenes nacionales","estatal":"Certámenes estatales"}},
  "columnas":[["Nombre[s] de alumnos / egresados (nombre[s], apellido paterno y apellido materno)","nombre"],["Precisar si es alumno/a o egresado/a","tipo_persona"],["Espacio académico","espacio"],["Nombre oficial del Evento","evento"],["Organizadores (externos a la UAEMex)","organizador"],["Lugar o premio obtenido","resultado"],["Programa educativo","programa"],["Fecha","fecha"],["Correo electrónico","email"],["Evidencia","evidencia"],["Notas","notas"],["Autoriza publicar en redes","redes"]]},
 {"hoja":"Profesorado","titulo":"Personal académico galardonada o galardonado 2026","filtro":{"tipo_persona":["profesor"]},
  "secciones":{"campo":"caracter","titulos":{"internacional":"Galardones internacionales","nacional":"Galardones nacionales","estatal":"Galardones estatales"}},
  "columnas":[["Nombre[s] de profesores (nombre[s], apellido paterno y apellido materno)","nombre"],["Espacio académico","espacio"],["Programa(s) educativo(s) en que imparte docencia","programa"],["Concepto (premio, reconocimiento o distinción que obtuvo)",["evento","resultado"]],["Institución que lo otorga (externa a la UAEMex)","organizador"],["Fecha","fecha"],["Correo electrónico","email"],["Evidencia","evidencia"],["Notas","notas"],["Autoriza publicar en redes","redes"]]}]')
ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), agrupar_por = VALUES(agrupar_por), varios_por_correo = VALUES(varios_por_correo),
  descripcion = VALUES(descripcion), campos = VALUES(campos), excel = VALUES(excel);
