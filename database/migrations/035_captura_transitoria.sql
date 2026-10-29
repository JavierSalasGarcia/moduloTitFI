-- Captura transitoria de Titulación: trámites que el Departamento de Evaluación Profesional atiende en papel mientras la
-- plataforma no se inaugura (tema aprobado, prórroga, afinidad CEA, trabajo escrito en revisión). Cada registro es la ficha
-- del alumno; los que ya presentaron examen se enlazan a su renglón del libro de actas (tit_historico), de donde salen las
-- constancias. Al inaugurar, el personal vincula cada ficha con el trámite en línea del alumno (por número de cuenta).
--
-- Carga inicial: «EvaluacionProfesional.docx» (oficios y constancias emitidos hasta el 20 de octubre de 2026). Repetible:
-- los registros se identifican por `origen` y las actualizaciones del libro solo llenan datos vacíos.

CREATE TABLE IF NOT EXISTS tit_captura (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  origen VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  estado VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'tema_aprobado',
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  sistema VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'fi',
  alumno VARCHAR(160) NOT NULL DEFAULT '',
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  clave_registro VARCHAR(30) NOT NULL DEFAULT '',
  titulo VARCHAR(500) NOT NULL DEFAULT '',
  asesor VARCHAR(160) NOT NULL DEFAULT '',
  coasesor VARCHAR(160) NOT NULL DEFAULT '',
  revisores_protocolo VARCHAR(600) NOT NULL DEFAULT '',
  revisores_trabajo VARCHAR(600) NOT NULL DEFAULT '',
  programa_cea VARCHAR(300) NOT NULL DEFAULT '',
  fecha_tema DATE NULL,
  fecha_prorroga DATE NULL,
  fecha_afinidad DATE NULL,
  fecha_dictamen DATE NULL,
  fecha_examen DATE NULL,
  revisar TINYINT(1) NOT NULL DEFAULT 0,
  observaciones TEXT NULL,
  fuente VARCHAR(300) NOT NULL DEFAULT '',
  historico_id BIGINT UNSIGNED NULL,
  tramite_id BIGINT UNSIGNED NULL,
  creado_por INT UNSIGNED NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_captura_origen (origen),
  KEY idx_tit_captura_cuenta (cuenta),
  KEY idx_tit_captura_tramite (tramite_id),
  KEY idx_tit_captura_hist (historico_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ajustes (clave, valor, descripcion)
VALUES ('titulacion_captura_cerrada', '0', 'Captura transitoria de Titulación en solo lectura (1 al inaugurar la plataforma)');

-- ------------------------------------------------------------------ fichas del documento

INSERT IGNORE INTO tit_captura (origen, estado, carrera, sistema, alumno, cuenta, modalidad, clave_registro, titulo, asesor, coasesor,
  revisores_protocolo, revisores_trabajo, programa_cea, fecha_tema, fecha_prorroga, fecha_afinidad, fecha_dictamen, fecha_examen, revisar, observaciones, fuente) VALUES
-- Oficios de aprobación de tema
('EP2026-01', 'tema_aprobado', 'civil', 'fi', 'ADRIAN FLORES MENA', '1711262', 'tesis', '',
 'IMPLEMENTACIÓN DE SUPERVISIÓN PROACTIVA FRENTE A OMISIONES DE DISEÑO Y SU IMPACTO EN LA REASIGNACIÓN DE RECURSOS: CASO "CONSTRUCCIÓN DE ÁREA ADMINISTRATIVA, IXTLAHUACA, PLANTA BAJA"',
 'ING. JORGE VALENTE GARCÍA VELÁZQUEZ', 'DR. DAVID JOAQUIN DELGADO HERNÁNDEZ', 'ING. JUDITH PÉREZ MORALES', '', '', '2026-08-31', NULL, NULL, NULL, NULL, 1,
 'El oficio de aprobación (TESIS) no trae fecha; se tomó la del dictamen del protocolo (31/08/2026). La constancia de revisora de protocolo de Judith Pérez Morales lo registra como MEMORIA DE EXPERIENCIA LABORAL con el título «SUPERVISION PROACTIVA FRENTE A OMISIONES DE PLANEACIÓN MEDIANTE INGENIERIA DE DETALLE: DISEÑO, CÁLCULO Y EJECUCIÓN DE ADECUACIÓN FUNCIONAL EN "CONSTRUCCIÓN DE ÁREA ADMINISTRATIVA, IXTLAHUACA, PLANTA BAJA", CABECERA MUNICIPAL, IXTLAHUACA, MÉXICO». Confirmar modalidad, título y fecha. El coasesor debe aprobarlo el H. Consejo Académico y no formará parte del jurado.',
 'Oficio de aprobación de tema (sin fecha); constancia de revisora de protocolo de Judith Pérez Morales, 19/10/2026'),
('EP2026-02', 'tema_aprobado', 'computacion', 'fi', 'ALFREDO JAIR SANCHEZ GARCIA', '1614391', 'tesina', '',
 'ANALISIS DE ARQUITECTURA DE RESPUESTA AUTOMATIZADA A INCIDENTES (SOAR) BASADAS EN CODIGO ABIERTO: ESTUDIO COMPARATIVO DE WAZUH Y GLPI Y SU APLICACIÓN EN UN CASO DE ESTUDIO PARA ENTORNOS DE TI.',
 'ING. JUAN CARLOS ESCOBAR GONZALEZ', '', '', '', '', '2026-09-02', NULL, NULL, NULL, NULL, 0, NULL,
 'Oficio de aprobación de tema, 02/09/2026 (aparece dos veces en el documento)'),
('EP2026-03', 'tema_aprobado', 'sistemas', 'fi', 'ANA ELIZABETH URÍA ARTEAGA', '1710915', 'tesis', '',
 'RESPUESTA ELECTRICA Y OPTICA DE POLIMEROS CETÓNICOS SINTETIZADOS POR PLASMA PARA POSIBLE APLICACIÓN FOTOELÉCTRICA',
 'DRA. ELENA COLIN OROZCO', 'DR. JESÚS GUILLERMO CRUZ CRUZ', '', '', '', '2026-10-05', NULL, NULL, NULL, NULL, 0,
 'El coasesor debe aprobarlo el H. Consejo Académico y no formará parte del jurado.', 'Oficio de aprobación de tema, 05/10/2026'),
('EP2026-04', 'tema_aprobado', 'computacion', 'fi', 'JANETTE RAMIREZ RAMIREZ', '2021001', 'ensayo', '',
 'USO ÉTICO Y RESPONSABLE DE CHATGPT EN LA FORMACIÓN DE ESTUDIANTES DE INGENIERIA EN COMPUTACIÓN DE LA FI-UAEMEX.',
 'M. EN A.T.I LUIS ENRIQUE LEDEZMA FUENTES', '', '', '', '', '2026-10-08', NULL, NULL, NULL, NULL, 0, NULL, 'Oficio de aprobación de tema, 08/10/2026'),
('EP2026-05', 'tema_aprobado', 'sistemas', 'fi', 'MARIANO DE LEON Y GARCIA', '1513111', 'autoempleo', '',
 'CONSTITUCIÓN Y OPERACIÓN DE LA EMPRESA MSOLAREX', 'ING. DIANA LUZ RAMÍREZ MARTÍNEZ', '', '', '', '', '2026-10-19', NULL, NULL, NULL, NULL, 0, NULL,
 'Oficio de aprobación de tema, 19/10/2026'),
('EP2026-06', 'tema_aprobado', 'mecanica', 'fi', 'DANIEL VIVERO RIOS', '1640665', 'autoempleo', '',
 'DESARROLLO DE UNA METODOLOGIA ESTANDARIZADA BASADA EN HOJA DE CALCULO PARA DEFINICIÓN Y GESTIÓN DE PRUEBAS DE VALIDACIÓN DE PRODUCTO: (PV: PRODUCT VALIDATION) ASOCIADAS A CAMBIOS DE INGENIERÍA EN COMPONENTES AUTOMOTRICES.',
 'ING. JORGE SAÚL GALLEGOS MOLINA', '', '', '', '', '2026-10-19', NULL, NULL, NULL, NULL, 0, NULL, 'Oficio de aprobación de tema, 19/10/2026'),
-- Prórroga
('EP2026-07', 'prorroga', 'computacion', 'fi', 'ESTEFANIA CAMPOS DE LA CRUZ', '1521188', 'memoria', '',
 'IMPLEMENTACIÓN DE LA APLICACIÓN EBIND PE COMO ESTANDAR BASE PARA GARANTIZAR LA CALIDAD DE LAS APLICACIONES EN UNA EMPRESA ESPECIALIZADA EN SISTEMAS DE PAGO',
 'DRA. SILVIA EDITH ALBARRAN TRUJILLO', '', '', '', '', NULL, '2028-09-11', NULL, NULL, NULL, 0,
 'Prórroga aprobada del 12/09/2026 al 11/09/2028. Falta la fecha de aprobación original del tema.', 'Oficio de prórroga, 29/09/2026'),
-- Afinidad para Créditos en Estudios Avanzados
('EP2026-08', 'afinidad', 'computacion', 'ui', 'ÁNGEL GUSTAVO HERNÁNDEZ HERNÁNDEZ', '2060974', 'creditos', '', '', '', '', '', '',
 'Maestría en Administración de Tecnologías de Información (RVOE 2052A0000/617/2010), Universidad de Ixtlahuaca CUI, A.C.', NULL, NULL, '2026-09-22', NULL, NULL, 0, NULL,
 'Dictamen de afinidad, 22/09/2026'),
('EP2026-09', 'afinidad', 'sistemas', 'fi', 'OSCAR JEFTÉ HERNÁNDEZ GONZÁLEZ', '1712397', 'creditos', '', '', '', '', '', '',
 'Diplomado Superior en Diseño Ambiental con Énfasis en Agua y Energía, Facultad de Arquitectura y Diseño', NULL, NULL, '2026-10-19', NULL, NULL, 0, NULL,
 'Dictamen de afinidad, 19/10/2026'),
('EP2026-10', 'afinidad', 'sistemas', 'fi', 'JOSE EDUARDO GARCÍA TEJEDA', '2021110', 'creditos', '', '', '', '', '', '',
 'Diplomado Superior en Diseño Ambiental con Énfasis en Agua y Energía, Facultad de Arquitectura y Diseño', NULL, NULL, '2026-10-19', NULL, NULL, 0, NULL,
 'Dictamen de afinidad, 19/10/2026'),
('EP2026-11', 'afinidad', 'sistemas', 'fi', 'ALEJANDRO MONTAÑO SANCHEZ', '1510747', 'creditos', '', '', '', '', '', '',
 'Diplomado Superior en Diseño Ambiental con Énfasis en Agua y Energía, Facultad de Arquitectura y Diseño', NULL, NULL, '2026-10-20', NULL, NULL, 0, NULL,
 'Dictamen de afinidad, 20/10/2026'),
-- Informe de estatus de protocolo (sin nombre del alumno)
('EP2026-12', 'protocolo', 'sistemas', 'fi', '', '', NULL, '', '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, 1,
 'Informe de estatus de protocolo sin nombre del alumno: turnado a la comisión revisora el 28/09/2026. Posiblemente corresponde a Ana Elizabeth Uría Arteaga (ISES, tema aprobado el 05/10/2026); si es así, elimina esta ficha.',
 'Informe sobre el estatus de protocolo de titulación'),
-- Examinados (se enlazan al libro de actas más abajo)
('EP2026-13', 'examinado', 'civil', 'fi', 'Jaquelinne Sotres Diaz', '', 'tesis', '',
 'Diseño de cimentaciones par aerogeneradores implementados en Ecatzingo, Estado de México', 'DR. FRANCISCO HECTOR BAÑUELOS GARCIA', '', '', '', '',
 NULL, NULL, NULL, NULL, '2026-06-12', 0, 'En el libro de actas: SOTRES DIAZ JAQUELINE, acta 1282. El título dice «par» (probablemente «para»).',
 'Constancia de cumplimiento (28/08/2026) y nombramiento (13/10/2026) del director'),
('EP2026-14', 'examinado', 'electronica', 'fi', 'Francisco Becerril Gutiérrez', '', 'aplicacion', 'IEL-202509081',
 'Construcción de un robot antropomórfico de 4 grados de libertad con fines académicos para los estudiantes de Ingeniería en Electrónica de la Facultad de Ingeniería de la UAEMéx',
 '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '', '2025-10-06', NULL, NULL, NULL, '2026-05-06', 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito (constancia fechada 08/10/2025) y como revisor de protocolo (19/10/2026); confirmar su función. Falta el director.',
 'Constancias de Germán García Benítez'),
('EP2026-15', 'examinado', 'electronica', 'fi', 'Edgar Javier Rebollar Guadarrama', '', 'tesis', 'IEL-202628003',
 'Diseño de un sistema de monitoreo y alarma de las variables de temperatura, caudal y nivel en el depósito de aceite en el proceso de temple de una empresa forjadora de piezas metalmecánicas',
 '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '', '2026-05-21', NULL, NULL, NULL, '2026-08-25', 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito (constancia fechada 08/10/2025) y como revisor de protocolo (19/10/2026); confirmar su función. La clave IEL-202628003 parece mal escrita. Falta el director.',
 'Constancias de Germán García Benítez'),
('EP2026-16', 'examinado', 'electronica', 'fi', 'Mauricio García Puine', '', 'tesina', 'IEL-202503241',
 'Análisis teórico y aplicaciones del controlador difuso de Mamdani', 'DR. EDUARDO RODRÍGUEZ ÁNGELES', '', '', 'DR. EDUARDO RODRÍGUEZ ÁNGELES', '',
 NULL, NULL, NULL, '2026-01-08', '2026-04-17', 1,
 'Eduardo Rodríguez Ángeles aparece como revisor del trabajo escrito (dictamen 08/01/2026) y como asesor (17/04/2026) del mismo trabajo; confirmar.',
 'Constancias de revisor y de asesor de Eduardo Rodríguez Ángeles (08/10/2025)'),
('EP2026-17', 'examinado', 'electronica', 'fi', 'Angel González Rodríguez', '', 'aplicacion', 'LIEL-202411041',
 'Desarrollo e implementación de un Full Diagnostic Testing para un vehículo automotriz', 'DR. EDUARDO RODRÍGUEZ ÁNGELES', '', '', '', '',
 NULL, NULL, NULL, NULL, '2026-07-02', 1,
 'La constancia de asesor registra el 12/06/2026 y el libro de actas el 02/07/2026 como fecha de evaluación; confirmar. Falta el número de cuenta también en el libro.',
 'Constancia de asesor de Eduardo Rodríguez Ángeles (08/10/2025)'),
('EP2026-18', 'examinado', 'electronica', 'fi', 'Carolina Venancio', '', 'tesina', 'IEL-202510011',
 'Redes de tecnología de comunicación celular', '', '', '', '', '', NULL, NULL, NULL, NULL, '2026-08-28', 1,
 'En la constancia de asesor de Eduardo Rodríguez Ángeles la columna «Asesor» dice «No»; confirmar quién fue el director. Nombre incompleto (en el libro: VENANCIO CAROLINA).',
 'Constancia de asesor de Eduardo Rodríguez Ángeles (08/10/2025)'),
('EP2026-19', 'examinado', 'civil', 'fi', 'Brenda González Sánchez', '', 'memoria', '',
 'GESTIÓN EN LA PLANEACIÓN Y CONTROL DEL PROYECTO "MOLCAJETE II"', 'ING. JUDITH PÉREZ MORALES', '', '', 'DRA. MA. DE LOURDES NÁJERA LÓPEZ', '',
 NULL, NULL, NULL, '2026-03-23', '2026-10-13', 1,
 'Dos títulos: «Gestión en la Planeación y Construcción del Proyecto "Molcajete II"» (revisora Nájera López, 23/03/2026) y «GESTIÓN EN LA PLANEACIÓN Y CONTROL DEL PROYECTO "MOLCAJETE II"» (asesora Pérez Morales, 16/06/2026); se dejó el más reciente.',
 'Constancias de Lourdes Nájera López y Judith Pérez Morales (16/10/2026)'),
('EP2026-20', 'examinado', 'civil', 'fi', 'Cristian Misael Barrios Alvarado', '', 'tesis', '',
 'EVALUACIÓN NUMERICA DEL COMPORTAMIENTO DE UNA PROPUESTA DE CONEXIÓN PARA COLUMNAS CON SECCIÓN CUADRADA HUECA (OR)', '', '', 'ING. JUDITH PÉREZ MORALES', '', '',
 '2026-03-04', NULL, NULL, NULL, '2026-07-10', 0, 'En el libro de actas: BARRIOS ALVARADO CHRISTIAN MISAEL. Falta el director.',
 'Constancia de revisora de protocolo de Judith Pérez Morales (19/10/2026)'),
-- Trabajos dictaminados en las constancias de profesores (sin examen registrado)
('EP2026-21', 'revision', 'electronica', 'fi', 'Omar Alejandro González Sánchez', '', 'tesina', 'IEL-202504281',
 'Propuesta de diseño mecánico y eléctrico de gabinete de control para automatización de uso académico', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '',
 '2025-08-06', NULL, NULL, NULL, NULL, 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito y como revisor de protocolo; confirmar su función y si la fecha es del protocolo o del trabajo escrito.',
 'Constancias de Germán García Benítez'),
('EP2026-22', 'revision', 'electronica', 'fi', 'Dariana Flores Camacho', '', 'memoria', 'IEL-202509021',
 'Descripción del puesto Ingeniero de Pruebas Hardware en Bucle en la validación de funcionalidades de sistemas de infoentretenimiento en sistemas automotrices', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '',
 '2025-09-25', NULL, NULL, NULL, NULL, 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito y como revisor de protocolo; confirmar su función y si la fecha es del protocolo o del trabajo escrito.',
 'Constancias de Germán García Benítez'),
('EP2026-23', 'revision', 'electronica', 'fi', 'Brayan Luis Pineda Aviles', '', 'tesina', 'IEL-202510082',
 'Diseño de un sistema de automatización para la operación de cortes rectos de madera en talleres de carpintería', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '',
 '2025-11-25', NULL, NULL, NULL, NULL, 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito y como revisor de protocolo; confirmar su función y si la fecha es del protocolo o del trabajo escrito.',
 'Constancias de Germán García Benítez'),
('EP2026-24', 'revision', 'electronica', 'fi', 'Cristian Salinas Castillo', '', 'aplicacion', 'IEL-202606004',
 'Diseño y mejora funcional de una plataforma electrónica para la evaluación del tiempo de respuesta en porteros de fútbol', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', 'DR. GERMÁN GARCÍA BENÍTEZ', '',
 '2026-06-23', NULL, NULL, NULL, NULL, 1,
 'La misma tabla de Germán García Benítez aparece como revisor del trabajo escrito y como revisor de protocolo; confirmar su función y si la fecha es del protocolo o del trabajo escrito.',
 'Constancias de Germán García Benítez'),
('EP2026-25', 'revision', 'electronica', 'fi', 'Alondra Estrada Sotero', '', 'aplicacion', 'LIEL-202409111',
 'Corrección de la falla en la activación del indicador de nivel bajo de agua en el sistema de limpiaparabrisas de un clúster automotriz', '', '', '', 'DR. EDUARDO RODRÍGUEZ ÁNGELES', '',
 NULL, NULL, NULL, '2026-02-09', NULL, 0, NULL, 'Constancia de revisor de Eduardo Rodríguez Ángeles (08/10/2025)'),
('EP2026-26', 'tema_aprobado', 'civil', 'fi', 'Giovani Antonio de la Cruz Patiño', '', 'tesina', '',
 'ANALISIS TÉCNICO COMPARATIVO DEL DISEÑO ESTRUCTURAL DE PAVIMENTOS RÍGIDOS MEDIANTE LOS MÉTODOS AASHTO 93 Y PCA: SIMULACIÓN DE UN CASO DE ESTUDIO EN UNA VIALIDAD URBANA DEL VALLE DE TOLUCA',
 'DRA. MA. DE LOURDES NÁJERA LÓPEZ', '', 'ING. JUDITH PÉREZ MORALES', '', '', '2025-10-02', NULL, NULL, NULL, NULL, 1,
 'La constancia de asesora de Nájera López dice 2/octubre/2025 y la de revisora de protocolo de Pérez Morales 02/10/2026; confirmar el año.',
 'Constancias de Lourdes Nájera López (16/10/2026) y Judith Pérez Morales (19/10/2026)'),
('EP2026-27', 'revision', 'civil', 'fi', 'Jorge Alfredo Sánchez Navarrete', '', 'tesis', '',
 'Analisis del comportamiento hidrodinámico de deflactores acoplados a pilas de sacrificio para reducer la socavación local en puentes mediante CFD', '', '', '', 'DRA. MA. DE LOURDES NÁJERA LÓPEZ', '',
 NULL, NULL, NULL, '2026-06-16', NULL, 0, 'Título copiado como viene en la constancia («deflactores», «reducer»).', 'Constancia de revisora de Lourdes Nájera López (16/10/2026)'),
('EP2026-28', 'revision', 'civil', 'fi', 'Karla Alejandra Gutiérrez Osorio', '', 'tesis', '',
 'Diagnostico y Propuesta de Mejora del Sistema de Capacitación Técnica en Empresas Constructoras del Valle de Toluca.', '', '', '', 'DRA. MA. DE LOURDES NÁJERA LÓPEZ', '',
 NULL, NULL, NULL, '2026-06-08', NULL, 0, NULL, 'Constancia de revisora de Lourdes Nájera López (16/10/2026)'),
('EP2026-29', 'tema_aprobado', '', 'fi', 'Amalia Abigail Vázquez Perdomo', '', 'memoria', '',
 'Memoria de experiencia laboral en la gestión y supervisión de obra eléctrica en la subestación de Autotransformación Final del proyecto Tren Interurbano México-Toluca', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', '', '',
 '2026-08-18', NULL, NULL, NULL, NULL, 0, 'Licenciatura no indicada en la constancia.', 'Constancia de revisor de protocolo de Germán García Benítez (19/10/2026)'),
('EP2026-30', 'tema_aprobado', '', 'fi', 'Rolando Humberto López Corona', '', 'aplicacion', '',
 'Diseño y construcción de equipo especializado para laboratorio de validación y verificación de medidores eléctricos.', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', '', '',
 '2026-01-27', NULL, NULL, NULL, NULL, 0, 'Licenciatura no indicada en la constancia.', 'Constancia de revisor de protocolo de Germán García Benítez (19/10/2026)'),
('EP2026-31', 'tema_aprobado', '', 'fi', 'Jocelin Molina Crisóstomo', '', 'memoria', '',
 'Instalación y supervisión de sistemas de distribución eléctrica en entornos industriales: caso BOSCH como experiencia profesional', '', '', 'DR. GERMÁN GARCÍA BENÍTEZ', '', '',
 '2026-04-16', NULL, NULL, NULL, NULL, 0, 'Licenciatura no indicada en la constancia.', 'Constancia de revisor de protocolo de Germán García Benítez (19/10/2026)');

-- ------------------------------------------------------------------ examinados: enlace con el libro de actas

UPDATE tit_captura c JOIN tit_historico h ON (c.origen, h.hoja, h.fila) IN (
    ('EP2026-13', 'CIVIL', 657), ('EP2026-14', 'INGENIERIA EN ELECTRONICA', 197), ('EP2026-15', 'INGENIERIA EN ELECTRONICA', 206),
    ('EP2026-16', 'INGENIERIA EN ELECTRONICA', 194), ('EP2026-17', 'INGENIERIA EN ELECTRONICA', 202), ('EP2026-18', 'INGENIERIA EN ELECTRONICA', 207),
    ('EP2026-19', 'CIVIL', 680), ('EP2026-20', 'CIVIL', 658))
SET c.historico_id = h.id, c.cuenta = IF(c.cuenta = '', h.cuenta, c.cuenta)
WHERE c.historico_id IS NULL;

-- El libro solo recibe lo que tiene vacío: modalidad, fecha, título, director y (si la constancia es inequívoca) un revisor del trabajo escrito.
UPDATE tit_historico h JOIN tit_captura c ON c.historico_id = h.id AND c.origen LIKE 'EP2026-%'
SET h.modalidad = IF(h.modalidad IS NULL OR h.modalidad = '', c.modalidad, h.modalidad),
    h.fecha_examen = IFNULL(h.fecha_examen, c.fecha_examen),
    h.titulo_origen = IF(h.titulo = '', 'manual', h.titulo_origen),
    h.titulo = IF(h.titulo = '', c.titulo, h.titulo),
    h.director = IF(h.director = '', c.asesor, h.director),
    h.actualizado_en = NOW()
WHERE h.tramite_id IS NULL;

UPDATE tit_historico h JOIN tit_captura c ON c.historico_id = h.id AND c.origen = 'EP2026-19'
SET h.presidente = c.revisores_trabajo
WHERE h.tramite_id IS NULL AND h.presidente = '' AND h.secretario <> c.revisores_trabajo AND h.vocal <> c.revisores_trabajo;
