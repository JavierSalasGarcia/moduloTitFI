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
