<?php
use App\Core\Auth;
use App\Modules\Titulacion\Services\SubdireccionFirma;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$rotulo = ['personal' => 'Subdirector', 'delegada' => 'Asistente (por delegación)', 'automatica' => 'Piloto automático'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Formatos de titulación que llevan el nombre del Subdirector Académico con espacio para firma. Al firmar se estampa un sello electrónico con su nombre y cargo, el folio y la huella del documento. Cuando firma el asistente o el piloto, el sello añade una leyenda discreta y la bitácora conserva quién firmó realmente.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:18px">
  <div class="tarjeta-cabecera"><h2>Pendientes de firma (<?= count($pendientes) ?>)</h2></div>
  <?php if (!$pendientes): ?>
    <div class="ux-empty">No hay documentos esperando firma.</div>
  <?php else: ?>
  <form method="post" action="<?= url('titulacion/firmas/firmar') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="tabla-contenedor"><table class="tabla">
      <thead><tr><th></th><th>Documento</th><th>Pasante</th><th>Solicitado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pendientes as $p): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" aria-label="Seleccionar"></td>
          <td><?= e($p['descripcion']) ?></td>
          <td><?= e($p['alumno_nombre']) ?></td>
          <td><?= e(date('d/m/Y H:i', strtotime($p['creado_en']))) ?></td>
          <td><a class="btn btn-mini" target="_blank" rel="noopener" href="<?= url('titulacion/firmas/previa/' . (int) $p['id']) ?>">Ver</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
      <button class="btn btn-primario" type="submit">Firmar seleccionados</button>
      <button class="btn" type="submit" name="todas" value="1">Firmar todos los pendientes</button>
    </p>
  </form>
  <?php endif; ?>
</section>

<?php if ($esSubdirector): ?>
<section class="tarjeta" id="asistentes" style="margin-bottom:18px">
  <div class="tarjeta-cabecera"><h2>Mi asistente</h2></div>
  <p class="texto-secundario">Quien registres aquí podrá ver esta bandeja y firmar en tu nombre al entrar con una cuenta verificada con ese correo.</p>
  <?php if ($asistentes): ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Nombre</th><th>Correo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($asistentes as $a): ?>
      <tr>
        <td><?= e($a['nombre']) ?></td><td><?= e($a['email']) ?></td>
        <td><?= (int) $a['activo'] ? 'Activo' : 'Desactivado' ?></td>
        <td><form method="post" action="<?= url('titulacion/firmas/asistentes/' . (int) $a['id'] . '/alternar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini <?= (int) $a['activo'] ? 'btn-peligro' : '' ?>" type="submit"><?= (int) $a['activo'] ? 'Desactivar' : 'Reactivar' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <form method="post" action="<?= url('titulacion/firmas/asistentes') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:12px">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Nombre <input type="text" name="nombre" maxlength="160" required></label>
    <label>Correo <input type="email" name="email" maxlength="120" required></label>
    <button class="btn btn-primario" type="submit">Registrar asistente</button>
  </form>
</section>

<section class="tarjeta" id="piloto" style="margin-bottom:18px">
  <div class="tarjeta-cabecera">
    <h2>Piloto automático</h2>
    <span class="tit-chip <?= $pilotoVigente ? 'ok' : '' ?>"><?= $pilotoVigente ? 'Activo hasta ' . e(date('d/m/Y H:i', strtotime($pilotoVigente['fin']))) : 'Apagado' ?></span>
  </div>
  <p class="texto-secundario">Firma en tu nombre los formatos que elijas, solo dentro del periodo indicado y una vez transcurrido el retraso fijo desde que el pasante los solicita. Cada sello lleva la leyenda «Firma automática programada». Puedes apagarlo en cualquier momento.</p>
  <form method="post" action="<?= url('titulacion/firmas/piloto') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
      <label>Desde <input type="datetime-local" name="inicio" required value="<?= e($piloto ? date('Y-m-d\TH:i', strtotime($piloto['inicio'])) : '') ?>"></label>
      <label>Hasta <input type="datetime-local" name="fin" required value="<?= e($piloto ? date('Y-m-d\TH:i', strtotime($piloto['fin'])) : '') ?>"></label>
      <label>Retraso (minutos) <input type="number" name="retraso" min="5" max="1440" required value="<?= (int) ($piloto['retraso_min'] ?? 30) ?>"></label>
    </div>
    <fieldset style="margin-top:12px;border:0;padding:0"><legend class="texto-secundario">Formatos que puede firmar</legend>
      <?php $elegidos = $piloto ? (array) json_decode((string) $piloto['formatos'], true) : []; foreach ($formatos as $k => $f): ?>
        <label style="display:block"><input type="checkbox" name="formatos[]" value="<?= e($k) ?>" <?= in_array($k, $elegidos, true) ? 'checked' : '' ?>> <?= e($f['nombre']) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <p style="margin-top:12px"><button class="btn btn-primario" type="submit">Programar piloto</button></p>
  </form>
  <?php if ($pilotoVigente): ?>
  <form method="post" action="<?= url('titulacion/firmas/piloto/apagar') ?>" style="margin-top:8px"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-peligro" type="submit">Apagar piloto ahora</button></form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="tarjeta">
  <div class="tarjeta-cabecera"><h2>Bitácora de firmas</h2></div>
  <?php if (!$bitacora): ?>
    <div class="ux-empty">Aún no hay firmas registradas.</div>
  <?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Documento</th><th>Firmó realmente</th><th>Huella</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bitacora as $b): ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($b['firmado_en']))) ?></td>
        <td><?= e($b['descripcion']) ?></td>
        <td><?= e($rotulo[$b['capacidad']] ?? '') ?><?= $b['firmante_nombre'] ? ' · ' . e($b['firmante_nombre']) : '' ?></td>
        <td><code><?= e(substr((string) $b['hash_firmado'], 0, 12)) ?></code></td>
        <td><a class="btn btn-mini" target="_blank" rel="noopener" href="<?= url('titulacion/firmas/archivo/' . (int) $b['id']) ?>">PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
