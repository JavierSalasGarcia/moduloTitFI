<?php
use App\Core\Auth;
use App\Modules\Ingesta\Ingesta;

/** Campos comunes: quién puede consultar y a qué dependencia pertenece. */
$destino = function () use ($departamentos): void { ?>
  <label>¿Quién puede consultarlos?
    <select name="alcance" required>
      <?php foreach (Ingesta::ALCANCES as $clave => $texto): ?><option value="<?= e($clave) ?>" <?= $clave === 'departamento' ? 'selected' : '' ?>><?= e($texto) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Dependencia a la que pertenecen
    <select name="departamento_id">
      <?php foreach ($departamentos as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?></option><?php endforeach; ?>
    </select>
  </label>
<?php };
$metadatos = function (): void { ?>
  <label>Tipo de documento <span class="opcional">Opcional</span><input name="tipo_documento" maxlength="100" placeholder="Acta, reglamento, oficio, plan de estudios…"></label>
  <label>Palabras clave <span class="opcional">Opcional</span><input name="palabras_clave" maxlength="2000" placeholder="Separadas por comas"></label>
<?php };
?>
<p><a href="<?= url('ingesta') ?>">← Documentos indizados</a></p>
<p>Los archivos se guardan fuera del sitio público y solo los ve quien indiques. Máximo <?= (int) $maxMb ?> MB por PDF.</p>

<section class="card">
  <h2>1. Un PDF</h2>
  <form method="post" class="formulario" enctype="multipart/form-data" action="<?= url('ingesta/documento') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="origen" value="pdf">
    <label>Archivo PDF<input type="file" name="archivo" accept="application/pdf,.pdf" required></label>
    <label>Título <span class="opcional">Opcional: se toma del PDF o del nombre del archivo</span><input name="titulo" maxlength="250"></label>
    <label>Autor o emisor <span class="opcional">Opcional</span><input name="autor" maxlength="200"></label>
    <label>Fecha del documento <span class="opcional">Opcional</span><input type="date" name="fecha_documento"></label>
    <?php $metadatos(); ?>
    <label>Resumen <span class="opcional">Opcional</span><textarea name="resumen" rows="3" maxlength="4000"></textarea></label>
    <?php $destino(); ?>
    <button class="btn btn-primario">Indizar PDF</button>
  </form>
</section>

<section class="card">
  <h2>2. Una carpeta de PDFs</h2>
  <p>Elige la carpeta en tu equipo: se indizan todos sus PDF, con sus subcarpetas, uno por uno.</p>
  <form class="formulario" data-ingesta-lote data-crear="<?= url('ingesta/carpeta') ?>" data-csrf="<?= e(Auth::csrfToken()) ?>" data-base="<?= url('ingesta/carpeta/') ?>">
    <label>Nombre de la carpeta<input name="nombre" maxlength="200" required></label>
    <label>Descripción <span class="opcional">Opcional</span><textarea name="descripcion" rows="2" maxlength="4000"></textarea></label>
    <?php $destino(); ?>
    <label>Carpeta con PDFs<input type="file" accept="application/pdf,.pdf" webkitdirectory multiple required data-carpeta-archivos></label>
    <button class="btn btn-primario" type="submit">Crear carpeta e indizar</button>
    <p role="status" data-ingesta-estado></p>
  </form>
</section>

<section class="card">
  <h2>3. Un archivo de Teams</h2>
  <form method="post" class="formulario" action="<?= url('ingesta/documento') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="origen" value="teams">
    <label>Enlace del archivo en Teams, SharePoint u OneDrive<input type="url" name="enlace" maxlength="1000" required placeholder="https://…sharepoint.com/…"></label>
    <label>Título <span class="opcional">Opcional</span><input name="titulo" maxlength="250"></label>
    <?php $metadatos(); ?>
    <label>Resumen <span class="opcional">Opcional</span><textarea name="resumen" rows="2" maxlength="4000"></textarea></label>
    <?php $destino(); ?>
    <button class="btn btn-primario">Indizar enlace</button>
  </form>
</section>

<section class="card">
  <h2>4. Una carpeta de Teams</h2>
  <p>Pega el enlace de la carpeta y los enlaces de sus archivos (en Teams: «Copiar vínculo» en cada archivo). La plataforma no lee el contenido de Teams: guarda los enlaces y los metadatos que captures, y el acceso al archivo lo sigue controlando Microsoft 365.</p>
  <form method="post" class="formulario" action="<?= url('ingesta/carpeta') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="origen" value="teams">
    <label>Nombre de la carpeta<input name="nombre" maxlength="200" required></label>
    <label>Enlace de la carpeta <span class="opcional">Opcional</span><input type="url" name="enlace_carpeta" maxlength="1000" placeholder="https://…sharepoint.com/…"></label>
    <label>Descripción <span class="opcional">Opcional</span><textarea name="descripcion" rows="2" maxlength="4000"></textarea></label>
    <label>Enlaces de los archivos (uno por línea; opcionalmente «Título | enlace»)
      <textarea name="enlaces" rows="6" required placeholder="Acta de sesión 12 | https://…sharepoint.com/…/acta12.pdf&#10;https://…sharepoint.com/…/reglamento.pdf"></textarea>
    </label>
    <?php $metadatos(); ?>
    <?php $destino(); ?>
    <button class="btn btn-primario">Crear carpeta e indizar enlaces</button>
  </form>
</section>

<?php include __DIR__ . '/_lote_js.php'; ?>
<script>
(function () {
  const form = document.querySelector('[data-crear]');
  if (!form) return;
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const estado = form.querySelector('[data-ingesta-estado]'), boton = form.querySelector('button[type="submit"]');
    const archivos = form.querySelector('[data-carpeta-archivos]').files;
    if (!Array.from(archivos).some((f) => /\.pdf$/i.test(f.name))) { estado.textContent = 'La carpeta no contiene archivos PDF.'; return; }
    boton.disabled = true; estado.textContent = 'Creando la carpeta…';
    const datos = new FormData();
    datos.set('_csrf', form.dataset.csrf); datos.set('formato', 'json'); datos.set('origen', 'pdf');
    for (const campo of ['nombre', 'descripcion', 'alcance', 'departamento_id']) datos.set(campo, form.elements[campo].value);
    try {
      const r = await fetch(form.dataset.crear, { method: 'POST', body: datos, headers: { 'Accept': 'application/json' } });
      const cuerpo = await r.json();
      if (!cuerpo.ok) { estado.textContent = cuerpo.error || 'No se pudo crear la carpeta.'; boton.disabled = false; return; }
      const fin = await window.ingestaSubirLote(form.dataset.base + cuerpo.id + '/archivo', form.dataset.csrf, archivos);
      if (!fin.fallos.length) { location.href = cuerpo.url; } else { estado.insertAdjacentHTML('beforeend', ' <a href="' + cuerpo.url + '">Abrir la carpeta</a>'); boton.disabled = false; }
    } catch (e) { estado.textContent = 'No se pudo crear la carpeta. Revisa tu conexión.'; boton.disabled = false; }
  });
})();
</script>
