-- Módulo Constancias: constancias que se solicitan desde la plataforma y firma un titular o coordinador del directorio.
-- Una persona del personal (titular, coordinador, operativo o administración) captura a quién va dirigida, el cuerpo
-- (texto o un archivo de Word con tablas) y quién firma; la constancia se genera en hoja membretada con folio
-- consecutivo anual del departamento del firmante y QR de verificación, el firmante la firma con su sesión (sello
-- «CONFORMIDAD · registro interno» mientras los Consejos no aprueben la firma electrónica) y queda archivada en ese
-- departamento. El índice «Constancias emitidas» reúne además, sin copiarlas, las de Titulación y Posgrado.
-- Tablas con prefijo cst_ (con_ ya es de Concursos). Datos personales cifrados en la aplicación con App\Core\Cifrado (cuerpo, destinatario, persona, observaciones).
-- Solo estructura: ningún dato de personas. Es repetible: puede ejecutarse completa las veces que haga falta.
SET NAMES utf8mb4;

-- Consecutivo anual de folios por departamento firmante (se bloquea con SELECT … FOR UPDATE al apartar un número).
CREATE TABLE IF NOT EXISTS cst_folios (
  departamento_id SMALLINT UNSIGNED NOT NULL,
  anio SMALLINT UNSIGNED NOT NULL,
  ultimo INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (departamento_id, anio),
  CONSTRAINT fk_cst_folios_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada constancia solicitada. Estados: borrador (vista previa) → en_firma → firmada; en_firma → devuelta (con
-- observación; el solicitante corrige y la reenvía) → en_firma; borrador o devuelta → cancelada.
CREATE TABLE IF NOT EXISTS cst_solicitudes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estado ENUM('borrador','en_firma','devuelta','firmada','cancelada') NOT NULL DEFAULT 'borrador',
  solicitante_id INT UNSIGNED NOT NULL,
  -- Firmante elegido del directorio de titulares y coordinadores; nombre y cargo en claro para listar.
  firmante_id INT UNSIGNED NOT NULL,
  firmante_nombre VARCHAR(160) NOT NULL,
  firmante_cargo VARCHAR(255) NOT NULL,
  -- Departamento que emite y archiva la constancia (el del nombramiento del firmante).
  departamento_id SMALLINT UNSIGNED NOT NULL,
  asunto VARCHAR(160) NOT NULL DEFAULT 'CONSTANCIA',
  -- Cifrados (Cifrado::cifrar, contexto «cst_solicitudes.<columna>#<id>»).
  dirigido_a TEXT NULL,
  cuerpo MEDIUMTEXT NULL,
  persona_nombre TEXT NULL,
  persona_email TEXT NULL,
  -- Índice ciego del correo de la persona (para «las que recibí» y la vinculación de cuentas sin descifrar la tabla).
  persona_huella CHAR(66) NULL,
  -- Persona a quien se extiende, si tiene cuenta o ficha de profesor (Identidad::resolver / vincularDatos).
  persona_usuario_id INT UNSIGNED NULL,
  persona_profesor_id INT UNSIGNED NULL,
  origen ENUM('texto','docx') NOT NULL DEFAULT 'texto',
  -- Folio <SIGLAS>/CONST/<n>/<AAAA>: se aparta al enviar a firma por primera vez.
  folio VARCHAR(60) NULL,
  folio_anio SMALLINT UNSIGNED NULL,
  folio_numero INT UNSIGNED NULL,
  -- Departamento al que pertenece el folio (si al corregir se elige un firmante de otro departamento, se aparta otro).
  folio_departamento_id SMALLINT UNSIGNED NULL,
  -- Código aleatorio del QR (constancias/verificar/{codigo}).
  codigo CHAR(20) NOT NULL,
  -- PDF sin firma (vista previa / en firma) y PDF firmado, relativos a storage/.
  ruta VARCHAR(255) NULL,
  huella CHAR(64) NULL,
  caja JSON NULL,
  ruta_firmado VARCHAR(255) NULL,
  huella_firmado CHAR(64) NULL,
  observacion TEXT NULL,
  enviada_en DATETIME NULL,
  devuelta_en DATETIME NULL,
  firmada_en DATETIME NULL,
  firmada_ip VARCHAR(45) NULL,
  firmada_agente VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cst_solicitudes_codigo (codigo),
  UNIQUE KEY uq_cst_solicitudes_folio (folio),
  KEY idx_cst_solicitudes_solicitante (solicitante_id, estado),
  KEY idx_cst_solicitudes_firmante (firmante_id, estado),
  KEY idx_cst_solicitudes_departamento (departamento_id, estado, firmada_en),
  KEY idx_cst_solicitudes_persona (persona_usuario_id),
  KEY idx_cst_solicitudes_huella (persona_huella),
  CONSTRAINT fk_cst_solicitudes_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios(id),
  CONSTRAINT fk_cst_solicitudes_firmante FOREIGN KEY (firmante_id) REFERENCES usuarios(id),
  CONSTRAINT fk_cst_solicitudes_departamento FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
  CONSTRAINT fk_cst_solicitudes_persona FOREIGN KEY (persona_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cada constancia (solicitud, envío, devolución con observación, reenvío, firma, cancelación).
CREATE TABLE IF NOT EXISTS cst_historial (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  solicitud_id BIGINT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  accion ENUM('creada','editada','enviada','devuelta','reenviada','firmada','cancelada') NOT NULL,
  -- Observación de la devolución, cifrada (contexto «cst_historial.nota#<solicitud_id>»).
  nota TEXT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cst_historial_solicitud (solicitud_id, id),
  CONSTRAINT fk_cst_historial_solicitud FOREIGN KEY (solicitud_id) REFERENCES cst_solicitudes(id) ON DELETE CASCADE,
  CONSTRAINT fk_cst_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
