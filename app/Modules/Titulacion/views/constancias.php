<?php
use App\Modules\Titulacion\Services\Constancias;
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <?php if ($busqueda): ?>
    <p class="texto-secundario">Escribe el nombre o el correo institucional del profesor y elígelo de la lista para ver sus constancias de titulación, por año; elige las que necesites para descargarlas en ZIP.
      <?= $alcance === null ? 'Como Subdirector ves las constancias de todos los departamentos.' : 'Ves las constancias que emite el Departamento de Evaluación Profesional y Grado.' ?>
      Las que están «En firma» se pueden abrir o descargar sin firma para revisarlas antes de firmarlas (el archivo dice «SIN_FIRMA»).</p>
  <?php else: ?>
    <p class="texto-secundario">Tus constancias de titulación de licenciatura. Se generan solas cuando se cumple lo que avalan (protocolo aprobado o evaluación profesional concluida) y quedan aquí cuando las firma la Subdirección o la Jefatura de Evaluación Profesional. Elige el año, marca las que necesites y descárgalas en un ZIP, o ábrelas una por una con «Ver».</p>
  <?php endif; ?>
</div></div>

<?php if ($busqueda): ?>
<section class="tarjeta" style="margin-bottom:16px">
  <label for="buscar-prof"><strong>Profesor</strong></label>
  <input type="search" id="buscar-prof" placeholder="Nombre o correo institucional" autocomplete="off" style="width:100%;max-width:520px" value="<?= e((string) ($email ?? '')) ?>">
  <ul id="buscar-res" style="list-style:none;padding:0;margin:6px 0 0;max-width:520px"></ul>
</section>
<script>
(function () {
  var i = document.getElementById('buscar-prof'), r = document.getElementById('buscar-res'), t;
  var url = <?= json_encode(url('titulacion/constancias/personas')) ?>, ir = <?= json_encode(url('titulacion/constancias/busqueda')) ?>;
  i.addEventListener('input', function () {
    clearTimeout(t);
    t = setTimeout(function () {
      var q = i.value.trim();
      if (q.length < 2) { r.innerHTML = ''; return; }
      fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (d) {
        r.innerHTML = '';
        if (!d.length) { r.innerHTML = '<li class="texto-secundario">Sin coincidencias.</li>'; return; }
        d.forEach(function (p) {
          var li = document.createElement('li'), a = document.createElement('a');
          a.href = ir + '?email=' + encodeURIComponent(p.email);
          a.textContent = p.nombre + ' · ' + p.email + ' (' + p.total + ')';
          a.style.display = 'block'; a.style.padding = '6px 8px';
          li.appendChild(a); r.appendChild(li);
        });
      });
    }, 250);
  });
})();
</script>
<?php endif; ?>

<?php if (!$busqueda || $persona):
  $anios = array_values(array_unique(array_column($constancias, 'anio')));
  $firmadas = count(array_filter($constancias, static fn(array $c): bool => !empty($c['subdir_ruta'])));
