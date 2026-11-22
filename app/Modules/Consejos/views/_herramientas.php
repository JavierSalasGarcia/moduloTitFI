<div class="cns-herr" role="toolbar" aria-label="Formato">
  <button type="button" class="btn btn-mini" data-cmd="bold" title="Negrita"><b>N</b></button>
  <button type="button" class="btn btn-mini" data-cmd="italic" title="Cursiva"><i>C</i></button>
  <?php if (empty($soloTexto)): ?><button type="button" class="btn btn-mini" data-cmd="insertUnorderedList" title="Lista con viñetas">• Lista</button>
  <?php endif; ?><button type="button" class="btn btn-mini" data-cmd="mencionar" title="Mencionar a un profesor: escribe @ y parte de su nombre o correo">@ Mencionar</button>
  <?php if (empty($soloTexto)): ?><button type="button" class="btn btn-mini" data-cmd="tabla" title="Insertar una tabla vacía">▦ Tabla nueva</button>
  <button type="button" class="btn btn-mini" data-cmd="fila+" title="Agregar una fila debajo de la celda donde estás">+ fila</button>
  <button type="button" class="btn btn-mini" data-cmd="fila-" title="Quitar la fila de la celda donde estás">− fila</button>
  <button type="button" class="btn btn-mini" data-cmd="col+" title="Agregar una columna a la derecha">+ columna</button>
  <button type="button" class="btn btn-mini" data-cmd="col-" title="Quitar la columna">− columna</button>
  <button type="button" class="btn btn-mini" data-cmd="tabla-" title="Borrar la tabla">Borrar tabla</button><?php endif; ?>
</div>
