<?php use App\Core\Auth; ?>
<section class="tarjeta" style="max-width:720px">
  <div class="tarjeta-cabecera">
    <div>
      <h2><?= icono('lista', 18) ?> Folio <?= e($folio) ?></h2>
      <p class="texto-secundario"><?= e($oficina['nombre']) ?> · este código todavía no tiene una entrada en la bitácora del servidor.</p>
    </div>
  </div>
  <p>¿Deseas abrir el expediente para comenzar su seguimiento? Al continuar se creará su carpeta protegida y tu correo quedará como primer usuario autorizado.</p>
  <form method="post" action="<?= url('fila/' . $oficina['ruta'] . '/reg.php?' . rawurlencode($folio)) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="accion" value="crear">
    <button class="btn btn-primario" type="submit"><?= icono('mas', 16) ?> Abrir esta entrada</button>
  </form>
</section>
