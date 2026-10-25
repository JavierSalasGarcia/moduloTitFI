<?php
use App\Modules\Titulacion\Services\Constancias;
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <?php if ($busqueda): ?>
    <p class="texto-secundario">Escribe el nombre o el correo institucional del profesor y elígelo de la lista para ver sus constancias de titulación y exportarlas en ZIP.
      <?= $alcance === null ? 'Como Subdirector ves las constancias de todos los departamentos.' : 'Ves las constancias que emite el Departamento de Evaluación Profesional y Grado.' ?></p>
  <?php else: ?>
    <p class="texto-secundario">Tus constancias de titulación de licenciatura. Se generan solas cuando se cumple lo que avalan (protocolo aprobado, voto aprobatorio firmado, evaluación profesional concluida) y quedan aquí cuando las firma la Subdirección o la Jefatura de Evaluación Profesional.</p>
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

<?php if (!$busqueda || $persona): ?>
<section class="tarjeta">
  <div class="tarjeta-cabecera">
    <h2><?= $persona ? e(\App\Core\Persona::tratamiento($persona)) : 'Mis constancias' ?></h2>
    <?php if ($busqueda && array_filter($constancias, static fn(array $c): bool => !empty($c['subdir_ruta']))): ?>
      <a class="btn btn-primario" href="<?= url('titulacion/constancias/zip?email=' . rawurlencode((string) $email)) ?>">Exportar todas en ZIP</a>
    <?php endif; ?>
  </div>
  <?php if (!$constancias): ?><div class="ux-empty">Sin constancias.</div>
  <?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Constancia</th><th>Trabajo</th><th>Emite</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($constancias as $c): ?>
      <tr>
        <td><strong><?= e(Constancias::titulo((string) $c['tipo'])) ?></strong></td>
        <td><?= e($c['alumno_nombre']) ?> · <?= e(Constancias::nombreModalidad((string) $c['modalidad'])) ?> · <?= e($c['folio']) ?><br><span class="texto-secundario">«<?= e(mb_strimwidth((string) $c['titulo'], 0, 90, '…')) ?>»</span></td>
        <td><?= e(Constancias::EMISORES[$c['emisor']] ?? '') ?></td>
        <td><?= e(fecha_corta((string) $c['creado_en'])) ?></td>
        <td><?php if ($c['subdir_ruta']): ?><a class="btn btn-mini btn-primario" href="<?= url('titulacion/constancias/' . (int) $c['id']) ?>" target="_blank" rel="noopener">Descargar</a><?php else: ?><span class="tit-chip rev">En firma</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
