<?php
use App\Core\Auth;
use App\Modules\Espacios\Agenda;
include __DIR__ . '/_estilos.php';
$fmt = static fn(string $f): string => Agenda::DIAS[(int) date('N', strtotime($f))] . ' ' . date('d/m/Y', strtotime($f));
$libres = count($fechas) - count($choques);
?>
<section class="tarjeta" style="max-width:760px">
  <h2 style="margin-top:0">Algunas fechas de la serie ya están ocupadas</h2>
  <p><strong><?= e($datos['descripcion']) ?></strong> · <?= e($espacio['nombre']) ?> · <?= e($datos['inicio']) ?>–<?= e($datos['fin']) ?> · <?= count($fechas) ?> sesiones en total.</p>
  <p>Estas <?= count($choques) ?> fechas chocan con otro registro y <strong>no se apartarán</strong>:</p>
  <ul>
    <?php foreach ($choques as $f => $otro): ?><li><strong><?= e($fmt($f)) ?></strong> · ocupado por: <?= e($otro) ?></li><?php endforeach; ?>
  </ul>
  <form method="post" action="<?= url('espacios/registrar') ?>" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="espacio" value="<?= (int) $espacio['id'] ?>">
    <?php foreach ($datos as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
    <input type="hidden" name="repetir" value="semanal"><input type="hidden" name="hasta" value="<?= e($hasta) ?>"><input type="hidden" name="confirmar" value="1">
    <?php foreach ($dias as $d): ?><input type="hidden" name="dias[]" value="<?= (int) $d ?>"><?php endforeach; ?>
    <?php if ($libres > 0): ?><button class="btn btn-primario" type="submit">Guardar las <?= $libres ?> sesiones sin choque</button><?php endif; ?>
    <a class="btn" href="<?= e(url($volver)) ?>">Cancelar y cambiar el horario</a>
  </form>
</section>
