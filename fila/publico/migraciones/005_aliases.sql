CREATE TABLE IF NOT EXISTS fila_aliases (
  dependencia_id INT NOT NULL,
  email VARCHAR(160) NOT NULL,
  alias VARCHAR(120) NOT NULL,
  actualizado DATETIME NOT NULL,
  PRIMARY KEY (dependencia_id,email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
