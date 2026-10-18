-- REUNIONES-002 · Migración del módulo Reuniones
-- Ejecutar después de schema.sql y seed_departamentos.sql.
-- Esta migración no crea controladores, vistas ni tareas programadas.

ALTER TABLE usuarios
  MODIFY COLUMN rol enum('admin','titular','operativo','profesor','alumno','coordinador')
  NOT NULL DEFAULT 'operativo';

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
