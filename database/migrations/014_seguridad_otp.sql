-- Los códigos temporales dejan de almacenarse en texto claro.
-- Los códigos existentes expiran en diez minutos y se invalidan al desplegar.
ALTER TABLE usuarios_codigos_otp
  MODIFY COLUMN codigo VARCHAR(255) NOT NULL;
