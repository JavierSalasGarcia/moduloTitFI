-- Módulo Posgrado: trámite de evaluación de grado (maestría, doctorado y especialidad) de la Coordinación de Estudios
-- Avanzados de la Facultad de Ingeniería. Solo estructura: ningún dato de personas.
-- Es repetible: puede ejecutarse completa las veces que haga falta.
-- Los datos personales del trámite (columna «datos» de pos_tramites y de pos_hitos) se guardan cifrados con
-- App\Core\Cifrado cuando hay llave configurada (docs/CIFRADO_DE_DATOS.md); por eso son MEDIUMTEXT y no JSON.
SET NAMES utf8mb4;

-- Un trámite por egresado y evaluación de grado.
CREATE TABLE IF NOT EXISTS pos_tramites (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(20) NULL,
  alumno_id INT UNSIGNED NOT NULL,
  nivel ENUM('maestria','doctorado','especialidad') NOT NULL DEFAULT 'maestria',
  modalidad VARCHAR(30) NOT NULL DEFAULT '',
  titulo VARCHAR(500) NULL,
  momento TINYINT UNSIGNED NOT NULL DEFAULT 1,
  estado ENUM('abierto','concluido','cancelado') NOT NULL DEFAULT 'abierto',
  resultado VARCHAR(30) NULL,
  datos MEDIUMTEXT NOT NULL,
  responsable_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_tramites_folio (folio),
  KEY idx_pos_tramites_alumno (alumno_id, estado),
  KEY idx_pos_tramites_bandeja (estado, momento, actualizado_en),
  CONSTRAINT fk_pos_tramites_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id),
  CONSTRAINT fk_pos_tramites_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentos del expediente, versionados: una versión nueva deja la anterior como «reemplazado».
-- tipo pdf: lo sube el egresado y el personal lo valida o lo devuelve; tipo fisico: el personal lo marca como recibido.
CREATE TABLE IF NOT EXISTS pos_documentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  clave VARCHAR(60) NOT NULL,
  tipo ENUM('pdf','fisico') NOT NULL DEFAULT 'pdf',
  estado ENUM('subido','validado','devuelto','recibido','reemplazado') NOT NULL DEFAULT 'subido',
  ruta VARCHAR(255) NULL,
  nombre_original VARCHAR(255) NULL,
  huella CHAR(64) NULL,
  comentario VARCHAR(1000) NULL,
  subido_por INT UNSIGNED NOT NULL,
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_documentos_tramite (tramite_id, clave, estado),
  KEY idx_pos_documentos_estado (estado),
  CONSTRAINT fk_pos_documentos_tramite FOREIGN KEY (tramite_id) REFERENCES pos_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_documentos_subido FOREIGN KEY (subido_por) REFERENCES usuarios(id),
  CONSTRAINT fk_pos_documentos_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hitos que registra el personal (cotejo, alta de externos, programación, resultado, expediente integrado).
CREATE TABLE IF NOT EXISTS pos_hitos (
  tramite_id BIGINT UNSIGNED NOT NULL,
  clave VARCHAR(40) NOT NULL,
  datos MEDIUMTEXT NOT NULL,
  completado_por INT UNSIGNED NOT NULL,
  completado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tramite_id, clave),
  CONSTRAINT fk_pos_hitos_tramite FOREIGN KEY (tramite_id) REFERENCES pos_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_hitos_usuario FOREIGN KEY (completado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora del trámite; visible_alumno indica si el egresado la ve.
CREATE TABLE IF NOT EXISTS pos_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  detalle VARCHAR(500) NOT NULL,
  visible_alumno TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_eventos_tramite (tramite_id, id),
  CONSTRAINT fk_pos_eventos_tramite FOREIGN KEY (tramite_id) REFERENCES pos_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_eventos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Oficios y constancias que emite la plataforma (guardados en storage/posgrado/<id>/), con su huella SHA-256.
CREATE TABLE IF NOT EXISTS pos_generados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  clave VARCHAR(60) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  huella CHAR(64) NOT NULL,
  generado_por INT UNSIGNED NOT NULL,
  generado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pos_generados_tramite (tramite_id, clave, id),
  CONSTRAINT fk_pos_generados_tramite FOREIGN KEY (tramite_id) REFERENCES pos_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_generados_usuario FOREIGN KEY (generado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
