-- Cifrado de secretos TOTP en reposo (docs/CIFRADO_DE_DATOS.md).
-- El valor cifrado («enc:v1:…») mide unos 90 caracteres; la columna original admitía 32. Repetible: solo ensancha si
-- falta y no toca los datos. El cifrado de los secretos existentes se hace con tools/cifrar_totp.php, no desde SQL,
-- porque la llave nunca debe estar en la base de datos.
SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios MODIFY COLUMN totp_secret VARCHAR(255) NULL DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_secret'
  AND (DATA_TYPE <> 'varchar' OR CHARACTER_MAXIMUM_LENGTH < 255));
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
