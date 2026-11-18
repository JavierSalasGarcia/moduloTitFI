-- Módulo Constancias: plantillas para «Solicitar una constancia».
-- Quien puede editar los textos de constancias (Titulación → Constancias → Textos: administración, Subdirector Académico y
-- Jefatura de Evaluación Profesional y Grado) crea plantillas con nombre, asunto, destinatario y cuerpo con marcadores
-- {clave}. Al solicitar una constancia, el personal elige una plantilla, llena los campos que piden sus marcadores y el
-- cuerpo, el asunto y el destinatario se arman con ellos; después sigue el flujo de siempre (vista previa, folio, firma).
-- Los marcadores {fecha_letras}, {firmante}, {cargo_firmante} y {suscribe} no se piden: los llena el sistema al generar el PDF.
-- Solo estructura y dos plantillas genéricas de ejemplo (sin datos de personas). Es repetible: puede ejecutarse completa
-- las veces que haga falta.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cst_plantillas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NOT NULL DEFAULT '',
  asunto VARCHAR(160) NOT NULL DEFAULT 'CONSTANCIA',
  dirigido_a VARCHAR(600) NOT NULL DEFAULT 'A QUIEN CORRESPONDA',
  -- Cuerpo con el formato de la solicitud (párrafos, **negritas**, tablas con |) y marcadores {clave}.
  cuerpo TEXT NOT NULL,
  -- Definición de cada marcador que se pide: [{clave, etiqueta, tipo (texto|fecha|numero|texto_largo|tabla), obligatorio, ayuda, columnas[]}].
  campos JSON NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  creada_por INT UNSIGNED NULL,
  actualizada_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cst_plantillas_nombre (nombre),
  KEY idx_cst_plantillas_activa (activa, nombre),
  CONSTRAINT fk_cst_plantillas_creada FOREIGN KEY (creada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_cst_plantillas_actualizada FOREIGN KEY (actualizada_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de cambios de cada plantilla, con la versión que quedó guardada (sin datos de personas).
CREATE TABLE IF NOT EXISTS cst_plantillas_historial (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  plantilla_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  accion ENUM('creada','editada','duplicada','desactivada','activada') NOT NULL,
  version JSON NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cst_plantillas_historial (plantilla_id, id),
  CONSTRAINT fk_cst_plantillas_historial_plantilla FOREIGN KEY (plantilla_id) REFERENCES cst_plantillas(id) ON DELETE CASCADE,
  CONSTRAINT fk_cst_plantillas_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cst_solicitudes.plantilla_id: plantilla con la que se armó la constancia (NULL = en blanco o Word).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE cst_solicitudes ADD COLUMN plantilla_id INT UNSIGNED NULL AFTER origen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cst_solicitudes' AND COLUMN_NAME = 'plantilla_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE cst_solicitudes ADD KEY idx_cst_solicitudes_plantilla (plantilla_id)', 'DO 0')
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cst_solicitudes' AND INDEX_NAME = 'idx_cst_solicitudes_plantilla');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE cst_solicitudes ADD CONSTRAINT fk_cst_solicitudes_plantilla FOREIGN KEY (plantilla_id) REFERENCES cst_plantillas(id) ON DELETE SET NULL', 'DO 0')
            FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cst_solicitudes' AND CONSTRAINT_NAME = 'fk_cst_solicitudes_plantilla');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Plantillas de ejemplo (genéricas; solo se agregan si no existe una con el mismo nombre).
INSERT INTO cst_plantillas (nombre, descripcion, asunto, dirigido_a, cuerpo, campos)
SELECT 'Impartición de curso', 'Para quien impartió uno o varios cursos, con la tabla de cursos, periodos y horas.', 'CONSTANCIA', 'A QUIEN CORRESPONDA',
       '{suscribe}, {firmante}, {cargo_firmante} de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, hace constar que **{nombre_persona}** impartió los cursos que a continuación se relacionan, dirigidos a {dirigido_a_quienes}:\n\n{cursos}\n\nPara los fines que a la persona interesada convengan, se extiende la presente CONSTANCIA en la Ciudad de Toluca de Lerdo, Estado de México, {fecha_letras}.',
       '[{"clave":"nombre_persona","etiqueta":"Nombre de quien impartió los cursos","tipo":"texto","obligatorio":true,"ayuda":"Con grado, como debe aparecer en la constancia.","columnas":[]},{"clave":"dirigido_a_quienes","etiqueta":"A quiénes se dirigieron","tipo":"texto","obligatorio":true,"ayuda":"Por ejemplo: profesores de la Facultad.","columnas":[]},{"clave":"cursos","etiqueta":"Cursos impartidos","tipo":"tabla","obligatorio":true,"ayuda":"Un renglón por curso.","columnas":["Curso","Periodo","Horas"]}]'
WHERE NOT EXISTS (SELECT 1 FROM cst_plantillas WHERE nombre = 'Impartición de curso');

INSERT INTO cst_plantillas (nombre, descripcion, asunto, dirigido_a, cuerpo, campos)
SELECT 'Participación en evento académico', 'Para ponentes, moderadores o integrantes de comités de un evento académico.', 'CONSTANCIA', 'A QUIEN CORRESPONDA',
       '{suscribe}, {firmante}, {cargo_firmante} de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, hace constar que **{nombre_persona}** participó como {participacion} en el evento «{evento}», celebrado el {fecha_evento} en la Facultad de Ingeniería.\n\n{detalle}\n\nPara los fines que a la persona interesada convengan, se extiende la presente CONSTANCIA en la Ciudad de Toluca de Lerdo, Estado de México, {fecha_letras}.',
       '[{"clave":"nombre_persona","etiqueta":"Nombre de la persona","tipo":"texto","obligatorio":true,"ayuda":"Con grado, como debe aparecer en la constancia.","columnas":[]},{"clave":"participacion","etiqueta":"Participó como","tipo":"texto","obligatorio":true,"ayuda":"Por ejemplo: ponente, moderadora, integrante del comité organizador.","columnas":[]},{"clave":"evento","etiqueta":"Nombre del evento","tipo":"texto","obligatorio":true,"ayuda":"","columnas":[]},{"clave":"fecha_evento","etiqueta":"Fecha del evento","tipo":"fecha","obligatorio":true,"ayuda":"","columnas":[]},{"clave":"detalle","etiqueta":"Detalle adicional","tipo":"texto_largo","obligatorio":false,"ayuda":"Opcional: título de la ponencia, horas acreditadas, etc.","columnas":[]}]'
WHERE NOT EXISTS (SELECT 1 FROM cst_plantillas WHERE nombre = 'Participación en evento académico');
