<?php
use App\Core\Auth;
use App\Modules\Espacios\Agenda;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="esp-cabecera">
  <p class="texto-secundario" style="margin:0">Da de alta auditorios y salas, define su horario de uso y a las personas autorizadas para registrar su ocupación. Los exámenes de titulación se apartan solos en el espacio que configure Titulación, aunque el pasante no esté autorizado.</p>
  <a class="btn btn-mini" href="<?= url('espacios') ?>">← Calendario</a>
</div>

<?php foreach ($espacios as $e): $id = (int) $e['id']; ?>
<section class="tarjeta" id="esp-<?= $id ?>" style="margin-bottom:16px">
  <div class="tarjeta-cabecera"><h2><?= e($e['nombre']) ?> <?= (int) $e['activo'] ? '' : '<small class="texto-secundario">(inactivo)</small>' ?></h2></div>
  <details>
    <summary>Datos del espacio</summary>
    <form class="formulario" method="post" action="<?= url('espacios/admin/espacio') ?>" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="esp-campos">
        <label>Nombre <input type="text" name="nombre" maxlength="120" required value="<?= e($e['nombre']) ?>"></label>
        <label>Ubicación <input type="text" name="ubicacion" maxlength="160" value="<?= e((string) $e['ubicacion']) ?>"></label>
        <label>Cupo <input type="number" name="capacidad" min="0" max="65000" value="<?= e((string) $e['capacidad']) ?>"></label>
        <label>Orden <input type="number" name="orden" min="0" max="9999" value="<?= (int) $e['orden'] ?>"></label>
        <label>Estado <select name="activo"><option value="1" <?= (int) $e['activo'] ? 'selected' : '' ?>>Activo</option><option value="0" <?= (int) $e['activo'] ? '' : 'selected' ?>>Inactivo</option></select></label>
        <label class="ancho">Descripción <input type="text" name="descripcion" maxlength="500" value="<?= e((string) $e['descripcion']) ?>"></label>
      </div>
      <div class="acciones-formulario"><button class="btn btn-primario btn-mini" type="submit">Guardar datos</button></div>
    </form>
  </details>

  <h3>Horario de uso</h3>
  <form class="formulario" method="post" action="<?= url('espacios/admin/espacio/' . $id . '/horario') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="esp-horario">
      <?php foreach (Agenda::DIAS as $dia => $nombre): ?>
        <label><?= e($nombre) ?>
          <input type="text" name="h[<?= $dia ?>]" placeholder="Sin servicio" value="<?= e(implode(', ', array_map(static fn(array $h): string => $h['inicio'] . '-' . $h['fin'], $e['horario'][$dia] ?? []))) ?>"></label>
      <?php endforeach; ?>
    </div>
    <p class="texto-secundario" style="font-size:13px">Escribe uno o varios rangos por día, separados por coma (p. ej. 08:00-14:00, 16:00-19:00). Vacío: no se presta ese día.</p>
    <div class="acciones-formulario"><button class="btn btn-primario btn-mini" type="submit">Guardar horario</button></div>
  </form>

  <h3>Personas autorizadas para registrar su ocupación</h3>
  <?php if (!$e['autorizados']): ?><p class="texto-secundario">Nadie aún (los administradores del módulo siempre pueden registrar).</p><?php endif; ?>
  <ul style="list-style:none;padding:0;margin:6px 0 0">
    <?php foreach ($e['autorizados'] as $u): ?>
      <li style="display:flex;gap:10px;align-items:center;margin:4px 0"><span><?= e($u['nombre']) ?> · <span class="texto-secundario"><?= e($u['email']) ?></span></span>
        <form method="post" action="<?= url('espacios/admin/espacio/' . $id . '/autorizados/' . (int) $u['id'] . '/quitar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Quitar</button></form></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="<?= url('espacios/admin/espacio/' . $id . '/autorizados') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:8px">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Correo de la cuenta <input type="email" name="email" maxlength="120" required data-prof-correo="a<?= $id ?>" list="dl-prof-a<?= $id ?>-c" autocomplete="off"></label>
    <datalist id="dl-prof-a<?= $id ?>-c"></datalist>
    <button class="btn btn-primario btn-mini" type="submit">Autorizar</button>
  </form>
</section>
<?php endforeach; ?>

<section class="tarjeta" style="margin-bottom:16px">
  <h2>Agregar espacio</h2>
  <form class="formulario" method="post" action="<?= url('espacios/admin/espacio') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="esp-campos">
      <label>Nombre <input type="text" name="nombre" maxlength="120" required></label>
      <label>Ubicación <input type="text" name="ubicacion" maxlength="160"></label>
      <label>Cupo <input type="number" name="capacidad" min="0" max="65000"></label>
      <label>Orden <input type="number" name="orden" min="0" max="9999" value="100"></label>
      <label class="ancho">Descripción <input type="text" name="descripcion" maxlength="500"></label>
    </div>
    <p class="texto-secundario" style="font-size:13px">Se crea con horario de lunes a viernes de 8:00 a 20:00; ajústalo después.</p>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Agregar</button></div>
  </form>
</section>

<?php if ($esAdminGeneral): ?>
<section class="tarjeta" id="administradores">
  <h2>Administradores del módulo</h2>
  <p class="texto-secundario">Los nombra el administrador general. Pueden dar de alta espacios, cambiar horarios, autorizar personas y liberar cualquier registro.</p>
  <ul style="list-style:none;padding:0;margin:6px 0 0">
    <?php foreach ($administradores as $u): ?>
      <li style="display:flex;gap:10px;align-items:center;margin:4px 0"><span><?= e($u['nombre']) ?> · <span class="texto-secundario"><?= e($u['email']) ?></span></span>
        <form method="post" action="<?= url('espacios/admin/administradores/' . (int) $u['id'] . '/quitar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Quitar</button></form></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="<?= url('espacios/admin/administradores') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:8px">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Correo de la cuenta <input type="email" name="email" maxlength="120" required data-prof-correo="adm" list="dl-prof-adm-c" autocomplete="off"></label>
    <datalist id="dl-prof-adm-c"></datalist>
    <button class="btn btn-primario btn-mini" type="submit">Nombrar administrador</button>
  </form>
</section>
<?php endif; ?>
<script src="<?= url('assets/js/titulacion-profesores.js') ?>" data-url="<?= url('espacios/personas') ?>" defer></script>
