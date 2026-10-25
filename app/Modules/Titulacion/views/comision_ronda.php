<?php
use App\Core\Auth;
use App\Modules\Titulacion\Services\Mensajes;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$base = 'titulacion/comision/' . (int) $r['id'];
$activas = array_filter($asignaciones, static fn(array $a): bool => $a['estado'] !== 'cancelada');
$puedeAsignar = in_array($r['estado'], ['en_comision', 'en_evaluacion', 'por_dictaminar'], true) && !($r['estado'] === 'por_dictaminar' && count($activas) >= $maxRevisores);
$etiquetas = ['aprobado' => 'Propone aprobar', 'no_aprobado' => 'Propone no aprobar'];
$correccion = (int) $r['numero'] > 1;
$vuelta = Mensajes::ordinal((int) $r['numero']);
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/comision') ?>">← Mi comité de revisión</a></p>
  <h2 style="margin:4px 0"><?= e($r['alumno_nombre']) ?> · <?= e($r['folio']) ?> <small class="texto-secundario">(<?= e($vuelta) ?> vuelta<?= $correccion ? ', versión corregida' : '' ?>)</small></h2>
  <p class="texto-secundario">«<?= e((string) $r['titulo']) ?>» · <?= e(\App\Modules\Titulacion\Catalogo::modalidad((string) $r['modalidad'])['nombre'] ?? '') ?></p>
</div>
<div><a class="btn btn-primario" href="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/documento') ?>" target="_blank" rel="noopener">Ver protocolo (PDF)</a></div></div>

<?php if ($r['estado'] === 'dictaminada'): ?>
  <section class="tarjeta" style="margin-bottom:16px">
    <h2>Vuelta resuelta</h2>
    <p><span class="tit-chip <?= $r['dictamen'] === 'aprobado' ? 'ok' : 'mal' ?>"><?= $r['dictamen'] === 'aprobado' ? 'Aprobado' : 'Con observaciones' ?></span>
      · <?= e(fecha_corta((string) $r['dictaminado_en'])) ?> · <a href="<?= url('titulacion/licenciatura/tramite/' . (int) $r['tramite_id'] . '/dictamen/' . (int) $r['id']) ?>" target="_blank" rel="noopener">Ver dictamen firmado (PDF)</a></p>
    <?php if ($r['mensaje']): ?><details><summary>Mensaje enviado al pasante</summary><div class="tit-ok" style="white-space:pre-wrap"><?= e((string) $r['mensaje']) ?></div></details><?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($correccion && $r['estado'] === 'en_comision'): ?>
  <p class="tit-aviso">Es la versión corregida del protocolo. Si atiende las observaciones de la vuelta anterior, resuélvela abajo sin enviarla a revisión; si no, envíala a una nueva vuelta (los revisores tendrán de nuevo <?= (int) $dias ?> días hábiles).</p>
  <?php if ($mensajes): ?><details style="margin-bottom:14px"><summary>Observaciones de la vuelta anterior</summary><div class="tit-obs" style="white-space:pre-wrap"><?= e((string) $mensajes[0]['cuerpo']) ?></div></details><?php endif; ?>
<?php endif; ?>

