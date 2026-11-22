<?php
/**
 * Filas para subir documentos con su descripción. Variables: $admiteOficio (bool): ofrece «Oficio original escaneado» además de «Anexo».
 * Se usa dentro de un formulario con enctype="multipart/form-data"; el servidor exige la descripción de cada archivo (Services/Adjuntos).
 */
$admiteOficio = !empty($admiteOficio);
$idAdj = 'adj' . substr(md5((string) microtime(true) . random_int(0, 9999)), 0, 6);
?>
<div class="cns-adj" id="<?= e($idAdj) ?>">
  <div class="cns-adj-filas"></div>
  <button type="button" class="btn btn-mini" data-adj-mas>+ Agregar otro archivo</button>
  <span class="cns-ayuda">PDF, imagen (JPG o PNG), Word o Excel; hasta 15 MB cada uno. Cada archivo lleva una breve descripción de qué es.</span>
</div>
<style>
  .cns-adj-fila{display:grid;grid-template-columns:minmax(0,1.1fr) <?= $admiteOficio ? 'minmax(0,.8fr) ' : '' ?>minmax(0,1.6fr);gap:8px;margin:0 0 8px;align-items:center}
  @media (max-width:700px){.cns-adj-fila{grid-template-columns:1fr}}
</style>
<script>
(() => {
  const raiz = document.getElementById(<?= json_encode($idAdj) ?>), filas = raiz.querySelector('.cns-adj-filas'), OFICIO = <?= $admiteOficio ? 'true' : 'false' ?>;
  const nueva = () => {
    const f = document.createElement('div'); f.className = 'cns-adj-fila';
    f.innerHTML = '<input type="file" name="adjuntos[]" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" aria-label="Archivo">'
      + (OFICIO ? '<select name="adjuntos_tipo[]" aria-label="Qué es"><option value="anexo">Anexo</option><option value="oficio">Oficio original escaneado</option></select>' : '')
      + '<input type="text" name="adjuntos_desc[]" maxlength="200" placeholder="De qué es: «Constancias de recursos humanos», «Anexo 1…»" aria-label="Descripción del archivo">';
    filas.append(f);
  };
  nueva();
  raiz.querySelector('[data-adj-mas]').addEventListener('click', nueva);
  const form = raiz.closest('form');
  if (form) form.addEventListener('submit', e => {
    for (const f of filas.children) {
      const arch = f.querySelector('input[type=file]'), desc = f.querySelector('input[type=text]');
      if (arch.files.length && desc.value.trim() === '') { e.preventDefault(); desc.focus(); alert('Escribe de qué es el archivo «' + arch.files[0].name + '» (por ejemplo: Constancias de recursos humanos).'); return; }
    }
  });
})();
</script>
