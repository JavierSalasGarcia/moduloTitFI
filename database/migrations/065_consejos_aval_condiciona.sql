-- Consejos: tercer desenlace del Consejo Académico («con condición / se turna / se devuelve»), según las actas de 2026.
ALTER TABLE consejos_asuntos
  MODIFY aval_academico ENUM('pendiente','avala','no_avala','condiciona') NOT NULL DEFAULT 'pendiente';
