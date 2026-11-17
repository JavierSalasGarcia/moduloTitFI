<?php
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Flujo;
use App\Modules\Posgrado\PosgradoAccess;
use App\Modules\Posgrado\Services\Firmas;

include __DIR__ . '/_estilos.php';

$gbase = 'posgrado/gestion/' . (int) $t['id'];
$i = $t['datos']['interesado'] ?? [];
$s = $t['datos']['sinodo'] ?? [];
$actual = (int) $ev['actual'];
$momentos = Catalogo::momentos();
$opera = PosgradoAccess::esPersonalOperativo();
$abierto = $t['estado'] === 'abierto' && $opera;
$cotejado = isset($ev['hitos']['cotejo']);
$firmas = $ev['firmas'];
$prog = $ev['hitos']['programacion']['datos'] ?? [];
$res = $ev['hitos']['resultado']['datos'] ?? [];
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$chk = static fn(bool $b): string => $b ? ' checked' : '';
$valor = static fn(string $k): string => trim((string) ($i[$k] ?? '')) !== '' ? (string) $i[$k] : '—';
$persona = static fn(string $rol): string => trim(($i[$rol . '_grado'] ?? '') . ' ' . ($i[$rol . '_nombre'] ?? '')) . (($i[$rol . '_correo'] ?? '') !== '' ? ' · ' . $i[$rol . '_correo'] : '');
$emitir = static function (string $clave, string $texto, bool $primario = true) use ($gbase, $csrf, $ev, $abierto): string {
    $g = $ev['generados'][$clave] ?? null;
    $h = '';
    if ($g) {
        $ef = Flujo::estadoFirma($ev['firmas'], $clave);
        $h .= $ef === 'firmado' ? '<span class="pos-estado pos-e-ok">Firmado</span> '
            : (Firmas::rolCompleto($ev['firmas'], $clave, 'coordinacion') ? '<span class="pos-estado pos-e-rev">Esperando acuses</span> ' : '<span class="pos-estado pos-e-rev">En firma de la Coordinación</span> ');
    }
    if ($g) {
        $h .= '<a class="btn btn-mini" href="' . url('posgrado/generado/' . (int) $g['id']) . '" target="_blank" rel="noopener">Ver ' . e(mb_strtolower($texto)) . '</a> ';
    }
    if ($abierto) {
        $h .= '<form method="post" action="' . url($gbase . '/emitir/' . $clave) . '" style="display:inline">' . $csrf
            . '<button class="btn btn-mini ' . ($g || !$primario ? '' : 'btn-primario') . '" type="submit">' . ($g ? 'Volver a emitir' : 'Emitir ' . e(mb_strtolower($texto))) . '</button></form>';
    }
    if ($g) {
        $h .= ' <small class="texto-secundario">Emitido ' . e(fecha_corta((string) $g['generado_en'])) . ' · SHA-256 ' . e(substr((string) $g['huella'], 0, 12)) . '…</small>';
    }
    return $h;
};
?>
<div class="pos-cabecera">
  <div>
    <p class="pos-volver"><a href="<?= url('posgrado/gestion') ?>">← Bandeja de trámites</a></p>
    <p><strong><?= e((string) $t['alumno_nombre']) ?></strong> · <?= e((string) $t['alumno_email']) ?></p>
    <p class="texto-secundario"><?= e(Catalogo::niveles()[$ev['nivel']] ?? '') ?> · <?= e((string) ($i['programa'] ?? '')) ?><?= $t['titulo'] ? ' · «' . e(mb_strimwidth((string) $t['titulo'], 0, 120, '…')) . '»' : '' ?></p>
  </div>
  <div>
    <span class="estado estado-<?= e($t['estado'] === 'concluido' ? 'concluida' : $t['estado']) ?>"><?= e(Catalogo::estados()[$t['estado']] ?? $t['estado']) ?></span>
    <?php if ($t['responsable_nombre']): ?><p class="texto-secundario">Atiende: <?= e((string) $t['responsable_nombre']) ?></p><?php endif; ?>
    <p><a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id']) ?>">Ver como lo ve el egresado</a></p>
  </div>
