<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';

$base = 'titulacion/gestion/' . (int) $t['id'];
$ver = 'titulacion/licenciatura/tramite/' . (int) $t['id'];
$p = $t['datos']['datos'] ?? [];
$chips = [
  'firmas' => ['rev', 'Esperando firma del asesor'],
    'pendiente' => ['pend', 'Pendiente'], 'en_revision' => ['rev', 'Por validar'], 'aprobado' => ['ok', 'Validado'],
    'rechazado' => ['mal', 'Devuelto'], 'no_aplica' => ['na', 'No le aplica'],
];
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/gestion') ?>">← Bandeja</a></p>
    <h1><?= e($t['folio']) ?> · <?= e($t['alumno_nombre']) ?></h1>
    <p class="texto-secundario"><?= e($modalidad['nombre'] ?? $t['modalidad']) ?><?= $t['variante'] ? ' (' . e($t['variante']) . ')' : '' ?><?php if ($t['titulo']): ?> · «<?= e($t['titulo']) ?>»<?php endif; ?></p>
  </div>
  <div style="text-align:right">
    <span class="estado estado-<?= e($t['estado']) ?>"><?= e(Catalogo::estadosTramite()[$t['estado']] ?? $t['estado']) ?></span>
    <?php if ($t['resultado']): ?><div class="texto-secundario">Resultado: <strong><?= e($t['resultado']) ?></strong></div><?php endif; ?>
    <div class="texto-secundario">Responsable: <?= e((string) ($t['responsable_nombre'] ?? 'sin asignar')) ?></div>
    <div style="margin-top:4px"><a class="btn btn-mini" href="<?= url($base . '/expediente') ?>">Descargar expediente (ZIP)</a></div>
    <form method="post" action="<?= url($base . '/tomar') ?>" style="margin-top:4px"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Asignarme</button></form>
  </div>
</div>

<div class="tit-dos">
<div style="display:flex;flex-direction:column;gap:18px">

