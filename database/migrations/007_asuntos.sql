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
