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
