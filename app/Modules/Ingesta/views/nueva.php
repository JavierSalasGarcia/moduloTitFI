<?php
use App\Core\Auth;
use App\Modules\Ingesta\Ingesta;

$propia = Ingesta::dependencias()[0] ?? 0;
$etiquetas = [
    'propio' => ['Solo yo', 'Nadie más lo ve.'],
    'departamento' => ['Mi dependencia', 'Quienes pertenecen a ella.'],
    'personal' => ['Todo el personal', 'Personal y profesores, sin alumnos.'],
    'comunidad' => ['Toda la comunidad', 'Incluye a los alumnos.'],
];
?>
<p><a href="<?= url('ingesta') ?>">← Documentos indizados</a></p>

<form class="card formulario ing-simple" data-ingesta-lote data-csrf="<?= e(Auth::csrfToken()) ?>"
      data-doc="<?= url('ingesta/documento') ?>" data-carpeta="<?= url('ingesta/carpeta') ?>" data-base="<?= url('ingesta/carpeta/') ?>">

  <h2>1. ¿Qué vas a ingresar?</h2>
  <div class="ing-zona" data-zona tabindex="0">
    <p><strong>Arrastra aquí tus PDF o una carpeta</strong></p>
    <p>
      <label class="btn">Elegir PDF <input type="file" accept="application/pdf,.pdf" multiple hidden data-sin-mejora data-archivos></label>
      <label class="btn">Elegir una carpeta <input type="file" accept="application/pdf,.pdf" webkitdirectory multiple hidden data-sin-mejora data-carpeta-archivos></label>
    </p>
    <p class="ayuda-form" data-seleccion>Hasta <?= (int) $maxMb ?> MB por PDF. Se guardan fuera del sitio público.</p>
  </div>
  <label>…o pega enlaces de Teams, SharePoint u OneDrive (uno por línea)
    <textarea name="enlaces" rows="3" data-enlaces placeholder="https://…sharepoint.com/…/acta12.pdf&#10;Reglamento | https://…sharepoint.com/…/reglamento.pdf"></textarea>
  </label>
  <p class="ayuda-form">La plataforma no lee el contenido de Teams: guarda el enlace y lo que captures; el acceso al archivo lo controla Microsoft 365.</p>

  <h2>2. ¿Quién puede consultarlos?</h2>
  <div class="ing-alcances">
    <?php foreach (Ingesta::ALCANCES as $clave => $_): ?>
      <label class="ing-alcance"><input type="radio" name="alcance" value="<?= e($clave) ?>" <?= $clave === 'departamento' ? 'checked' : '' ?>>
        <strong><?= e($etiquetas[$clave][0]) ?></strong><small><?= e($etiquetas[$clave][1]) ?></small></label>
    <?php endforeach; ?>
  </div>

  <details class="ing-mas">
    <summary>Más datos (opcional: todo se completa solo)</summary>
    <label>Nombre de la carpeta <small class="opcional">si ingresas varios documentos</small><input name="nombre" maxlength="200" placeholder="Si lo dejas vacío se usa el nombre de la carpeta o la fecha"></label>
    <label>Título <small class="opcional">si ingresas uno solo</small><input name="titulo" maxlength="250" placeholder="Se toma del PDF o del nombre del archivo"></label>
    <label>Tipo de documento<input name="tipo_documento" maxlength="100" placeholder="Acta, reglamento, oficio, plan de estudios…"></label>
    <label>Palabras clave<input name="palabras_clave" maxlength="2000" placeholder="Separadas por comas"></label>
    <label>Resumen<textarea name="resumen" rows="2" maxlength="4000"></textarea></label>
    <label>Dependencia a la que pertenecen
      <select name="departamento_id">
        <?php foreach ($departamentos as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $propia ? 'selected' : '' ?>><?= e($d['nombre']) ?></option><?php endforeach; ?>
      </select>
    </label>
  </details>

  <button class="btn btn-primario" type="submit">Indizar</button>
  <p role="status" data-ingesta-estado></p>
</form>

<?php include __DIR__ . '/_lote_js.php'; ?>
<script>
(function () {
  const form = document.querySelector('[data-doc]');
  if (!form) return;
  const estado = form.querySelector('[data-ingesta-estado]'), boton = form.querySelector('button[type="submit"]'), zona = form.querySelector('[data-zona]');
  const sel = form.querySelector('[data-seleccion]'), enlaces = form.querySelector('[data-enlaces]');
  const aviso = sel.textContent;
  let pdfs = [];
  const esPdf = (f) => /\.pdf$/i.test(f.name);
  function elegir(lista) {
    pdfs = Array.from(lista).filter(esPdf);
    const otros = lista.length - pdfs.length;
    sel.textContent = pdfs.length ? pdfs.length + ' PDF seleccionado(s)' + (otros ? ' (' + otros + ' archivo(s) que no son PDF se omiten)' : '') + '.' : aviso;
  }
  form.querySelector('[data-archivos]').addEventListener('change', (e) => elegir(e.target.files));
  form.querySelector('[data-carpeta-archivos]').addEventListener('change', (e) => elegir(e.target.files));
  ['dragenter', 'dragover'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.add('ing-zona-activa'); }));
  ['dragleave', 'drop'].forEach((n) => zona.addEventListener(n, (e) => { e.preventDefault(); zona.classList.remove('ing-zona-activa'); }));
  zona.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files.length) elegir(e.dataTransfer.files); });

  const campo = (n) => (form.elements[n] ? form.elements[n].value : '');
  const fecha = new Date().toLocaleDateString('es-MX');
  async function enviar(url, datos) {
    const r = await fetch(url, { method: 'POST', body: datos, headers: { 'Accept': 'application/json' } });
    const cuerpo = await r.json().catch(() => ({ ok: false, error: 'Respuesta no válida (' + r.status + ')' }));
    if (!cuerpo.ok) throw new Error(cuerpo.error || 'No se pudo ingresar.');
    return cuerpo;
  }
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const lineas = enlaces.value.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
    if (!pdfs.length && !lineas.length) { estado.textContent = 'Elige PDF, una carpeta o pega al menos un enlace.'; return; }
    if (pdfs.length && lineas.length) { estado.textContent = 'Ingresa PDF o enlaces, no ambos a la vez (hazlo en dos pasos).'; return; }
    const base = new FormData();
    base.set('_csrf', form.dataset.csrf); base.set('formato', 'json');
    for (const n of ['alcance', 'departamento_id', 'tipo_documento', 'palabras_clave']) base.set(n, campo(n));
    const radio = form.querySelector('input[name="alcance"]:checked'); base.set('alcance', radio ? radio.value : 'departamento');
    boton.disabled = true; estado.textContent = 'Ingresando…';
    try {
      if (pdfs.length === 1) {                                       // un PDF
        const d = new FormData(base); d.set('origen', 'pdf'); d.set('archivo', pdfs[0]);
        for (const n of ['titulo', 'resumen']) d.set(n, campo(n));
        location.href = (await enviar(form.dataset.doc, d)).url; return;
      }
      if (lineas.length === 1) {                                     // un enlace
        const m = lineas[0].split('|'), enlace = m.pop().trim(), titulo = m.join('|').trim() || campo('titulo');
        const d = new FormData(base); d.set('origen', 'teams'); d.set('enlace', enlace); d.set('titulo', titulo); d.set('resumen', campo('resumen'));
        location.href = (await enviar(form.dataset.doc, d)).url; return;
      }
      const d = new FormData(base);                                  // varios: se crea una carpeta
      if (pdfs.length) {
        const raiz = (pdfs[0].webkitRelativePath || '').split('/')[0];
        d.set('origen', 'pdf'); d.set('nombre', campo('nombre') || raiz || 'PDF del ' + fecha); d.set('descripcion', '');
        const c = await enviar(form.dataset.carpeta, d);
        const fin = await window.ingestaSubirLote(form.dataset.base + c.id + '/archivo', form.dataset.csrf, pdfs);
        if (!fin.fallos.length) { location.href = c.url; } else { estado.insertAdjacentHTML('beforeend', ' <a href="' + c.url + '">Abrir la carpeta</a>'); boton.disabled = false; }
        return;
      }
      d.set('origen', 'teams'); d.set('nombre', campo('nombre') || 'Enlaces de Teams del ' + fecha); d.set('descripcion', ''); d.set('enlaces', lineas.join('\n'));
      location.href = (await enviar(form.dataset.carpeta, d)).url;
    } catch (e) { estado.textContent = e.message || 'No se pudo ingresar. Revisa tu conexión.'; boton.disabled = false; }
  });
})();
</script>
