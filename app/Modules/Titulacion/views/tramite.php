<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';

$base = 'titulacion/licenciatura/tramite/' . (int) $t['id'];
$total = count($flujo['etapas']);
$hechas = count(array_filter($flujo['etapas'], static fn(array $e): bool => $e['situacion'] === 'completa'));
$chips = [
    'pendiente' => ['pend', 'Pendiente'], 'en_revision' => ['rev', 'En validación'], 'aprobado' => ['ok', 'Validado'],
    'rechazado' => ['mal', 'Con observaciones'], 'no_aplica' => ['na', 'No me aplica'],
];
$bloqueada = $etapa['situacion'] === 'bloqueada';
$editable = $puedeEditar && $etapa['situacion'] === 'actual';
$editaCaptura = $puedeEditar && $etapa['situacion'] !== 'bloqueada';
$datosEtapa = $t['datos'][$etapa['clave']] ?? [];
$nombreAlumno = (string) ($t['datos']['datos']['nombre'] ?? $t['alumno_nombre']);
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/licenciatura') ?>">← Mis trámites y opciones</a></p>
    <h1><?= e($modalidad['nombre'] ?? $t['modalidad']) ?></h1>
    <p class="texto-secundario">Folio <strong><?= e($t['folio']) ?></strong> · <?= e($nombreAlumno) ?><?php if ($t['titulo']): ?> · «<?= e(mb_strimwidth($t['titulo'], 0, 110, '…')) ?>»<?php endif; ?></p>
  </div>
  <div style="text-align:right">
    <span class="estado estado-<?= e($t['estado']) ?>"><?= e(Catalogo::estadosTramite()[$t['estado']] ?? $t['estado']) ?></span>
    <?php if ($t['resultado']): ?><div class="texto-secundario" style="margin-top:4px">Resultado: <strong><?= e($t['resultado']) ?></strong></div><?php endif; ?>
    <?php if ($esPersonal && !$esDueno): ?><div style="margin-top:6px"><a class="btn btn-mini" href="<?= url('titulacion/gestion/' . (int) $t['id']) ?>">Abrir en validación</a></div><?php endif; ?>
  </div>
</div>

