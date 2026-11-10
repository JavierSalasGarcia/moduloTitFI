-- Directorio de profesores (sin cuenta en la plataforma), importado del Excel de profesores por carrera del periodo 2026B:
-- 371 profesores distintos en 563 adscripciones a licenciaturas, propedéutico, inglés y posgrados.
-- Generado por tools/importar_profesores_xlsx.py. Repetible y sin sobrescribir: un profesor se identifica por su RFC sin homoclave y
-- aparece una sola vez aunque imparta en varios programas; al volver a ejecutarla no se pisan los nombres que el profesor corrigió.
--
--  profesores            una fila por persona (nombre con mayúscula inicial, género para «Profesor»/«Profesora», cuenta enlazada).
--  profesor_correos      correos institucionales de la persona (un correo no puede ser de dos personas).
--  profesor_unidades     catálogo de lo que se imparte: licenciaturas (con su programa), propedéutico, inglés, maestría y doctorado.
--  profesor_adscripciones  qué imparte cada profesor, por periodo (llave primaria compuesta: sin repetidos).
-- Cuando el profesor se registra con uno de sus correos y lo verifica, su cuenta se enlaza a este registro (usuario_id) en lugar de
-- crear otra persona; sus programas pasan a programa_profesores y su género y nombre se toman de aquí.

CREATE TABLE IF NOT EXISTS profesores (
  id int unsigned NOT NULL AUTO_INCREMENT,
  rfc10 char(10) NOT NULL,
  rfc varchar(13) NOT NULL,
  nombre varchar(80) NOT NULL,
  apellido1 varchar(60) NOT NULL,
  apellido2 varchar(60) DEFAULT NULL,
  grado varchar(30) DEFAULT NULL,
  genero enum('masculino','femenino') DEFAULT NULL,
  usuario_id int unsigned DEFAULT NULL,
  observaciones varchar(500) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_rfc (rfc10),
  UNIQUE KEY uq_profesor_usuario (usuario_id),
  CONSTRAINT fk_profesor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_correos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  profesor_id int unsigned NOT NULL,
  email varchar(120) NOT NULL,
  principal tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_correo (email),
  KEY idx_profesor_correo (profesor_id),
  CONSTRAINT fk_profesor_correo FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_unidades (
  id smallint unsigned NOT NULL AUTO_INCREMENT,
  clave varchar(12) NOT NULL,
  nombre varchar(80) NOT NULL,
  nivel enum('licenciatura','posgrado','otro') NOT NULL,
  programa_id smallint unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_unidad (clave),
  CONSTRAINT fk_profesor_unidad_programa FOREIGN KEY (programa_id) REFERENCES programas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_adscripciones (
  profesor_id int unsigned NOT NULL,
  unidad_id smallint unsigned NOT NULL,
  periodo char(5) NOT NULL,
  fecha_inicio date NOT NULL,
  PRIMARY KEY (profesor_id, unidad_id, periodo),
  KEY idx_profesor_adscripcion_unidad (unidad_id),
  CONSTRAINT fk_profesor_adscripcion_profesor FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE,
  CONSTRAINT fk_profesor_adscripcion_unidad FOREIGN KEY (unidad_id) REFERENCES profesor_unidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT IGNORE INTO profesor_unidades (clave, nombre, nivel, programa_id) VALUES
  ('ICI', 'Ingeniería Civil', 'licenciatura', NULL),
  ('IME', 'Ingeniería Mecánica', 'licenciatura', (SELECT id FROM programas WHERE siglas = 'IME')),
  ('ICO', 'Ingeniería en Computación', 'licenciatura', (SELECT id FROM programas WHERE siglas = 'ICO')),
  ('IEL', 'Ingeniería en Electrónica', 'licenciatura', (SELECT id FROM programas WHERE siglas = 'IEL')),
  ('ISES', 'Ingeniería en Sistemas Energéticos Sustentables', 'licenciatura', (SELECT id FROM programas WHERE siglas = 'ISES')),
  ('IIA', 'Ingeniería en Inteligencia Artificial', 'licenciatura', (SELECT id FROM programas WHERE siglas = 'IIA')),
  ('PROPE', 'Propedéutico', 'otro', NULL),
  ('INGLES', 'Inglés', 'otro', NULL),
  ('MAESTRIA', 'Maestría', 'posgrado', NULL),
  ('DOCTORADO', 'Doctorado', 'posgrado', NULL);

-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/026_directorio_profesores.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
