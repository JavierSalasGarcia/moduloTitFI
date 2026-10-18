-- Titulación: las firmas de la Subdirección se aplican sobre el PDF generado y guardado en el expediente.
ALTER TABLE tit_firmas_subdireccion
  ADD COLUMN caja VARCHAR(120) NULL AFTER descripcion;
