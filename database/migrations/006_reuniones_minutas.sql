CREATE TABLE IF NOT EXISTS reuniones_minutas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reunion_id INT UNSIGNED NULL,
 autor_id INT UNSIGNED NOT NULL,
 subido_por_email VARCHAR(160) NOT NULL DEFAULT '',
 asunto VARCHAR(200) NOT NULL,
 fecha DATETIME NOT NULL,
 calidad VARCHAR(30) NOT NULL DEFAULT 'profesor',
 convocante VARCHAR(200) NOT NULL DEFAULT '',
 resumen MEDIUMTEXT NULL,
 archivo VARCHAR(255) NOT NULL DEFAULT '',
 nombre_archivo VARCHAR(160) NOT NULL DEFAULT '',
 fila_registro_id BIGINT UNSIGNED NULL,
 creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY fila_registro (fila_registro_id),
 KEY reunion (reunion_id), KEY autor_fecha (autor_id,fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reuniones_minuta_presentes (
 minuta_id BIGINT UNSIGNED NOT NULL,
 email VARCHAR(160) NOT NULL,
 enviado DATETIME NULL,
 PRIMARY KEY (minuta_id,email), KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS reuniones_historial_enlaces (
 autor_id INT UNSIGNED NOT NULL,
 email VARCHAR(160) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (token_hash), KEY autor_email (autor_id,email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
