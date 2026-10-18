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
