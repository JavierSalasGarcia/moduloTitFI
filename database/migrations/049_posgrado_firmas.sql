-- Módulo Posgrado: firmas en línea de los documentos del trámite de evaluación de grado (como en la titulación de
-- licenciatura). Cada firmante (director de tesis, integrantes del sínodo, Coordinación de Estudios Avanzados y el
-- propio egresado) firma sobre el PDF que la plataforma generó una sola vez y guardó en pos_generados; cada firma
-- estampa un sello visible en la caja de ese firmante y deja registro de correo, fecha y hora, IP, agente de usuario y
-- huella SHA-256 del documento firmado. Director y sinodales firman desde un enlace personal, sin cuenta: de ese
-- enlace solo se guarda la huella SHA-256 del token.
-- Además, la vigencia de la firma electrónica institucional de la UAEMéx (la del SICDE, consultable en
-- https://fise.uaemex.mx/) que cada persona declara en «Mi perfil» o al firmar el visto bueno del sínodo.
-- Solo estructura: ningún dato de personas. Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pos_firmas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tramite_id BIGINT UNSIGNED NOT NULL,
  -- Clave del documento firmable: voto, datos_interesado, solicitud, visto_bueno, cotejo, no_adeudo, invitaciones,
  -- acuse, notificacion, constancias_revisores, constancias_direccion, integracion.
  documento VARCHAR(40) NOT NULL,
  -- PDF que se firma (pos_generados); cada firma reemplaza su ruta y su huella por las del archivo ya sellado.
  generado_id BIGINT UNSIGNED NOT NULL,
  rol ENUM('director','sinodal','coordinacion','egresado') NOT NULL,
  -- Posición del integrante del sínodo (0 a 4 propietarios, 5 y 6 suplentes); NULL en los demás roles.
  orden TINYINT UNSIGNED NULL,
  nombre VARCHAR(160) NOT NULL,
  -- Correo al que se envió el enlace; NULL para la Coordinación y el egresado, que firman con su sesión.
  email VARCHAR(190) NULL,
  token_hash CHAR(64) NULL,
  vence DATETIME NULL,
  -- Dónde va el sello: {"sellos":[{"p":0,"x":108.0,"y":200.5}], "marcas":[…], "paginas":{…}} (mm; p = página desde 0).
  caja JSON NOT NULL,
  -- Visto bueno del sínodo: {"concluida":"si","entregada":"si","periodo":"no","vigencia":"2027-06-30"}.
  respuestas JSON NULL,
  estado ENUM('pendiente','firmada','cancelada') NOT NULL DEFAULT 'pendiente',
  enviada_en DATETIME NULL,
  firmada_en DATETIME NULL,
  -- Cuenta con la que se firmó: la sesión con ese mismo correo, la Coordinación o el egresado; NULL si fue por enlace.
  firmante_id INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  agente VARCHAR(255) NULL,
  hash_firmado CHAR(64) NULL,
  cancelada_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pos_firmas_token (token_hash),
  KEY idx_pos_firmas_tramite (tramite_id, documento, estado),
  KEY idx_pos_firmas_correo (email, estado),
  KEY idx_pos_firmas_rol (rol, estado),
  KEY idx_pos_firmas_generado (generado_id),
  CONSTRAINT fk_pos_firmas_tramite FOREIGN KEY (tramite_id) REFERENCES pos_tramites(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_firmas_generado FOREIGN KEY (generado_id) REFERENCES pos_generados(id) ON DELETE CASCADE,
  CONSTRAINT fk_pos_firmas_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- pos_generados.vigente: 0 cuando el documento se regeneró (cambiaron los datos), se devolvió o se reprogramó.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE pos_generados ADD COLUMN vigente TINYINT(1) NOT NULL DEFAULT 1 AFTER huella', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_generados' AND COLUMN_NAME = 'vigente');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- pos_generados.huella_datos: SHA-256 de los datos del trámite que alimentan el documento; si cambian antes de que
-- firmen todos, el documento se regenera; si ya firmaron todos, esos datos quedan bloqueados para el egresado.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE pos_generados ADD COLUMN huella_datos CHAR(64) NULL AFTER vigente', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_generados' AND COLUMN_NAME = 'huella_datos');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE pos_generados ADD KEY idx_pos_generados_vigente (tramite_id, vigente, clave)', 'DO 0')
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_generados' AND INDEX_NAME = 'idx_pos_generados_vigente');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- usuarios.fise_vigencia: fecha hasta la que es válida la firma electrónica UAEMéx de la persona (la del SICDE, con la
-- que los sinodales firman el acta). La declara la persona en «Mi perfil» o al firmar un visto bueno de posgrado.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN fise_vigencia DATE NULL', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'fise_vigencia');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- usuarios.fise_avisada: vigencia para la que ya se dejó el aviso «tu firma vence en un mes»; evita duplicarlo.
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE usuarios ADD COLUMN fise_avisada DATE NULL AFTER fise_vigencia', 'DO 0')
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'fise_avisada');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