<?php if ($puedeAsignar || $activas): ?>
<section class="tarjeta" style="margin-bottom:16px">
  <h2>Revisores</h2>
  <?php if ($activas): ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Revisor</th><th>Asignado</th><th>Semáforo</th><th>Revisión</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($activas as $a): ?>
      <tr>
        <td><?= e($a['miembro_nombre']) ?><br><span class="texto-secundario"><?= e($a['miembro_email']) ?></span></td>
        <td><?= e(date('d/m/Y', strtotime((string) $a['asignada_en']))) ?><?= (int) $a['recordatorios'] ? '<br><small class="texto-secundario">' . (int) $a['recordatorios'] . ' recordatorio(s)</small>' : '' ?></td>
        <td><?php include __DIR__ . '/_semaforo.php'; ?></td>
        <td><?php if ($a['estado'] === 'firmada'): ?><?= e($etiquetas[$a['propuesta']] ?? '') ?> · <a href="<?= url($base . '/revision/' . (int) $a['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php else: ?>—<?php endif; ?></td>
        <td style="white-space:nowrap">
          <?php if (in_array($a['estado'], ['pendiente', 'borrador'], true)): ?>
            <?php if ($a['semaforo'] === 'rojo'): ?>
              <form method="post" action="<?= url($base . '/recordatorio/' . (int) $a['id']) ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-primario" type="submit">Enviar recordatorio</button></form>
            <?php endif; ?>
            <form method="post" action="<?= url($base . '/cancelar/' . (int) $a['id']) ?>" style="display:inline" onsubmit="return confirm('¿Retirar a este revisor? Su enlace dejará de funcionar.')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Retirar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="texto-secundario" style="font-size:13px">Verde: del día 0 al <?= (int) $dias ?> hábil desde la asignación. Rojo: a partir del día <?= (int) $dias + 1 ?>; entonces aparece el botón para enviar un recordatorio amable con un enlace nuevo.</p>
  <?php endif; ?>

  <?php $libres = array_filter($miembros, static fn(array $m): bool => !in_array((int) $m['id'], array_map('intval', $asignados), true)); $faltan = $maxRevisores - count($activas); ?>
  <?php if ($puedeAsignar && $faltan > 0): ?>
  <h3 style="margin-top:16px"><?= $activas ? 'Agregar revisor' : ($correccion ? 'Enviar a una nueva vuelta de revisión' : 'Enviar a revisión') ?></h3>
  <?php if (!$libres): ?><p class="texto-secundario">Todos los integrantes activos del comité ya tienen asignación.</p>
  <?php else: ?>
    <form class="formulario" method="post" action="<?= url($base . '/asignar') ?>">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <p class="texto-secundario" style="font-size:13px">Elige <?= $activas ? 'hasta ' . $faltan : $maxRevisores ?> integrante(s) del comité.<?= $anteriores ? ' Se marcaron quienes revisaron la vuelta anterior.' : '' ?></p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px">
        <?php foreach ($libres as $m): ?>
          <label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="miembros[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $anteriores, true) ? 'checked' : '' ?>> <span><strong><?= e($m['nombre']) ?></strong><br><span class="texto-secundario"><?= e($m['email']) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <p class="texto-secundario" style="font-size:13px">Cada persona recibe por correo un enlace personal para ver el protocolo en PDF (sin iniciar sesión) y tiene <strong><?= (int) $dias ?> días hábiles</strong> (hasta el <strong><?= e($limite) ?></strong> si se envía hoy). Para firmar su revisión debe entrar a Traza FI.</p>
      <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar a revisión</button></div>
    </form>
  <?php endif; endif; ?>
</section>
<?php endif; ?>

