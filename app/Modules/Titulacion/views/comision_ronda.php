<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$base = 'titulacion/comision/' . (int) $r['id'];
$activas = array_filter($asignaciones, static fn(array $a): bool => $a['estado'] !== 'cancelada');
$puedeAsignar = in_array($r['estado'], ['en_comision', 'en_evaluacion', 'por_dictaminar'], true);
$etiquetas = ['aprobado' => 'Propone aprobar', 'no_aprobado' => 'Propone no aprobar'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/comision') ?>">← Mi comisión</a></p>
  <h2 style="margin:4px 0"><?= e($r['alumno_nombre']) ?> · <?= e($r['folio']) ?> <small class="texto-secundario">(<?= (int) $r['numero'] ?>ª revisión)</small></h2>
  <p class="texto-secundario">«<?= e((string) $r['titulo']) ?>» · <?= e(\App\Modules\Titulacion\Catalogo::modalidad((string) $r['modalidad'])['nombre'] ?? '') ?></p>
</div>
<div><a class="btn btn-primario" href="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/documento') ?>" target="_blank" rel="noopener">Ver protocolo (PDF)</a></div></div>

<?php if ($r['estado'] === 'dictaminada'): ?>
  <section class="tarjeta" style="margin-bottom:16px">
    <h2>Dictamen emitido</h2>
    <p><span class="tit-chip <?= $r['dictamen'] === 'aprobado' ? 'ok' : 'mal' ?>"><?= $r['dictamen'] === 'aprobado' ? 'Se aprueba' : 'No se aprueba' ?></span>
      · Firmado el <?= e(fecha_corta((string) $r['dictaminado_en'])) ?> · <a href="<?= url('titulacion/licenciatura/tramite/' . (int) $r['tramite_id'] . '/dictamen/' . (int) $r['id']) ?>" target="_blank" rel="noopener">Ver PDF firmado</a></p>
  </section>
<?php endif; ?>

<?php if ($puedeAsignar): ?>
<section class="tarjeta" style="margin-bottom:16px">
  <h2>Evaluadores</h2>
  <?php if ($activas): ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Evaluador</th><th>Límite</th><th>Semáforo</th><th>Propuesta</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($activas as $a): ?>
      <tr>
        <td><?= e($a['miembro_nombre']) ?><br><span class="texto-secundario"><?= e($a['miembro_email']) ?></span></td>
        <td><?= e(date('d/m/Y', strtotime((string) $a['fecha_limite']))) ?></td>
        <td><?php include __DIR__ . '/_semaforo.php'; ?></td>
        <td><?php if ($a['estado'] === 'firmada'): ?><?= e($etiquetas[$a['propuesta']] ?? '') ?> · <a href="<?= url($base . '/revision/' . (int) $a['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php else: ?>—<?php endif; ?></td>
        <td style="white-space:nowrap">
          <?php if (in_array($a['estado'], ['pendiente', 'borrador'], true)): ?>
            <form method="post" action="<?= url($base . '/recordatorio/' . (int) $a['id']) ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Recordatorio</button></form>
            <form method="post" action="<?= url($base . '/cancelar/' . (int) $a['id']) ?>" style="display:inline" onsubmit="return confirm('¿Retirar a este evaluador? Su enlace dejará de funcionar.')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Retirar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if ($r['estado'] !== 'por_dictaminar' || true): ?>
  <h3 style="margin-top:16px">Enviar a más evaluadores</h3>
  <?php $libres = array_filter($miembros, static fn(array $m): bool => !in_array((int) $m['id'], array_map('intval', $asignados), true)); ?>
  <?php if (!$libres): ?><p class="texto-secundario">Todos los integrantes activos de la comisión ya tienen asignación.</p>
  <?php else: ?>
    <form class="formulario" method="post" action="<?= url($base . '/asignar') ?>">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px">
        <?php foreach ($libres as $m): ?>
          <label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="miembros[]" value="<?= (int) $m['id'] ?>"> <span><strong><?= e($m['nombre']) ?></strong><br><span class="texto-secundario"><?= e($m['email']) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <p class="texto-secundario" style="font-size:13px">Cada persona recibe por correo un enlace personal y tiene <strong><?= (int) $dias ?> días hábiles</strong> (hasta el <strong><?= e($limite) ?></strong> si se envía hoy).</p>
      <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar a revisión</button></div>
    </form>
  <?php endif; endif; ?>
</section>
<?php endif; ?>

<?php if ($r['estado'] === 'por_dictaminar'): ?>
<section class="tarjeta">
  <h2>Dictamen de la comisión</h2>
  <p class="texto-secundario">Todas las revisiones están firmadas. Las recomendaciones se reunieron abajo; edítalas si lo necesitas (el formato admite unos 1,900 caracteres). Este dictamen es el único que verá el pasante.</p>
  <form class="formulario" method="post" action="<?= url($base . '/dictaminar') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="tit-campos">
      <fieldset style="border:0;padding:0"><legend><strong>Dictamen</strong></legend>
        <label style="display:flex;gap:6px"><input type="radio" name="dictamen" value="aprobado" required> Se aprueba</label>
        <label style="display:flex;gap:6px"><input type="radio" name="dictamen" value="no_aprobado"> No se aprueba</label></fieldset>
      <fieldset style="border:0;padding:0"><legend><strong>Requiere revisión del coordinador</strong></legend>
        <label style="display:flex;gap:6px"><input type="radio" name="requiere_coord" value="no" required checked> No</label>
        <label style="display:flex;gap:6px"><input type="radio" name="requiere_coord" value="si"> Sí</label></fieldset>
    </div>
    <label>Observaciones
      <textarea name="observaciones" rows="8" maxlength="1900"><?= e($sugeridas) ?></textarea></label>
    <?php if ($nivelFirma === 'pades'): ?>
      <label>Código de 6 dígitos de tu app de autenticación (firma PAdES) <input type="text" name="codigo_2fa" inputmode="numeric" maxlength="6" required style="max-width:200px"></label>
    <?php else: ?>
      <p class="texto-secundario" style="font-size:13px">Al enviar, el dictamen se firma electrónicamente con tu sello (nombre, fecha y huella del documento).</p>
    <?php endif; ?>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Firmar y enviar dictamen al pasante</button></div>
  </form>
</section>
<?php endif; ?>
