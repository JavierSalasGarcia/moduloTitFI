-- Conserva los intervalos semanales al inhabilitarlos temporalmente.
ALTER TABLE horarios
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER fin;
