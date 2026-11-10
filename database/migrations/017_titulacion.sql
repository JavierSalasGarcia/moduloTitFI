-- Titulación (licenciatura), Auditorios/Salas, Notificaciones, enlaces de descarga, Concursos y Formularios: migración única de todo lo posterior a 016.
-- Es repetible: puede ejecutarse completa las veces que haga falta, aunque una parte ya esté aplicada
-- (tablas con IF NOT EXISTS, columnas e índices solo si faltan, ajustes con INSERT IGNORE).
-- Se aplica a mano en pruebas y producción; copiar las carpetas de los módulos no ejecuta SQL.

-- El archivo está en UTF-8: así se lee bien aunque el cliente (consola mysql, phpMyAdmin) use otro juego de caracteres.
SET NAMES utf8mb4;

-- ================================================================ Trámites
CREATE TABLE IF NOT EXISTS tit_tramites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  nivel ENUM('licenciatura','posgrado') NOT NULL DEFAULT 'licenciatura',
  alumno_id INT UNSIGNED NULL,
  tipo ENUM('sin_trabajo','con_trabajo') NOT NULL,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  variante VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  estado ENUM('abierto','concluido','cancelado') NOT NULL DEFAULT 'abierto',
  etapa_actual VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'datos',
  titulo VARCHAR(500) NULL,
  datos MEDIUMTEXT NOT NULL,
  resultado VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  responsable_id INT UNSIGNED NULL,
  historico TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_folio (folio),
  KEY idx_tit_alumno (alumno_id, estado),
  KEY idx_tit_bandeja (estado, etapa_actual),
  KEY idx_tit_modalidad (modalidad),
  CONSTRAINT fk_tit_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_documentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  origen ENUM('alumno','personal') NOT NULL DEFAULT 'alumno',
  estado ENUM('en_revision','aprobado','rechazado','no_aplica','reemplazado') NOT NULL DEFAULT 'en_revision',
  ruta VARCHAR(500) NULL,
  nombre_original VARCHAR(255) NULL,
  subido_por INT UNSIGNED NOT NULL,
  comentario VARCHAR(1000) NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  cotejo_tipo ENUM('sello','pades') NULL,
  cotejo_por INT UNSIGNED NULL,
  cotejo_en DATETIME NULL,
  cotejo_hash CHAR(64) NULL,
  cotejo_ruta VARCHAR(500) NULL,
  subdir_en DATETIME NULL,
  subdir_capacidad ENUM('personal','delegada','automatica') NULL,
  subdir_ruta VARCHAR(500) NULL,
  pendiente_firmas TINYINT(1) NOT NULL DEFAULT 0,
  cajas TEXT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_doc_tramite (tramite_id, etapa, clave, estado),
  KEY idx_tit_doc_revision (estado, creado_en),
  CONSTRAINT fk_tit_doc_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_doc_subio FOREIGN KEY (subido_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_doc_reviso FOREIGN KEY (revisado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_doc_cotejo FOREIGN KEY (cotejo_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_hitos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  datos TEXT NOT NULL,
  completado_por INT UNSIGNED NOT NULL,
  completado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_hito (tramite_id, etapa),
  CONSTRAINT fk_tit_hito_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_hito_usuario FOREIGN KEY (completado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  visible_alumno TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_evento_tramite (tramite_id, creado_en),
  CONSTRAINT fk_tit_evento_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bandeja única de notificaciones de la plataforma (por correo de la persona); el correo electrónico solo la alerta.
CREATE TABLE IF NOT EXISTS notificaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(120) NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  enlace VARCHAR(500) NULL,
  origen VARCHAR(40) NOT NULL DEFAULT 'plataforma',
  tramite_id BIGINT UNSIGNED NULL,
  leida_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_email (email, leida_en),
  KEY idx_notif_tramite (tramite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificación pública (QR) de los documentos que genera la plataforma: código del QR, documento del expediente
-- (o revisión/dictamen del comité, o formato descargado por el pasante) y el contenido impreso que se muestra.
CREATE TABLE IF NOT EXISTS tit_verificaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tramite_id BIGINT UNSIGNED NOT NULL,
  documento_id BIGINT UNSIGNED NULL,
  revision_id BIGINT UNSIGNED NULL,
  revision_asignacion_id BIGINT UNSIGNED NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ruta VARCHAR(500) NULL,
  contenido MEDIUMTEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_verif_codigo (codigo),
  KEY idx_tit_verif_ruta (ruta(191)),
  KEY idx_tit_verif_doc (documento_id),
  KEY idx_tit_verif_clave (tramite_id, clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Numeración de oficios: consecutivo anual por serie (subdireccion = ACAD/FI/###/AAAA; jefatura = Evaluación
-- Profesional y Grado) y bitácora de cada número con su destinatario y asunto (sustituye los controles en Excel).
CREATE TABLE IF NOT EXISTS tit_oficio_series (
  serie VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  ultimo INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (serie, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La numeración en la plataforma empieza en el 100 del año en que se instala (los anteriores se dieron en Excel).
INSERT IGNORE INTO tit_oficio_series (serie, anio, ultimo) VALUES ('subdireccion', YEAR(CURDATE()), 99), ('jefatura', YEAR(CURDATE()), 99);

CREATE TABLE IF NOT EXISTS tit_oficios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  serie VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  texto VARCHAR(60) NOT NULL,
  tramite_id BIGINT UNSIGNED NOT NULL,
  asunto VARCHAR(250) NOT NULL,
  dirigido_a VARCHAR(250) NOT NULL DEFAULT '',
  verificacion CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
  generado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_oficio_numero (serie, anio, numero),
  KEY idx_tit_oficio_tramite (tramite_id),
  KEY idx_tit_oficio_verif (verificacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Números de acta de evaluación profesional y de aplazamiento (por licenciatura; no se repiten).
CREATE TABLE IF NOT EXISTS tit_actas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tipo ENUM('evaluacion','aplazamiento') NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_acta_tramite (tramite_id, tipo),
  UNIQUE KEY uq_tit_acta_numero (carrera, tipo, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Textos editables de las constancias (si un tipo no tiene fila, se usa el texto de fábrica del código).
CREATE TABLE IF NOT EXISTS tit_constancia_textos (
  tipo VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asunto VARCHAR(80) NOT NULL DEFAULT '',
  intro TEXT NOT NULL,
  cuerpo TEXT NOT NULL,
  cierre TEXT NOT NULL,
  tabla TINYINT(1) NOT NULL DEFAULT 0,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial del libro de actas (carga única al final de este archivo; se completa en la plataforma) para constancias anteriores.
CREATE TABLE IF NOT EXISTS tit_historico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoja VARCHAR(60) NOT NULL,
  fila INT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  acta INT UNSIGNED NULL,
  alumno VARCHAR(160) NOT NULL DEFAULT '',
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidad_origen VARCHAR(60) NOT NULL DEFAULT '',
  vez VARCHAR(10) NOT NULL DEFAULT '',
  testimonio VARCHAR(20) NOT NULL DEFAULT '',
  fecha_aut_egel DATE NULL,
  fecha_examen DATE NULL,
  fecha_afinidad DATE NULL,
  titulo VARCHAR(500) NOT NULL DEFAULT '',
  director VARCHAR(160) NOT NULL DEFAULT '',
  director_email VARCHAR(120) NOT NULL DEFAULT '',
  codirector VARCHAR(160) NOT NULL DEFAULT '',
  codirector_email VARCHAR(120) NOT NULL DEFAULT '',
  presidente VARCHAR(160) NOT NULL DEFAULT '',
  presidente_email VARCHAR(120) NOT NULL DEFAULT '',
  secretario VARCHAR(160) NOT NULL DEFAULT '',
  secretario_email VARCHAR(120) NOT NULL DEFAULT '',
  vocal VARCHAR(160) NOT NULL DEFAULT '',
  vocal_email VARCHAR(120) NOT NULL DEFAULT '',
  correos VARCHAR(500) NOT NULL DEFAULT '',
  tramite_id BIGINT UNSIGNED NULL,
  generado_en DATETIME NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_hist_fila (hoja, fila),
  KEY idx_tit_hist_acta (carrera, acta),
  KEY idx_tit_hist_tramite (tramite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enlaces de descarga: un token aleatorio por archivo (acción y parámetros) en lugar de números consecutivos.
CREATE TABLE IF NOT EXISTS tit_archivo_tokens (
  token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  accion VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  params VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token),
  UNIQUE KEY uq_tit_archivo_accion (accion, params)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enlaces de descarga de toda la plataforma (Core\Descargas): token aleatorio => ruta de descarga original.
CREATE TABLE IF NOT EXISTS descarga_tokens (
  token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token),
  UNIQUE KEY uq_descarga_ruta (ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los mensajes al pasante y las plantillas/coordenadas de formatos ya no existen: todo llega a «notificaciones»
-- y la plataforma genera los formatos por código.
DROP TABLE IF EXISTS tit_mensajes;
DROP TABLE IF EXISTS tit_formatos_cfg;

-- ================================================================ Configuración de formatos y documentos
-- modalidad '*' vale para todas las opciones; NULL en una columna hereda del catálogo.
CREATE TABLE IF NOT EXISTS tit_docs_cfg (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  activo TINYINT(1) NULL,
  fisico TINYINT(1) NULL,
  cotejo TINYINT(1) NULL,
  firma_cotejo ENUM('sello','pades') NULL,
  nombre VARCHAR(250) NULL,
  nota VARCHAR(500) NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_docs_cfg (modalidad, clave),
  CONSTRAINT fk_tit_dcfg_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_docs_extra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '*',
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  clave VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(250) NOT NULL,
  nota VARCHAR(500) NULL,
  condicion VARCHAR(300) NULL,
  formato VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  solo_fisico TINYINT(1) NOT NULL DEFAULT 0,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_docs_extra (modalidad, etapa, clave),
  CONSTRAINT fk_tit_dex_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_config_bitacora (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  accion VARCHAR(60) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_cfgbit (creado_en),
  CONSTRAINT fk_tit_cfgbit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ Revisión de protocolos
-- Integrantes de cada comité de revisión (la carrera es la clave de Catalogo::carreras()); el «encargado» es el responsable.
CREATE TABLE IF NOT EXISTS tit_comision_miembros (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  email VARCHAR(120) NOT NULL,
  rol ENUM('encargado','miembro') NOT NULL DEFAULT 'miembro',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_miembro (carrera, email),
  KEY idx_tit_miembro_email (email),
  CONSTRAINT fk_tit_miembro_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada versión del protocolo que firma el asesor abre una vuelta de revisión (1ª, 2ª…).
CREATE TABLE IF NOT EXISTS tit_revisiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  numero SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  documento_id BIGINT UNSIGNED NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  estado ENUM('recibida','en_comision','en_evaluacion','por_dictaminar','dictaminada','cancelada') NOT NULL DEFAULT 'recibida',
  fecha_recepcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviada_por INT UNSIGNED NULL,
  enviada_en DATETIME NULL,
  evaluacion_en DATETIME NULL,
  dictamen ENUM('aprobado','no_aprobado') NULL,
  requiere_coord TINYINT(1) NULL,
  observaciones TEXT NULL,
  mensaje TEXT NULL,
  obs_oficio TEXT NULL,
  dictaminado_por INT UNSIGNED NULL,
  dictaminado_en DATETIME NULL,
  firma_tipo ENUM('sello','pades') NULL,
  dictamen_ruta VARCHAR(500) NULL,
  dictamen_hash CHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_rev_ronda (tramite_id, numero),
  KEY idx_tit_rev_estado (carrera, estado),
  CONSTRAINT fk_tit_rev_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_rev_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_rev_envia FOREIGN KEY (enviada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_rev_dictamina FOREIGN KEY (dictaminado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revisor asignado a una vuelta. El token del enlace del correo se guarda solo como huella.
CREATE TABLE IF NOT EXISTS tit_revision_asignaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  revision_id BIGINT UNSIGNED NOT NULL,
  miembro_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_vence DATE NOT NULL,
  estado ENUM('pendiente','borrador','firmada','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_limite DATE NOT NULL,
  asignada_por INT UNSIGNED NULL,
  asignada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  propuesta ENUM('aprobado','no_aprobado') NULL,
  requiere_coord TINYINT(1) NULL,
  forma TEXT NULL,
  fondo TEXT NULL,
  firmada_en DATETIME NULL,
  firma_tipo ENUM('sello','pades') NULL,
  firmante_id INT UNSIGNED NULL,
  pdf_ruta VARCHAR(500) NULL,
  pdf_hash CHAR(64) NULL,
  recordatorios SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_recordatorio DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_asig_token (token_hash),
  UNIQUE KEY uq_tit_asig_miembro (revision_id, miembro_id),
  CONSTRAINT fk_tit_asig_rev FOREIGN KEY (revision_id) REFERENCES tit_revisiones(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_asig_miembro FOREIGN KEY (miembro_id) REFERENCES tit_comision_miembros(id),
  CONSTRAINT fk_tit_asig_asigna FOREIGN KEY (asignada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_asig_firma FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantilla del mensaje de cada vuelta, editable por cada responsable de comité (por correo).
CREATE TABLE IF NOT EXISTS tit_plantillas_mensaje (
  email VARCHAR(120) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Constancias para profesores (revisor de protocolo, revisor de trabajo escrito, asesor, cumplimiento y nombramiento
-- como director). Se generan solas cuando se cumple lo que avalan; las firma la Subdirección o la Jefatura (emisor).
CREATE TABLE IF NOT EXISTS tit_constancias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(120) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  tipo VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'revisor_protocolo',
  emisor ENUM('subdirector','jefe_titulacion') NOT NULL DEFAULT 'subdirector',
  vueltas VARCHAR(40) NOT NULL DEFAULT '',
  rol VARCHAR(120) NOT NULL DEFAULT '',
  huella CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  detalle TEXT NULL,
  documento_id BIGINT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_constancia_rol (tramite_id, tipo, rol),
  KEY idx_tit_constancia_email (email),
  CONSTRAINT fk_tit_const_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_const_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de correos enviados (sin cuerpo: los mensajes contienen enlaces personales).
CREATE TABLE IF NOT EXISTS tit_correos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  destinatario VARCHAR(120) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  tramite_id BIGINT UNSIGNED NULL,
  estado ENUM('enviado','fallido','no_configurado') NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_correos (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ Firmas (Subdirección, Dirección y Jefatura de Titulación)
CREATE TABLE IF NOT EXISTS tit_firmas_subdireccion (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('documento','dictamen') NOT NULL DEFAULT 'documento',
  documento_id BIGINT UNSIGNED NULL,
  revision_id BIGINT UNSIGNED NULL,
  formato VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  rol_firma ENUM('subdirector','director','jefe_titulacion') NOT NULL DEFAULT 'subdirector',
  descripcion VARCHAR(250) NOT NULL,
  caja VARCHAR(120) NULL,
  estado ENUM('pendiente','firmado','cancelado') NOT NULL DEFAULT 'pendiente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  programado_para DATETIME NULL,
  firmado_en DATETIME NULL,
  capacidad ENUM('personal','delegada','automatica') NULL,
  firmante_id INT UNSIGNED NULL,
  piloto_id INT UNSIGNED NULL,
  ruta_firmado VARCHAR(500) NULL,
  hash_firmado CHAR(64) NULL,
  PRIMARY KEY (id),
  KEY idx_tit_fs_estado (estado, programado_para),
  KEY idx_tit_fs_tramite (tramite_id),
  CONSTRAINT fk_tit_fs_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fs_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_fs_rev FOREIGN KEY (revision_id) REFERENCES tit_revisiones(id) ON DELETE SET NULL,
  CONSTRAINT fk_tit_fs_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_asistentes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(120) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_asistente (email),
  CONSTRAINT fk_tit_asist_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_piloto (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  retraso_min SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  formatos TEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  apagado_en DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_tit_piloto (activo, inicio, fin),
  CONSTRAINT fk_tit_piloto_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Firma de asesores y coasesores por enlace de correo.
CREATE TABLE IF NOT EXISTS tit_firmas_externas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  documento_id BIGINT UNSIGNED NOT NULL,
  rol VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  grado VARCHAR(30) NULL,
  genero ENUM('masculino','femenino') NULL,
  email VARCHAR(120) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  vence DATE NOT NULL,
  caja VARCHAR(160) NULL,
  estado ENUM('pendiente','firmada','cancelada') NOT NULL DEFAULT 'pendiente',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  firmada_en DATETIME NULL,
  firmante_id INT UNSIGNED NULL,
  hash_firmado CHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_fext_token (token_hash),
  KEY idx_tit_fext_doc (documento_id, estado),
  CONSTRAINT fk_tit_fext_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fext_doc FOREIGN KEY (documento_id) REFERENCES tit_documentos(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_fext_usuario FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ Auditorios y salas (módulo independiente)
CREATE TABLE IF NOT EXISTS esp_espacios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  ubicacion VARCHAR(160) NULL,
  capacidad SMALLINT UNSIGNED NULL,
  descripcion VARCHAR(500) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_esp_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario en que puede usarse cada espacio: uno o más intervalos por día de la semana (1 = lunes … 7 = domingo).
CREATE TABLE IF NOT EXISTS esp_horarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  espacio_id INT UNSIGNED NOT NULL,
  dia TINYINT UNSIGNED NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_esp_horario (espacio_id, dia, hora_inicio),
  CONSTRAINT fk_esp_hor_espacio FOREIGN KEY (espacio_id) REFERENCES esp_espacios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Administradores del módulo: los nombra el administrador general de la plataforma.
CREATE TABLE IF NOT EXISTS esp_administradores (
  usuario_id INT UNSIGNED NOT NULL,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_esp_adm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_esp_adm_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personas autorizadas a registrar la ocupación de cada espacio (las asigna el administrador del módulo).
CREATE TABLE IF NOT EXISTS esp_autorizados (
  espacio_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (espacio_id, usuario_id),
  KEY idx_esp_aut_usuario (usuario_id),
  CONSTRAINT fk_esp_aut_espacio FOREIGN KEY (espacio_id) REFERENCES esp_espacios(id) ON DELETE CASCADE,
  CONSTRAINT fk_esp_aut_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_esp_aut_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ocupación registrada. El responsable es quien solicitó el evento (p. ej. el profesor que lo organiza);
-- registrado_por es la persona autorizada que lo capturó en la plataforma.
CREATE TABLE IF NOT EXISTS esp_reservas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  espacio_id INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  descripcion VARCHAR(500) NOT NULL,
  responsable VARCHAR(160) NOT NULL,
  responsable_email VARCHAR(120) NULL,
  registrado_por INT UNSIGNED NOT NULL,
  origen ENUM('manual','titulacion') NOT NULL DEFAULT 'manual',
  tramite_id BIGINT UNSIGNED NULL,
  examen ENUM('previo','definitivo') NULL,
  estado ENUM('activa','cancelada') NOT NULL DEFAULT 'activa',
  cancelada_por INT UNSIGNED NULL,
  cancelada_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_esp_res_dia (espacio_id, fecha, estado),
  KEY idx_esp_res_tramite (tramite_id, examen, estado),
  CONSTRAINT fk_esp_res_espacio FOREIGN KEY (espacio_id) REFERENCES esp_espacios(id),
  CONSTRAINT fk_esp_res_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_esp_res_cancela FOREIGN KEY (cancelada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_esp_res_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO esp_espacios (nombre, ubicacion, orden) VALUES
('Auditorio 120-A', 'Edificio A', 10),
('Sala Multimedia', NULL, 20),
('Sala de usos múltiples', NULL, 30),
('Sala de juntas', NULL, 40),
('Sala de la Dirección', NULL, 50),
('Sala 1 Edificio G', 'Edificio G', 60),
('Sala 2 Edificio G', 'Edificio G', 70),
('Sala de profesores', NULL, 80);

-- Horario inicial: lunes a viernes de 8:00 a 20:00 (el administrador del módulo lo ajusta).
INSERT IGNORE INTO esp_horarios (espacio_id, dia, hora_inicio, hora_fin)
SELECT e.id, d.dia, '08:00:00', '20:00:00'
FROM esp_espacios e
JOIN (SELECT 1 AS dia UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) d
WHERE NOT EXISTS (SELECT 1 FROM esp_horarios h WHERE h.espacio_id = e.id);

-- ================================================================ Exámenes: solicitud al Coordinador y sinodales
-- Los coordinadores, el Director, el Subdirector y la Jefatura de Titulación se toman de Organización.
DROP TABLE IF EXISTS tit_coordinadores;

CREATE TABLE IF NOT EXISTS tit_asignaciones_jurado (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('previo','definitivo','protesta') NOT NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  fecha DATE NOT NULL,
  hora CHAR(5) NOT NULL,
  lugar VARCHAR(200) NOT NULL,
  reserva_id BIGINT UNSIGNED NULL,
  propuesta TEXT NULL,
  coord_nombre VARCHAR(160) NOT NULL,
  coord_genero ENUM('masculino','femenino') NULL,
  coord_email VARCHAR(120) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  vence DATE NOT NULL,
  estado ENUM('solicitada','firmada','cancelada') NOT NULL DEFAULT 'solicitada',
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  firmada_en DATETIME NULL,
  firmante_id INT UNSIGNED NULL,
  documento_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_asig_token (token_hash),
  KEY idx_tit_asig_tramite (tramite_id, estado),
  CONSTRAINT fk_tit_asig_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_asig_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_tit_asig_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_sinodales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  asignacion_id BIGINT UNSIGNED NOT NULL,
  tramite_id BIGINT UNSIGNED NOT NULL,
  calidad ENUM('propietario','suplente') NOT NULL,
  funcion ENUM('presidente','secretario','vocal') NULL,
  orden TINYINT UNSIGNED NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  grado VARCHAR(30) NULL,
  genero ENUM('masculino','femenino') NULL,
  email VARCHAR(120) NOT NULL,
  oficio_doc_id BIGINT UNSIGNED NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  vence DATE NULL,
  estado ENUM('esperando_firma','pendiente','acuse','cancelado') NOT NULL DEFAULT 'esperando_firma',
  enviado_en DATETIME NULL,
  acuse_en DATETIME NULL,
  firmante_id INT UNSIGNED NULL,
  ruta_acuse VARCHAR(500) NULL,
  hash_acuse CHAR(64) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_sin_token (token_hash),
  KEY idx_tit_sin_asig (asignacion_id),
  KEY idx_tit_sin_email (email, estado),
  CONSTRAINT fk_tit_sin_asig FOREIGN KEY (asignacion_id) REFERENCES tit_asignaciones_jurado(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_sin_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_sin_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ Columnas que faltan en instalaciones de prueba anteriores
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN cotejo_tipo ENUM(''sello'',''pades'') NULL AFTER revisado_en, ADD COLUMN cotejo_por INT UNSIGNED NULL AFTER cotejo_tipo, ADD COLUMN cotejo_en DATETIME NULL AFTER cotejo_por, ADD COLUMN cotejo_hash CHAR(64) NULL AFTER cotejo_en, ADD COLUMN cotejo_ruta VARCHAR(500) NULL AFTER cotejo_hash, ADD CONSTRAINT fk_tit_doc_cotejo FOREIGN KEY (cotejo_por) REFERENCES usuarios(id)', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'cotejo_tipo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_en DATETIME NULL AFTER cotejo_ruta', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_en');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_capacidad ENUM(''personal'',''delegada'',''automatica'') NULL AFTER subdir_en', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_capacidad');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN subdir_ruta VARCHAR(500) NULL AFTER subdir_capacidad', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'subdir_ruta');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN pendiente_firmas TINYINT(1) NOT NULL DEFAULT 0 AFTER subdir_ruta', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'pendiente_firmas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_revisiones ADD COLUMN mensaje TEXT NULL AFTER observaciones', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_revisiones' AND COLUMN_NAME = 'mensaje');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_revisiones ADD COLUMN obs_oficio TEXT NULL AFTER mensaje', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_revisiones' AND COLUMN_NAME = 'obs_oficio');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_firmas_subdireccion ADD COLUMN caja VARCHAR(120) NULL AFTER descripcion', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_firmas_subdireccion' AND COLUMN_NAME = 'caja');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_firmas_subdireccion ADD COLUMN rol_firma ENUM(''subdirector'',''director'',''jefe_titulacion'') NOT NULL DEFAULT ''subdirector'' AFTER formato', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_firmas_subdireccion' AND COLUMN_NAME = 'rol_firma');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE tit_firmas_subdireccion SET rol_firma = 'director' WHERE formato = 'oficio_estructura' AND rol_firma = 'subdirector';

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_sinodales ADD COLUMN grado VARCHAR(30) NULL AFTER nombre, ADD COLUMN genero ENUM(''masculino'',''femenino'') NULL AFTER grado', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_sinodales' AND COLUMN_NAME = 'genero');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_firmas_externas ADD COLUMN grado VARCHAR(30) NULL AFTER nombre, ADD COLUMN genero ENUM(''masculino'',''femenino'') NULL AFTER grado', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_firmas_externas' AND COLUMN_NAME = 'genero');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_asignaciones_jurado ADD COLUMN coord_genero ENUM(''masculino'',''femenino'') NULL AFTER coord_nombre', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_asignaciones_jurado' AND COLUMN_NAME = 'coord_genero');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_asignaciones_jurado ADD COLUMN reserva_id BIGINT UNSIGNED NULL AFTER lugar, ADD COLUMN propuesta TEXT NULL AFTER reserva_id', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_asignaciones_jurado' AND COLUMN_NAME = 'propuesta');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_documentos ADD COLUMN cajas TEXT NULL AFTER pendiente_firmas', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_documentos' AND COLUMN_NAME = 'cajas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Trámites históricos (importados del libro de actas, sin cuenta de alumno en la plataforma) para sus constancias.
SET @s = (SELECT IF(COUNT(*) = 1, 'ALTER TABLE tit_tramites MODIFY alumno_id INT UNSIGNED NULL', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_tramites' AND COLUMN_NAME = 'alumno_id' AND IS_NULLABLE = 'NO');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_tramites ADD COLUMN historico TINYINT(1) NOT NULL DEFAULT 0 AFTER responsable_id', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_tramites' AND COLUMN_NAME = 'historico');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Función de cada sinodal propietario en el jurado.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_sinodales ADD COLUMN funcion ENUM(''presidente'',''secretario'',''vocal'') NULL AFTER calidad', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_sinodales' AND COLUMN_NAME = 'funcion');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE tit_sinodales SET funcion = IF(orden = 1, 'presidente', IF(orden = 2, 'secretario', 'vocal')) WHERE funcion IS NULL AND calidad = 'propietario';

-- Firmantes por enlace: además de asesor y coasesor, los revisores (voto aprobatorio).
SET @s = (SELECT IF(COUNT(*) = 1, 'ALTER TABLE tit_firmas_externas MODIFY rol VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_firmas_externas' AND COLUMN_NAME = 'rol' AND DATA_TYPE = 'enum');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_constancias ADD COLUMN tipo VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''revisor_protocolo'' AFTER nombre, ADD COLUMN emisor ENUM(''subdirector'',''jefe_titulacion'') NOT NULL DEFAULT ''subdirector'' AFTER tipo', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND COLUMN_NAME = 'tipo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Cada constancia es de un rol en el trámite (director, codirector, presidente… o el correo del revisor); con su
-- huella se detecta si una corrección cambió su contenido y hay que regenerarla sobre el mismo archivo.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_constancias ADD COLUMN rol VARCHAR(120) NOT NULL DEFAULT '''' AFTER tipo, ADD COLUMN huella CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER vueltas, ADD COLUMN detalle TEXT NULL AFTER huella', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND COLUMN_NAME = 'rol');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
UPDATE tit_constancias SET rol = email WHERE rol = '';

-- Primero el índice nuevo (lo usa la llave foránea del trámite) y después se retiran los anteriores.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_constancias ADD UNIQUE KEY uq_tit_constancia_rol (tramite_id, tipo, rol)', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND INDEX_NAME = 'uq_tit_constancia_rol');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE tit_constancias DROP INDEX uq_tit_constancia_tipo', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND INDEX_NAME = 'uq_tit_constancia_tipo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE tit_constancias DROP INDEX uq_tit_constancia', 'DO 0')
          FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND INDEX_NAME = 'uq_tit_constancia');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE tit_firmas_subdireccion SET formato = 'constancia' WHERE formato = 'constancia_revision';

-- ================================================================ Grado y género (comunes a toda la plataforma)
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN grado VARCHAR(30) NULL AFTER nombre', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'grado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN genero ENUM(''masculino'',''femenino'') NULL AFTER grado', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'genero');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ================================================================ Ajustes
INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
('titulacion_dias_revision', '7', 'Titulación: días hábiles que tiene cada revisor de protocolo; a partir del siguiente el semáforo pasa a rojo.'),
('titulacion_revisores_protocolo', '3', 'Titulación: número de revisores al que el responsable del comité envía cada protocolo.'),
('titulacion_firma_revisores', 'sello', 'Titulación: firma de las revisiones de protocolo (sello o pades).'),
('titulacion_dias_inhabiles', '', 'Titulación: fechas no laborables (AAAA-MM-DD, una por línea) que no cuentan como días hábiles.'),
('titulacion_token_gracia', '14', 'Titulación: días extra tras la fecha límite en que sigue vigente el enlace del correo.'),
('titulacion_max_mb', '5', 'Titulación: tamaño máximo en MB de cada PDF que sube el pasante.'),
('titulacion_vigencia_protocolo', '2', 'Titulación: años de vigencia del protocolo aprobado; vencidos, el examen previo pide el oficio de prórroga.'),
('titulacion_prorroga_anios', '1', 'Titulación: años que concede el oficio de aprobación de prórroga del tema.'),
('titulacion_examen_dias_min', '10', 'Titulación: días hábiles mínimos entre la solicitud y la fecha del examen que elige el pasante.'),
('titulacion_examen_hora_inicio', '09:00', 'Titulación: primera hora en que puede iniciar un examen.'),
('titulacion_examen_hora_fin', '15:00', 'Titulación: hora a la que debe haber terminado un examen.'),
('titulacion_examen_duracion', '120', 'Titulación: minutos que se aparta el auditorio o sala para cada examen.'),
('titulacion_sinodales_propietarios', '3', 'Titulación: sinodales propietarios que propone el pasante.'),
('titulacion_sinodales_suplentes', '2', 'Titulación: sinodales suplentes que propone el pasante.'),
('titulacion_oficio_formato_subdireccion', 'ACAD/FI/{n}/{AAAA}', 'Titulación: número de los oficios que firma la Subdirección o la Dirección ({n} consecutivo, {AAAA} año).'),
('titulacion_oficio_formato_jefatura', 'FI/DEPyG/{n}/{AAAA}', 'Titulación: número de los oficios que firma la Jefatura de Evaluación Profesional y Grado ({n} consecutivo, {AAAA} año).');

INSERT IGNORE INTO ajustes (clave, valor, descripcion)
SELECT 'titulacion_examen_espacio', CAST(id AS CHAR), 'Titulación: auditorio o sala (módulo Auditorios/Salas) donde se realizan los exámenes.'
FROM esp_espacios WHERE nombre = 'Auditorio 120-A';

-- Leyenda oficial del año (común a toda la plataforma).
INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
('institucion_lema_anual', COALESCE((SELECT valor FROM (SELECT valor FROM ajustes WHERE clave = 'titulacion_lema') t), ''), 'Lema oficial del año que se imprime bajo «Patria, Ciencia y Trabajo» en los oficios (se antepone el año). Vacío para omitirlo.');

-- Claves que ya no se usan: las autoridades salen de Organización y el semáforo solo es verde o rojo.
DELETE FROM ajustes WHERE clave IN ('titulacion_director', 'titulacion_director_email', 'titulacion_subdirector', 'titulacion_subdirector_email', 'titulacion_lema',
  'institucion_director_nombre', 'institucion_director_email', 'institucion_subdirector_nombre', 'institucion_subdirector_email', 'titulacion_dias_aviso');
UPDATE ajustes SET descripcion = 'Titulación: días hábiles que tiene cada revisor de protocolo; a partir del siguiente el semáforo pasa a rojo.' WHERE clave = 'titulacion_dias_revision';

-- ================================================================ Concursos (actividades temporales, tablas propias con_*)
CREATE TABLE IF NOT EXISTS con_concursos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  resumen VARCHAR(400) NOT NULL DEFAULT '',
  tematica VARCHAR(200) NOT NULL DEFAULT '',
  bases MEDIUMTEXT NOT NULL,
  estado ENUM('borrador','publicado','cancelado') NOT NULL DEFAULT 'borrador',
  publicacion DATETIME NOT NULL,
  recepcion_fin DATETIME NOT NULL,
  votacion_inicio DATETIME NOT NULL,
  votacion_fin DATETIME NOT NULL,
  premiacion DATETIME NOT NULL,
  premio_1 DECIMAL(10,2) NULL,
  premio_2 DECIMAL(10,2) NULL,
  premio_3 DECIMAL(10,2) NULL,
  max_por_persona TINYINT UNSIGNED NOT NULL DEFAULT 1,
  max_caracteres SMALLINT UNSIGNED NOT NULL DEFAULT 1500,
  creado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_con_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Obras enviadas. «numero» identifica la obra de forma anónima durante el prefiltro y la votación.
CREATE TABLE IF NOT EXISTS con_participaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  concurso_id INT UNSIGNED NOT NULL,
  numero SMALLINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  texto TEXT NOT NULL,
  estado ENUM('enviada','aprobada','rechazada','retirada') NOT NULL DEFAULT 'enviada',
  motivo VARCHAR(600) NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  desempate SMALLINT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_con_numero (concurso_id, numero),
  KEY idx_con_part_usuario (concurso_id, usuario_id),
  CONSTRAINT fk_con_part_concurso FOREIGN KEY (concurso_id) REFERENCES con_concursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Voto anónimo: con_votantes guarda QUIÉN ya votó (sin la opción ni la hora); con_votos guarda el voto con su recibo,
-- sin usuario, sin hora y sin número consecutivo, para que no se pueda cruzar el orden de una tabla con el de la otra.
CREATE TABLE IF NOT EXISTS con_votantes (
  concurso_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (concurso_id, usuario_id),
  CONSTRAINT fk_con_votante_concurso FOREIGN KEY (concurso_id) REFERENCES con_concursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS con_votos (
  recibo CHAR(14) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  concurso_id INT UNSIGNED NOT NULL,
  participacion_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (recibo),
  KEY idx_con_voto_part (concurso_id, participacion_id),
  CONSTRAINT fk_con_voto_concurso FOREIGN KEY (concurso_id) REFERENCES con_concursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Concurso de calaveritas 2026 (queda en borrador: revisar bases y montos, y publicarlo desde Concursos → Gestión).
INSERT IGNORE INTO con_concursos (slug, titulo, resumen, tematica, estado, publicacion, recepcion_fin, votacion_inicio, votacion_fin, premiacion, premio_1, premio_2, premio_3, max_por_persona, max_caracteres, bases) VALUES
('calaveritas-2026', 'Concurso de Calaveritas Literarias 2026',
 'Escribe una calaverita sobre la comunidad de Ingeniería y sus profes. Sin ofender, pero con la picardía de siempre.',
 'La comunidad de Ingeniería y sus profes', 'borrador',
 '2026-10-26 00:00:00', '2026-10-27 23:59:59', '2026-10-28 00:00:00', '2026-10-29 23:59:59', '2026-10-30 16:00:00', 1500.00, 1000.00, 750.00, 2, 1500,
'## Presentación
La Facultad de Ingeniería invita a toda su comunidad a celebrar el Día de Muertos con una de las tradiciones literarias más mexicanas: la calaverita. Las calaveritas son versos breves, con rima y humor, en los que la Muerte (la Catrina, la Flaca, la Huesuda) viene por alguien y se lo lleva al panteón. Nacieron en la prensa del siglo XIX, se popularizaron con los grabados de José Guadalupe Posada y cada 2 de noviembre nos sirven para reírnos de la vida, de la muerte y de nosotros mismos.

## Tema
{tematica}: las clases, los exámenes, los laboratorios, las entregas de proyecto, las desveladas, la cafetería, el estacionamiento… y, por supuesto, las y los profes de Ingeniería.

## Quiénes pueden participar
Estudiantes, profesores y personal de la Facultad con cuenta en Traza FI. Cada persona puede enviar hasta {max_por_persona} calaveritas.

## Requisitos
- Original e inédita, escrita en español.
- De 4 a 10 estrofas; se recomiendan versos octosílabos con rima consonante (por ejemplo ABBA o ABAB). Máximo {max_caracteres} caracteres.
- La Muerte debe aparecer y llevarse a alguien o algo; se vale la picardía y la sátira de la tradición.
- Sin ofender: no se aceptan insultos, groserías, burlas sobre la apariencia, la salud o la vida privada, discriminación de ningún tipo ni acusaciones. Se puede mencionar a profesores o personajes de la comunidad, siempre con respeto y buen humor.
- Se escribe directamente en Traza FI (título y versos), en el apartado Concursos.

## Recepción
Desde la publicación de esta convocatoria y hasta el {recepcion_fin}. Mientras tu calaverita no haya sido revisada puedes corregirla o retirarla.

## Fase 1: prefiltro
Cada calaverita pasa, conforme se recibe, por una revisión de prefiltro que verifica que cumpla estas bases. Las que no las cumplan no pasan a la votación y su autor recibe el aviso en Traza FI, con la posibilidad de enviar otra mientras siga abierta la recepción.

## Fase 2: votación libre
Del {votacion_inicio} al {votacion_fin} las calaveritas aprobadas se publican sin el nombre de su autor y toda la comunidad registrada en Traza FI puede votar. Cada persona tiene un voto y no puede votar por una calaverita propia.

El voto es anónimo: la plataforma registra que ya votaste, pero no por cuál calaverita. Al votar recibes un recibo de voto con el que puedes comprobar, en la página de verificación, que tu voto se contó por la calaverita que elegiste; nadie puede ligar ese recibo con tu identidad.

## Premiación
Ganan los tres primeros lugares por número de votos. Los resultados, con el nombre de quienes ganaron, se publican en Traza FI el {premiacion}, fecha de la premiación.
- Primer lugar: {premio_1}
- Segundo lugar: {premio_2}
- Tercer lugar: {premio_3}

En caso de empate, el comité organizador decide el orden. Cualquier situación no prevista en estas bases la resuelve el comité organizador.

## Datos personales
Solo se publican los nombres de los tres primeros lugares. El resto de las calaveritas se identifica por su número.');

-- Premios del concurso de calaveritas: 1,000 + 500, 750 + 250 y 500 + 250 MXN (solo si aún no se capturaron otros).
UPDATE con_concursos SET premio_1 = 1500.00, premio_2 = 1000.00, premio_3 = 750.00
WHERE slug = 'calaveritas-2026' AND premio_1 IS NULL AND premio_2 IS NULL AND premio_3 IS NULL;
-- Premiación el viernes 30 de octubre a las 16:00 (solo si sigue la hora original de las 12:00).
UPDATE con_concursos SET premiacion = '2026-10-30 16:00:00' WHERE slug = 'calaveritas-2026' AND premiacion = '2026-10-30 12:00:00';

-- ================================================================ Formularios (registros temporales, tablas propias frm_*)
CREATE TABLE IF NOT EXISTS frm_formularios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion MEDIUMTEXT NOT NULL,
  campos MEDIUMTEXT NOT NULL,
  agrupar_por VARCHAR(40) NOT NULL DEFAULT '',
  estado ENUM('borrador','abierto','cerrado') NOT NULL DEFAULT 'borrador',
  cierra_en DATETIME NULL,
  responsables TEXT NULL,
  acceso VARCHAR(200) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_frm_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Autoridades con acceso de consulta y corrección (subdirector, coordinadores_docencia), tomadas de Organización;
-- NULL = aún no configurado (vacío = ninguna).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE frm_formularios ADD COLUMN acceso VARCHAR(200) NULL AFTER responsables', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'frm_formularios' AND COLUMN_NAME = 'acceso');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS frm_respuestas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  formulario_id INT UNSIGNED NOT NULL,
  email VARCHAR(160) NOT NULL,
  datos MEDIUMTEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_frm_email (formulario_id, email),
  CONSTRAINT fk_frm_resp_formulario FOREIGN KEY (formulario_id) REFERENCES frm_formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de profesores para la clasificación de programas de licenciatura (El Universal), según el formato en Word.
INSERT IGNORE INTO frm_formularios (slug, titulo, agrupar_por, estado, descripcion, campos) VALUES
('clasificacion-el-universal-2026', 'Clasificación de programas de licenciatura · Registro de profesores', 'programa', 'abierto',
'La Facultad de Ingeniería participa en la clasificación de programas de licenciatura del periódico El Universal. Si cumples los requisitos, registra tus datos para formar parte de la lista de profesores del programa en el que impartes clase; la selección de los académicos que serán contactados se hace de manera aleatoria.

Es importante que conozcas el programa de licenciatura en el que te registras: te llamarán del número 55 5709 1313 (Periódico El Universal), tenlo presente.

Escribe tus datos en MAYÚSCULAS. Regístrate en un solo programa: aquel en el que eres especialista.',
'[{"k":"programa","label":"Programa de licenciatura en el que te registras","tipo":"select","req":true,"opciones":{"civil":"LICENCIATURA EN INGENIERÍA CIVIL","electronica":"LICENCIATURA EN INGENIERÍA EN ELECTRÓNICA","computacion":"LICENCIATURA EN INGENIERÍA EN COMPUTACIÓN","mecanica":"LICENCIATURA EN INGENIERÍA MECÁNICA"}},
{"k":"institucion","label":"Nombre de la institución","tipo":"texto","req":true,"max":160,"mayusculas":true,"defecto":"UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO"},
{"k":"campus","label":"Nombre del campus, escuela o facultad","tipo":"texto","req":true,"max":160,"mayusculas":true,"defecto":"FACULTAD DE INGENIERÍA"},
{"k":"grado","label":"Grado académico","tipo":"select","req":true,"opciones":{"DOCTORADO":"DOCTORADO","MAESTRÍA":"MAESTRÍA","LICENCIATURA":"LICENCIATURA"}},
{"k":"paterno","label":"Apellido paterno","tipo":"texto","req":true,"max":80,"mayusculas":true},
{"k":"materno","label":"Apellido materno","tipo":"texto","req":false,"max":80,"mayusculas":true},
{"k":"nombres","label":"Nombres","tipo":"texto","req":true,"max":120,"mayusculas":true},
{"k":"telefono","label":"Teléfono (incluye clave lada y extensión)","tipo":"texto","req":true,"max":60,"mayusculas":true},
{"k":"email","label":"Correo electrónico","tipo":"email","req":true,"max":160},
{"k":"horario","label":"Horario en que se te puede contactar","tipo":"texto","req":true,"max":120,"mayusculas":true},
{"k":"req_a","label":"Soy profesor titular de al menos una materia en el periodo que inicia en otoño de 2026 y continúa vigente por lo menos hasta marzo de 2027.","tipo":"confirmar","req":true},
{"k":"req_b","label":"Tengo mínimo cinco años de antigüedad en la institución.","tipo":"confirmar","req":true},
{"k":"req_c","label":"La materia que imparto es obligatoria (no es curso, optativa ni de tronco común).","tipo":"confirmar","req":true},
{"k":"req_d","label":"Imparto al menos una materia en el programa en el que me registro y conozco el programa.","tipo":"confirmar","req":true},
{"k":"req_e","label":"No imparto la materia de idiomas.","tipo":"confirmar","req":true},
{"k":"req_f","label":"Me registro en un solo programa: aquel en el que soy especialista.","tipo":"confirmar","req":true}]');
-- El registro de El Universal lo consultan la Subdirección Académica y los Coordinadores de Docencia.
UPDATE frm_formularios SET acceso = 'subdirector,coordinadores_docencia' WHERE slug = 'clasificacion-el-universal-2026' AND acceso IS NULL;

-- ================================================================ Historial del libro de actas
-- Carga única de los registros del CONTROL DE LIBROS DE LICENCIATURAS (hoja y fila de origen). INSERT IGNORE: al
-- volver a ejecutar la migración no se duplican ni se pisan los datos que el personal ya completó en el visor.
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): aquí se cargaban datos personales (historial de titulación con nombres y números de cuenta).
-- El repositorio conserva solo la estructura. La carga original está, fuera del control de versiones, en
-- database/datos_privados/migraciones/017_titulacion.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