</div>

<div class="pos-pila">
  <nav aria-label="Avance del trámite">
    <ol class="pos-momentos">
      <?php foreach ($momentos as $n => $m): $clase = $ev['hecho'][$n] ? 'hecho' : ($n === $actual ? 'actual' : 'pendiente'); ?>
        <li class="<?= $clase ?>"<?= $n === $actual ? ' aria-current="step"' : '' ?>><a href="#momento-<?= (int) $n ?>"><span class="pos-num" aria-hidden="true"><?= $ev['hecho'][$n] ? '✓' : (int) $n ?></span><?= e($m['breve']) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <section class="pos-paso <?= $ev['paso']['turno'] === 'personal' ? '' : 'personal' ?>">
    <p class="pos-paso-etiqueta"><?= $ev['paso']['turno'] === 'personal' ? 'Le toca a Apoyo a Grado' : ($ev['paso']['turno'] === 'tuyo' ? 'Le toca al egresado' : 'Estado') ?></p>
    <h2><?= e($ev['paso']['titulo']) ?></h2>
    <p style="margin:0"><?= e($ev['paso']['texto']) ?></p>
  </section>

  <?php /* ------------------------------------------------------------ Momento 1 */ ?>
  <details class="pos-momento <?= $actual === 1 ? 'actual' : '' ?>" id="momento-1" <?= $actual === 1 ? 'open' : '' ?>>
    <summary>1. Requisitos y cotejo <small>· <?= $ev['hecho'][1] ? 'Cotejado' : (int) $ev['listos'] . ' de ' . (int) $ev['total'] . ' listos' . ($ev['enRevision'] ? ', ' . (int) $ev['enRevision'] . ' por validar' : '') ?></small></summary>
    <div class="pos-momento-cuerpo">
      <details class="pos-grupo" <?= $ev['faltanDatos'] ? 'open' : '' ?>>
        <summary>Datos del interesado <span class="pos-cuenta"><?= $ev['faltanDatos'] ? 'Faltan: ' . e(implode(', ', array_slice($ev['faltanDatos'], 0, 4))) : 'Completos' ?></span></summary>
        <dl class="pos-dl">
          <dt>Nombre (como irá en el grado)</dt><dd><?= e($valor('nombre')) ?> · acentos: <?= e($valor('acentos')) ?></dd>
          <dt>Número de cuenta</dt><dd><?= e($valor('cuenta')) ?></dd>
          <dt>Programa y nivel</dt><dd><?= e($valor('programa')) ?> · <?= e(Catalogo::niveles()[$i['nivel'] ?? ''] ?? '—') ?></dd>
          <dt>Plan de estudios · inicio</dt><dd><?= e($valor('plan')) ?> · <?= e($valor('periodo_inicio')) ?></dd>
          <dt>Teléfono · correo</dt><dd><?= e($valor('telefono')) ?> · <?= e($valor('correo')) ?></dd>
          <dt>Ocupación</dt><dd><?= e($valor('ocupacion')) ?></dd>
          <dt>Modalidad</dt><dd><?= e(Catalogo::modalidades()[$i['modalidad'] ?? ''] ?? '—') ?></dd>
          <dt>Examen previo</dt><dd><?= e($valor('fecha_previo')) ?></dd>
          <dt>Director(a)</dt><dd><?= e($persona('director') ?: '—') ?></dd>
          <dt>Codirector(a) · Tutor(a)</dt><dd><?= e($persona('codirector') ?: '—') ?> · <?= e($persona('tutor') ?: '—') ?></dd>
          <dt>Art. 53 del REA</dt><dd><?= e($valor('art53')) ?></dd>
          <dt>Título</dt><dd><?= e($valor('titulo')) ?></dd>
        </dl>

      </details>
      <?php
      $porGrupo = [];
      foreach ($ev['requisitos'] as $r) {
          $porGrupo[$r['grupo']][] = $r;
      }
      foreach (Catalogo::grupos() as $g => $nombreGrupo):
          $filas = $porGrupo[$g] ?? [];
          if (!$filas) {
              continue;
          }
          $cuentan = array_filter($filas, static fn(array $r): bool => $r['cuenta'] ?? true);
          $hechos = count(array_filter($cuentan, static fn(array $r): bool => $r['listo']));
          $porValidar = count(array_filter($filas, static fn(array $r): bool => $r['estado'] === 'subido')); ?>
        <details class="pos-grupo" <?= !$ev['hecho'][1] && ($porValidar > 0 || $hechos < count($cuentan)) ? 'open' : '' ?>>
          <summary><?= e($nombreGrupo) ?> <span class="pos-cuenta"><?= $hechos ?> de <?= count($cuentan) ?><?= $porValidar ? ' · ' . $porValidar . ' por validar' : '' ?></span></summary>
          <ul class="pos-lista">
            <?php foreach ($filas as $fila) {
                $prefijo = 'req-';
                $bloqueado = $cotejado || !$abierto;
                if ($fila['tipo'] === 'generado' && ($fila['formato'] ?? '') === 'no_adeudo') {
                    $bloqueado = !$abierto;
                }
                include __DIR__ . '/_doc_personal.php';
            } ?>
          </ul>
        </details>
      <?php endforeach; ?>

      <h3 class="pos-sub">Cotejo de originales</h3>
      <?php if ($cotejado): $c = $ev['hitos']['cotejo']; ?>
        <div class="pos-ok">Cotejo registrado: <?= e(Flujo::fechaLarga((string) ($c['datos']['fecha'] ?? ''))) ?>, <?= e((string) ($c['datos']['hora'] ?? '')) ?> horas, por <?= e((string) $c['completado_nombre']) ?>.</div>
        <p><?= $emitir('cotejo', 'Oficio de cotejo') ?></p>
      <?php elseif ($abierto): ?>
        <?php if (!$ev['requisitosCompletos']): ?><p class="pos-nota">Para cotejar deben estar completos los datos, firmados el voto aprobatorio y el formato de datos, validados todos los PDF, marcadas las entregas en físico y firmada por la Coordinación la constancia de no adeudo.</p><?php endif; ?>
        <form class="formulario" method="post" action="<?= url($gbase . '/cotejo') ?>">
          <?= $csrf ?>
          <div class="pos-campos">
            <label>Fecha de recepción del total de requisitos<input type="date" name="fecha" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label>Hora<input type="time" name="hora" value="<?= e(date('H:i')) ?>" required></label>
          </div>
          <div class="acciones-formulario"><button class="btn btn-primario" type="submit" <?= $ev['requisitosCompletos'] ? '' : 'disabled' ?>>Registrar cotejo y emitir oficio</button> <small class="texto-secundario">El oficio queda en firma de la Coordinación.</small></div>
        </form>
      <?php endif; ?>
    </div>
  </details>

  <?php /* ------------------------------------------------------------ Momento 2 */ ?>
  <details class="pos-momento <?= $actual === 2 ? 'actual' : '' ?>" id="momento-2" <?= $actual === 2 ? 'open' : '' ?>>
    <summary>2. Sínodo, fecha y programación <small><?= $ev['hecho'][2] ? '· Programado' : '' ?></small></summary>
    <div class="pos-momento-cuerpo">
      <?php if (!empty($t['datos']['aplazamientos'])): ?>
        <p class="pos-nota">Aplazamientos anteriores: <?= e(implode(', ', array_map(static fn(array $a): string => Flujo::fechaLarga((string) ($a['fecha'] ?? '')), $t['datos']['aplazamientos']))) ?>.</p>
      <?php endif; ?>
      <?php if (!($s['miembros'] ?? [])): ?>
        <p class="texto-secundario">El egresado aún no propone su sínodo.</p>
      <?php else: ?>
        <div class="tabla-contenedor">
          <table class="tabla">
            <thead><tr><th>#</th><th>Integrante</th><th>Tipo</th><th>Cargo</th><th>Correo</th><th>Externo</th></tr></thead>
            <tbody>
            <?php foreach ($s['miembros'] as $n => $m): ?>
              <tr><td><?= $n + 1 ?></td><td><?= e((string) $m['nombre']) ?></td><td><?= e(Catalogo::tipoMiembro((string) $m['tipo'], (string) ($i['programa_siglas'] ?? ''))) ?></td><td><?= $n < 5 ? 'Propietario' : 'Suplente' ?></td><td><?= e((string) ($m['correo'] ?? '')) ?></td><td><?= !empty($m['externo']) ? 'Sí' : 'No' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p>Propuesta: <strong><?= e(Flujo::fechaLarga((string) ($s['fecha'] ?? '')) ?: '—') ?></strong> a las <strong><?= e((string) ($s['hora'] ?? '—')) ?></strong> · <?= e((string) ($s['modalidad'] ?? '')) ?>
          <?php if (!$ev['faltanSinodo']): ?> · <a href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/solicitud') ?>">Solicitud</a> · <a href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/visto_bueno') ?>">Visto bueno</a><?php endif; ?></p>
      <?php endif; ?>
      <div id="firmas-m2">
      <?php if (!$ev['propuestaEnviada']): ?>
        <p class="texto-secundario">El egresado aún no envía su propuesta a firma.</p>
      <?php else: ?>
        <?php foreach ($ev['avisosVigencia'] as $aviso): ?><p class="pos-alerta" role="alert"><strong><?= e($aviso) ?></strong></p><?php endforeach; ?>
        <?php foreach ($ev['respuestasNo'] as $rn): ?><p class="pos-alerta"><strong><?= e($rn['nombre']) ?> contestó «No»</strong> a: <?= e(implode(', ', $rn['preguntas'])) ?>. Decide si validas la propuesta (al programar, con justificación) o la devuelves al egresado.</p><?php endforeach; ?>
        <?php foreach (['solicitud' => 'Solicitud de evaluación de grado', 'visto_bueno' => 'Visto bueno del sínodo'] as $doc => $etq): $docFirmas = $firmas[$doc] ?? null; $g = $ev['generados'][$doc] ?? null; ?>
          <h4 style="margin:12px 0 4px;font-size:14px"><?= e($etq) ?> <?php if ($docFirmas): ?><span class="pos-estado <?= $docFirmas['completo'] ? 'pos-e-ok' : 'pos-e-rev' ?>"><?= $docFirmas['completo'] ? 'Firmado por todos' : 'En firma' ?></span><?php endif; ?>
            <?php if ($g): ?> <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $g['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php endif; ?></h4>
          <?php $gestiona = $abierto && !$ev['hecho'][2]; $volver = 'firmas-m2'; $fechaExamen = $ev['fechaExamen']; include __DIR__ . '/_firmantes.php'; ?>
        <?php endforeach; ?>
        <?php if ($abierto && !$ev['hecho'][2]): ?>
          <details class="pos-reemplazar" style="margin-top:8px"><summary>Devolver la propuesta al egresado</summary>
            <form method="post" action="<?= url($gbase . '/devolver') ?>">
              <?= $csrf ?>
              <label class="sr-only" for="devolver-com">Qué debe ajustar</label>
              <textarea id="devolver-com" name="comentario" rows="2" maxlength="600" placeholder="Qué debe ajustar el egresado" required></textarea>
              <button class="btn btn-mini btn-peligro" type="submit">Devolver y cancelar las firmas</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
      </div>
      <?php if ($ev['externos']): ?>
        <div class="pos-nota" id="externos"><strong>Hay sinodales externos.</strong> Verifica que estén registrados en el Sistema de Control Escolar y que tengan firma electrónica para el SICDE.
          <?php if ($abierto && !$ev['hecho'][2]): ?>
          <form method="post" action="<?= url($gbase . '/externos') ?>" style="margin-top:8px">
            <?= $csrf ?>
            <input type="hidden" name="accion" value="<?= $ev['externosAlta'] ? 'quitar' : 'marcar' ?>">
            <button class="btn btn-mini <?= $ev['externosAlta'] ? '' : 'btn-primario' ?>" type="submit"><?= $ev['externosAlta'] ? 'Desmarcar «externos dados de alta»' : 'Marcar: externos dados de alta en Control Escolar' ?></button>
          </form>
          <?php elseif ($ev['externosAlta']): ?><br><span class="pos-estado pos-e-ok">Externos dados de alta en Control Escolar</span><?php endif; ?>
        </div>
      <?php endif; ?>

      <h3 class="pos-sub">Programación del examen</h3>
      <?php if ($ev['hecho'][2]): ?>
        <div class="pos-ok"><?= e(Flujo::fechaLarga((string) $prog['fecha'])) ?>, <?= e((string) $prog['hora']) ?> horas, <?= e((string) $prog['modalidad']) ?>, en <?= e((string) $prog['lugar']) ?>.</div>
      <?php elseif ($abierto && $ev['hecho'][1]):
          $listo = !$ev['faltanSinodo'] && $ev['propuestaEnviada'] && (!$ev['externos'] || $ev['externosAlta']);
          $salvedad = !$ev['firmasM2'] || $ev['respuestasNo'] || $ev['vigencias']; ?>
        <?php if (!$listo): ?><p class="pos-nota">Se habilita con la propuesta enviada a firma<?= $ev['externos'] ? ' y el alta de externos' : '' ?>.</p>
        <?php elseif ($salvedad): ?><p class="pos-nota">Aún no se cumple todo (firmas del director y los siete integrantes, sin «No» y con vigencias de firma UAEMéx suficientes). Solo puedes programar escribiendo la justificación, que queda en la bitácora.</p><?php endif; ?>
        <form class="formulario" method="post" action="<?= url($gbase . '/programacion') ?>">
          <?= $csrf ?>
          <div class="pos-campos">
            <label>Fecha<input type="date" name="fecha" min="<?= e(date('Y-m-d')) ?>" value="<?= e((string) ($s['fecha'] ?? '')) ?>" required></label>
            <label>Hora<input type="time" name="hora" value="<?= e((string) ($s['hora'] ?? '')) ?>" required></label>
            <label class="ancho">Lugar, como irá en los oficios<input type="text" name="lugar" maxlength="200" placeholder="el Auditorio 120 de la Facultad de Ingeniería" required>
              <small>Se escribe después de «en …». Si es virtual, indica la plataforma.</small></label>
            <?php if (!empty($salvedad)): ?><label class="ancho">Justificación (obligatoria para programar con salvedades)<textarea name="justificacion" rows="2" maxlength="900"></textarea></label><?php endif; ?>
            <div class="ancho"><div class="pos-radios"><label><input type="radio" name="modalidad" value="presencial"<?= $chk(($s['modalidad'] ?? 'presencial') !== 'virtual') ?>> Presencial</label><label><input type="radio" name="modalidad" value="virtual"<?= $chk(($s['modalidad'] ?? '') === 'virtual') ?>> Virtual</label></div></div>
          </div>
          <div class="acciones-formulario"><button class="btn btn-primario" type="submit" <?= $listo ? '' : 'disabled' ?>>Registrar programación</button></div>
        </form>
      <?php endif; ?>
    </div>
  </details>

  <?php /* ------------------------------------------------------------ Momento 3 */ ?>
  <details class="pos-momento <?= $actual === 3 ? 'actual' : '' ?>" id="momento-3" <?= $actual === 3 ? 'open' : '' ?>>
    <summary>3. Invitaciones al sínodo <small><?= $ev['hecho'][3] ? '· Acuses completos' : '' ?></small></summary>
    <div class="pos-momento-cuerpo">
      <?php if (!$ev['hecho'][2]): ?>
        <p class="texto-secundario">Se habilita al registrar la programación.</p>
      <?php else: ?>
        <ul class="pos-lista">
          <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['invitaciones']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Oficios de invitación (una hoja por integrante)</h4></div><div class="pos-doc-acciones"><?= $emitir('invitaciones', 'Invitaciones') ?></div></li>
          <li class="pos-doc" id="doc-acuse"><span class="pos-icono <?= isset($ev['generados']['acuse']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Acuse de recibido de oficios</h4><p class="pos-ayuda">Cuando la Coordinación firma las invitaciones y el acuse, cada integrante recibe su invitación por correo y acusa de recibido en línea.</p></div>
            <?php if (isset($firmas['acuse'])): ?><div style="grid-column:2"><?php $docFirmas = $firmas['acuse']; $gestiona = $abierto; $volver = 'doc-acuse'; $fechaExamen = ''; include __DIR__ . '/_firmantes.php'; ?></div><?php endif; ?>
            <div class="pos-doc-acciones"><?= $emitir('acuse', 'Acuse') ?></div></li>
          <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['notificacion']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Notificación al director de tesis</h4><p class="pos-ayuda">Lleva los datos de contacto de <a href="<?= url('posgrado/configuracion') ?>">Configuración</a>.</p></div><div class="pos-doc-acciones"><?= $emitir('notificacion', 'Notificación') ?></div></li>
        </ul>
        <?php if (isset($ev['hitos']['acuse_cierre'])): ?>
          <div class="pos-ok">Invitaciones cerradas con justificación: <?= e((string) ($ev['hitos']['acuse_cierre']['datos']['justificacion'] ?? '')) ?></div>
        <?php elseif ($abierto && !$ev['hecho'][3] && $ev['invitacionesFirmadas']): ?>
          <details class="pos-reemplazar"><summary>Cerrar sin todos los acuses</summary>
            <form method="post" action="<?= url($gbase . '/acuse') ?>">
              <?= $csrf ?>
              <label class="sr-only" for="cierre-just">Justificación</label>
              <textarea id="cierre-just" name="justificacion" rows="2" maxlength="900" placeholder="Por qué se cierra sin todos los acuses" required></textarea>
              <button class="btn btn-mini" type="submit">Cerrar invitaciones</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <?php /* ------------------------------------------------------------ Momento 4 */ ?>
  <details class="pos-momento <?= $actual >= 4 ? 'actual' : '' ?>" id="momento-4" <?= $actual >= 4 ? 'open' : '' ?>>
    <summary>4. Examen y cierre <small><?= $ev['hecho'][4] ? '· Expediente integrado' : '' ?></small></summary>
    <div class="pos-momento-cuerpo">
      <?php if (!$ev['hecho'][3]): ?>
        <p class="texto-secundario">Se habilita cuando el sínodo acusa de recibido sus invitaciones (o al cerrarlas con justificación).</p>
      <?php else: ?>
        <h3 class="pos-sub">Resultado</h3>
        <?php if ($res): ?>
          <div class="pos-ok"><strong><?= e(Catalogo::resultados()[$res['resultado']] ?? '') ?></strong> · <?= e(Flujo::fechaLarga((string) $res['fecha'])) ?>. Sínodo definitivo: <?= e(implode('; ', array_map(static fn($n): string => (string) ($s['miembros'][(int) $n]['nombre'] ?? ''), (array) ($res['definitivo'] ?? [])))) ?>.</div>
        <?php elseif ($abierto): ?>
          <form class="formulario" method="post" action="<?= url($gbase . '/resultado') ?>">
            <?= $csrf ?>
            <div class="pos-radios"><?php foreach (Catalogo::resultados() as $k => $v): ?><label><input type="radio" name="resultado" value="<?= e($k) ?>" required> <?= e($v) ?></label><?php endforeach; ?></div>
            <div class="pos-campos"><label>Fecha del examen<input type="date" name="fecha" max="<?= e(date('Y-m-d')) ?>" value="<?= e(min(date('Y-m-d'), (string) ($prog['fecha'] ?? date('Y-m-d')))) ?>" required></label></div>
            <fieldset class="pos-fieldset"><legend>Sínodo definitivo (marca a los cinco que participaron)</legend>
              <?php foreach ($s['miembros'] ?? [] as $n => $m): ?>
                <label class="pos-check" style="display:flex !important;margin:4px 0"><input type="checkbox" name="definitivo[]" value="<?= (int) $n ?>"<?= $chk($n < 5) ?>> <?= e((string) $m['nombre']) ?> <small class="texto-secundario">(<?= $n < 5 ? 'propietario' : 'suplente' ?>)</small></label>
              <?php endforeach; ?>
            </fieldset>
            <p class="texto-secundario" style="font-size:13px">«Aplazado» devuelve el trámite a «Propón sínodo y fecha» para reprogramar; se conserva el antecedente.</p>
            <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Registrar resultado</button></div>
          </form>
        <?php endif; ?>
        <?php if ($ev['aprobado']): ?>
          <h3 class="pos-sub">Cierre</h3>
          <p>Folio de seguimiento del SICDE: <strong><?= $ev['folioSicde'] !== '' ? e($ev['folioSicde']) : 'el egresado aún no lo captura' ?></strong></p>
          <ul class="pos-lista">
            <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['constancias_revisores']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Constancias de revisores</h4><p class="pos-ayuda">Una hoja por integrante del sínodo definitivo.</p></div><div class="pos-doc-acciones"><?= $emitir('constancias_revisores', 'Constancias') ?></div></li>
            <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['constancias_direccion']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Constancias de director, codirector y tutor</h4></div><div class="pos-doc-acciones"><?= $emitir('constancias_direccion', 'Constancias') ?></div></li>
            <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['no_adeudo']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Constancia de no adeudo de equipo y mobiliario</h4></div><div class="pos-doc-acciones"><?= $emitir('no_adeudo', 'Constancia', false) ?></div></li>
            <li class="pos-doc"><span class="pos-icono <?= isset($ev['generados']['integracion']) ? 'pos-e-ok' : 'pos-e-pend' ?>" aria-hidden="true"></span><div><h4>Formato de integración de expediente</h4><p class="pos-ayuda">Casillas marcadas según modalidad y nivel.</p></div><div class="pos-doc-acciones"><?= $emitir('integracion', 'Formato') ?></div></li>
          </ul>
          <?php if ($ev['hecho'][4]): ?>
            <div class="pos-ok">Expediente integrado el <?= e(Flujo::fechaLarga((string) ($ev['hitos']['expediente']['datos']['fecha'] ?? ''))) ?>. Trámite concluido.</div>
          <?php elseif ($abierto): $puede = $ev['folioSicde'] !== '' && Flujo::estadoFirma($firmas, 'integracion') === 'firmado'; ?>
            <form method="post" action="<?= url($gbase . '/expediente') ?>">
              <?= $csrf ?>
              <?php if (!$puede): ?><p class="pos-nota">Se habilita cuando el egresado capture su folio del SICDE y la Coordinación firme el formato de integración.</p><?php endif; ?>
              <button class="btn btn-primario" type="submit" <?= $puede ? '' : 'disabled' ?>>Marcar expediente como integrado y concluir</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </details>

  <details class="pos-momento">
    <summary>Bitácora <small>· <?= count($eventos) ?> movimientos</small></summary>
    <div class="pos-momento-cuerpo">
      <?php foreach ($eventos as $evt): ?>
        <div class="pos-evento"><time datetime="<?= e((string) $evt['creado_en']) ?>"><?= e(fecha_corta((string) $evt['creado_en'])) ?> · <?= e((string) $evt['autor']) ?><?= $evt['visible_alumno'] ? '' : ' · solo personal' ?></time><?= e((string) $evt['detalle']) ?></div>
      <?php endforeach; ?>
    </div>
  </details>
</div>
<script>
(function () {
  function abrir() {
    var id = decodeURIComponent(location.hash.slice(1));
    var el = id ? document.getElementById(id) : null;
    for (var n = el; n; n = n.parentElement) { if (n.tagName === 'DETAILS') { n.open = true; } }
    if (el) { el.scrollIntoView({block: 'start'}); }
  }
  window.addEventListener('hashchange', abrir);
  if (location.hash) { abrir(); }
})();
</script>
