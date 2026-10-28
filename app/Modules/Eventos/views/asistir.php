<?php use App\Core\Auth; ?>
<section class="card" style="max-width:560px;margin:0 auto;text-align:center">
  <h2 style="margin-top:0"><?= e($ev['titulo']) ?></h2>
  <p class="texto-suave"><?= e(fecha_corta($ev['inicio'])) ?> · <?= e($ev['depto']) ?></p>
  <?php if ($asistencia && $asistencia['estado'] === 'validada'): ?>
    <p><strong>Tu asistencia ya está registrada.</strong> Folio <?= e($asistencia['folio']) ?>.</p>
    <a class="btn btn-primario" href="<?= url('eventos/expediente') ?>">Ver mi expediente</a>
  <?php elseif (!$abierta): ?>
    <p>El registro con QR solo está abierto durante el evento. Si asististe, sube tu constancia o capturas en la ficha del evento.</p>
    <a class="btn btn-primario" href="<?= url('eventos/' . (int) $ev['id']) ?>">Ir a la ficha del evento</a>
  <?php else: ?>
    <form method="post" action="<?= url('eventos/' . (int) $ev['id'] . '/asistir/' . $token) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <p>Confirma que estás en el evento, <?= e((string) (Auth::usuario()['nombre'] ?? '')) ?>.</p>
      <button class="btn btn-primario" type="submit" style="font-size:16px;padding:12px 22px">Registrar mi asistencia (+<?= (int) $ev['puntos'] ?> puntos)</button>
    </form>
  <?php endif; ?>
</section>
