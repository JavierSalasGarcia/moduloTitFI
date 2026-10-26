<p class="texto-secundario">Formularios temporales con enlace público: se llenan sin cuenta en Traza FI. Comparte el enlace de cada uno y consulta aquí sus registros.</p>
<section class="tarjeta">
  <?php if (!$lista): ?><div class="ux-empty">Sin formularios.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Formulario</th><th>Estado</th><th>Registros</th><th>Enlace público</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $f): ?>
      <tr>
        <td><strong><?= e($f['titulo']) ?></strong></td>
        <td><?= e($f['estado']) ?><?= $f['cierra_en'] ? '<br><small class="texto-secundario">cierra ' . e(fecha_corta((string) $f['cierra_en'])) . '</small>' : '' ?></td>
        <td><?= (int) $f['total'] ?></td>
        <td><code style="font-size:12px"><?= e(rtrim((string) \App\Core\App::config('app.url_publica', ''), '/') . url('f/' . $f['slug'])) ?></code></td>
        <td style="white-space:nowrap"><a class="btn btn-mini btn-primario" href="<?= url('formularios/' . (int) $f['id']) ?>">Registros</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
