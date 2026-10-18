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
