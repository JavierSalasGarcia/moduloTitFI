<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Define quiénes integran la comisión que revisa los protocolos de cada licenciatura. El <strong>encargado</strong> recibe los protocolos que turna Titulación y elige a los evaluadores; los demás integrantes pueden ser elegidos como evaluadores. Plazo de revisión actual: <strong><?= (int) $dias ?> días hábiles</strong> (se cambia en «Configurar formatos → Parámetros de revisión»).</p>
  <p class="texto-secundario">Basta con el correo: si la persona aún no se registra, recibe el enlace por correo y podrá revisar; para firmar deberá crear su cuenta con ese mismo correo.</p>
</div></div>

<?php foreach ($carreras as $clave => $c): $lista = $miembros[$clave] ?? []; $tieneEnc = (bool) array_filter($lista, static fn(array $m): bool => $m['rol'] === 'encargado' && (int) $m['activo'] === 1); ?>
<section class="tarjeta" id="c-<?= e($clave) ?>" style="margin-bottom:18px">
  <div class="tarjeta-cabecera">
    <h2><?= e($c['nombre']) ?> <small class="texto-secundario">(<?= e(implode('/', $c['siglas'])) ?>)</small></h2>
    <span class="tit-chip <?= $tieneEnc ? 'ok' : 'mal' ?>"><?= $tieneEnc ? 'Con encargado' : 'Sin encargado: no podrá recibir protocolos' ?></span>
  </div>
  <?php $co = $coordinadores[$clave] ?? null; ?>
  <div style="margin:8px 0 14px;padding:10px;border:1px solid var(--borde,#e2e8f0);border-radius:8px">
    <strong>Coordinador de Docencia</strong>
    <?php if ($co): ?>
      <span> · <?= e($co['nombre']) ?> (<?= e($co['email']) ?>)</span>
      <form method="post" action="<?= url('titulacion/estructura/coordinador/' . (int) $co['id'] . '/quitar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Retirar</button></form>
    <?php else: ?><span class="tit-chip mal">Sin coordinador: no se podrán asignar sinodales</span><?php endif; ?>
    <form method="post" action="<?= url('titulacion/estructura/coordinador') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="carrera" value="<?= e($clave) ?>">
      <label>Nombre <input type="text" name="nombre" maxlength="160" required></label>
      <label>Correo <input type="email" name="email" maxlength="120" required></label>
      <button class="btn btn-mini btn-primario" type="submit"><?= $co ? 'Reemplazar' : 'Registrar' ?></button>
    </form>
  </div>
  <?php if ($lista): ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $m): ?>
      <tr style="<?= (int) $m['activo'] ? '' : 'opacity:.55' ?>">
        <td><input form="m<?= (int) $m['id'] ?>" type="text" name="nombre" value="<?= e($m['nombre']) ?>" maxlength="160" aria-label="Nombre"></td>
        <td><?= e($m['email']) ?></td>
        <td><select form="m<?= (int) $m['id'] ?>" name="rol" aria-label="Rol"><option value="miembro" <?= $m['rol'] === 'miembro' ? 'selected' : '' ?>>Integrante</option><option value="encargado" <?= $m['rol'] === 'encargado' ? 'selected' : '' ?>>Encargado</option></select></td>
        <td><input form="m<?= (int) $m['id'] ?>" type="checkbox" name="activo" value="1" <?= (int) $m['activo'] ? 'checked' : '' ?> aria-label="Activo"></td>
        <td style="white-space:nowrap">
          <button form="m<?= (int) $m['id'] ?>" class="btn btn-mini btn-primario" type="submit">Guardar</button>
          <button class="btn btn-mini btn-peligro" type="submit" form="x<?= (int) $m['id'] ?>" onclick="return confirm('¿Retirar a esta persona de la comisión?')">Retirar</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php foreach ($lista as $m): ?>
    <form method="post" action="<?= url('titulacion/estructura/miembros/' . (int) $m['id']) ?>" id="m<?= (int) $m['id'] ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"></form>
    <form method="post" action="<?= url('titulacion/estructura/miembros/' . (int) $m['id'] . '/eliminar') ?>" id="x<?= (int) $m['id'] ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"></form>
  <?php endforeach; ?>
  <?php else: ?><p class="texto-secundario">Aún no hay integrantes.</p><?php endif; ?>
  <form class="formulario" method="post" action="<?= url('titulacion/estructura/miembros') ?>" style="margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="carrera" value="<?= e($clave) ?>">
    <div class="tit-campos">
      <label>Nombre <input type="text" name="nombre" maxlength="160" required></label>
      <label>Correo <input type="email" name="email" maxlength="120" required placeholder="usuario@profesor.uaemex.mx"></label>
      <label>Rol <select name="rol"><option value="miembro">Integrante</option><option value="encargado">Encargado de la comisión</option></select></label>
    </div>
    <div class="acciones-formulario"><button class="btn btn-mini btn-primario" type="submit">Añadir a la comisión</button></div>
  </form>
</section>
<?php endforeach; ?>
