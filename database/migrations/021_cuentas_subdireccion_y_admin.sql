-- Cuentas institucionales de la Subdirección Académica (octubre 2026). Idempotente.
--  * academica_fi@uaemex.mx es la cuenta oficial de la Subdirección Académica.
--  * subacademica@fi.uaemex.mx (cuenta de demostración) se desactiva.
--  * jsalasg@uaemex.mx pasa a ser administrador (puede impersonar cuentas
--    no administrativas desde Ajustes → Accesos, con registro de auditoría).
-- La cuenta nueva se crea sin contraseña utilizable (permite_contrasena = 0).

INSERT INTO usuarios (nombre, email, password_hash, departamento_id, rol, activo, permite_contrasena)
SELECT 'Subdirección Académica', 'academica_fi@uaemex.mx', '$2y$10$ptw3zGPmiihiW/4l5LLoteROeNSvJ6ok5CUDuRcSX8VRAJCqMXeTG', 24, 'titular', 1, 0
 WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'academica_fi@uaemex.mx');

UPDATE usuarios SET departamento_id = 24, rol = 'titular', activo = 1
 WHERE email = 'academica_fi@uaemex.mx';

UPDATE usuarios SET activo = 0 WHERE email = 'subacademica@fi.uaemex.mx';

UPDATE usuarios SET rol = 'admin' WHERE email = 'jsalasg@uaemex.mx';