<?php foreach ($flujo['etapas'] as $i => $e): ?>
  <section class="tarjeta" id="etapa-<?= e($e['clave']) ?>">
    <div class="tarjeta-cabecera">
      <h2><?= $i + 1 ?>. <?= e($e['titulo']) ?></h2>
      <span class="tit-chip <?= $e['situacion'] === 'completa' ? 'ok' : ($e['situacion'] === 'actual' ? 'rev' : 'na') ?>"><?= ['completa' => 'Completa', 'actual' => 'En turno', 'bloqueada' => 'Pendiente de etapas previas'][$e['situacion']] ?></span>
    </div>

    <?php if ($e['situacion'] === 'actual' && !empty($e['paso'])): ?>
      <p class="texto-secundario"><strong><?= $e['paso']['turno'] === 'personal' ? 'Te toca:' : 'Espera al pasante:' ?></strong> <?= e($e['paso']['texto']) ?></p>
    <?php endif; ?>

    <?php if (!empty($e['campos']) && $e['situacion'] !== 'bloqueada'): $de = $t['datos'][$e['clave']] ?? []; ?>
      <details <?= $e['situacion'] === 'actual' ? 'open' : '' ?>><summary>Datos capturados</summary>
        <dl style="display:grid;grid-template-columns:200px 1fr;gap:6px 14px;margin:8px 0">
          <?php foreach ($e['campos'] as $c): $v = $de[$c['k']] ?? ''; ?>
            <dt><strong><?= e($c['label']) ?></strong></dt>
            <dd style="margin:0;white-space:pre-wrap"><?php
              if ($c['tipo'] === 'cronograma') { foreach ((array) $v as $fila) { echo e($fila['a'] . ' → bimestres ' . implode(', ', $fila['b'])) . '<br>'; } if (!$v) echo '—'; }
              elseif ($c['tipo'] === 'select') { echo e($c['opciones'][$v] ?? '—'); }
              else { echo e((string) $v ?: '—'); } ?></dd>
          <?php endforeach; ?>
        </dl>
      </details>
    <?php endif; ?>

    <?php if ($e['situacion'] !== 'bloqueada'): foreach ($e['docs_estado'] as $dc => $info): $d = $info['def']; $fila = $info['fila']; $est = $info['estado']; [$cl, $et] = $chips[$est] ?? ['pend', $est]; ?>
      <div class="tit-doc" id="doc-<?= $fila ? (int) $fila['id'] : e($dc) ?>">
        <div>
          <h4><?= e($d['nombre']) ?><?= !empty($d['fisico']) || !empty($d['cotejo']) ? ' *' : '' ?> <span class="tit-chip <?= $cl ?>"><?= e($et) ?></span><?= !empty($d['cotejo']) ? ' <span class="tit-chip na">Cotejo de original</span>' : '' ?></h4>
          <?php if ($d['nota']): ?><p class="nota"><?= e($d['nota']) ?></p><?php endif; ?>
          <?php if ($fila && $fila['ruta']): ?>
            <p class="nota"><a href="<?= url($ver . '/archivo/' . (int) $fila['id'] . '?ver=1') ?>" target="_blank" rel="noopener">Abrir PDF</a> · <?= e((string) $fila['nombre_original']) ?> · <?= e(fecha_corta($fila['creado_en'])) ?></p>
          <?php endif; ?>
          <?php if ($fila && $fila['comentario']): ?><div class="<?= $est === 'rechazado' ? 'tit-obs' : 'tit-ok' ?>"><?= e((string) $fila['comentario']) ?><?= $fila['revisor_nombre'] ? ' — ' . e($fila['revisor_nombre']) : '' ?></div><?php endif; ?>
          <?php if (!empty($d['cotejo']) && $est === 'aprobado' && $fila): ?>
            <?php if ($fila['cotejo_en']): ?>
              <div class="tit-ok">Cotejado con el original por <?= e((string) $fila['cotejo_nombre']) ?> · <?= e(fecha_corta($fila['cotejo_en'])) ?> · <?= $fila['cotejo_tipo'] === 'pades' ? 'firma PAdES' : 'sello de cotejo' ?>
                <br><small>Huella SHA-256: <code><?= e(substr((string) $fila['cotejo_hash'], 0, 24)) ?>…</code><?= $fila['cotejo_ruta'] ? ' · <a href="' . e(url($ver . '/archivo/' . (int) $fila['id'] . '?cotejo=1')) . '" target="_blank" rel="noopener">PDF con sello</a>' : '' ?></small></div>
              <form method="post" action="<?= url($base . '/cotejo/' . (int) $fila['id']) ?>" style="margin-top:4px" onsubmit="return confirm('¿Retirar el cotejo?')"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="accion" value="quitar"><button class="btn btn-mini" type="submit">Retirar cotejo</button></form>
            <?php else: ?>
              <form class="formulario" method="post" action="<?= url($base . '/cotejo/' . (int) $fila['id']) ?>" style="margin-top:8px">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <span class="tit-chip rev">Pendiente: original para cotejo</span>
                <?php if (($d['firma_cotejo'] ?? 'sello') === 'pades'): ?>
                  <input type="text" name="codigo_2fa" inputmode="numeric" maxlength="6" placeholder="Código de 6 dígitos (TOTP)" required style="max-width:200px">
                  <button class="btn btn-mini btn-primario" type="submit">Cotejar y firmar (PAdES)</button>
                <?php else: ?>
                  <button class="btn btn-mini btn-primario" type="submit">Cotejado con el original · sellar</button>
                <?php endif; ?>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php $porComision = ($d['validacion'] ?? '') === 'comision' && \App\Modules\Titulacion\RevisionRepository::comisionConfigurada((string) ($t['datos']['datos']['carrera'] ?? '')); ?>
          <?php if ($porComision): ?>
            <p class="nota">La valida la comisión de revisión de protocolos. <a href="<?= url('titulacion/protocolos') ?>">Ver revisión</a></p>
          <?php endif; ?>
          <?php if (!$porComision && $fila && in_array($est, ['en_revision', 'aprobado', 'no_aplica', 'rechazado'], true) && $t['estado'] === 'abierto'): ?>
            <form class="formulario" method="post" action="<?= url($base . '/documento/' . (int) $fila['id']) ?>" style="margin-top:8px">
              <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
              <input type="text" name="comentario" maxlength="900" placeholder="Observaciones (obligatorias si lo devuelves)" style="min-width:260px">
              <button class="btn btn-mini btn-primario" name="decision" value="aprobado" type="submit">Validar</button>
              <button class="btn btn-mini btn-peligro" name="decision" value="rechazado" type="submit">Devolver</button>
            </form>
          <?php endif; ?>
        </div>
        <div></div>
      </div>
    <?php endforeach; endif; ?>

    <?php if (isset($e['personal']) && $e['situacion'] !== 'bloqueada'): $hito = $e['hito']; $ent = $e['personal']['entrega'] ?? null;
          $filaEnt = $ent ? ($docs[$e['clave'] . '|' . $ent['clave']] ?? null) : null; ?>
      <h3><?= e($e['personal']['titulo']) ?></h3>
      <?php if ($e['personal']['descripcion'] ?? ''): ?><p class="texto-secundario"><?= e($e['personal']['descripcion']) ?></p><?php endif; ?>
      <?php if ($hito): ?>
        <div class="tit-ok">
          <?php foreach ($e['personal']['campos'] ?? [] as $c): $v = (string) ($hito['datos'][$c['k']] ?? ''); if ($v === '') continue; if (($c['tipo'] ?? '') === 'select') { $v = $c['opciones'][$v] ?? $v; } ?>
            <div><strong><?= e($c['label']) ?>:</strong> <span style="white-space:pre-wrap"><?= e($v) ?></span></div>
          <?php endforeach; ?>
          <?php if ($filaEnt): ?><div><a href="<?= url($ver . '/archivo/' . (int) $filaEnt['id'] . '?ver=1') ?>" target="_blank" rel="noopener"><?= e($ent['nombre']) ?></a><?= empty($filaEnt['subdir_en']) ? ' · esperando la firma de la Subdirección' : ' · firmado' ?></div><?php endif; ?>
          <?php if (!empty($e['personal']['comision_fija'])): $cf = \App\Modules\Titulacion\Services\Autoridades::class; $pr = $cf::director(); $se = $cf::subdirector(); ?>
          <div>Comisión: <?= e($pr ? \App\Core\Persona::tratamiento($pr) : 'Director') ?> (<?= e(\App\Core\Persona::f($pr ?? [], 'presidente', 'presidenta')) ?>) y <?= e($se ? \App\Core\Persona::tratamiento($se) : 'Subdirector Académico') ?> (<?= e(\App\Core\Persona::f($se ?? [], 'secretario', 'secretaria')) ?>)</div>
        <?php endif; ?>
