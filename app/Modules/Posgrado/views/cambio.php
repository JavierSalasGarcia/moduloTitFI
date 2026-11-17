<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
// $modo: 'iniciar' (comenzar un trámite nuevo) o 'retomar' (uno abandonado); $actuales: trámites activos y su plan.
$hayActivo = $actuales !== [];
$verbo = $modo === 'iniciar' ? 'comenzar' : 'retomar';
?>
<section class="tarjeta pos-confirma" aria-labelledby="pos-cambio-t">
  <p class="pos-volver" style="margin:0 0 6px"><a href="<?= url($volver) ?>">← Volver</a></p>
  <?php if (!$hayActivo): ?>
    <h2 id="pos-cambio-t">¿Retomar <?= e($destinoNombre) ?>?</h2>
    <p class="pos-lead">Volverá a estar activo justo donde se quedó. Las firmas que estaban pendientes vuelven a funcionar si no vencieron; si alguna venció, podrás reenviarla desde tu trámite.</p>
  <?php else: ?>
    <h2 id="pos-cambio-t">Antes de <?= $verbo ?> <?= e($destinoNombre) ?></h2>
    <p class="pos-lead">Solo puedes tener un trámite de grado activo a la vez. Si <?= $modo === 'iniciar' ? 'comienzas uno nuevo' : 'lo retomas' ?>, esto pasará con lo que tienes activo:</p>
    <?php foreach ($actuales as $x): ?>
      <div class="pos-nota"><?php include __DIR__ . '/_plan_dejar.php'; ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <form method="post" action="<?= url($accion) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="confirmo" value="1">
    <?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
    <?php foreach ($actuales as $x): ?><input type="hidden" name="plan[<?= (int) $x['t']['id'] ?>]" value="<?= e($x['plan']['accion']) ?>"><?php endforeach; ?>
    <?php if ($hayActivo):
      $frases = array_map(static fn(array $x): string => $x['t']['folio'] . ' (' . $x['nombre'] . ') ' . ($x['plan']['accion'] === 'borrar' ? 'se borrará definitivamente' : 'pasará a «Abandonadas»'), $actuales); ?>
      <label class="pos-entiendo"><input type="checkbox" name="entiendo" value="1" required>
        <span>Entiendo que mi trámite <?= e(implode(' y mi trámite ', $frases)) ?>.</span></label>
    <?php endif; ?>
    <div class="pos-acciones" style="<?= $hayActivo ? '' : 'margin-top:14px' ?>">
      <button class="btn btn-primario" type="submit">Sí, <?= $verbo ?> <?= e($destinoNombre) ?></button>
      <a class="btn" href="<?= url($volver) ?>"><?= $hayActivo ? 'No, seguir con ' . e((string) $actuales[0]['t']['folio']) : 'No, volver' ?></a>
    </div>
  </form>
</section>