<?php if ($puedeDictaminar): ?>
<section class="tarjeta" id="mensaje">
  <h2><?= $revisiones ? 'Observaciones y mensaje al pasante' : 'Resolver la versión corregida' ?></h2>
  <p class="texto-secundario"><?= $revisiones ? 'Compilación de las observaciones de cada revisor. Pulsa «Editar» para ajustar un texto; el mensaje de abajo se arma con la plantilla y las observaciones, y también puedes corregirlo antes de enviarlo.' : 'Escribe, si hace falta, tus observaciones; el mensaje se arma con tu plantilla.' ?></p>
  <form class="formulario" method="post" action="<?= url($base . '/dictaminar') ?>" id="form-dictamen">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <?php foreach ($revisiones as $rv): ?>
      <div style="margin:0 0 12px">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
          <strong>Revisor <?= (int) $rv['n'] ?></strong>
          <span><span class="texto-secundario" style="font-size:12px"><?= e($rv['miembro_nombre']) ?> · <?= e($etiquetas[$rv['propuesta']] ?? '') ?></span>
            <button class="btn btn-mini" type="button" data-editar="rv<?= (int) $rv['id'] ?>">Editar</button></span>
        </div>
        <textarea id="rv<?= (int) $rv['id'] ?>" name="revisor[<?= (int) $rv['id'] ?>]" rows="6" readonly data-revisor="<?= (int) $rv['n'] ?>" style="width:100%;background:var(--gris-050,#f8fafc)"><?= e($rv['texto']) ?></textarea>
      </div>
    <?php endforeach; ?>
    <label>Observaciones del responsable del comité (opcional)
      <textarea name="propias" rows="4" data-propias></textarea></label>

    <fieldset style="border:0;padding:0;margin:12px 0"><legend><strong>Resultado de la vuelta</strong></legend>
      <label style="display:flex;gap:6px"><input type="radio" name="dictamen" value="no_aprobado" required checked> Debe atender las observaciones (el pasante sube una versión corregida)</label>
      <label style="display:flex;gap:6px"><input type="radio" name="dictamen" value="aprobado"> Se aprueba el protocolo</label>
    </fieldset>
    <fieldset style="border:0;padding:0;margin:0 0 12px"><legend><strong>Requiere revisión del coordinador</strong></legend>
      <label style="display:inline-flex;gap:6px;margin-right:14px"><input type="radio" name="requiere_coord" value="no" checked> No</label>
      <label style="display:inline-flex;gap:6px"><input type="radio" name="requiere_coord" value="si"> Sí</label></fieldset>

    <div data-si="no_aprobado">
      <label>Asunto <input type="text" name="asunto" maxlength="200" data-asunto value="<?= e(Mensajes::aplicar($plantilla['asunto'], $marcas)) ?>"></label>
      <label>Mensaje al pasante (le llega a «Titulación → Mensajes» y un aviso por correo)
        <textarea name="mensaje" rows="16" data-mensaje></textarea></label>
      <p style="margin:4px 0 12px"><button class="btn btn-mini" type="button" data-rearmar>Volver a armar el mensaje con la plantilla</button></p>
    </div>
    <div data-si="aprobado" hidden>
      <label>Observaciones del oficio de aprobación del tema
        <textarea name="obs_oficio" rows="5" maxlength="1500"><?= e($obsOficio) ?></textarea></label>
      <p class="texto-secundario" style="font-size:13px">El pasante recibirá un mensaje de aprobación. El oficio pasa al Vo. Bo. de la Jefatura de Evaluación Profesional y a la firma de la Subdirección; se avisa al pasante, a la Jefatura y a la Subdirección, y se generan las constancias de quienes revisaron.</p>
    </div>

    <?php if ($nivelFirma === 'pades'): ?>
      <label>Código de 6 dígitos de tu app de autenticación (firma PAdES del dictamen) <input type="text" name="codigo_2fa" inputmode="numeric" maxlength="6" required style="max-width:200px"></label>
    <?php else: ?>
      <p class="texto-secundario" style="font-size:13px">Al enviar se genera el dictamen en PDF firmado con tu sello electrónico (nombre, fecha y huella del documento).</p>
    <?php endif; ?>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar</button></div>
  </form>

  <details style="margin-top:16px">
    <summary>Editar mi plantilla del mensaje<?= $plantilla['propia'] ? ' (personalizada)' : '' ?></summary>
    <form class="formulario" method="post" action="<?= url('titulacion/comision/plantilla') ?>" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="volver" value="<?= e($base) ?>">
      <label>Asunto <input type="text" name="asunto" maxlength="200" required value="<?= e($plantilla['asunto']) ?>"></label>
      <label>Cuerpo <textarea name="cuerpo" rows="12" required data-plantilla><?= e($plantilla['cuerpo']) ?></textarea></label>
      <p class="texto-secundario" style="font-size:13px">Marcadores: <?= e(implode(' ', Mensajes::MARCAS)) ?>. {observaciones} se sustituye por la compilación de los revisores.</p>
      <div class="acciones-formulario" style="gap:8px">
        <button class="btn btn-primario btn-mini" type="submit" name="accion" value="guardar">Guardar plantilla</button>
        <?php if ($plantilla['propia']): ?><button class="btn btn-mini" type="submit" name="accion" value="restablecer" formnovalidate>Restablecer la original</button><?php endif; ?>
      </div>
    </form>
  </details>
</section>
<script>
(function () {
  var form = document.getElementById('form-dictamen');
  if (!form) { return; }
  var plantilla = <?= json_encode($plantilla['cuerpo'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var marcas = <?= json_encode($marcas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var mensaje = form.querySelector('[data-mensaje]');
  function observaciones() {
    var partes = [];
    form.querySelectorAll('[data-revisor]').forEach(function (t) { partes.push('Revisor ' + t.getAttribute('data-revisor') + ':\n' + t.value.trim()); });
    var p = form.querySelector('[data-propias]').value.trim();
    if (p) { partes.push('Responsable del comité:\n' + p); }
    return partes.join('\n\n');
  }
  function armar() {
    var texto = plantilla;
    Object.keys(marcas).forEach(function (k) { texto = texto.split(k).join(marcas[k]); });
    mensaje.value = texto.split('{observaciones}').join(observaciones());
  }
  var editado = false;
  mensaje.addEventListener('input', function () { editado = true; });
  form.querySelectorAll('[data-revisor], [data-propias]').forEach(function (t) {
    t.addEventListener('input', function () { if (!editado) { armar(); } });
  });
  form.querySelectorAll('[data-editar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.getElementById(b.getAttribute('data-editar'));
      t.readOnly = !t.readOnly;
      t.style.background = t.readOnly ? 'var(--gris-050,#f8fafc)' : '#fff';
      b.textContent = t.readOnly ? 'Editar' : 'Listo';
      if (!t.readOnly) { t.focus(); }
    });
  });
  form.querySelector('[data-rearmar]').addEventListener('click', function () { editado = false; armar(); });
  function alternar() {
    var v = (form.querySelector('input[name=dictamen]:checked') || {}).value;
    form.querySelectorAll('[data-si]').forEach(function (d) { d.hidden = d.getAttribute('data-si') !== v; });
  }
  form.querySelectorAll('input[name=dictamen]').forEach(function (r) { r.addEventListener('change', alternar); });
  armar();
  alternar();
})();
</script>
<?php endif; ?>
