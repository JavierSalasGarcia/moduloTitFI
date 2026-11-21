-- Consejos (HH. Consejos Académico y de Gobierno): reuniones, asuntos con su acuerdo de cada Consejo, profesores
-- mencionados (alimentan «Comisiones»), oficios de dictamen y archivos escaneados. Solo estructura; repetible.
-- Ver docs/CONSEJOS.md.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consejos_reuniones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  etiqueta VARCHAR(80) NOT NULL,                       -- p. ej. «Noviembre 2026»
  tipo_sesion ENUM('ordinaria','extraordinaria') NOT NULL DEFAULT 'ordinaria',
  presidente VARCHAR(160) NOT NULL DEFAULT '',
  secretario VARCHAR(160) NOT NULL DEFAULT '',
  consejeros_academico TEXT NULL,                      -- uno por línea, con su título
  consejeros_gobierno TEXT NULL,
  actas_anteriores_academico TEXT NULL,                -- una por línea: «ordinaria del día 9 de octubre de 2026 a las 09:00 horas en segunda convocatoria»
  actas_anteriores_gobierno TEXT NULL,
  academico_fecha DATE NULL,
  academico_hora TIME NULL,
  academico_lugar VARCHAR(160) NOT NULL DEFAULT '',
  academico_convocatoria ENUM('primera','segunda','unica') NOT NULL DEFAULT 'segunda',
  academico_fin DATETIME NULL,                         -- terminación de la sesión del Consejo Académico
  gobierno_fecha DATE NULL,
  gobierno_hora TIME NULL,
  gobierno_lugar VARCHAR(160) NOT NULL DEFAULT '',
  gobierno_convocatoria ENUM('primera','segunda','unica') NOT NULL DEFAULT 'segunda',
  gobierno_fin DATETIME NULL,
  estado ENUM('preparacion','academico','gobierno','cerrada') NOT NULL DEFAULT 'preparacion',
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS consejos_asuntos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reunion_id INT UNSIGNED NOT NULL,
  grupo ENUM('profesor','alumno','general') NOT NULL,   -- P#, A#, AG#
  numero SMALLINT UNSIGNED NOT NULL,
  solicitud_html MEDIUMTEXT NOT NULL,                   -- HTML limpio (texto, listas y tablas pegadas desde Word)
  antecedentes_html MEDIUMTEXT NULL,
  texto_busqueda MEDIUMTEXT NULL,                       -- solicitud + antecedentes en texto plano, para el buscador
  aval_academico ENUM('pendiente','avala','no_avala') NOT NULL DEFAULT 'pendiente',
  acuerdo_academico TEXT NULL,
  aprob_gobierno ENUM('pendiente','aprueba','no_aprueba') NOT NULL DEFAULT 'pendiente',
  acuerdo_gobierno TEXT NULL,
  nota_gobierno TEXT NULL,
  version_acuerdo INT UNSIGNED NOT NULL DEFAULT 1,      -- sube con cada cambio de acuerdo: un oficio con versión menor está desactualizado
  creado_por INT UNSIGNED NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consejos_asunto (reunion_id, grupo, numero),
  CONSTRAINT fk_consejos_asunto_reunion FOREIGN KEY (reunion_id) REFERENCES consejos_reuniones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Profesores con cuenta a quienes se menciona en el asunto: lo elige la persona que captura (nunca por coincidencia de nombre).
CREATE TABLE IF NOT EXISTS consejos_asunto_profesores (
  asunto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (asunto_id, usuario_id),
  KEY idx_consejos_prof_usuario (usuario_id),
  CONSTRAINT fk_consejos_prof_asunto FOREIGN KEY (asunto_id) REFERENCES consejos_asuntos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS consejos_oficios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asunto_id INT UNSIGNED NOT NULL,
  destinatario VARCHAR(600) NOT NULL DEFAULT '',        -- líneas separadas por salto de línea
  cargo VARCHAR(600) NOT NULL DEFAULT '',
  numero VARCHAR(40) NULL,
  fecha DATE NULL,
  cuerpo TEXT NULL,
  acuerdo TEXT NULL,
  version_acuerdo INT UNSIGNED NOT NULL DEFAULT 1,
  revisar TINYINT(1) NOT NULL DEFAULT 0,                -- el sistema no estuvo seguro: se resalta en amarillo
  estado ENUM('borrador','revisado','emitido') NOT NULL DEFAULT 'borrador',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consejos_oficio_numero (numero),
  KEY idx_consejos_oficio_asunto (asunto_id),
  CONSTRAINT fk_consejos_oficio_asunto FOREIGN KEY (asunto_id) REFERENCES consejos_asuntos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Escaneados firmados y sellados (actas y dictámenes); viven en storage/consejos/, nunca en el repositorio.
CREATE TABLE IF NOT EXISTS consejos_archivos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reunion_id INT UNSIGNED NOT NULL,
  asunto_id INT UNSIGNED NULL,
  oficio_id INT UNSIGNED NULL,
  tipo ENUM('acta_academico','acta_gobierno','dictamen') NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  ruta VARCHAR(255) NOT NULL,
  bytes INT UNSIGNED NOT NULL DEFAULT 0,
  subido_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_consejos_archivo_reunion (reunion_id, tipo),
  KEY idx_consejos_archivo_asunto (asunto_id),
  CONSTRAINT fk_consejos_archivo_reunion FOREIGN KEY (reunion_id) REFERENCES consejos_reuniones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES
  ('consejos_oficio_prefijo', 'ACAD/FI/', 'Prefijo del número de los oficios de dictamen de los Consejos'),
  ('consejos_oficio_digitos', '4', 'Dígitos del consecutivo de los oficios de dictamen'),
  ('consejos_oficio_ultimo', '0', 'Último consecutivo de oficio de dictamen asignado');
