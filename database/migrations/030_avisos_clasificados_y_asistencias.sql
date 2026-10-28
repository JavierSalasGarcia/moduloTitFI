-- Avisos y eventos clasificables (Difusión Cultural): público, carreras, semestres, tema, modalidad y enlaces; preferencias
-- de cada persona en su perfil; bitácora de evidencias por evento y asistencias de alumnos (QR, palabra clave o
-- constancia) con puntos y folio verificable, para el expediente de formación integral y los organismos acreditadores.
-- Repetible: solo crea o cambia lo que falta y no borra datos.

SET NAMES utf8mb4;

-- Formato «convocatoria» (avisos con fecha límite que no son un evento con hora, como la de galardonados).
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE eventos MODIFY COLUMN categoria ENUM(''reunion'',''conferencia'',''taller'',''curso'',''academico'',''cultural'',''deportivo'',''convocatoria'',''otro'') NOT NULL DEFAULT ''otro''', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'categoria' AND COLUMN_TYPE NOT LIKE '%convocatoria%');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Atributos de clasificación (ver docs/avisos_clasificacion_ia.md). publico: lista separada por comas de alumno, egresado
-- y profesor (vacío = toda la comunidad). Semestres vacíos = todos. puntos y horas cuentan solo con asistencia validada.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN tema VARCHAR(30) NOT NULL DEFAULT ''otro'' AFTER categoria', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'tema');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN publico VARCHAR(60) NOT NULL DEFAULT '''' AFTER tema', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'publico');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN semestre_min TINYINT UNSIGNED NULL AFTER publico', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'semestre_min');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN semestre_max TINYINT UNSIGNED NULL AFTER semestre_min', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'semestre_max');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN modalidad ENUM(''presencial'',''en_linea'',''hibrida'') NULL AFTER lugar', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'modalidad');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN organizador VARCHAR(200) NULL AFTER modalidad', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'organizador');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN enlace_evento VARCHAR(500) NULL AFTER organizador', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'enlace_evento');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN requiere_registro TINYINT(1) NOT NULL DEFAULT 0 AFTER enlace_evento', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'requiere_registro');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN fecha_limite DATETIME NULL AFTER requiere_registro', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'fecha_limite');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN post_url VARCHAR(500) NULL AFTER fecha_limite', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'post_url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN post_imagen VARCHAR(255) NULL AFTER post_url', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'post_imagen');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN grabacion_url VARCHAR(500) NULL AFTER post_imagen', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'grabacion_url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN formacion_integral TINYINT(1) NOT NULL DEFAULT 0 AFTER grabacion_url', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'formacion_integral');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN horas DECIMAL(4,1) NULL AFTER formacion_integral', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'horas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN puntos SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER horas', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'puntos');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN clave_asistencia VARCHAR(30) NULL AFTER puntos', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'clave_asistencia');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN qr_token CHAR(32) NULL AFTER clave_asistencia', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'qr_token');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN actualizado_en DATETIME NULL AFTER creado_en', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'actualizado_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Carreras a las que va dirigido el aviso (sin filas = todas).
CREATE TABLE IF NOT EXISTS evento_programas (
  evento_id INT UNSIGNED NOT NULL,
  programa_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (evento_id, programa_id),
  KEY idx_evento_programas_programa (programa_id),
  CONSTRAINT fk_evento_programas_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Preferencias de avisos de cada persona: semestre que declara (y el periodo en que lo confirmó) y temas que le interesan.
CREATE TABLE IF NOT EXISTS aviso_preferencias (
  usuario_id INT UNSIGNED NOT NULL,
  semestre TINYINT UNSIGNED NULL,
  periodo CHAR(5) NULL,
  temas VARCHAR(400) NOT NULL DEFAULT '',
  notificar TINYINT(1) NOT NULL DEFAULT 1,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_aviso_preferencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Bitácora de evidencias del evento (fotos, listas de asistencia, grabación…), general o de una carrera.
CREATE TABLE IF NOT EXISTS evento_evidencias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'otro',
  archivo VARCHAR(255) NULL,
  nombre VARCHAR(200) NULL,
  url VARCHAR(500) NULL,
  descripcion VARCHAR(300) NULL,
  programa_id SMALLINT UNSIGNED NULL,
  autor_id INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_evento_evidencias_evento (evento_id),
  CONSTRAINT fk_evento_evidencias_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Asistencia de cada persona a un evento. QR o palabra clave: validada al instante; constancia o capturas: pendiente
-- hasta que la revisa Difusión Cultural. La carrera y el semestre se guardan como estaban al registrar.
CREATE TABLE IF NOT EXISTS evento_asistencias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  programa_id SMALLINT UNSIGNED NULL,
  semestre TINYINT UNSIGNED NULL,
  metodo ENUM('qr','clave','constancia') NOT NULL,
  archivos TEXT NULL,
  estado ENUM('validada','pendiente','rechazada') NOT NULL DEFAULT 'pendiente',
  motivo VARCHAR(300) NULL,
  puntos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  folio CHAR(11) NOT NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evento_asistencia (evento_id, usuario_id),
  UNIQUE KEY uq_evento_asistencia_folio (folio),
  KEY idx_evento_asistencias_usuario (usuario_id),
  KEY idx_evento_asistencias_programa (programa_id, estado),
  CONSTRAINT fk_evento_asistencias_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  CONSTRAINT fk_evento_asistencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Avisos iniciales, publicados por la Coordinación de Difusión Cultural a nombre de la primera cuenta de administración.
-- Se insertan una sola vez (si ya existe un evento con el mismo título, no se repite).
INSERT INTO eventos (titulo, descripcion, categoria, tema, publico, semestre_min, semestre_max, depto_id, autor_id, inicio, fin, lugar, modalidad,
                     organizador, enlace_evento, requiere_registro, fecha_limite, formacion_integral, horas, puntos, clave_asistencia, qr_token)
SELECT v.titulo, v.descripcion, v.categoria, v.tema, v.publico, v.smin, v.smax, d.id, u.id, v.inicio, v.fin, v.lugar, v.modalidad,
       v.organizador, v.enlace, v.registro, v.limite, v.formacion, v.horas, v.puntos,
       IF(v.formacion = 1, UPPER(SUBSTRING(MD5(CONCAT(UUID(), RAND())), 1, 6)), NULL), LOWER(SUBSTRING(MD5(CONCAT(UUID(), RAND())), 1, 32))
  FROM (
    SELECT '¿Ganaste un premio en 2026? Regístralo' AS titulo,
           'Si eres alumna, alumno, egresada/o o profesor/a de la FI y en 2026 ganaste un premio o distinción internacional, nacional o estatal otorgado por una institución externa a la UAEMEX, regístralo en el formulario. Publicaremos tu logro en las redes de la Facultad. Fecha límite: 12 de noviembre (si lo recibes después, regístralo hasta el 31 de diciembre).' AS descripcion,
           'convocatoria' AS categoria, 'reconocimientos' AS tema, 'alumno,egresado,profesor' AS publico, NULL AS smin, NULL AS smax,
           '2026-10-28 09:00:00' AS inicio, '2026-12-31 23:59:00' AS fin, NULL AS lugar, 'en_linea' AS modalidad,
           'Facultad de Ingeniería · Memorándum DEP/06/2026' AS organizador, 'https://fingenieria.mx/f/galardonados-2026' AS enlace, 1 AS registro,
           '2026-11-12 23:59:00' AS limite, 0 AS formacion, NULL AS horas, 0 AS puntos
    UNION ALL SELECT 'Conferencia: Habilidades blandas en el proceso de formación',
           'Ciclo de conferencias de noviembre «Habilidades para tu vida profesional». Imparte Livia Berenice Templos Peralta (Bimestral A.C.). Conéctate con el enlace y registra tu asistencia en Traza FI con la palabra clave que se dará en la sesión.',
           'conferencia', 'desarrollo_profesional', 'alumno', NULL, NULL, '2026-11-03 13:00:00', '2026-11-03 14:30:00', NULL, 'en_linea',
           'Bimestral A.C. · ciclo UAEMEX', 'https://acortar.link/xGYejl', 0, NULL, 1, 1.5, 10
    UNION ALL SELECT 'Conferencia: Liderazgo',
           'Ciclo de conferencias de noviembre «Habilidades para tu vida profesional». Imparte Andrea Elizabeth García Villafaña (LABSA). Conéctate con el enlace y registra tu asistencia en Traza FI con la palabra clave que se dará en la sesión.',
           'conferencia', 'desarrollo_profesional', 'alumno', NULL, NULL, '2026-11-10 13:00:00', '2026-11-10 14:30:00', NULL, 'en_linea',
           'LABSA · ciclo UAEMEX', 'https://acortar.link/4OyxPY', 0, NULL, 1, 1.5, 10
    UNION ALL SELECT 'Conferencia: No todo es lana: encuentra lo que quieres',
           'Ciclo de conferencias de noviembre «Habilidades para tu vida profesional». Imparte Angélica Sánchez Martínez (OCC). Conéctate con el enlace y registra tu asistencia en Traza FI con la palabra clave que se dará en la sesión.',
           'conferencia', 'desarrollo_profesional', 'alumno', NULL, NULL, '2026-11-12 13:00:00', '2026-11-12 14:30:00', NULL, 'en_linea',
           'OCC · ciclo UAEMEX', 'https://acortar.link/5jck98', 0, NULL, 1, 1.5, 10
    UNION ALL SELECT 'Conferencia: Campaña de Trainees 2027 Avante Textil',
           'Ciclo de conferencias de noviembre «Habilidades para tu vida profesional». Daniel de Jesús Segura Cruz (Avante Textil) presenta su programa de trainees 2027, pensado para quienes están por terminar la carrera. Conéctate con el enlace y registra tu asistencia en Traza FI con la palabra clave que se dará en la sesión.',
           'conferencia', 'desarrollo_profesional', 'alumno,egresado', 7, 10, '2026-11-17 13:00:00', '2026-11-17 14:30:00', NULL, 'en_linea',
           'Avante Textil · ciclo UAEMEX', 'https://acortar.link/ytsWn6', 0, NULL, 1, 1.5, 10
  ) v
  JOIN departamentos d ON d.clave = 'coordinacion_de_difusion_cultural'
  JOIN (SELECT MIN(id) AS id FROM usuarios WHERE rol = 'admin' AND activo = 1) u ON u.id IS NOT NULL
 WHERE NOT EXISTS (SELECT 1 FROM eventos e WHERE e.titulo = v.titulo);