<?php foreach ($e['personal']['extras'] ?? [] as $x): $fx = $docs[$e['clave'] . '|' . $x['clave']] ?? null; if ($fx): ?><div><a href="<?= url($ver . '/archivo/' . (int) $fx['id'] . '?ver=1') ?>" target="_blank" rel="noopener"><?= e($x['nombre']) ?></a><?= empty($fx['subdir_en']) ? ' · esperando la firma' : ' · firmado' ?></div><?php endif; endforeach; ?>
          <?php if (!empty($ent['asignacion'])): $asig = \App\Modules\Titulacion\Services\Jurado::vigente((int) $t['id'], $e['clave']); ?>
            <?php if ($asig && $asig['estado'] === 'solicitada'): ?>
              <div class="tit-aviso">Esperando al Coordinador de Docencia: <?= e($asig['coord_nombre']) ?> (<?= e($asig['coord_email']) ?>).
                <form method="post" action="<?= url($base . '/jurado/' . (int) $asig['id'] . '/reenviar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Reenviar correo</button></form></div>
            <?php elseif ($asig): foreach (\App\Modules\Titulacion\Services\Jurado::sinodales((int) $asig['id']) as $sn): ?>
              <div><?= e($sn['calidad'] === 'suplente' ? 'Suplente' : 'Propietario') ?>: <?= e($sn['nombre']) ?> · <?= e(['esperando_firma' => 'oficio por firmar', 'pendiente' => 'sin acuse', 'acuse' => 'acuse firmado'][$sn['estado']] ?? $sn['estado']) ?></div>
            <?php endforeach; endif; ?>
          <?php endif; ?>
          <div class="texto-secundario">Registrado por <?= e($hito['completado_nombre']) ?> · <?= e(fecha_corta($hito['completado_en'])) ?></div>
        </div>
        <form method="post" action="<?= url($base . '/reabrir/' . $e['clave']) ?>" onsubmit="return confirm('¿Reabrir esta etapa?')" style="margin-top:8px"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Reabrir etapa</button></form>
      <?php elseif ($e['situacion'] === 'actual' && $t['estado'] === 'abierto'): ?>
        <?php if (!$e['propia_ok'] || !$e['docs_ok']): ?>
          <p class="tit-aviso">Podrás completar esto cuando el pasante termine su captura y todos sus documentos estén validados.</p>
        <?php else: ?>
          <form class="formulario" method="post" action="<?= url($base . '/hito/' . $e['clave']) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <div class="tit-campos">
              <?php foreach ($e['personal']['campos'] ?? [] as $campo): $valor = ''; $nombre = 'h[' . $campo['k'] . ']'; include __DIR__ . '/_campo.php'; endforeach; ?>
            </div>
            <?php if ($ent): ?>
              <p class="texto-secundario"><?= !empty($ent['asignacion']) ? 'Al registrar, se envía por correo al Coordinador de Docencia de la licenciatura la solicitud para asignar a los sinodales y firmar.' : 'Al registrar, la plataforma genera «' . e($ent['nombre']) . '» con estos datos y lo envía a la firma electrónica correspondiente.' ?></p>
            <?php endif; ?>
            <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Registrar y completar etapa</button></div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
