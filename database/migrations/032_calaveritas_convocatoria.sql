-- Concurso de calaveritas 2026: enlace de la convocatoria oficial y lugar de la premiación en las bases.
-- Repetible: cada cambio se aplica una sola vez y respeta lo que la administración haya editado.

SET NAMES utf8mb4;

UPDATE con_concursos
   SET bases = CONCAT('## Convocatoria\nConsulta la convocatoria oficial: https://www.facebook.com/share/p/1A1xwSwwnC/\n\n', bases)
 WHERE slug = 'calaveritas-2026' AND bases NOT LIKE '%1A1xwSwwnC%';

UPDATE con_concursos
   SET bases = REPLACE(bases, 'fecha de la premiación.', 'fecha de la premiación.\n\nLa premiación será el viernes 30 de octubre a las 4:00 pm en la Biblioteca de la FI.')
 WHERE slug = 'calaveritas-2026' AND bases LIKE '%fecha de la premiación.%' AND bases NOT LIKE '%en la Biblioteca de la FI%';
