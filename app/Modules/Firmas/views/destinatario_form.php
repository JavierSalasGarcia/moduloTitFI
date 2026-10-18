<?php use App\Core\{Auth,RecipientGroups}; ?>
<p><a href="<?= url('grupos-destinatarios') ?>">← Volver a grupos</a></p>
<section class="card" style="max-width:850px">
<?php if(!$editable): ?>
  <h2><?= e($g['nombre']) ?></h2><p>Este grupo está compartido contigo. Puedes usarlo, pero solo su creador y el administrador pueden editarlo.</p>
  <ul><?php foreach($miembros as $u): ?><li><?= e($u['nombre']) ?> · <?= e($u['email']) ?><?= empty($u['activo'])?' (inactivo)':'' ?></li><?php endforeach; ?></ul>
<?php else: ?>
<form method="post" action="<?= url('grupos-destinatarios'.($g['id']?'/'.$g['id']:'')) ?>" class="formulario">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="version" value="<?= (int)$g['version'] ?>">
  <label>Nombre del grupo<input type="text" name="nombre" value="<?= e($g['nombre']) ?>" maxlength="160" required placeholder="Ej. Comité de tutoría"></label>
  <h2>Integrantes</h2><p class="ayuda-form">Estas personas serán los destinatarios cuando uses el grupo. Puedes agregar hasta 500.</p>
  <div data-person-picker data-field="miembros" data-selected="<?= e(json_encode($miembros,JSON_UNESCAPED_UNICODE)) ?>">
    <label>Buscar integrantes<input type="search" autocomplete="off" placeholder="Nombre o correo (al menos 2 caracteres)"></label><p data-picker-status role="status"></p><div data-picker-results></div><ul data-picker-selected><?php foreach($miembros as $u): ?><li><?= e($u['nombre']) ?><input type="hidden" name="miembros[]" value="<?= (int)$u['id'] ?>"></li><?php endforeach; ?></ul>
  </div>
  <?php if(Auth::tieneRol('admin')): ?>
  <hr><h2>Quién puede ver y usar este grupo</h2><p class="ayuda-form">Esta audiencia no modifica la lista de integrantes ni recibe permisos para editar.</p>
  <label>Visibilidad<select name="visibilidad" id="grupo-visibilidad"><option value="privado" <?= $g['visibilidad']==='privado'?'selected':'' ?>>Privado · solo su creador</option><option value="publico" <?= $g['visibilidad']==='publico'?'selected':'' ?>>Público · compartir dentro de la plataforma</option></select></label>
  <div id="grupo-audiencia">
    <fieldset><legend>Compartir por categoría</legend><?php foreach(RecipientGroups::CATEGORIES as $key=>$label): ?><label style="display:flex;align-items:center;gap:10px"><input type="checkbox" name="roles[]" value="<?= e($key) ?>" <?= in_array($key,$roles,true)?'checked':'' ?>><?= e($label) ?></label><?php endforeach; ?></fieldset>
    <p class="ayuda-form">Administrativos incluye titulares y operativos. Coordinadores incluye el rol de coordinador y las coordinaciones de posgrado activas.</p>
    <div data-person-picker data-field="audiencia" data-selected="<?= e(json_encode($audiencia,JSON_UNESCAPED_UNICODE)) ?>">
      <label>O compartir con personas específicas<input type="search" autocomplete="off" placeholder="Busca a quien podrá usar el grupo"></label><p data-picker-status role="status"></p><div data-picker-results></div><ul data-picker-selected><?php foreach($audiencia as $u): ?><li><?= e($u['nombre']) ?><input type="hidden" name="audiencia[]" value="<?= (int)$u['id'] ?>"></li><?php endforeach; ?></ul>
    </div>
  </div>
  <p class="ayuda-form">El administrador siempre puede consultar y gestionar el grupo, incluso cuando sea privado.</p>
  <?php else: ?><p class="ayuda-form"><?= $g['visibilidad']==='privado'?'Este grupo es privado: solo tú puedes usarlo, además de la administración.':'La administración ha compartido este grupo. Conservas el derecho de editarlo.' ?> Solo el administrador cambia su visibilidad.</p><?php endif; ?>
  <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar grupo</button><a class="btn" href="<?= url('grupos-destinatarios') ?>">Cancelar</a></div>
</form>
<?php if($g['id']): ?><details style="margin-top:24px"><summary>Eliminar grupo</summary><p>Se eliminará la lista y dejará de estar disponible para todos. No elimina usuarios ni cambia envíos anteriores.</p><form method="post" action="<?= url('grupos-destinatarios/'.$g['id'].'/eliminar') ?>" data-confirm="¿Eliminar este grupo de destinatarios? No se eliminarán usuarios ni envíos anteriores."><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="version" value="<?= (int)$g['version'] ?>"><button class="btn btn-peligro" type="submit">Eliminar definitivamente el grupo</button></form></details><?php endif; ?>
<?php endif; ?>
</section>
<script src="<?= url('assets/js/recipient-groups.js?v='.filemtime(dirname(__DIR__,4).'/public/assets/js/recipient-groups.js')) ?>" defer></script>
