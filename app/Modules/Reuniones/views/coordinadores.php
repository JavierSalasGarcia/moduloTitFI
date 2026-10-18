<?php use App\Core\Auth; ?>
<div class="barra-acciones"><a class="btn" href="<?= url('reuniones') ?>">Volver a reuniones</a></div>
<div class="card">
  <h2>Coordinadores por posgrado</h2>
  <p class="ayuda">La asignación es explícita y solo utiliza usuarios activos con rol coordinador.</p>
  <form method="post" action="<?= url('reuniones/coordinadores') ?>" class="formulario" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <label>Posgrado<select name="posgrado_id" required><?php foreach ($asignaciones as $a): ?><option value="<?= (int) $a['posgrado_id'] ?>"><?= e($a['codigo']) ?> · <?= e($a['posgrado']) ?></option><?php endforeach; ?></select></label>
    <label>Coordinador<select name="usuario_id" required><option value="">Selecciona</option><?php foreach ($usuarios as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?> · <?= e($u['email']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primario" type="submit">Asignar</button>
  </form>
</div>
<div class="card sin-relleno"><table class="tabla"><thead><tr><th>Posgrado</th><th>Coordinador activo</th><th>Correo</th><th></th></tr></thead><tbody>
<?php foreach ($asignaciones as $a): ?><tr><td><?= e($a['codigo']) ?> · <?= e($a['posgrado']) ?></td><td><?= e($a['nombre'] ?: 'Sin asignar') ?></td><td><?= e($a['email'] ?: '—') ?></td><td><?php if ($a['asignacion_id']): ?><form method="post" action="<?= url('reuniones/coordinadores/' . (int) $a['asignacion_id'] . '/revocar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Revocar</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
