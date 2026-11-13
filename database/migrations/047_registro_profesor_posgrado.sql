-- Registro: la persona declara que es profesor del directorio y, si además estudia un posgrado de la Facultad, cuál.
-- Catálogo de siglas: profesor_unidades.clave se relaciona con programas.id (licenciaturas y posgrados); en las bases de datos y en
-- los reportes se usan las siglas, no los nombres completos. Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

-- Maestría en Ingeniería de la Cadena de Suministro (MICS), que faltaba en el catálogo de programas.
INSERT INTO programas (siglas, nombre, nivel, depto_id)
SELECT 'MICS', 'Maestría en Ingeniería de la Cadena de Suministro', 'posgrado', (SELECT depto_id FROM programas WHERE siglas = 'MCI' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM programas WHERE siglas = 'MICS') AND EXISTS (SELECT 1 FROM programas WHERE siglas = 'MCI');

-- Ingeniería Civil ya es programa del catálogo: se relaciona su unidad.
UPDATE profesor_unidades u JOIN programas p ON p.siglas = u.clave SET u.programa_id = p.id WHERE u.programa_id IS NULL AND u.clave = 'ICI';

-- Un posgrado concreto por unidad (las adscripciones históricas siguen en MAESTRIA y DOCTORADO, que no distinguen el programa).
INSERT IGNORE INTO profesor_unidades (clave, nombre, nivel, programa_id)
SELECT p.siglas, p.nombre, 'posgrado', p.id FROM programas p WHERE p.nivel = 'posgrado';

-- Declaración hecha al registrarse. No enlaza la cuenta (eso solo ocurre con un correo verificado o por Recursos Humanos): deja
-- constancia de que la persona dice ser ese profesor y, si estudia un posgrado, en cuál. La asociación como estudiante vive en
-- usuarios.programa_id y la de profesor en profesores.usuario_id + programa_profesores, así que se conservan ambas.
CREATE TABLE IF NOT EXISTS profesor_declaraciones (
  usuario_id int unsigned NOT NULL,
  profesor_id int unsigned NOT NULL,
  posgrado_programa_id smallint unsigned DEFAULT NULL,
  estado enum('pendiente','enlazada','descartada') NOT NULL DEFAULT 'pendiente',
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (usuario_id),
  KEY idx_declaracion_profesor (profesor_id),
  CONSTRAINT fk_declaracion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_declaracion_profesor FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE,
  CONSTRAINT fk_declaracion_posgrado FOREIGN KEY (posgrado_programa_id) REFERENCES programas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