<div class="tit-dos">
<div style="display:flex;flex-direction:column;gap:18px">

  <section class="tarjeta" id="etapa-<?= e($etapa['clave']) ?>">
    <div class="tarjeta-cabecera">
      <div>
        <h2><?= e($etapa['titulo']) ?></h2>
        <?php if ($etapa['situacion'] === 'actual' && !empty($etapa['paso'])): ?>
          <p class="texto-secundario"><strong><?= $etapa['paso']['turno'] === 'alumno' ? 'Te toca a ti:' : 'En revisión:' ?></strong> <?= e($etapa['paso']['texto']) ?></p>
        <?php elseif ($etapa['situacion'] === 'completa'): ?>
          <p class="texto-secundario">Etapa completa.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($bloqueada): ?>
      <p class="tit-aviso">Esta etapa se habilita cuando termines las anteriores.</p>
    <?php endif; ?>
    <?php if (!empty($etapa['descripcion'])): ?><p><?= e($etapa['descripcion']) ?></p><?php endif; ?>

    <?php if (!$bloqueada && !empty($etapa['campos'])): ?>
      <h3>Datos <?= $etapa['captura_ok'] ? '<span class="tit-chip ok">Completos</span>' : '<span class="tit-chip pend">Pendientes</span>' ?></h3>
      <form class="formulario" method="post" action="<?= url($base . '/captura/' . $etapa['clave']) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <div class="tit-campos">
          <?php foreach ($etapa['campos'] as $campo): ?>
            <?php $valor = $datosEtapa[$campo['k']] ?? ($campo['tipo'] === 'cronograma' ? [] : '');
                  if ($valor === '' && $etapa['clave'] === 'revision' && $campo['k'] === 'fecha_protocolo') { $valor = $flujo['hitos']['protocolo']['datos']['fecha'] ?? ''; }
                  if ($valor === '' && $etapa['clave'] === 'revision' && $campo['k'] === 'titulo') { $valor = (string) ($t['titulo'] ?? ''); }
                  $nombre = 'c[' . $campo['k'] . ']'; ?>
            <?php if ($editaCaptura): include __DIR__ . '/_campo.php'; else: ?>
              <div class="tit-campo <?= $campo['tipo'] === 'area' ? 'ancho' : '' ?>"><strong><?= e($campo['label']) ?></strong><br>
                <?php if ($campo['tipo'] === 'cronograma'): ?><?= $valor ? e(count($valor) . ' actividad(es)') : '—' ?>
                <?php elseif ($campo['tipo'] === 'select'): ?><?= e($campo['opciones'][$valor] ?? '—') ?>
                <?php else: ?><span style="white-space:pre-wrap"><?= e((string) $valor ?: '—') ?></span><?php endif; ?></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php if ($editaCaptura): ?>
          <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar datos</button></div>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <?php if (!$bloqueada && $formatos): ?>
      <h3>Formatos para firmar</h3>
      <p class="texto-secundario">Se llenan con los datos que capturaste. Imprímelos, fírmalos (<?= e('firma de quien indica cada formato') ?>), escanéalos en PDF y súbelos en la sección de documentos.</p>
      <?php foreach ($formatos as $clave => $f): ?>
        <div class="tit-doc" <?= $clave === 'art5' ? 'id="art5"' : '' ?>>
          <div>
            <h4><?= e($f['def']['nombre']) ?></h4>
            <p class="nota">Firma: <?= e($f['def']['firma']) ?>. Formato <?= $f['def']['tipo'] === 'docx' ? 'Word (.docx)' : 'PDF' ?>.</p>
            <?php if ($clave === 'art5' && $editaCaptura): ?>
              <form class="formulario" method="post" action="<?= url($base . '/art5') ?>" style="margin-top:8px">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <label>Motivos por los que excediste el plazo
                  <textarea name="motivos" rows="3" maxlength="900" required><?= e((string) ($t['datos']['art5']['motivos'] ?? '')) ?></textarea></label>
                <div class="acciones-formulario"><button class="btn btn-mini" type="submit">Guardar motivos</button></div>
              </form>
            <?php endif; ?>
            <?php if ($f['impedimento']): ?><p class="nota" style="color:var(--alerta)"><?= e($f['impedimento']) ?></p><?php endif; ?>
          </div>
          <div>
            <?php if (!$f['impedimento']): ?>
              <a class="btn btn-primario btn-mini" href="<?= url($base . '/formato/' . $clave) ?>"><?= icono('lista', 14) ?> Descargar llenado</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$bloqueada && !empty($etapa['documentos'])): ?>
      <h3>Documentos <span class="texto-secundario" style="font-weight:400;font-size:13px">(solo PDF, legibles y a color)</span></h3>
      <?php foreach ($etapa['docs_estado'] as $dc => $info): $d = $info['def']; $fila = $info['fila']; $est = $info['estado']; [$cl, $et] = $chips[$est] ?? ['pend', $est]; ?>
        <div class="tit-doc" id="doc-<?= e($dc) ?>">
          <div>
            <h4><?= e($d['nombre']) ?><?= !empty($d['fisico']) || !empty($d['cotejo']) ? ' <span title="Entrega en físico" aria-label="Entrega en físico">*</span>' : '' ?> <span class="tit-chip <?= $cl ?>"><?= e($et) ?></span>
              <?php if (!empty($d['cotejo'])): ?>
                <?php if ($fila && $fila['cotejo_en']): ?><span class="tit-chip ok">Original cotejado</span>
                <?php else: ?><span class="tit-chip rev">Llevar original para cotejo</span><?php endif; ?>
              <?php endif; ?></h4>
            <?php if (!empty($d['cotejo']) && $fila && $fila['cotejo_en']): ?>
              <p class="nota">Cotejado por <?= e((string) $fila['cotejo_nombre']) ?> el <?= e(fecha_corta($fila['cotejo_en'])) ?><?= $fila['cotejo_ruta'] ? ' · <a href="' . e(url($base . '/archivo/' . (int) $fila['id'] . '?cotejo=1')) . '" target="_blank" rel="noopener">PDF con sello de cotejo</a>' : '' ?></p>
            <?php endif; ?>
            <?php if ($d['nota']): ?><p class="nota"><?= e($d['nota']) ?></p><?php endif; ?>
            <?php if ($d['condicion']): ?><p class="nota"><em><?= e($d['condicion']) ?></em></p><?php endif; ?>
            <?php if ($est === 'rechazado' && $fila): ?><div class="tit-obs"><strong>Observaciones<?= $fila['revisor_nombre'] ? ' de ' . e($fila['revisor_nombre']) : '' ?>:</strong> <?= e((string) $fila['comentario']) ?></div><?php endif; ?>
            <?php if ($est === 'aprobado' && $fila && $fila['comentario']): ?><div class="tit-ok"><?= e((string) $fila['comentario']) ?></div><?php endif; ?>
            <?php if ($fila && $fila['ruta']): ?>
              <p class="nota"><a href="<?= url($base . '/archivo/' . (int) $fila['id'] . '?ver=1') ?>" target="_blank" rel="noopener">Ver PDF enviado</a> · <?= e((string) $fila['nombre_original']) ?></p>
            <?php endif; ?>
            <?php if ($editable && $etapa['propia_ok'] && $est !== 'aprobado'): ?>
              <form method="post" enctype="multipart/form-data" action="<?= url($base . '/documento/' . $etapa['clave'] . '/' . $dc) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="file" name="archivo" accept="application/pdf,.pdf" required aria-label="PDF de <?= e($d['nombre']) ?>">
                <button class="btn btn-mini btn-primario" type="submit"><?= $fila && $fila['ruta'] ? 'Reemplazar' : 'Subir PDF' ?></button>
              </form>
              <?php if ($d['condicion'] && $est !== 'no_aplica'): ?>
                <form method="post" action="<?= url($base . '/noaplica/' . $etapa['clave'] . '/' . $dc) ?>" style="margin-top:4px">
                  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                  <button class="btn btn-mini" type="submit">No me aplica</button>
                </form>
              <?php endif; ?>
            <?php elseif ($editable && !$etapa['propia_ok']): ?>
              <p class="nota">Completa primero los datos de esta etapa para subir documentos.</p>
            <?php endif; ?>
          </div>
          <div>
            <?php if (!empty($d['formato']) && isset($formatos[$d['formato']]) && !$formatos[$d['formato']]['impedimento']): ?>
              <a class="btn btn-mini" href="<?= url($base . '/formato/' . $d['formato']) ?>">Formato llenado</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$bloqueada && array_filter($etapa['docs_estado'] ?? [], static fn(array $x): bool => !empty($x['def']['fisico']) || !empty($x['def']['cotejo']))): ?>
      <p class="texto-secundario" style="font-size:13px">* Este documento también se entrega en físico en Apoyo a Titulación. Cuando se indica «original para cotejo», el personal lo compara con el PDF que subiste y registra el cotejo con su firma electrónica.</p>
    <?php endif; ?>

    <?php if (!$bloqueada && !empty($etapa['presencial'])): ?>
      <h3>Entrega presencial en Apoyo a Titulación</h3>
      <ul class="tit-lista-simple"><?php foreach ($etapa['presencial'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php if (!$bloqueada && $etapa['tipo'] === 'confirmacion'): ?>
      <?php if ($etapa['confirmada']): ?>
        <p class="tit-ok">Confirmaste que tu trabajo está listo para revisión.</p>
      <?php elseif ($editable): ?>
        <form method="post" action="<?= url($base . '/confirmar/' . $etapa['clave']) ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button class="btn btn-primario" type="submit"><?= e($etapa['confirmar']) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$bloqueada && isset($etapa['personal'])): $hito = $etapa['hito']; ?>
      <h3><?= e($etapa['personal']['titulo']) ?></h3>
      <?php if ($hito): ?>
        <div class="tit-ok">
          <?php foreach ($etapa['personal']['campos'] ?? [] as $c): $v = (string) ($hito['datos'][$c['k']] ?? ''); if ($v === '') continue;
            if (($c['tipo'] ?? '') === 'select') { $v = $c['opciones'][$v] ?? $v; } ?>
            <div><strong><?= e($c['label']) ?>:</strong> <span style="white-space:pre-wrap"><?= e($v) ?></span></div>
          <?php endforeach; ?>
          <?php $ent = $etapa['personal']['entrega'] ?? null; $filaEnt = $ent ? ($docs[$etapa['clave'] . '|' . $ent['clave']] ?? null) : null; ?>
          <?php if ($filaEnt): ?><div><a href="<?= url($base . '/archivo/' . (int) $filaEnt['id'] . '?ver=1') ?>" target="_blank" rel="noopener"><?= e($ent['nombre']) ?></a></div><?php endif; ?>
        </div>
      <?php else: ?>
        <p class="texto-secundario"><?= e($etapa['personal']['descripcion'] ?? 'Esta parte la realiza el Departamento de Evaluación Profesional; te avisaremos aquí cuando esté lista.') ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($etapa['blanco'])): ?>
      <h3>Formatos en blanco</h3>
      <ul class="tit-lista-simple">
        <?php foreach ($etapa['blanco'] as $b): if (!isset($blanco[$b])) continue; ?>
          <li><a href="<?= url('titulacion/licenciatura/blanco/' . $b) ?>"><?= e($blanco[$b][0]) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($puedeEditar): ?>
  <section class="tarjeta">
    <details>
      <summary>Cancelar este trámite</summary>
      <form method="post" action="<?= url($base . '/cancelar') ?>" onsubmit="return confirm('¿Cancelar el trámite? Tus datos se conservan, pero tendrás que iniciar otro.')" style="margin-top:10px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button class="btn btn-peligro btn-mini" type="submit">Sí, cancelar mi trámite</button>
      </form>
    </details>
  </section>
  <?php endif; ?>
</div>

<aside style="display:flex;flex-direction:column;gap:18px">
  <section class="tarjeta">
    <h2 style="font-size:16px">Avance</h2>
    <div class="tit-barra" role="progressbar" aria-valuenow="<?= $hechas ?>" aria-valuemin="0" aria-valuemax="<?= $total ?>"><i style="width:<?= $total ? (int) round(100 * $hechas / $total) : 0 ?>%"></i></div>
    <p class="texto-secundario" style="margin:6px 0 10px"><?= $hechas ?> de <?= $total ?> etapas</p>
    <ol class="tit-etapas">
      <?php foreach ($flujo['etapas'] as $i => $e): $cls = $e['situacion'] . ($e['clave'] === $etapa['clave'] ? ' seleccionada' : ''); ?>
        <li class="<?= e($cls) ?>">
          <?php if ($e['situacion'] === 'bloqueada'): ?>
            <span><b class="num"><?= $i + 1 ?></b><?= e($e['titulo']) ?></span>
          <?php else: ?>
            <a href="<?= url($base . '?etapa=' . $e['clave']) ?>"><b class="num"><?= $e['situacion'] === 'completa' ? '✓' : $i + 1 ?></b><?= e($e['titulo']) ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="tarjeta">
    <h2 style="font-size:16px">Historial</h2>
    <?php if (!$eventos): ?><p class="texto-secundario">Sin movimientos.</p><?php endif; ?>
    <?php foreach (array_slice($eventos, 0, 12) as $ev): ?>
      <div class="tit-evento"><?= e($ev['detalle']) ?><time><?= e($ev['autor']) ?> · <?= e(fecha_corta($ev['creado_en'])) ?></time></div>
    <?php endforeach; ?>
  </section>

  <section class="tarjeta">
    <h2 style="font-size:16px">¿Dudas?</h2>
    <p class="texto-secundario" style="font-size:13px">Apoyo a Titulación · 9:00 a 15:00 y 16:00 a 17:30 h<br><a href="mailto:titulacion_fi@uaemex.mx">titulacion_fi@uaemex.mx</a></p>
  </section>
</aside>
</div>
