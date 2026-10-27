-- Género «No binario» en el registro y el perfil. Repetible: MODIFY deja el mismo resultado si ya está aplicado.
-- Las personas con este valor reciben el saludo neutro («Hola …»), igual que quien no indica género.
ALTER TABLE usuarios MODIFY COLUMN genero ENUM('masculino','femenino','no_binario') NULL;