?>
<section class="tarjeta">
  <div class="tarjeta-cabecera">
    <h2><?= $persona ? e(\App\Core\Persona::tratamiento($persona)) : 'Mis constancias' ?></h2>
    <span class="texto-secundario"><?= count($constancias) ?> constancia(s)<?= $firmadas < count($constancias) ? ', ' . (count($constancias) - $firmadas) . ' en firma' : '' ?></span>
  </div>
  <?php if (!$constancias): ?><div class="ux-empty">Sin constancias.</div>
  <?php else: ?>
  <form method="post" action="<?= url('titulacion/constancias/seleccion') ?>" id="form-constancias">
    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="volver" value="<?= $busqueda ? 'busqueda' : '' ?>">
    <?php if ($busqueda): ?><input type="hidden" name="email" value="<?= e((string) $email) ?>"><?php endif; ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:12px">
      <label style="flex:0 1 180px">Año
        <select id="cns-anio"><option value="">Todos</option><?php foreach ($anios as $i => $an): ?><option value="<?= e($an) ?>" <?= $i === 0 && count($constancias) > 12 ? 'selected' : '' ?>><?= e($an) ?></option><?php endforeach; ?></select></label>
      <label style="display:flex;gap:6px;align-items:center;flex:0 1 auto"><input type="checkbox" id="cns-todas"> Seleccionar todas las mostradas</label>
      <button class="btn btn-primario" type="submit" id="cns-zip" disabled>Descargar seleccionadas (ZIP)</button>
      <span class="texto-secundario" id="cns-cuenta"></span>
    </div>
    <?php foreach ($anios as $an): $grupo = array_filter($constancias, static fn(array $c): bool => $c['anio'] === $an); ?>
    <div class="cns-anio" data-anio="<?= e($an) ?>">
      <h3 style="margin:14px 0 6px"><?= e($an) ?> <span class="texto-secundario" style="font-weight:400;font-size:14px">· <?= count($grupo) ?></span></h3>
      <div class="tabla-contenedor"><table class="tabla">
        <tbody>
        <?php foreach ($grupo as $c): $firmada = !empty($c['subdir_ruta']); $revisable = $firmada || ($busqueda && !empty($c['ruta'])); ?>
          <tr>
            <td style="width:34px"><?php if ($revisable): ?><input type="checkbox" name="c[]" value="<?= e($c['token']) ?>" aria-label="Elegir <?= e($c['descripcion']) ?>"><?php endif; ?></td>
            <td><strong><?= e($c['descripcion']) ?></strong><br>
              <span class="texto-secundario" style="font-size:13px"><?= e(Constancias::titulo((string) $c['tipo'])) ?> · <?= e(Constancias::nombreModalidad((string) $c['modalidad'])) ?> · <?= e($c['folio']) ?> · emite <?= e(Constancias::EMISORES[$c['emisor']] ?? '') ?></span></td>
            <td style="width:190px;text-align:right;white-space:nowrap">
              <?php if (!$firmada): ?><span class="tit-chip rev">En firma</span><?php endif; ?>
              <?php if ($revisable): ?><a class="btn btn-mini" href="<?= url($c['ver']) ?>" target="_blank" rel="noopener" <?= $firmada ? '' : 'title="Versión sin firma, para revisarla antes de firmar"' ?>><?= $firmada ? 'Ver' : 'Ver sin firma' ?></a><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php endforeach; ?>
  </form>
  <script>
  (function () {
    var f = document.getElementById('form-constancias'), anio = document.getElementById('cns-anio'), todas = document.getElementById('cns-todas'),
        boton = document.getElementById('cns-zip'), cuenta = document.getElementById('cns-cuenta');
    function visibles() { return Array.prototype.filter.call(f.querySelectorAll('input[name="c[]"]'), function (c) { return !c.closest('.cns-anio').hidden; }); }
    function actualizar() {
      var n = f.querySelectorAll('input[name="c[]"]:checked').length, v = visibles();
      boton.disabled = n === 0; cuenta.textContent = n ? n + ' seleccionada(s)' : '';
      todas.checked = v.length > 0 && v.every(function (c) { return c.checked; });
    }
    function filtrar() {
      f.querySelectorAll('.cns-anio').forEach(function (g) { g.hidden = anio.value !== '' && g.dataset.anio !== anio.value; });
      f.querySelectorAll('.cns-anio[hidden] input[name="c[]"]').forEach(function (c) { c.checked = false; });
      actualizar();
    }
    anio.addEventListener('change', filtrar);
    todas.addEventListener('change', function () { visibles().forEach(function (c) { c.checked = todas.checked; }); actualizar(); });
    f.addEventListener('change', function (e) { if (e.target.name === 'c[]') { actualizar(); } });
    filtrar();
  })();
  </script>
  <?php endif; ?>
</section>
<?php endif; ?>
