-- Cambio de titulares (octubre 2026). Idempotente: puede ejecutarse varias veces.
--  * Dr. Javier Salas García pasa a ser Subdirector Académico.
--  * Francisco Héctor Bañuelos García es el nuevo Coordinador de Investigación.
-- La cuenta nueva se crea sin contraseña utilizable (permite_contrasena = 0);
-- ingresa por los demás métodos de acceso o el administrador le asigna una.

UPDATE usuarios
   SET nombre = 'Dr. Javier Salas García', departamento_id = 24
 WHERE email = 'jsalasg@uaemex.mx';

INSERT INTO usuarios (nombre, email, password_hash, departamento_id, rol, activo, permite_contrasena)
SELECT 'Francisco Héctor Bañuelos García', 'hbanuelosg589@profesor.uaemex.mx', '$2y$10$05GtuGcywcwHh8FgSEstCO7zX.X6nuwODElgi82YZC3XIjGqalmCm', 14, 'titular', 1, 0
 WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'hbanuelosg589@profesor.uaemex.mx');

UPDATE usuarios
   SET departamento_id = 14, rol = 'titular', activo = 1
 WHERE email = 'hbanuelosg589@profesor.uaemex.mx';
