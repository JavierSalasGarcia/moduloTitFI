-- Titulación (licenciatura) y Auditorios/Salas: migración única de todo lo posterior a 016.
-- Es repetible: puede ejecutarse completa las veces que haga falta, aunque una parte ya esté aplicada
-- (tablas con IF NOT EXISTS, columnas e índices solo si faltan, ajustes con INSERT IGNORE).
-- Se aplica a mano en pruebas y producción; copiar las carpetas de los módulos no ejecuta SQL.

-- ================================================================ Trámites
CREATE TABLE IF NOT EXISTS tit_tramites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  nivel ENUM('licenciatura','posgrado') NOT NULL DEFAULT 'licenciatura',
  alumno_id INT UNSIGNED NOT NULL,
  tipo ENUM('sin_trabajo','con_trabajo') NOT NULL,
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  variante VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  estado ENUM('abierto','concluido','cancelado') NOT NULL DEFAULT 'abierto',
  etapa_actual VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'datos',
  titulo VARCHAR(500) NULL,
  datos MEDIUMTEXT NOT NULL,
  resultado VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  responsable_id INT UNSIGNED NULL,
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

-- Mensajes al pasante (dictámenes de cada vuelta y avisos del departamento): «Titulación → Mensajes».
CREATE TABLE IF NOT EXISTS tit_mensajes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  revision_id BIGINT UNSIGNED NULL,
  remitente_id INT UNSIGNED NULL,
  remitente VARCHAR(200) NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  leido_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_msj_tramite (tramite_id, creado_en),
  CONSTRAINT fk_tit_msj_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_tit_msj_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================ Configuración de formatos y documentos
CREATE TABLE IF NOT EXISTS tit_formatos_cfg (
  clave VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  personalizado TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  nombre VARCHAR(200) NULL,
  firma VARCHAR(200) NULL,
  etapa VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidades VARCHAR(200) CHARACTER SET ascii COLLATE ascii_bin NULL,
  plantilla_ruta VARCHAR(300) NULL,
  spec MEDIUMTEXT NULL,
  actualizado_por INT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clave),
  CONSTRAINT fk_tit_fmt_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Constancia de revisión por revisor y protocolo; la firma la Subdirección cuando el protocolo se aprueba.
CREATE TABLE IF NOT EXISTS tit_constancias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(120) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  vueltas VARCHAR(40) NOT NULL,
  documento_id BIGINT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tit_constancia (tramite_id, email),
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
  rol ENUM('asesor','coasesor') NOT NULL,
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
('titulacion_examen_dias_min', '10', 'Titulación: días hábiles mínimos entre la solicitud y la fecha del examen que elige el pasante.'),
('titulacion_examen_hora_inicio', '09:00', 'Titulación: primera hora en que puede iniciar un examen.'),
('titulacion_examen_hora_fin', '15:00', 'Titulación: hora a la que debe haber terminado un examen.'),
('titulacion_examen_duracion', '120', 'Titulación: minutos que se aparta el auditorio o sala para cada examen.'),
('titulacion_sinodales_propietarios', '3', 'Titulación: sinodales propietarios que propone el pasante.'),
('titulacion_sinodales_suplentes', '2', 'Titulación: sinodales suplentes que propone el pasante.');

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
