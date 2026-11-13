-- ============================================================================================================
-- Traza FI · Actualización acumulativa de la base de datos (generada: 2026-11-13 17:34)
--
-- Lleva la base desde cualquier versión anterior hasta la actual. Es repetible: se puede importar las veces que
-- haga falta (phpMyAdmin → Importar, o mysql < actualizar.sql); solo crea o cambia lo que falta y no borra datos.
-- Requiere una base ya instalada con schema.sql. No editar a mano: se genera con database/construir_actualizar.php
-- a partir de las migraciones (41 fuentes, en orden).
-- ============================================================================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------------------------------------------------------
-- schema_reuniones.sql
-- ------------------------------------------------------------------------------------------------------------
-- REUNIONES-002 · Migración del módulo Reuniones
-- Ejecutar después de schema.sql y seed_departamentos.sql.
-- Esta migración no crea controladores, vistas ni tareas programadas.

SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios MODIFY COLUMN rol enum(''admin'',''titular'',''operativo'',''profesor'',''alumno'',''coordinador'') NOT NULL DEFAULT ''operativo''', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol' AND COLUMN_TYPE NOT LIKE '%coordinador%');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS reuniones_posgrados (
  id smallint unsigned NOT NULL AUTO_INCREMENT,
  codigo varchar(10) NOT NULL,
  nombre varchar(180) NOT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_posgrado_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_coordinadores (
  id int unsigned NOT NULL AUTO_INCREMENT,
  posgrado_id smallint unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  asignado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_coordinador_activo (posgrado_id, usuario_id, activo),
  KEY idx_reuniones_coordinador_usuario (usuario_id),
  CONSTRAINT fk_reuniones_coord_posgrado FOREIGN KEY (posgrado_id) REFERENCES reuniones_posgrados (id),
  CONSTRAINT fk_reuniones_coord_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones (
  id int unsigned NOT NULL AUTO_INCREMENT,
  asunto varchar(180) NOT NULL,
  descripcion text DEFAULT NULL,
  creador_id int unsigned NOT NULL,
  organizador_id int unsigned NOT NULL,
  posgrado_id smallint unsigned DEFAULT NULL,
  estado enum('precargada','borrador','convocada','en_respuesta','fecha_confirmada','finalizada','cancelada') NOT NULL DEFAULT 'borrador',
  rango_inicio date NOT NULL,
  rango_fin date NOT NULL,
  hora_default_inicio time NOT NULL DEFAULT '08:00:00',
  hora_default_fin time NOT NULL DEFAULT '20:00:00',
  fecha_confirmada date DEFAULT NULL,
  hora_confirmada_inicio time DEFAULT NULL,
  hora_confirmada_fin time DEFAULT NULL,
  finalizada_en datetime DEFAULT NULL,
  retener_hasta date DEFAULT NULL,
  zip_archivo varchar(255) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_reuniones_estado_fecha (estado, rango_inicio, rango_fin),
  KEY idx_reuniones_organizador (organizador_id, estado),
  KEY idx_reuniones_posgrado (posgrado_id),
  FULLTEXT KEY ft_reuniones_busqueda (asunto, descripcion),
  CONSTRAINT fk_reuniones_creador FOREIGN KEY (creador_id) REFERENCES usuarios (id),
  CONSTRAINT fk_reuniones_organizador FOREIGN KEY (organizador_id) REFERENCES usuarios (id),
  CONSTRAINT fk_reuniones_posgrado FOREIGN KEY (posgrado_id) REFERENCES reuniones_posgrados (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_participantes (
  id int unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  usuario_id int unsigned DEFAULT NULL,
  correo varchar(180) NOT NULL,
  nombre varchar(120) DEFAULT NULL,
  tipo enum('organizador','invitado','coordinador','externo') NOT NULL DEFAULT 'invitado',
  token_hash char(64) DEFAULT NULL,
  token_expira_en datetime DEFAULT NULL,
  estado_invitacion enum('pendiente','enviada','aceptada','rechazada','revocada') NOT NULL DEFAULT 'pendiente',
  respondio_en datetime DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_participante_correo (reunion_id, correo),
  UNIQUE KEY uq_reuniones_participante_token (token_hash),
  KEY idx_reuniones_participante_usuario (usuario_id),
  CONSTRAINT fk_reuniones_participante_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_participante_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_horarios (
  id int unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  inicio datetime NOT NULL,
  fin datetime NOT NULL,
  creado_por int unsigned NOT NULL,
  activa tinyint(1) NOT NULL DEFAULT 1,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_horario (reunion_id, inicio, fin),
  KEY idx_reuniones_horario_reunion (reunion_id, inicio),
  CONSTRAINT fk_reuniones_horario_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_horario_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_disponibilidades (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  horario_id int unsigned NOT NULL,
  participante_id int unsigned NOT NULL,
  estado enum('disponible','no_disponible','sin_respuesta') NOT NULL DEFAULT 'sin_respuesta',
  version int unsigned NOT NULL DEFAULT 1,
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_disponibilidad (horario_id, participante_id),
  KEY idx_reuniones_disponibilidad_estado (horario_id, estado),
  CONSTRAINT fk_reuniones_disp_horario FOREIGN KEY (horario_id) REFERENCES reuniones_horarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_disp_participante FOREIGN KEY (participante_id) REFERENCES reuniones_participantes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_mensajes (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  participante_id int unsigned NOT NULL,
  contenido text NOT NULL,
  tipo enum('normal','urgente') NOT NULL DEFAULT 'normal',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_reuniones_mensaje_reunion (reunion_id, creado_en),
  FULLTEXT KEY ft_reuniones_mensaje_contenido (contenido),
  CONSTRAINT fk_reuniones_mensaje_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_mensaje_participante FOREIGN KEY (participante_id) REFERENCES reuniones_participantes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_notificaciones (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  mensaje_id bigint unsigned DEFAULT NULL,
  destinatario varchar(180) NOT NULL,
  tipo_mensaje enum('normal','urgente') NOT NULL,
  evento enum('invitacion','disponibilidad','confirmacion','chat','finalizacion') NOT NULL,
  estado_entrega enum('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
  intentos smallint unsigned NOT NULL DEFAULT 0,
  ultimo_error text DEFAULT NULL,
  enviado_en datetime DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_reuniones_notificacion_pendiente (estado_entrega, creado_en),
  KEY idx_reuniones_notificacion_reunion (reunion_id),
  CONSTRAINT fk_reuniones_notif_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_notif_mensaje FOREIGN KEY (mensaje_id) REFERENCES reuniones_mensajes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_archivos (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  subido_por int unsigned DEFAULT NULL,
  nombre_original varchar(255) NOT NULL,
  ruta_storage varchar(255) NOT NULL,
  extension varchar(10) NOT NULL,
  mime varchar(120) NOT NULL,
  tamano_bytes int unsigned NOT NULL,
  tipo enum('inicial','complementario','respaldo_chat') NOT NULL DEFAULT 'complementario',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_reuniones_archivo_reunion (reunion_id, creado_en),
  CONSTRAINT fk_reuniones_archivo_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_archivo_usuario FOREIGN KEY (subido_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_categorias (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  nombre varchar(80) NOT NULL,
  color char(7) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_reuniones_categoria_usuario_nombre (usuario_id, nombre),
  CONSTRAINT fk_reuniones_categoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_categoria_relacion (
  reunion_id int unsigned NOT NULL,
  categoria_id int unsigned NOT NULL,
  PRIMARY KEY (reunion_id, categoria_id),
  CONSTRAINT fk_reuniones_categoria_rel_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_categoria_rel_categoria FOREIGN KEY (categoria_id) REFERENCES reuniones_categorias (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_auditoria (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  reunion_id int unsigned NOT NULL,
  usuario_id int unsigned DEFAULT NULL,
  accion varchar(80) NOT NULL,
  datos_json json DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_reuniones_auditoria_reunion (reunion_id, creado_en),
  CONSTRAINT fk_reuniones_auditoria_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS reuniones_respaldo (
  reunion_id int unsigned NOT NULL,
  ruta_zip varchar(255) NOT NULL,
  hash_sha256 char(64) NOT NULL,
  generado_en datetime NOT NULL,
  generado_por int unsigned DEFAULT NULL,
  descargas int unsigned NOT NULL DEFAULT 0,
  ultimo_acceso_en datetime DEFAULT NULL,
  PRIMARY KEY (reunion_id),
  CONSTRAINT fk_reuniones_respaldo_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reuniones_respaldo_usuario FOREIGN KEY (generado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- seed_reuniones.sql
-- ------------------------------------------------------------------------------------------------------------
-- REUNIONES-002 · Catálogo inicial de posgrados.
-- No asigna coordinadores a usuarios reales.

INSERT INTO reuniones_posgrados (codigo, nombre)
VALUES
  ('MCI', CONVERT(0x4D6165737472C3AD6120656E204369656E63696173206465206C6120496E67656E696572C3AD61 USING utf8mb4)),
  ('DCI', CONVERT(0x446F63746F7261646F20656E204369656E63696173206465206C6120496E67656E696572C3AD61 USING utf8mb4)),
  ('MIAA', CONVERT(0x4D6165737472C3AD6120656E20496E74656C6967656E636961204172746966696369616C2041706C6963616461 USING utf8mb4)),
  ('MMT', CONVERT(0x4D6165737472C3AD6120656E204D6F76696C696461642079205472616E73706F727465 USING utf8mb4)),
  ('MCS', CONVERT(0x4D6165737472C3AD6120656E20436164656E612064652053756D696E697374726F USING utf8mb4))
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;

-- ------------------------------------------------------------------------------------------------------------
-- schema_documentos.sql
-- ------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS archivo_documentos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, subido_por INT UNSIGNED NOT NULL,
          departamento_id SMALLINT UNSIGNED NOT NULL, coordinacion_id SMALLINT UNSIGNED NULL, programa_id SMALLINT UNSIGNED NULL,
          titulo VARCHAR(200) NOT NULL, tipo_documento VARCHAR(100) NOT NULL DEFAULT '', folio VARCHAR(100) NOT NULL DEFAULT '',
          fecha_documento DATE NULL, emisor VARCHAR(200) NOT NULL DEFAULT '', destinatario VARCHAR(200) NOT NULL DEFAULT '',
          palabras_clave TEXT NOT NULL, resumen TEXT NOT NULL, nivel ENUM('restringida','confidencial') NOT NULL DEFAULT 'restringida',
          estado ENUM('borrador','indizado') NOT NULL DEFAULT 'borrador', version INT UNSIGNED NOT NULL DEFAULT 1,
          creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL, indizado_en DATETIME NULL,
          INDEX (subido_por,estado,creado_en), INDEX (departamento_id,creado_en), INDEX (coordinacion_id), INDEX(programa_id), INDEX(fecha_documento), INDEX(folio),
          FULLTEXT KEY busqueda (titulo,tipo_documento,folio,emisor,destinatario,palabras_clave,resumen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_accesos (
          documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL, permiso ENUM('leer','responder') NOT NULL DEFAULT 'leer',
          otorgado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, PRIMARY KEY(documento_id,usuario_id), INDEX(usuario_id),
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_acciones (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          tipo VARCHAR(40) NOT NULL, descripcion TEXT NOT NULL, responsable_id INT UNSIGNED NULL, vence_en DATE NULL,
          estado ENUM('registrada','pendiente','completada') NOT NULL DEFAULT 'registrada', creado_en DATETIME NOT NULL, completado_en DATETIME NULL,
          INDEX(documento_id,creado_en), INDEX(responsable_id,estado), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_adjuntos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          clave VARCHAR(40) NOT NULL UNIQUE, nombre_original VARCHAR(200) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INT UNSIGNED NOT NULL,
          sha256 CHAR(64) NOT NULL, tipo ENUM('original','respuesta','anexo') NOT NULL, creado_en DATETIME NOT NULL,
          INDEX(documento_id,tipo), INDEX(sha256), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_extracciones (
          documento_id BIGINT UNSIGNED PRIMARY KEY, version_documento INT UNSIGNED NOT NULL, estado ENUM('procesando','lista','error') NOT NULL,
          resultado MEDIUMTEXT NULL, solicitado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL,
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- schema_portal_consultas.sql
-- ------------------------------------------------------------------------------------------------------------
-- Portada conversacional y proveedor opcional NVIDIA NIM.
-- Idempotente: puede ejecutarse más de una vez.
INSERT INTO ajustes (clave, valor, descripcion) VALUES
('ia_nvidia_api_key', '', 'API key de NVIDIA NIM (opcional; vacío = motor local)'),
('ia_nvidia_modelo', 'meta/llama-3.1-8b-instruct', 'Modelo de chat de NVIDIA NIM'),
('ia_nvidia_url', 'https://integrate.api.nvidia.com/v1/chat/completions', 'Endpoint compatible con OpenAI de NVIDIA NIM')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- ------------------------------------------------------------------------------------------------------------
-- migrations/006_reuniones_minutas.sql
-- ------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reuniones_minutas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reunion_id INT UNSIGNED NULL,
 autor_id INT UNSIGNED NOT NULL,
 subido_por_email VARCHAR(160) NOT NULL DEFAULT '',
 asunto VARCHAR(200) NOT NULL,
 fecha DATETIME NOT NULL,
 calidad VARCHAR(30) NOT NULL DEFAULT 'profesor',
 convocante VARCHAR(200) NOT NULL DEFAULT '',
 resumen MEDIUMTEXT NULL,
 archivo VARCHAR(255) NOT NULL DEFAULT '',
 nombre_archivo VARCHAR(160) NOT NULL DEFAULT '',
 fila_registro_id BIGINT UNSIGNED NULL,
 creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY fila_registro (fila_registro_id),
 KEY reunion (reunion_id), KEY autor_fecha (autor_id,fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reuniones_minuta_presentes (
 minuta_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(160) NOT NULL,
 enviado DATETIME NULL,
 PRIMARY KEY (minuta_id,email), KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reuniones_historial_enlaces (
 autor_id INT UNSIGNED NOT NULL,
 email VARCHAR(160) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (token_hash), KEY autor_email (autor_id,email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/008_generalizar_asuntos.sql
-- ------------------------------------------------------------------------------------------------------------
-- Compatibilidad si la migración 007 anterior (tablas sa_*) ya fue aplicada.
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expedientes')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes'),
  'RENAME TABLE sa_expedientes TO asunto_expedientes', 'DO 0'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_accesos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_accesos'),
  'RENAME TABLE sa_expediente_accesos TO asunto_expediente_accesos', 'DO 0'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_archivos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_archivos'),
  'RENAME TABLE sa_expediente_archivos TO asunto_expediente_archivos', 'DO 0'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sa_expediente_eventos')
  AND NOT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expediente_eventos'),
  'RENAME TABLE sa_expediente_eventos TO asunto_expediente_eventos', 'DO 0'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes')
  AND NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'asunto_expedientes' AND column_name = 'oficina'),
  "ALTER TABLE asunto_expedientes ADD COLUMN oficina VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'SA' AFTER id",
  'DO 0'
); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/007_asuntos.sql
-- ------------------------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asunto_expedientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficina VARCHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  folio VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  asunto VARCHAR(500) NOT NULL DEFAULT '',
  personas_caso TEXT NOT NULL,
  fecha DATE NOT NULL,
  estado ENUM('abierto','en_seguimiento','en_espera','atendido','cerrado') NOT NULL DEFAULT 'abierto',
  creado_por INT UNSIGNED NOT NULL,
  actualizado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asunto_folio (oficina, folio),
  KEY idx_asunto_estado_fecha (oficina, estado, fecha),
  CONSTRAINT fk_asunto_expediente_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_asunto_expediente_actualizador FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asunto_expediente_accesos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  expediente_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(120) NOT NULL,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asunto_acceso (expediente_id, email),
  KEY idx_asunto_acceso_email (email),
  CONSTRAINT fk_asunto_acceso_expediente FOREIGN KEY (expediente_id) REFERENCES asunto_expedientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_asunto_acceso_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asunto_expediente_archivos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  expediente_id BIGINT UNSIGNED NOT NULL,
  subido_por INT UNSIGNED NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  ruta VARCHAR(500) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_asunto_archivo_expediente (expediente_id),
  CONSTRAINT fk_asunto_archivo_expediente FOREIGN KEY (expediente_id) REFERENCES asunto_expedientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_asunto_archivo_usuario FOREIGN KEY (subido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asunto_expediente_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  expediente_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_asunto_evento_expediente (expediente_id, creado_en),
  CONSTRAINT fk_asunto_evento_expediente FOREIGN KEY (expediente_id) REFERENCES asunto_expedientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_asunto_evento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/009_firma_pades_pki.sql
-- ------------------------------------------------------------------------------------------------------------
-- Certificados individuales y firma remota PDF Advanced Electronic Signatures
-- (PAdES), estándar de firma digital incorporada al documento PDF.

CREATE TABLE IF NOT EXISTS firma_certificados (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int(10) unsigned NOT NULL,
  serie varchar(64) NOT NULL,
  sujeto varchar(500) NOT NULL,
  huella_sha256 char(64) NOT NULL,
  pfx_path varchar(500) NOT NULL COMMENT 'Ruta absoluta fuera de public; nunca se entrega al usuario',
  secreto_envuelto text NOT NULL COMMENT 'Contraseña del PKCS#12 cifrada por el servicio criptográfico',
  valido_desde datetime NOT NULL,
  valido_hasta datetime NOT NULL,
  estado enum('activo','revocado','expirado') NOT NULL DEFAULT 'activo',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  revocado_en timestamp NULL DEFAULT NULL,
  motivo_revocacion varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_cert_serie (serie),
  KEY idx_firma_cert_usuario_estado (usuario_id, estado),
  CONSTRAINT firma_certificados_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS firma_operaciones (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  solicitud_id int(10) unsigned NOT NULL,
  firmante_id int(10) unsigned NOT NULL,
  documento_id int(10) unsigned NOT NULL,
  certificado_id int(10) unsigned NOT NULL,
  campo_pdf varchar(100) NOT NULL,
  segundo_factor enum('totp','webauthn') NOT NULL,
  desafio_sha256 char(64) NOT NULL,
  entrada_sha256 char(64) NOT NULL,
  salida_sha256 char(64) NOT NULL,
  resultado enum('firmado','fallido') NOT NULL DEFAULT 'firmado',
  detalle varchar(500) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_operacion_doc_firmante (documento_id, firmante_id),
  KEY idx_firma_operacion_solicitud (solicitud_id),
  KEY idx_firma_operacion_certificado (certificado_id),
  CONSTRAINT firma_operaciones_solicitud_fk FOREIGN KEY (solicitud_id) REFERENCES firma_solicitudes (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_firmante_fk FOREIGN KEY (firmante_id) REFERENCES firma_firmantes (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_documento_fk FOREIGN KEY (documento_id) REFERENCES firma_documentos (id) ON DELETE CASCADE,
  CONSTRAINT firma_operaciones_certificado_fk FOREIGN KEY (certificado_id) REFERENCES firma_certificados (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS firma_segundo_factor_usos (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int(10) unsigned NOT NULL,
  codigo_hash char(64) NOT NULL,
  usado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_firma_2fa_usuario_codigo (usuario_id, codigo_hash),
  KEY idx_firma_2fa_fecha (usado_en),
  CONSTRAINT firma_2fa_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/014_seguridad_otp.sql
-- ------------------------------------------------------------------------------------------------------------
-- Los códigos temporales dejan de almacenarse en texto claro.
-- Los códigos existentes expiran en diez minutos y se invalidan al desplegar.
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios_codigos_otp MODIFY COLUMN codigo VARCHAR(255) NOT NULL', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_codigos_otp' AND COLUMN_NAME = 'codigo' AND (DATA_TYPE <> 'varchar' OR CHARACTER_MAXIMUM_LENGTH < 255));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/015_estructura_organizacional.sql
-- ------------------------------------------------------------------------------------------------------------
-- Catálogo organizacional compartido para responsables, NAB y CAP.
-- No elimina ni modifica las tablas heredadas de Reuniones.

CREATE TABLE IF NOT EXISTS departamento_responsables (
  id int unsigned NOT NULL AUTO_INCREMENT,
  departamento_id smallint unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  cargo enum('titular','jefe','coordinador','encargado','secretario') NOT NULL,
  fecha_inicio date NOT NULL,
  fecha_fin date DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  observaciones varchar(255) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_departamento_responsable_periodo (departamento_id, usuario_id, cargo, fecha_inicio),
  KEY idx_departamento_responsable_vigente (departamento_id, cargo, activo, fecha_fin),
  KEY idx_departamento_responsable_usuario (usuario_id, activo),
  CONSTRAINT fk_departamento_responsable_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
  CONSTRAINT fk_departamento_responsable_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS programa_responsables (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id smallint unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  cargo enum('coordinador','responsable','secretario_academico') NOT NULL DEFAULT 'coordinador',
  fecha_inicio date NOT NULL,
  fecha_fin date DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  observaciones varchar(255) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_programa_responsable_periodo (programa_id, usuario_id, cargo, fecha_inicio),
  KEY idx_programa_responsable_vigente (programa_id, cargo, activo, fecha_fin),
  KEY idx_programa_responsable_usuario (usuario_id, activo),
  CONSTRAINT fk_programa_responsable_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
  CONSTRAINT fk_programa_responsable_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS programa_organos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id smallint unsigned NOT NULL,
  tipo enum('NAB','CAP') NOT NULL,
  nombre varchar(180) NOT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_programa_organo_tipo (programa_id, tipo),
  KEY idx_programa_organo_activo (tipo, activo),
  CONSTRAINT fk_programa_organo_programa FOREIGN KEY (programa_id) REFERENCES programas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS programa_organo_miembros (
  id int unsigned NOT NULL AUTO_INCREMENT,
  organo_id int unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  cargo enum('presidente','coordinador','secretario','vocal','miembro') NOT NULL DEFAULT 'miembro',
  fecha_inicio date NOT NULL,
  fecha_fin date DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  observaciones varchar(255) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_organo_miembro_periodo (organo_id, usuario_id, cargo, fecha_inicio),
  KEY idx_organo_miembro_vigente (organo_id, activo, fecha_fin),
  KEY idx_organo_miembro_usuario (usuario_id, activo),
  CONSTRAINT fk_organo_miembro_organo FOREIGN KEY (organo_id) REFERENCES programa_organos (id) ON DELETE CASCADE,
  CONSTRAINT fk_organo_miembro_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/016_profesores_por_programa.sql
-- ------------------------------------------------------------------------------------------------------------
-- Adscripción académica de profesores a uno o varios programas educativos.
-- Es independiente de usuarios.programa_id, que representa el programa principal
-- de estudiantes y no admite relaciones múltiples.

CREATE TABLE IF NOT EXISTS programa_profesores (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id smallint unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  tipo_vinculo enum('docente','responsable_academico','colaborador') NOT NULL DEFAULT 'docente',
  fecha_inicio date NOT NULL,
  fecha_fin date DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  observaciones varchar(255) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_programa_profesor_periodo (programa_id, usuario_id, fecha_inicio),
  KEY idx_programa_profesor_vigente (programa_id, activo, fecha_fin),
  KEY idx_programa_profesor_usuario (usuario_id, activo),
  CONSTRAINT fk_programa_profesor_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
  CONSTRAINT fk_programa_profesor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/017_titulacion.sql
-- ------------------------------------------------------------------------------------------------------------
-- Titulación (licenciatura), Auditorios/Salas, Notificaciones, enlaces de descarga, Concursos y Formularios: migración única de todo lo posterior a 016.
-- Es repetible: puede ejecutarse completa las veces que haga falta, aunque una parte ya esté aplicada
-- (tablas con IF NOT EXISTS, columnas e índices solo si faltan, ajustes con INSERT IGNORE).
-- Se aplica a mano en pruebas y producción; copiar las carpetas de los módulos no ejecuta SQL.

-- El archivo está en UTF-8: así se lee bien aunque el cliente (consola mysql, phpMyAdmin) use otro juego de caracteres.

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

-- ------------------------------------------------------------------------------------------------------------
-- migrations/018_anonimato_encuestas.sql
-- ------------------------------------------------------------------------------------------------------------
-- Desvincula de cuentas las entradas históricas de auditoría del flujo de voto.
-- Conserva qué operación ocurrió y su resultado, pero elimina actor y usuario.
-- Las tablas funcionales mantienen únicamente:
--   encuesta_participantes: que una cuenta ya participó en una encuesta;
--   encuesta_votos: recibo y opción, sin usuario ni FK hacia usuarios.

UPDATE auditoria_acciones
SET actor_id = NULL,
    actor_email = '',
    usuario_id = NULL,
    usuario_email = ''
WHERE accion = 'encuestas::votar'
   OR accion = 'BD: INSERT INTO encuesta_participantes'
   OR accion = 'BD: INSERT INTO encuesta_votos';

-- ------------------------------------------------------------------------------------------------------------
-- migrations/019_oficios_enviados.sql
-- ------------------------------------------------------------------------------------------------------------
-- Archivo de oficios, memorándums y circulares que cada coordinación o
-- departamento envía a otras dependencias, profesores o alumnos.
-- Cada registro conserva el PDF firmado en storage/oficios/<departamento>/.

CREATE TABLE IF NOT EXISTS oficios_enviados (
  id int unsigned NOT NULL AUTO_INCREMENT,
  departamento_id smallint unsigned NOT NULL,
  tipo enum('memorandum','oficio','circular','otro') NOT NULL DEFAULT 'memorandum',
  folio varchar(60) NOT NULL,
  fecha date NOT NULL,
  asunto varchar(250) NOT NULL,
  destinatarios varchar(500) NOT NULL,
  firmante varchar(160) NOT NULL DEFAULT '',
  notas text DEFAULT NULL,
  archivo varchar(255) NOT NULL,
  archivo_nombre varchar(200) NOT NULL,
  sha256 char(64) NOT NULL,
  creado_por int unsigned NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_oficio_folio (departamento_id, folio),
  KEY idx_oficio_fecha (departamento_id, fecha),
  CONSTRAINT fk_oficio_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos (id),
  CONSTRAINT fk_oficio_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/020_cambio_titulares_subdireccion_investigacion.sql
-- ------------------------------------------------------------------------------------------------------------
-- Cambio de titulares y cuentas institucionales (carga de datos de cuentas).
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/020_cambio_titulares_subdireccion_investigacion.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
DO 0;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/021_cuentas_subdireccion_y_admin.sql
-- ------------------------------------------------------------------------------------------------------------
-- Cuentas institucionales de la Subdirección Académica y administración (carga de datos de cuentas).
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/021_cuentas_subdireccion_y_admin.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
DO 0;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/022_genero_no_binario.sql
-- ------------------------------------------------------------------------------------------------------------
-- Género «No binario» en el registro y el perfil. Repetible: MODIFY deja el mismo resultado si ya está aplicado.
-- Las personas con este valor reciben el saludo neutro («Hola …»), igual que quien no indica género.
ALTER TABLE usuarios MODIFY COLUMN genero ENUM('masculino','femenino','no_binario') NULL;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/023_justificantes_faltas.sql
-- ------------------------------------------------------------------------------------------------------------
-- Oficios para justificar faltas de alumnos (Coordinadores de Docencia y Subdirección Académica). Repetible.

CREATE TABLE IF NOT EXISTS jus_oficios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  firmante VARCHAR(40) CHARACTER SET ascii NOT NULL,
  numero VARCHAR(80) NOT NULL DEFAULT '',
  fecha DATE NOT NULL,
  asunto VARCHAR(200) NOT NULL,
  destinatario VARCHAR(200) NOT NULL DEFAULT '',
  saludo VARCHAR(200) NOT NULL DEFAULT '',
  cuerpo TEXT NOT NULL,
  periodo VARCHAR(250) NOT NULL DEFAULT '',
  motivo VARCHAR(250) NOT NULL DEFAULT '',
  alumnos TEXT NOT NULL,
  firmante_nombre VARCHAR(200) NOT NULL,
  firmante_cargo VARCHAR(250) NOT NULL,
  archivo VARCHAR(255) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_jus_usuario (usuario_id, creado_en),
  KEY idx_jus_firmante (firmante, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jus_adjuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficio_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(200) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_jus_adj (oficio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/024_roles_multiples.sql
-- ------------------------------------------------------------------------------------------------------------
-- Roles múltiples por persona, cargos Titular/Asistente y avisos dirigidos a varios programas.
-- Repetible: solo crea o cambia lo que falta y no borra datos.

-- Cargo principal de la cuenta dentro de su departamento o coordinación (Titular de… / Asistente de…).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN cargo ENUM(''titular'',''asistente'') NULL DEFAULT NULL AFTER rol', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cargo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Roles adicionales: la persona conserva su rol principal en usuarios.rol y suma los que aquí se registren.
-- Ejemplo: profesor en IC e ISC (dos filas con programa) y, además, titular de una coordinación de docencia.
CREATE TABLE IF NOT EXISTS usuario_roles (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  rol enum('admin','titular','operativo','profesor','alumno','coordinador') NOT NULL,
  departamento_id smallint unsigned NOT NULL DEFAULT 0,
  programa_id smallint unsigned NOT NULL DEFAULT 0,
  cargo enum('titular','asistente') DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_rol (usuario_id, rol, departamento_id, programa_id),
  KEY idx_usuario_roles_rol (rol, departamento_id, programa_id),
  CONSTRAINT fk_usuario_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Avisos: a quién se dirigen (alumnos, profesores o todos) y a cuántos programas a la vez.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE comunicados ADD COLUMN destinatarios ENUM(''todos'',''alumno'',''profesor'') NOT NULL DEFAULT ''todos'' AFTER alcance', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comunicados' AND COLUMN_NAME = 'destinatarios');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

CREATE TABLE IF NOT EXISTS comunicado_programas (
  comunicado_id int unsigned NOT NULL,
  programa_id smallint unsigned NOT NULL,
  PRIMARY KEY (comunicado_id, programa_id),
  KEY idx_comunicado_programa (programa_id),
  CONSTRAINT fk_comunicado_programa_com FOREIGN KEY (comunicado_id) REFERENCES comunicados (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Los avisos ya publicados para un programa pasan a la nueva tabla.
INSERT IGNORE INTO comunicado_programas (comunicado_id, programa_id)
SELECT id, programa_id FROM comunicados WHERE alcance = 'programa' AND programa_id IS NOT NULL;

-- Concursos: calaveritas favoritas de cada persona (de ellas elige a su preferida al votar). No son el voto.
CREATE TABLE IF NOT EXISTS con_favoritos (
  concurso_id int unsigned NOT NULL,
  usuario_id int unsigned NOT NULL,
  numero int unsigned NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (concurso_id, usuario_id, numero),
  CONSTRAINT fk_con_favorito_concurso FOREIGN KEY (concurso_id) REFERENCES con_concursos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cargos: los antiguos departamentos «Titular de la …» se unifican con su dependencia y el cargo pasa a la cuenta.
-- Subdirección Académica, Subdirección Administrativa y Coordinación de Planeación (por clave, no por id).
UPDATE usuarios u
  JOIN departamentos o ON o.id = u.departamento_id
  JOIN departamentos n ON n.clave = REPLACE(o.clave, 'titular_de_la_', '')
   SET u.cargo = 'titular', u.departamento_id = n.id
 WHERE u.rol = 'titular'
   AND o.clave IN ('titular_de_la_subdireccion_academica', 'titular_de_la_subdireccion_administrativa', 'titular_de_la_coordinacion_de_planeacion');
UPDATE usuarios SET cargo = 'titular' WHERE cargo IS NULL AND rol = 'titular' AND departamento_id IS NOT NULL;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/025_historico_sistema_anterior.sql
-- ------------------------------------------------------------------------------------------------------------
-- Historial del libro de actas del sistema de Titulación anterior (carga de datos; la tabla tit_historico se crea en 017).
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/025_historico_sistema_anterior.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.
DO 0;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/026_directorio_profesores.sql
-- ------------------------------------------------------------------------------------------------------------
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

-- ------------------------------------------------------------------------------------------------------------
-- migrations/027_identidad_estable.sql
-- ------------------------------------------------------------------------------------------------------------
-- Identidad estable de las personas ante el cambio de dominio del correo institucional (@profesor.uaemex.mx y @alumno.uaemex.mx
-- pasan a @uaemex.mx). Repetible: solo crea o cambia lo que falta y no borra datos.
--
--  · La persona es la cuenta (usuarios.id) o, si aún no tiene, el profesor del directorio (profesores.id, que se identifica por su RFC,
--    un dato interno que nunca se muestra ni se pide al profesor). El correo es un atributo que puede cambiar.
--  · usuario_correos: todos los correos que la cuenta ha tenido y verificó (el vigente es usuarios.email). Se acepta cualquiera para
--    iniciar sesión, pero los códigos siempre se envían al vigente.
--  · cambio_correo: solicitud de cambio de correo con código enviado al correo nuevo.
--  · tit_constancias.usuario_id / profesor_id: la constancia pertenece a la persona, no a un correo; así no se pierde si el correo cambia.
--  · rrhh_administradores: personal de Recursos Humanos que sube el RFC (y el número de empleado) para desambiguar y enlaza cuentas.

CREATE TABLE IF NOT EXISTS usuario_correos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  usuario_id int unsigned NOT NULL,
  email varchar(120) NOT NULL,
  principal tinyint(1) NOT NULL DEFAULT 0,
  origen enum('cuenta','cambio','recursos_humanos') NOT NULL DEFAULT 'cuenta',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_correo (email),
  KEY idx_usuario_correo_usuario (usuario_id),
  CONSTRAINT fk_usuario_correo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT IGNORE INTO usuario_correos (usuario_id, email, principal, origen) SELECT id, email, 1, 'cuenta' FROM usuarios;

CREATE TABLE IF NOT EXISTS cambio_correo (
  usuario_id int unsigned NOT NULL,
  email_nuevo varchar(120) NOT NULL,
  codigo_hash varchar(255) NOT NULL,
  expira_en datetime NOT NULL,
  intentos tinyint unsigned NOT NULL DEFAULT 0,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_cambio_correo_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS rrhh_administradores (
  usuario_id int unsigned NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_rrhh_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Número de empleado: desambiguador opcional que sube Recursos Humanos (único cuando se conoce).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE profesores ADD COLUMN num_empleado varchar(12) NULL DEFAULT NULL AFTER rfc, ADD UNIQUE KEY uq_profesor_empleado (num_empleado)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profesores' AND COLUMN_NAME = 'num_empleado');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Constancias por persona.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_constancias ADD COLUMN usuario_id int unsigned NULL DEFAULT NULL AFTER email, ADD COLUMN profesor_id int unsigned NULL DEFAULT NULL AFTER usuario_id, ADD KEY idx_tit_constancia_usuario (usuario_id), ADD KEY idx_tit_constancia_profesor (profesor_id)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_constancias' AND COLUMN_NAME = 'usuario_id');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Relleno desde el correo con que se emitieron (la cuenta, y el profesor del directorio por cualquiera de sus correos).
UPDATE tit_constancias c JOIN usuario_correos uc ON uc.email COLLATE utf8mb4_unicode_ci = c.email
SET c.usuario_id = uc.usuario_id WHERE c.usuario_id IS NULL;
UPDATE tit_constancias c JOIN profesor_correos pc ON pc.email COLLATE utf8mb4_unicode_ci = c.email
SET c.profesor_id = pc.profesor_id WHERE c.profesor_id IS NULL;
UPDATE tit_constancias c JOIN profesores p ON p.usuario_id = c.usuario_id
SET c.profesor_id = p.id WHERE c.profesor_id IS NULL AND c.usuario_id IS NOT NULL;
UPDATE tit_constancias c JOIN profesores p ON p.id = c.profesor_id
SET c.usuario_id = p.usuario_id WHERE c.usuario_id IS NULL AND p.usuario_id IS NOT NULL;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/028_historial_titulos.sql
-- ------------------------------------------------------------------------------------------------------------
-- Historial de constancias: de dónde salió el título del trabajo y, si cambió, cuál fue el del protocolo. Repetible.
--  titulo_origen: '' (capturado a mano o del libro), 'manual', 'sistema_anterior', 'revision' (título del trabajo escrito en la solicitud
--  de revisión de la plataforma, el definitivo) o 'protocolo' (solo se encontró el del protocolo: puede diferir del definitivo).
--  titulo_protocolo: título que tenía el protocolo cuando es distinto del definitivo (informativo).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN titulo_origen VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '''' AFTER titulo, ADD COLUMN titulo_protocolo VARCHAR(500) NOT NULL DEFAULT '''' AFTER titulo_origen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'titulo_origen');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Los títulos que trajo la importación del sistema de Titulación anterior (migración 025).
UPDATE tit_historico SET titulo_origen = 'sistema_anterior' WHERE hoja = 'SISTEMA ANTERIOR' AND titulo <> '' AND titulo_origen = '';

-- ------------------------------------------------------------------------------------------------------------
-- migrations/029_formulario_galardonados.sql
-- ------------------------------------------------------------------------------------------------------------
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
-- Al volver a importar se actualizan el texto, los campos y el formato del Excel; el estado, el cierre y el acceso
-- se conservan como los haya dejado la administración.
INSERT INTO frm_formularios (slug, titulo, agrupar_por, varios_por_correo, estado, cierra_en, acceso, descripcion, campos, excel) VALUES
('galardonados-2026', '¿Ganaste un premio en 2026?', 'tipo_persona', 1, 'abierto', '2026-12-31 23:59:59', 'subdirector',
'Si eres alumna, alumno, egresada/o o profesor/a de la FI y en 2026 ganaste un premio, lugar o distinción internacional, nacional o estatal otorgado por una institución externa a la UAEMEX, regístralo para que la Facultad lo dé a conocer en sus redes sociales y lo cuente entre los logros que reporta a la Universidad.

Haz un registro por cada premio. Ten a la mano tu constancia o diploma (y una foto con tu reconocimiento, si la tienes) en un .zip o en una carpeta de Drive. Fecha límite: 12 de noviembre; si recibes un premio después, regístralo hasta el 31 de diciembre.',
'[{"k":"tipo_persona","label":"Eres","tipo":"select","req":true,"opciones":{"alumno":"Alumno/a","egresado":"Egresado/a","profesor":"Profesor/a"}},
{"k":"nombre","label":"Nombre completo","tipo":"texto","req":true,"max":160,"mayusculas":true,"de_cuenta":"nombre","ayuda":"Nombre(s) Apellido paterno Apellido materno"},
{"k":"email","label":"Correo electrónico","tipo":"email","req":true,"max":160,"de_cuenta":"email"},
{"k":"espacio","label":"Espacio académico","tipo":"fijo","valor":"FACULTAD DE INGENIERÍA"},
{"k":"programa","label":"Programa educativo","tipo":"programa","req":true,"varios_si":{"tipo_persona":"profesor"},"ayuda_varios":"marca todos en los que impartes clase"},
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

-- ------------------------------------------------------------------------------------------------------------
-- migrations/030_avisos_clasificados_y_asistencias.sql
-- ------------------------------------------------------------------------------------------------------------
-- Avisos y eventos clasificables (Difusión Cultural): público, carreras, semestres, tema, modalidad y enlaces; preferencias
-- de cada persona en su perfil; bitácora de evidencias por evento y asistencias de alumnos (QR, palabra clave o
-- constancia) con puntos y folio verificable, para el expediente de formación integral y los organismos acreditadores.
-- Repetible: solo crea o cambia lo que falta y no borra datos.

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

-- ------------------------------------------------------------------------------------------------------------
-- migrations/031_recordatorios_y_activacion.sql
-- ------------------------------------------------------------------------------------------------------------
-- Recordatorios en la bandeja de Traza FI de los eventos agregados al calendario (un día y una hora antes, y la víspera
-- de la fecha límite) y activación de cuentas existentes desde el registro (sin duplicar a la persona).
-- Repetible: solo crea lo que falta y no borra datos.

-- Un recordatorio por persona, evento y momento: así no se repite aunque la persona entre varias veces.
CREATE TABLE IF NOT EXISTS evento_recordatorios (
  evento_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  momento ENUM('dia','hora','limite') NOT NULL,
  enviado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (evento_id, usuario_id, momento),
  KEY idx_evento_recordatorios_usuario (usuario_id),
  CONSTRAINT fk_evento_recordatorios_evento FOREIGN KEY (evento_id) REFERENCES eventos (id) ON DELETE CASCADE,
  CONSTRAINT fk_evento_recordatorios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Quien se registra con un correo que ya tiene cuenta (alta de Titulación o de la administración) la activa con un
-- código enviado a ese correo, en vez de crear otra. Aquí espera la contraseña elegida hasta que confirma el código.
CREATE TABLE IF NOT EXISTS cuenta_activaciones (
  usuario_id INT UNSIGNED NOT NULL,
  codigo_hash VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  grado VARCHAR(30) NULL,
  genero VARCHAR(20) NULL,
  expira_en DATETIME NOT NULL,
  intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  enviado_en DATETIME NOT NULL,
  PRIMARY KEY (usuario_id),
  CONSTRAINT fk_cuenta_activaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respuestas de formularios enviadas sin sesión con el correo de una cuenta existente: quedan ligadas a esa cuenta.
UPDATE frm_respuestas r JOIN usuarios u ON u.email COLLATE utf8mb4_unicode_ci = r.email SET r.usuario_id = u.id WHERE r.usuario_id IS NULL;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/032_calaveritas_convocatoria.sql
-- ------------------------------------------------------------------------------------------------------------
-- Concurso de calaveritas 2026: enlace de la convocatoria oficial y lugar de la premiación en las bases.
-- Repetible: cada cambio se aplica una sola vez y respeta lo que la administración haya editado.

UPDATE con_concursos
   SET bases = CONCAT('## Convocatoria\nConsulta la convocatoria oficial: https://www.facebook.com/share/p/1A1xwSwwnC/\n\n', bases)
 WHERE slug = 'calaveritas-2026' AND bases NOT LIKE '%1A1xwSwwnC%';

UPDATE con_concursos
   SET bases = REPLACE(bases, 'fecha de la premiación.', 'fecha de la premiación.\n\nLa premiación será el viernes 30 de octubre a las 4:00 pm en la Biblioteca de la FI.')
 WHERE slug = 'calaveritas-2026' AND bases LIKE '%fecha de la premiación.%' AND bases NOT LIKE '%en la Biblioteca de la FI%';

-- ------------------------------------------------------------------------------------------------------------
-- migrations/033_evento_publicacion_evidencia.sql
-- ------------------------------------------------------------------------------------------------------------
-- Eventos: enlace de la publicación en Facebook que muestra la evidencia (se agrega después del evento) y alta del
-- sorteo público del Rizoma para la Igualdad. Repetible: solo crea lo que falta y no borra datos.

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE eventos ADD COLUMN evidencia_post_url VARCHAR(500) NULL AFTER grabacion_url', 'DO 0')
          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos' AND COLUMN_NAME = 'evidencia_post_url');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

INSERT INTO eventos (titulo, descripcion, categoria, tema, publico, depto_id, autor_id, inicio, lugar, modalidad, organizador, post_url,
                     requiere_registro, formacion_integral, horas, puntos)
SELECT 'Sorteo público para integrar el Rizoma para la Igualdad',
       'Te invitamos a presenciar el sorteo público para seleccionar a las personas que integrarán el Rizoma para la Igualdad de la Facultad de Ingeniería. Este grupo contribuirá a promover una comunidad universitaria libre de violencia, mediante la orientación y el acompañamiento a quienes lo necesiten. Tu presencia contribuye a la transparencia del proceso.',
       'otro', 'comunidad', 'alumno,egresado,profesor', d.id, u.id, '2026-10-30 12:00:00', 'Auditorio "Ing. José Yurrieta Valdés"', 'presencial',
       'Administración F.I. 2026-2030', 'https://www.facebook.com/share/p/19r1KdHMYi/', 0, 0, NULL, 0
  FROM departamentos d
  JOIN (SELECT MIN(id) AS id FROM usuarios WHERE rol = 'admin' AND activo = 1) u ON u.id IS NOT NULL
 WHERE d.clave = 'coordinacion_de_difusion_cultural'
   AND NOT EXISTS (SELECT 1 FROM eventos e WHERE e.titulo = 'Sorteo público para integrar el Rizoma para la Igualdad');

-- ------------------------------------------------------------------------------------------------------------
-- migrations/034_programa_ingenieria_civil.sql
-- ------------------------------------------------------------------------------------------------------------
-- Ingeniería Civil como programa educativo del catálogo (formularios, eventos, avisos, alumnos y archivo documental).
INSERT INTO programas (siglas, nombre, nivel, depto_id)
SELECT 'ICI', 'Ingeniería Civil', 'licenciatura', d.id
FROM departamentos d
WHERE d.clave = 'coordinacion_de_docencia_de_la_licenciatura_en_ingenieria_civil'
  AND NOT EXISTS (SELECT 1 FROM programas WHERE siglas = 'ICI');

-- ------------------------------------------------------------------------------------------------------------
-- migrations/035_encuesta_privada_viajes_2027.sql
-- ------------------------------------------------------------------------------------------------------------
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

-- ------------------------------------------------------------------------------------------------------------
-- migrations/036_fecha_aprobacion_protocolo.sql
-- ------------------------------------------------------------------------------------------------------------
-- Historial de constancias: fecha de aprobación del protocolo (último dictamen favorable de la Comisión de Evaluación), tomada del
-- sistema de Titulación anterior (respaldo del 24-03-2021: tablas timaecep y timaedef). Permite filtrar y ordenar el historial por esa
-- fecha y elegir fechas de examen a partir de ella. Repetible: nunca pisa una fecha ya capturada.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN fecha_protocolo DATE NULL AFTER fecha_examen', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'fecha_protocolo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

DROP TEMPORARY TABLE IF EXISTS tmp_fecha_protocolo;
CREATE TEMPORARY TABLE tmp_fecha_protocolo (
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cuenta VARCHAR(12) NOT NULL,
  fecha_examen DATE NOT NULL,
  fecha_protocolo DATE NOT NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): aquí se cargaban datos personales (números de cuenta y fechas de examen).
-- El repositorio conserva solo la estructura. La carga original está, fuera del control de versiones, en
-- database/datos_privados/migraciones/036_fecha_aprobacion_protocolo.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.

-- 1) misma licenciatura, cuenta y fecha de examen
UPDATE tit_historico h JOIN tmp_fecha_protocolo t ON t.carrera = h.carrera AND t.cuenta = h.cuenta AND t.fecha_examen = h.fecha_examen
   SET h.fecha_protocolo = t.fecha_protocolo WHERE h.fecha_protocolo IS NULL;
-- 2) si la fecha del libro difiere: misma licenciatura y cuenta, cuando son únicas en ambos lados
UPDATE tit_historico h
  JOIN (SELECT carrera, cuenta, MIN(fecha_protocolo) fecha_protocolo FROM tmp_fecha_protocolo GROUP BY carrera, cuenta HAVING COUNT(*) = 1) t ON t.carrera = h.carrera AND t.cuenta = h.cuenta
  JOIN (SELECT carrera, cuenta FROM tit_historico WHERE cuenta <> '' GROUP BY carrera, cuenta HAVING COUNT(*) = 1) u ON u.carrera = h.carrera AND u.cuenta = h.cuenta
   SET h.fecha_protocolo = t.fecha_protocolo WHERE h.fecha_protocolo IS NULL;
DROP TEMPORARY TABLE IF EXISTS tmp_fecha_protocolo;
-- 3) una aprobación posterior al examen es imposible: cruce dudoso, se descarta
UPDATE tit_historico SET fecha_protocolo = NULL WHERE fecha_protocolo > fecha_examen;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/037_historico_protocolos.sql
-- ------------------------------------------------------------------------------------------------------------
-- Captura de protocolos revisados fuera del sistema (en papel): cada registro guarda al alumno, el título, la fecha de aprobación y los
-- revisores del protocolo; al generar sus constancias se crea un trámite histórico (concluido, sin cuenta de alumno) y se usa la misma
-- cola de firmas, búsqueda y QR. Repetible.
CREATE TABLE IF NOT EXISTS tit_historico_protocolos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  alumno VARCHAR(160) NOT NULL DEFAULT '',
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  titulo VARCHAR(500) NOT NULL DEFAULT '',
  director VARCHAR(160) NOT NULL DEFAULT '',
  fecha_inicio DATE NULL,
  fecha_aprobacion DATE NULL,
  revisor1 VARCHAR(160) NOT NULL DEFAULT '', revisor1_email VARCHAR(120) NOT NULL DEFAULT '',
  revisor2 VARCHAR(160) NOT NULL DEFAULT '', revisor2_email VARCHAR(120) NOT NULL DEFAULT '',
  revisor3 VARCHAR(160) NOT NULL DEFAULT '', revisor3_email VARCHAR(120) NOT NULL DEFAULT '',
  tramite_id BIGINT UNSIGNED NULL,
  generado_en DATETIME NULL,
  creado_por INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tit_histprot_carrera (carrera, fecha_aprobacion),
  KEY idx_tit_histprot_tramite (tramite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/038_protocolos_historicos.sql
-- ------------------------------------------------------------------------------------------------------------
-- Protocolos aprobados del sistema de Titulación anterior (respaldo del 24-03-2021) con todos sus revisores, para generar en lote
-- las constancias de «revisor del protocolo» desde Constancias → Captura de registros externos. Un registro por alumno (si el tema
-- fue de dos, ambos) con los revisores que dictaminaron en cualquier vuelta (hasta 3 en columnas; el resto en revisores_mas).
-- Solo carga los registros: no genera constancias. Repetible: no duplica (misma licenciatura, cuenta y fecha de aprobación).
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico_protocolos ADD COLUMN revisores_mas TEXT NULL AFTER revisor3_email', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico_protocolos' AND COLUMN_NAME = 'revisores_mas');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): este archivo cargaba datos personales.
-- El repositorio solo conserva la estructura de las tablas. La carga original está, fuera del control de versiones,
-- en database/datos_privados/migraciones/038_protocolos_historicos.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.

-- ------------------------------------------------------------------------------------------------------------
-- migrations/039_expediente_previo.sql
-- ------------------------------------------------------------------------------------------------------------
-- Expediente de titulación en tres etapas: revisión del protocolo, examen previo y examen definitivo.
--  · tit_historico.fecha_previo / acta_previo: el examen previo del trabajo escrito (editable en el historial).
--  · tit_historico_protocolos.fecha_previo / acta_previo / historico_id: el protocolo se liga al registro de su examen (uno a uno).
-- Se cargan los exámenes previos del sistema anterior (respaldo del 24-03-2021) y se ligan los protocolos con su examen cuando coinciden
-- licenciatura, cuenta y fecha de examen; el resto se liga desde la pantalla «Registrar o completar». Repetible.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico ADD COLUMN fecha_previo DATE NULL AFTER fecha_protocolo, ADD COLUMN acta_previo INT UNSIGNED NULL AFTER fecha_previo', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico' AND COLUMN_NAME = 'fecha_previo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE tit_historico_protocolos ADD COLUMN fecha_previo DATE NULL AFTER fecha_aprobacion, ADD COLUMN acta_previo INT UNSIGNED NULL AFTER fecha_previo, ADD COLUMN historico_id BIGINT UNSIGNED NULL AFTER acta_previo, ADD UNIQUE KEY uq_histprot_historico (historico_id)', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tit_historico_protocolos' AND COLUMN_NAME = 'fecha_previo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

DROP TEMPORARY TABLE IF EXISTS tmp_exp_ant;
CREATE TEMPORARY TABLE tmp_exp_ant (carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, cuenta VARCHAR(12) NOT NULL, fecha_aprobacion DATE NULL, fecha_examen DATE NULL, fecha_previo DATE NOT NULL, acta_previo INT UNSIGNED NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- DATOS RETIRADOS DEL REPOSITORIO (2026-11-10): aquí se cargaban datos personales (números de cuenta y fechas de examen).
-- El repositorio conserva solo la estructura. La carga original está, fuera del control de versiones, en
-- database/datos_privados/migraciones/039_expediente_previo.sql (ver docs/DATOS_SENSIBLES.md). Producción ya la tiene aplicada.

-- Examen previo en los exámenes del historial (misma licenciatura, cuenta y fecha de examen).
UPDATE tit_historico h JOIN tmp_exp_ant t ON t.carrera = h.carrera AND t.cuenta = h.cuenta AND t.fecha_examen = h.fecha_examen
   SET h.fecha_previo = t.fecha_previo, h.acta_previo = t.acta_previo WHERE h.fecha_previo IS NULL;
-- Examen previo en los protocolos (misma licenciatura, cuenta y fecha de aprobación).
UPDATE tit_historico_protocolos p JOIN tmp_exp_ant t ON t.carrera = p.carrera AND t.cuenta = p.cuenta AND t.fecha_aprobacion = p.fecha_aprobacion
   SET p.fecha_previo = t.fecha_previo, p.acta_previo = t.acta_previo WHERE p.fecha_previo IS NULL;
-- Liga protocolo y examen cuando son inequívocos: misma licenciatura y cuenta, y el examen del sistema anterior coincide en fecha.
UPDATE IGNORE tit_historico_protocolos p
  JOIN tmp_exp_ant t ON t.carrera = p.carrera AND t.cuenta = p.cuenta AND t.fecha_aprobacion = p.fecha_aprobacion AND t.fecha_examen IS NOT NULL
  JOIN tit_historico h ON h.carrera = t.carrera AND h.cuenta = t.cuenta AND h.fecha_examen = t.fecha_examen
   SET p.historico_id = h.id WHERE p.historico_id IS NULL;
DROP TEMPORARY TABLE IF EXISTS tmp_exp_ant;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/040_titulacion_recientes.sql
-- ------------------------------------------------------------------------------------------------------------
-- Registros del departamento de Titulación que se llevaban en Excel (2015-2026): solicitudes sin trabajo escrito, protocolos
-- recibidos, control de oficios, «artículo 5», actas de aplazamiento y las actas más recientes del libro. Repetible: cada
-- tabla tiene una huella única y se usa INSERT IGNORE; en tit_historico solo se agregan actas que no existen y se llenan vacíos.
CREATE TABLE IF NOT EXISTS tit_solicitudes_historicas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  siglas VARCHAR(20) NOT NULL DEFAULT '',
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  tipo VARCHAR(20) NOT NULL DEFAULT '',
  tipo_origen VARCHAR(40) NOT NULL DEFAULT '',
  fecha_solicitud DATE NULL,
  fecha_programada DATE NULL,
  vez VARCHAR(10) NOT NULL DEFAULT '',
  nivel VARCHAR(12) NOT NULL DEFAULT '',
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  lote VARCHAR(80) NOT NULL DEFAULT '',
  observaciones VARCHAR(200) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_solh_huella (huella), KEY idx_tit_solh_cuenta (cuenta), KEY idx_tit_solh_fecha (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_protocolos_recibidos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidad_origen VARCHAR(60) NOT NULL DEFAULT '',
  fecha_entrega DATE NULL,
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  incorporada VARCHAR(30) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_protr_huella (huella), KEY idx_tit_protr_cuenta (cuenta), KEY idx_tit_protr_fecha (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_oficios_control (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  serie CHAR(4) CHARACTER SET ascii NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NULL,
  dirigido VARCHAR(200) NOT NULL DEFAULT '',
  asunto VARCHAR(200) NOT NULL DEFAULT '',
  tipo VARCHAR(30) NOT NULL DEFAULT 'otro',
  persona VARCHAR(160) NOT NULL DEFAULT '',
  tomado_por VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_ofc_num (serie, numero), KEY idx_tit_ofc_fecha (fecha), KEY idx_tit_ofc_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_articulo5 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  plan VARCHAR(20) NOT NULL DEFAULT '',
  fecha_evaluacion DATE NULL,
  fecha_articulo5 DATE NULL,
  oficio VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_art5_huella (huella), KEY idx_tit_art5_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_actas_aplazamiento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consecutivo INT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  fecha DATE NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_aplaz_cons (consecutivo), KEY idx_tit_aplaz_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/041_acceso_egresados.sql
-- ------------------------------------------------------------------------------------------------------------
-- Acceso de egresados sin correo institucional (docs/ACCESO_EGRESADOS.md).
-- Repetible: solo crea o cambia lo que falta y no borra datos.

-- Marca de la cuenta de egresado: rol «alumno» con alcance limitado al módulo de Titulación, correo personal validado
-- por el personal y vigencia de 24 meses sin actividad.
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE usuarios ADD COLUMN egresado_externo TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN egresado_vence DATE NULL DEFAULT NULL, ADD COLUMN egresado_cuenta VARCHAR(12) NULL DEFAULT NULL, ADD COLUMN egresado_carrera VARCHAR(20) NULL DEFAULT NULL, ADD COLUMN egresado_validado_por INT UNSIGNED NULL DEFAULT NULL, ADD COLUMN egresado_validado_en DATETIME NULL DEFAULT NULL',
  'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'egresado_externo');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Solicitudes de acceso: no son cuentas; nada se activa sin la aprobación del personal de Titulación.
CREATE TABLE IF NOT EXISTS egresado_solicitudes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  genero ENUM('masculino','femenino','no_binario') NOT NULL,
  carrera VARCHAR(20) NOT NULL,
  cuenta VARCHAR(12) NOT NULL,
  email VARCHAR(120) NOT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  estado ENUM('sin_verificar','requiere_docs','pendiente','aprobada','activa','rechazada') NOT NULL DEFAULT 'sin_verificar',
  prevalidacion ENUM('coincide','media','ninguna') DEFAULT NULL,
  prevalidacion_detalle VARCHAR(500) DEFAULT NULL,
  codigo_hash VARCHAR(255) DEFAULT NULL,
  codigo_expira DATETIME DEFAULT NULL,
  codigo_intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  codigo_enviado DATETIME DEFAULT NULL,
  verificado_en DATETIME DEFAULT NULL,
  motivo VARCHAR(500) DEFAULT NULL,
  resuelto_por INT UNSIGNED DEFAULT NULL,
  resuelto_en DATETIME DEFAULT NULL,
  usuario_id INT UNSIGNED DEFAULT NULL,
  clave_expira DATETIME DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_egresado_token (token_hash),
  KEY idx_egresado_estado (estado, creado_en),
  KEY idx_egresado_cuenta (cuenta),
  KEY idx_egresado_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Documentos de identidad: viven en storage/ privado y se borran al resolver; queda el sello (huella y fechas).
CREATE TABLE IF NOT EXISTS egresado_documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id INT UNSIGNED NOT NULL,
  tipo ENUM('identificacion','comprobante') NOT NULL,
  ruta VARCHAR(255) DEFAULT NULL,
  nombre_original VARCHAR(160) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  tamano INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  subido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  borrado_en DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_egresado_doc_sol (solicitud_id),
  CONSTRAINT fk_egresado_doc_sol FOREIGN KEY (solicitud_id) REFERENCES egresado_solicitudes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/042_cifrado_totp.sql
-- ------------------------------------------------------------------------------------------------------------
-- Cifrado de secretos TOTP en reposo (docs/CIFRADO_DE_DATOS.md).
-- El valor cifrado («enc:v1:…») mide unos 90 caracteres; la columna original admitía 32. Repetible: solo ensancha si
-- falta y no toca los datos. El cifrado de los secretos existentes se hace con tools/cifrar_totp.php, no desde SQL,
-- porque la llave nunca debe estar en la base de datos.
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios MODIFY COLUMN totp_secret VARCHAR(255) NULL DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_secret'
  AND (DATA_TYPE <> 'varchar' OR CHARACTER_MAXIMUM_LENGTH < 255));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/043_acceso_concesiones.sql
-- ------------------------------------------------------------------------------------------------------------
-- Concesiones de acceso por persona (docs/CONCESIONES_DE_ACCESO.md).
-- Cada módulo se abre por rol; los casos especiales (alguien que, por su función, debe usar algo que su rol no incluye)
-- se asignan a una persona concreta, con vigencia obligatoria: la concesión caduca sola al terminar la función.
-- Repetible: solo crea la tabla si falta. No toca usuarios ni usuario_roles.
CREATE TABLE IF NOT EXISTS acceso_concesiones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL COMMENT 'Persona que recibe el acceso',
  funcion VARCHAR(80) NOT NULL COMMENT 'Clave de la función o tipo de recurso, p. ej. documentos, titulacion.panel',
  recurso_id BIGINT UNSIGNED NULL COMMENT 'NULL = toda la función; con valor = ese recurso',
  departamento_id SMALLINT UNSIGNED NULL COMMENT 'Dependencia dueña del recurso; define quién puede conceder',
  permiso VARCHAR(30) NOT NULL DEFAULT 'usar',
  motivo VARCHAR(255) NOT NULL DEFAULT '',
  otorgado_por INT UNSIGNED NOT NULL,
  vigente_desde DATE NOT NULL,
  vigente_hasta DATE NOT NULL COMMENT 'Obligatoria: el acceso termina con la función',
  revocado_en DATETIME NULL,
  revocado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_concesion_busqueda (usuario_id, funcion, recurso_id, vigente_hasta),
  KEY idx_concesion_depto (departamento_id, vigente_hasta),
  CONSTRAINT fk_concesion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_concesion_otorgante FOREIGN KEY (otorgado_por) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/044_ingesta_documental.sql
-- ------------------------------------------------------------------------------------------------------------
-- Ingesta documental (docs/INGESTA_DOCUMENTAL.md): PDFs sueltos, carpetas de PDFs y enlaces a archivos de Teams/SharePoint,
-- indizados para consultar sus metadatos y abrir su enlace. Repetible: solo crea lo que falta. No toca el archivo documental.
CREATE TABLE IF NOT EXISTS ing_carpetas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT NOT NULL,
  origen ENUM('pdf','teams') NOT NULL,
  enlace VARCHAR(1000) NULL COMMENT 'Enlace de la carpeta en Teams/SharePoint (origen teams)',
  alcance ENUM('propio','departamento','personal','comunidad') NOT NULL DEFAULT 'departamento',
  departamento_id SMALLINT UNSIGNED NULL,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ing_carpeta_alcance (alcance, departamento_id),
  KEY idx_ing_carpeta_autor (creado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ing_documentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  carpeta_id INT UNSIGNED NULL COMMENT 'NULL = documento ingresado de forma particular',
  origen ENUM('pdf','teams') NOT NULL,
  titulo VARCHAR(250) NOT NULL,
  tipo_documento VARCHAR(100) NOT NULL DEFAULT '',
  autor VARCHAR(200) NOT NULL DEFAULT '',
  fecha_documento DATE NULL,
  palabras_clave TEXT NOT NULL,
  resumen TEXT NOT NULL,
  ruta_relativa VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Ubicación dentro de la carpeta de origen',
  enlace VARCHAR(1000) NULL COMMENT 'URL del archivo en Teams/SharePoint (origen teams)',
  clave VARCHAR(40) NULL COMMENT 'Archivo en el almacenamiento privado (origen pdf)',
  nombre_original VARCHAR(250) NOT NULL DEFAULT '',
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  paginas SMALLINT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  texto MEDIUMTEXT NULL COMMENT 'Texto extraído del PDF para la búsqueda; nunca se muestra completo',
  -- Copia del alcance de la carpeta (o propio del documento particular): un solo predicado de acceso para toda consulta.
  alcance ENUM('propio','departamento','personal','comunidad') NOT NULL DEFAULT 'departamento',
  departamento_id SMALLINT UNSIGNED NULL,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ing_clave (clave),
  KEY idx_ing_doc_carpeta (carpeta_id, ruta_relativa(100)),
  KEY idx_ing_doc_alcance (alcance, departamento_id),
  KEY idx_ing_doc_autor (creado_por, creado_en),
  KEY idx_ing_doc_sha (sha256),
  FULLTEXT KEY ft_ing_documentos (titulo, tipo_documento, autor, palabras_clave, resumen, nombre_original, ruta_relativa, texto),
  CONSTRAINT fk_ing_doc_carpeta FOREIGN KEY (carpeta_id) REFERENCES ing_carpetas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/045_titulacion_expediente_final.sql
-- ------------------------------------------------------------------------------------------------------------
-- Verificación final del expediente de titulación y envío a Servicios Escolares (Administración Central).
-- Es repetible: puede ejecutarse completa las veces que haga falta.

-- Un registro por trámite: lista de verificación (formato de integración de expediente) y su estado.
CREATE TABLE IF NOT EXISTS tit_expediente_final (
  tramite_id BIGINT UNSIGNED NOT NULL,
  estado ENUM('en_integracion','verificado','enviado') NOT NULL DEFAULT 'en_integracion',
  marcas MEDIUMTEXT NOT NULL,
  observaciones VARCHAR(1000) NULL,
  verificado_por INT UNSIGNED NULL,
  verificado_en DATETIME NULL,
  enviado_por INT UNSIGNED NULL,
  enviado_en DATETIME NULL,
  lote_id BIGINT UNSIGNED NULL,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (tramite_id),
  KEY idx_tit_expfinal_estado (estado),
  CONSTRAINT fk_tit_expfinal_tramite FOREIGN KEY (tramite_id) REFERENCES tit_tramites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada exportación del expediente (individual o en lote) que se envía a Servicios Escolares.
CREATE TABLE IF NOT EXISTS tit_expediente_lotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creado_por INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  folios VARCHAR(1000) NOT NULL DEFAULT '',
  huella CHAR(64) NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------------------------------------
-- migrations/046_profesor_datos_editables.sql
-- ------------------------------------------------------------------------------------------------------------
-- Datos del profesor que él mismo puede completar o corregir en «Mi perfil»: categoría de contratación.
-- (grado, género y número de empleado ya existen.) Es repetible: puede ejecutarse completa las veces que haga falta.

SET @s = (SELECT IF(COUNT(*) = 0, "ALTER TABLE profesores ADD COLUMN categoria ENUM('ptc','medio_tiempo','asignatura','tecnico_academico') NULL DEFAULT NULL AFTER grado", 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'profesores' AND COLUMN_NAME = 'categoria');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

