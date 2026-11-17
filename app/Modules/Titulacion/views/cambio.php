<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
// $modo: 'iniciar' (eligió otra modalidad) o 'retomar' (retoma una abandonada); $actuales: trámites activos y su plan.
$verbo = $modo === 'iniciar' ? 'iniciar' : 'retomar';
$hayActivo = $actuales !== [];
?>
<section class="tarjeta tit-confirma" aria-labelledby="tit-cambio-t">
  <p class="texto-secundario" style="margin:0 0 6px"><a href="<?= url($volver) ?>">← Volver</a></p>
  <?php if (!$hayActivo): ?>
    <h2 id="tit-cambio-t">¿Retomar tu trámite «<?= e($destinoNombre) ?>»?</h2>
    <p class="tit-lead">Volverá a estar activo justo donde se quedó. Las firmas que estaban pendientes vuelven a funcionar si no vencieron; si alguna venció, podrás reenviarla desde tu trámite.</p>
  <?php else: ?>
    <h2 id="tit-cambio-t">Antes de <?= $verbo ?> «<?= e($destinoNombre) ?>»</h2>
    <p class="tit-lead">Solo puedes tener una modalidad activa a la vez. Si <?= $modo === 'iniciar' ? 'inicias' : 'retomas' ?> «<?= e($destinoNombre) ?>», esto pasará con lo que tienes activo:</p>
    <?php foreach ($actuales as $x): ?>
      <div class="tit-aviso" style="margin:12px 0"><?php include __DIR__ . '/_plan_dejar.php'; ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <form method="post" action="<?= url($accion) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="confirmo" value="1">
    <?php foreach ($ocultos as $k => $v): ?><input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
    <?php foreach ($actuales as $x): ?><input type="hidden" name="plan[<?= (int) $x['t']['id'] ?>]" value="<?= e($x['plan']['accion']) ?>"><?php endforeach; ?>
    <?php if ($hayActivo):
      $frases = array_map(static fn(array $x): string => '«' . $x['nombre'] . '» ' . ($x['plan']['accion'] === 'borrar' ? 'se borrará definitivamente' : 'pasará a «Abandonadas»'), $actuales); ?>
      <label class="tit-entiendo"><input type="checkbox" name="entiendo" value="1" required>
        <span>Entiendo que mi trámite <?= e(implode(' y mi trámite ', $frases)) ?>.</span></label>
    <?php endif; ?>
    <div class="tit-acciones" style="<?= $hayActivo ? '' : 'margin-top:14px' ?>">
      <button class="tit-boton" type="submit">Sí, <?= $verbo ?> «<?= e($destinoNombre) ?>»</button>
      <a class="btn" href="<?= url($volver) ?>"><?= $hayActivo ? 'No, seguir con «' . e($actuales[0]['nombre']) . '»' : 'No, volver' ?></a>
    </div>
  </form>
</section>
