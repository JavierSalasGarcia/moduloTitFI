-- Catálogos académicos de posgrado: asignaturas, profesores que imparten cada asignatura por periodo
-- y comités tutorales de los estudiantes (director, codirector y tutores).
-- Solo estructura: los datos de personas se cargan con tools/importar_posgrado_academico.php (fuera de git).
-- Repetible.

CREATE TABLE IF NOT EXISTS asignaturas (
  id int unsigned NOT NULL AUTO_INCREMENT,
  clave varchar(12) NOT NULL,
  nombre varchar(160) NOT NULL,
  programa_id smallint unsigned DEFAULT NULL,
  horas_teoricas tinyint unsigned NOT NULL DEFAULT 0,
  horas_practicas tinyint unsigned NOT NULL DEFAULT 0,
  activa tinyint(1) NOT NULL DEFAULT 1,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_asignatura_clave (clave),
  KEY idx_asignatura_programa (programa_id),
  CONSTRAINT fk_asignatura_programa FOREIGN KEY (programa_id) REFERENCES programas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Quién imparte qué asignatura en cada periodo (p. ej. 2026B).
CREATE TABLE IF NOT EXISTS profesor_asignaturas (
  profesor_id int unsigned NOT NULL,
  asignatura_id int unsigned NOT NULL,
  periodo char(5) NOT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (profesor_id, asignatura_id, periodo),
  KEY idx_profesor_asignatura_asig (asignatura_id, periodo),
  CONSTRAINT fk_profesor_asignatura_profesor FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE,
  CONSTRAINT fk_profesor_asignatura_asignatura FOREIGN KEY (asignatura_id) REFERENCES asignaturas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Comité tutoral de un estudiante de posgrado. El estudiante puede no tener cuenta todavía: su nombre y su número de
-- cuenta se guardan cifrados con App\Core\Cifrado (contexto pos_comites.<columna>), con índice ciego para no duplicar ni
-- buscar por igualdad ('i:' HMAC con la llave; 'h:' SHA-256 si el entorno no tiene llave). La cuenta se liga después solo
-- por correo verificado (docs/VINCULACION_DE_CUENTAS.md).
CREATE TABLE IF NOT EXISTS pos_comites (
  id int unsigned NOT NULL AUTO_INCREMENT,
  programa_id smallint unsigned NOT NULL,
  alumno_nombre varchar(400) NOT NULL,
  alumno_huella varchar(70) NOT NULL,
  numero_cuenta varchar(200) DEFAULT NULL,
  cuenta_huella varchar(70) DEFAULT NULL,
  genero enum('masculino','femenino') DEFAULT NULL,
  alumno_usuario_id int unsigned DEFAULT NULL,
  titulo varchar(600) DEFAULT NULL,
  periodo char(5) DEFAULT NULL,
  observaciones varchar(500) DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_comite_alumno (programa_id, alumno_huella),
  KEY idx_pos_comite_cuenta (cuenta_huella),
  KEY idx_pos_comite_usuario (alumno_usuario_id),
  CONSTRAINT fk_pos_comite_programa FOREIGN KEY (programa_id) REFERENCES programas (id),
  CONSTRAINT fk_pos_comite_usuario FOREIGN KEY (alumno_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Integrantes del comité. profesor_id se llena cuando el nombre coincide de forma única con una ficha del directorio;
-- si no (externos, nombres ambiguos), queda el nombre tal como se capturó.
CREATE TABLE IF NOT EXISTS pos_comite_miembros (
  id int unsigned NOT NULL AUTO_INCREMENT,
  comite_id int unsigned NOT NULL,
  rol enum('director','codirector','tutor') NOT NULL,
  orden tinyint unsigned NOT NULL DEFAULT 1,
  nombre varchar(160) NOT NULL,
  profesor_id int unsigned DEFAULT NULL,
  externo tinyint(1) NOT NULL DEFAULT 0,
  nota varchar(200) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_comite_miembro (comite_id, rol, orden),
  KEY idx_pos_comite_miembro_profesor (profesor_id),
  CONSTRAINT fk_pos_comite_miembro_comite FOREIGN KEY (comite_id) REFERENCES pos_comites (id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_comite_miembro_profesor FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
