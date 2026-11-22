-- Consejos: enlaces con token para ver y descargar un dictamen escaneado. El correo de «Notificar dictamen» lleva el token; en la base
-- solo se guarda su huella (SHA-256). El enlace sirve únicamente a la cuenta que tenga ese correo (verificado) y vence.
-- Repetible: solo crea lo que falta.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS consejos_enlaces (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  oficio_id INT UNSIGNED NOT NULL,
  email VARCHAR(160) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_en DATETIME NOT NULL,
  creado_por INT UNSIGNED NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_consejos_enlace_token (token_hash),
  KEY idx_consejos_enlace_oficio (oficio_id, email),
  CONSTRAINT fk_consejos_enlace_oficio FOREIGN KEY (oficio_id) REFERENCES consejos_oficios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
