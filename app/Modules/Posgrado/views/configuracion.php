<?php
use App\Core\Auth;
use App\Modules\Posgrado\Configuracion;

include __DIR__ . '/_estilos.php';
?>
<p class="pos-volver"><a href="<?= url('posgrado/gestion') ?>">← Bandeja de trámites</a></p>
<section class="tarjeta">
  <h2>Quién firma y datos de contacto</h2>
  <p class="texto-secundario">Estos datos van en los oficios y constancias que emite la plataforma. Los cambios aplican a los documentos que se emitan desde ahora; los ya emitidos no cambian.</p>
  <form class="formulario" method="post" action="<?= url('posgrado/configuracion') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="pos-campos">
      <?php foreach (Configuracion::CAMPOS as $clave => [$etiqueta, $omision, $ayuda]): ?>
        <label class="ancho"><?= e($etiqueta) ?>
          <input type="text" name="a[<?= e($clave) ?>]" maxlength="255" value="<?= e($valores[$clave]) ?>" <?= in_array($clave, ['posgrado_coordinador_nombre', 'posgrado_coordinador_cargo'], true) ? 'required' : '' ?>>
          <small><?= e($ayuda) ?></small></label>
      <?php endforeach; ?>
    </div>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar</button></div>
  </form>
</section>
