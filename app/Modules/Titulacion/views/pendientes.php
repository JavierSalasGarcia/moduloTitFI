<?php
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Lo que te toca hacer en los trámites de titulación. Desde aquí lo atiendes con tu sesión, sin buscar el enlace del correo.</p>
</div></div>
<section class="tarjeta">
  <?php if (!$items): ?><div class="ux-empty">No tienes pendientes. ¡Todo al día!</div>
  <?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Tipo</th><th>Qué hacer</th><th>Trámite</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><span class="tit-chip rev"><?= e($i['tipo']) ?></span></td>
        <td><?= e($i['texto']) ?><?php if ($i['fecha']): ?><br><small class="texto-secundario">Desde <?= e(fecha_corta((string) $i['fecha'])) ?></small><?php endif; ?></td>
        <td><?php if ($i['tramiteId']): ?><a href="<?= url('titulacion/ruta/' . (int) $i['tramiteId']) ?>"><?= e((string) $i['folio']) ?> · ruta</a><?php endif; ?></td>
        <td><a class="btn btn-mini btn-primario" href="<?= url($i['enlace']) ?>">Atender</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
