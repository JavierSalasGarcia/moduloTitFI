<?php use App\Core\Auth; ?>
<p class="texto-suave">Las entradas activas aparecen en la sección <strong>RECURSOS</strong> del menú de todos los usuarios.
Cada una abre una ficha con la descripción, tus notas de administrador y las notas personales del usuario.</p>

<div class="card sin-relleno tabla-contenedor">
  <table class="tabla">
    <thead><tr><th>Orden</th><th>Título</th><th class="oculta-movil">Tipo</th><th class="oculta-movil">Contenido</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($recursos as $r): ?>
        <tr>
          <td><?= (int) $r['orden'] ?></td>
          <td><a href="<?= url('recursos/' . $r['id']) ?>"><?= e($r['titulo']) ?></a></td>
          <td class="oculta-movil"><?= e($r['tipo']) ?></td>
          <td class="oculta-movil texto-suave">
            <?= $r['tipo'] === 'enlace' ? e($r['url']) : ($r['archivo'] ? '📄 ' . e(basename($r['archivo'])) : 'sin archivo aún') ?>
          </td>
          <td><span class="badge <?= $r['activo'] ? 'badge-verde' : 'badge-rojo' ?>"><?= $r['activo'] ? 'activo' : 'retirado' ?></span></td>
          <td>
            <?php if ($r['activo']): ?>
              <form method="post" action="<?= url('recursos/' . $r['id'] . '/eliminar') ?>" data-confirm="¿Retirar «<?= e($r['titulo']) ?>» del menú?">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button type="submit" class="btn btn-mini btn-peligro">Retirar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<section class="card" style="max-width:760px">
  <header class="tarjeta-cabecera"><h2>Agregar o actualizar recurso</h2></header>
  <p class="ayuda-form">Para actualizar uno existente (p. ej. subir el archivo del Calendario Escolar),
  selecciónalo en la lista de abajo; si eliges «— Nuevo recurso —» se crea una entrada nueva.</p>

  <form method="post" action="<?= url('recursos') ?>" class="formulario" enctype="multipart/form-data" id="formRecurso">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

    <div class="form-rejilla">
      <label>Recurso a editar
        <select id="selExistente">
          <option value="">— Nuevo recurso —</option>
          <?php foreach ($recursos as $r): ?>
            <option value="<?= (int) $r['id'] ?>"
                    data-titulo="<?= e($r['titulo']) ?>" data-tipo="<?= e($r['tipo']) ?>"
                    data-url="<?= e($r['url'] ?? '') ?>" data-orden="<?= (int) $r['orden'] ?>"
                    data-descripcion="<?= e($r['descripcion'] ?? '') ?>" data-notas="<?= e($r['notas_admin'] ?? '') ?>">
              <?= e($r['titulo']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Título <span class="req">*</span>
        <input type="text" name="titulo" required maxlength="120">
      </label>
      <label>Tipo
        <select name="tipo" id="selTipo">
          <option value="archivo">Archivo descargable</option>
          <option value="enlace">Enlace externo</option>
        </select>
      </label>
      <label>Orden en el menú
        <input type="number" name="orden" value="50" min="1" max="99">
      </label>
    </div>

    <label id="campoArchivo">Archivo (pdf, imagen, office, zip · máx. 10 MB)
      <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx,.pptx,.zip">
      <p class="ayuda-form">Si editas un recurso y no eliges archivo, se conserva el actual.</p>
    </label>
    <label id="campoUrl" style="display:none">URL del sitio externo
      <input type="text" name="url" placeholder="https://…">
    </label>

    <label>Descripción (visible en la ficha)
      <textarea name="descripcion" rows="2"></textarea>
    </label>
    <label>Notas del administrador (visibles para todos en la ficha)
      <textarea name="notas_admin" rows="2" placeholder="Ej. fechas importantes, versión vigente, a quién acudir…"></textarea>
    </label>

    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario">Guardar recurso</button>
    </div>
  </form>
</section>

<script>
  var sel = document.getElementById('selExistente');
  var form = document.getElementById('formRecurso');
  var tipo = document.getElementById('selTipo');

  function alternarTipo() {
    document.getElementById('campoArchivo').style.display = tipo.value === 'archivo' ? 'block' : 'none';
    document.getElementById('campoUrl').style.display = tipo.value === 'enlace' ? 'block' : 'none';
  }
  tipo.addEventListener('change', alternarTipo);

  sel.addEventListener('change', function () {
    var o = sel.selectedOptions[0];
    form.action = o.value ? '<?= url('recursos') ?>/' + o.value + '/actualizar' : '<?= url('recursos') ?>';
    form.titulo.value = o.dataset.titulo || '';
    form.tipo.value = o.dataset.tipo || 'archivo';
    form.url.value = o.dataset.url || '';
    form.orden.value = o.dataset.orden || 50;
    form.descripcion.value = o.dataset.descripcion || '';
    form.notas_admin.value = o.dataset.notas || '';
    alternarTipo();
  });
</script>
