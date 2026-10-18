CREATE TABLE IF NOT EXISTS fila_expedientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dependencia_id INT NOT NULL,
  turno_id INT NOT NULL,
  resumen MEDIUMTEXT NOT NULL,
  archivo VARCHAR(255) NOT NULL DEFAULT '',
  nombre_archivo VARCHAR(160) NOT NULL DEFAULT '',
  usuario_id INT UNSIGNED NOT NULL,
  creado DATETIME NOT NULL,
  INDEX (dependencia_id, turno_id, creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