</div>

<aside style="display:flex;flex-direction:column;gap:18px">
  <section class="tarjeta">
    <h2 style="font-size:16px">Pasante</h2>
    <p style="margin:0"><strong><?= e((string) ($p['nombre'] ?? $t['alumno_nombre'])) ?></strong><br>
      Cuenta: <?= e((string) ($p['cuenta'] ?? '—')) ?><br>
      <?= e($carreras[$p['carrera'] ?? '']['nombre'] ?? '—') ?> · <?= e($sistemas[$p['sistema'] ?? ''] ?? '—') ?><br>
      Ingreso <?= e((string) ($p['anio_ingreso'] ?? '—')) ?> · Egreso <?= e((string) ($p['anio_egreso'] ?? '—')) ?><br>
      <?= e((string) ($p['telefono'] ?? '')) ?><br><?= e((string) ($p['correo_inst'] ?? $t['alumno_email'])) ?><br><?= e((string) ($p['correo_pers'] ?? '')) ?></p>
  </section>

  <section class="tarjeta">
    <h2 style="font-size:16px">Mensaje o nota</h2>
    <form class="formulario" method="post" action="<?= url($base . '/mensaje') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <textarea name="mensaje" rows="3" maxlength="480" required></textarea>
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="visible" value="1" checked> Visible para el pasante</label>
      <div class="acciones-formulario"><button class="btn btn-mini btn-primario" type="submit">Guardar</button></div>
    </form>
  </section>

  <section class="tarjeta">
    <h2 style="font-size:16px">Bitácora</h2>
    <?php foreach ($eventos as $ev): ?>
      <div class="tit-evento"><?= e($ev['detalle']) ?><?= $ev['visible_alumno'] ? '' : ' <em>(interno)</em>' ?><time><?= e($ev['autor']) ?> · <?= e(fecha_corta($ev['creado_en'])) ?></time></div>
    <?php endforeach; ?>
  </section>
</aside>
</div>
