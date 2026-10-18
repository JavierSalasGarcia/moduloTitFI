CREATE TABLE IF NOT EXISTS archivo_documentos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, subido_por INT UNSIGNED NOT NULL,
          departamento_id SMALLINT UNSIGNED NOT NULL, coordinacion_id SMALLINT UNSIGNED NULL, programa_id SMALLINT UNSIGNED NULL,
          titulo VARCHAR(200) NOT NULL, tipo_documento VARCHAR(100) NOT NULL DEFAULT '', folio VARCHAR(100) NOT NULL DEFAULT '',
          fecha_documento DATE NULL, emisor VARCHAR(200) NOT NULL DEFAULT '', destinatario VARCHAR(200) NOT NULL DEFAULT '',
          palabras_clave TEXT NOT NULL, resumen TEXT NOT NULL, nivel ENUM('restringida','confidencial') NOT NULL DEFAULT 'restringida',
          estado ENUM('borrador','indizado') NOT NULL DEFAULT 'borrador', version INT UNSIGNED NOT NULL DEFAULT 1,
          creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL, indizado_en DATETIME NULL,
          INDEX (subido_por,estado,creado_en), INDEX (departamento_id,creado_en), INDEX (coordinacion_id), INDEX(programa_id), INDEX(fecha_documento), INDEX(folio),
          FULLTEXT KEY busqueda (titulo,tipo_documento,folio,emisor,destinatario,palabras_clave,resumen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_accesos (
          documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL, permiso ENUM('leer','responder') NOT NULL DEFAULT 'leer',
          otorgado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, PRIMARY KEY(documento_id,usuario_id), INDEX(usuario_id),
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_acciones (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          tipo VARCHAR(40) NOT NULL, descripcion TEXT NOT NULL, responsable_id INT UNSIGNED NULL, vence_en DATE NULL,
          estado ENUM('registrada','pendiente','completada') NOT NULL DEFAULT 'registrada', creado_en DATETIME NOT NULL, completado_en DATETIME NULL,
          INDEX(documento_id,creado_en), INDEX(responsable_id,estado), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_adjuntos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          clave VARCHAR(40) NOT NULL UNIQUE, nombre_original VARCHAR(200) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INT UNSIGNED NOT NULL,
          sha256 CHAR(64) NOT NULL, tipo ENUM('original','respuesta','anexo') NOT NULL, creado_en DATETIME NOT NULL,
          INDEX(documento_id,tipo), INDEX(sha256), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivo_extracciones (
          documento_id BIGINT UNSIGNED PRIMARY KEY, version_documento INT UNSIGNED NOT NULL, estado ENUM('procesando','lista','error') NOT NULL,
          resultado MEDIUMTEXT NULL, solicitado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL,
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
