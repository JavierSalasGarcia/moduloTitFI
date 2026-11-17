<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$borrar = $plan['accion'] === 'borrar';
$x = ['t' => $t, 'plan' => $plan, 'nombre' => $nombre];
?>
<section class="tarjeta pos-confirma" aria-labelledby="pos-dejar-t">
  <p class="pos-volver" style="margin:0 0 6px"><a href="<?= url('posgrado/tramite/' . (int) $t['id']) ?>">← Volver a mi trámite</a></p>
  <h2 id="pos-dejar-t"><?= $borrar ? '¿Borrar tu trámite ' . e((string) $t['folio']) . '?' : '¿Dejar en pausa tu trámite ' . e((string) $t['folio']) . '?' ?></h2>
  <?php include __DIR__ . '/_plan_dejar.php'; ?>
  <form method="post" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/dejar') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="accion" value="<?= e($plan['accion']) ?>">
    <label class="pos-entiendo"><input type="checkbox" name="entiendo" value="1" required>
      <span><?= $borrar
        ? 'Entiendo que mi trámite ' . e((string) $t['folio']) . ' (' . e($nombre) . ') se borrará definitivamente y no podré recuperarlo.'
        : 'Entiendo que mi trámite ' . e((string) $t['folio']) . ' (' . e($nombre) . ') quedará en pausa y que, mientras tanto, nadie podrá firmar nada en él.' ?></span></label>
    <div class="pos-acciones">
      <button class="btn btn-peligro" type="submit"><?= $borrar ? 'Borrar definitivamente' : 'Dejar en pausa' ?></button>
      <a class="btn" href="<?= url('posgrado/tramite/' . (int) $t['id']) ?>">No, volver a mi trámite</a>
    </div>
  </form>
</section>
