<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$borrar = $plan['accion'] === 'borrar';
$x = ['t' => $t, 'plan' => $plan, 'nombre' => $nombre];
?>
<section class="tarjeta tit-confirma" aria-labelledby="tit-dejar-t">
  <p class="texto-secundario" style="margin:0 0 6px"><a href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>">← Volver a mi trámite</a></p>
  <h2 id="tit-dejar-t"><?= $borrar ? '¿Borrar tu trámite «' . e($nombre) . '»?' : '¿Dejar en pausa tu trámite «' . e($nombre) . '»?' ?></h2>
  <?php include __DIR__ . '/_plan_dejar.php'; ?>
  <form method="post" action="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id'] . '/dejar') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="accion" value="<?= e($plan['accion']) ?>">
    <label class="tit-entiendo"><input type="checkbox" name="entiendo" value="1" required>
      <span><?= $borrar
        ? 'Entiendo que mi trámite «' . e($nombre) . '» se borrará definitivamente y no podré recuperarlo.'
        : 'Entiendo que mi trámite «' . e($nombre) . '» quedará en pausa y que, mientras tanto, nadie podrá firmar ni revisar nada en él.' ?></span></label>
    <div class="tit-acciones">
      <button class="btn btn-peligro" type="submit"><?= $borrar ? 'Borrar definitivamente' : 'Dejar en pausa' ?></button>
      <a class="btn" href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>">No, volver a mi trámite</a>
    </div>
  </form>
</section>
