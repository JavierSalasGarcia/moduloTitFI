<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\Expedientes;
use App\Modules\Titulacion\Services\HistoricoProtocolos;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$hoy = e(date('Y-m-d'));
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$modalidades = array_map(static fn(array $m): string => $m['nombre'], array_filter(Catalogo::modalidades(), static fn(array $m): bool => $m['tipo'] === 'con_trabajo'));
$ocultos = static function (array $d) use ($csrf): string {
    $h = '<input type="hidden" name="_csrf" value="' . $csrf . '">';
    foreach ($d as $k => $v) { $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">'; }
    return $h;
};
$fecha = static fn(?string $f): string => $f ? date('d/m/Y', strtotime($f)) : '';
$etapa = static function (string $nombre, ?string $f) use ($fecha): string {
    return '<span class="tit-chip ' . ($f ? 'ok' : '') . '" title="' . e($nombre) . '">' . e($nombre) . ': ' . ($f ? e($fecha($f)) : 'pendiente') . '</span> ';
};

/** Formularios de las tres etapas; $d lleva los datos de la persona y, si ya existen, protocolo_id / historico_id. */
$formProtocolo = static function (array $d, bool $homonimo) use ($ocultos, $hoy): string {
    ob_start(); ?>
    <form method="post" action="<?= url('titulacion/constancias/captura/protocolo') ?>">
      <?= $ocultos($d) ?>
      <div class="cap-rejilla">
        <label>Turnado a revisión el <input type="date" name="fecha_inicio" max="<?= $hoy ?>"></label>
        <label>Aprobado el <input type="date" name="fecha_aprobacion" required max="<?= $hoy ?>"></label>
        <label>Director de tesis <input name="director" maxlength="160"></label>
        <label class="ancho">Título del protocolo <input name="titulo" maxlength="500" required></label>
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <label>Revisor <?= $i ?> <input name="revisor<?= $i ?>" maxlength="160" <?= $i === 1 ? 'required' : '' ?> placeholder="Nombre con grado"></label>
          <label>Correo (opcional) <input type="email" name="revisor<?= $i ?>_email" maxlength="120"></label>
        <?php endfor; ?>
      </div>
      <?php if ($homonimo): ?><label class="cap-chk"><input type="checkbox" name="homonimo" value="1"> Es otra persona con el mismo nombre</label><?php endif; ?>
      <label class="cap-chk"><input type="checkbox" name="generar" value="1"> Generar las constancias de revisor del protocolo al guardar</label>
      <button class="btn btn-primario" type="submit">Guardar protocolo</button>
    </form>
    <?php return (string) ob_get_clean();
};
$formPrevio = static function (array $d, bool $homonimo, ?array $h = null) use ($ocultos, $hoy): string {
    ob_start(); ?>
    <form method="post" action="<?= url('titulacion/constancias/captura/previo') ?>">
      <?= $ocultos($d) ?>
      <div class="cap-rejilla">
        <label>Fecha del examen previo <input type="date" name="fecha_previo" required max="<?= $hoy ?>" value="<?= e((string) ($h['fecha_previo'] ?? '')) ?>"></label>
        <label>Acta del examen previo <input name="acta_previo" inputmode="numeric" pattern="[0-9]*" maxlength="8" value="<?= e((string) ($h['acta_previo'] ?? '')) ?>"></label>
      </div>
      <?php if ($homonimo): ?><label class="cap-chk"><input type="checkbox" name="homonimo" value="1"> Es otra persona con el mismo nombre</label><?php endif; ?>
      <button class="btn btn-primario" type="submit">Guardar examen previo</button>
    </form>
    <?php return (string) ob_get_clean();
};
$formExamen = static function (array $d, bool $homonimo, array $modalidades) use ($ocultos, $hoy): string {
    ob_start(); ?>
    <form method="post" action="<?= url('titulacion/constancias/captura/examen') ?>">
      <?= $ocultos($d) ?>
      <div class="cap-rejilla">
        <label>Fecha del examen definitivo <input type="date" name="fecha_examen" required max="<?= $hoy ?>"></label>
        <label>Acta <input name="acta" inputmode="numeric" pattern="[0-9]*" maxlength="8"></label>
        <label>Modalidad <select name="modalidad"><option value="">—</option><?php foreach ($modalidades as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
        <label class="ancho">Título del trabajo <input name="titulo" maxlength="500" placeholder="Si queda vacío se usa el del protocolo"></label>
      </div>
      <fieldset class="cap-grupo"><legend>Director, codirector y sinodales del acta</legend>
        <div class="cap-rejilla">
          <?php foreach (['director' => 'Director de tesis', 'codirector' => 'Codirector de tesis', 'presidente' => 'Presidente', 'secretario' => 'Secretario', 'vocal' => 'Vocal'] as $k => $n): ?>
            <label><?= e($n) ?> <input name="<?= e($k) ?>" maxlength="160" placeholder="Nombre con grado"></label>
            <label>Correo (opcional) <input type="email" name="<?= e($k) ?>_email" maxlength="120"></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?php if ($homonimo): ?><label class="cap-chk"><input type="checkbox" name="homonimo" value="1"> Es otra persona con el mismo nombre</label><?php endif; ?>
      <label class="cap-chk"><input type="checkbox" name="generar" value="1" checked> Generar las constancias al guardar (si el registro está completo)</label>
      <button class="btn btn-primario" type="submit">Guardar examen definitivo</button>
    </form>
    <?php return (string) ob_get_clean();
};
?>
<style>
.cap-rejilla{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:8px}
.cap-rejilla label{display:flex;flex-direction:column;gap:3px;font-size:13px}
.cap-rejilla .ancho{grid-column:1/-1}
.cap-grupo{border:1px solid var(--gris-100,#ddd);border-radius:8px;padding:10px;margin:10px 0}
.cap-grupo legend{font-size:13px;font-weight:600;padding:0 6px}
.cap-chk{display:flex;gap:6px;align-items:center;margin:6px 0;font-size:13px}
.cap-exp{border:1px solid var(--gris-100,#ddd);border-radius:8px;padding:12px;margin:10px 0}
.cap-exp summary{cursor:pointer;font-weight:600;margin-top:8px}
.cap-probable{background:#FEF3C7;border:1px solid #D97706;border-radius:6px;padding:8px;margin:8px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
</style>

<div class="tit-cabecera"><div>
  <p class="texto-secundario">Para trabajos escritos revisados <strong>fuera del sistema</strong> (en papel), mientras se adopta el flujo de la plataforma. Cada persona tiene <strong>un solo expediente con tres etapas</strong>: revisión del protocolo, examen previo y examen definitivo; pueden capturarse con meses o años de diferencia. <strong>Primero busca</strong> a la persona: si ya tiene expediente, completa la etapa que falta en lugar de crear otro. Los trámites hechos en la plataforma no se capturan aquí; sus constancias se generan solas.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:14px">
  <h2>1. Buscar el expediente</h2>
  <form method="get" action="<?= url('titulacion/constancias/captura') ?>" class="tit-campos">
    <input type="hidden" name="buscar" value="1">
    <label>Licenciatura <select name="b_carrera" required><option value="" disabled <?= $b['carrera'] === '' ? 'selected' : '' ?>>Elige…</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= $b['carrera'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Cuenta <input name="b_cuenta" value="<?= e($b['cuenta']) ?>" maxlength="12" inputmode="numeric"></label>
    <label>Nombre (cualquier parte) <input name="b_alumno" value="<?= e($b['alumno']) ?>" maxlength="160" placeholder="Apellidos y nombre"></label>
    <div><button class="btn btn-primario" type="submit">Buscar</button></div>
  </form>
</section>

<?php if ($expedientes !== null && $tramites): ?>
<section class="tarjeta" style="margin-bottom:14px;border-left:4px solid #D97706">
  <strong>Esta persona ya tiene trámite en la plataforma</strong>
  <?php foreach ($tramites as $tp): ?><div><a href="<?= url('titulacion/gestion/' . (int) $tp['id']) ?>"><?= e($tp['folio']) ?></a> · <?= e($tp['nombre']) ?> · <?= e(Catalogo::modalidad((string) $tp['modalidad'])['nombre'] ?? $tp['modalidad']) ?> · <?= e((string) (Catalogo::estadosTramite()[$tp['estado']] ?? $tp['estado'])) ?> (etapa: <?= e((string) $tp['etapa']) ?>)</div><?php endforeach; ?>
  <p class="texto-secundario" style="margin:6px 0 0">Sus constancias se generan solas al avanzar ese trámite; por eso no se permite capturarla de nuevo aquí.</p>
</section>
<?php endif; ?>
<?php if ($expedientes !== null): $d0 = ['carrera' => $b['carrera'], 'cuenta' => $b['cuenta'], 'alumno' => $b['alumno']]; ?>
<section class="tarjeta" style="margin-bottom:14px">
  <h2>2. <?= $expedientes ? count($expedientes) . ' expediente(s) encontrado(s)' : 'No hay ningún expediente de esa persona' ?></h2>
  <?php foreach ($expedientes as $e): $p = $e['protocolo']; $h = $e['historico']; $et = Expedientes::etapas($e);
      $d = ['carrera' => $p['carrera'] ?? $h['carrera'], 'cuenta' => $p['cuenta'] ?? $h['cuenta'], 'alumno' => $p['alumno'] ?? $h['alumno'], 'protocolo_id' => $p['id'] ?? '', 'historico_id' => $h['id'] ?? '']; ?>
    <div class="cap-exp">
      <strong><?= e($d['alumno']) ?></strong> · cuenta <?= e($d['cuenta'] ?: '—') ?> · <?= e(Catalogo::carreras()[$d['carrera']]['siglas'][0] ?? $d['carrera']) ?>
      <?php if ($h && $h['titulo'] || $p): ?><div class="texto-secundario"><?= e((string) (($h['titulo'] ?? '') ?: ($p['titulo'] ?? ''))) ?></div><?php endif; ?>
      <div style="margin-top:6px"><?= $etapa('Protocolo', $et['protocolo']) . $etapa('Examen previo', $et['previo']) . $etapa('Examen definitivo', $et['examen']) ?>
        <?= $h && $h['generado_en'] ? '<span class="tit-chip ok">Constancias del examen generadas</span>' : '' ?><?= $p && $p['generado_en'] ? '<span class="tit-chip ok">Constancias del protocolo generadas</span>' : '' ?></div>
      <?php if ($e['probable']): ?>
        <div class="cap-probable">Este protocolo y este examen tienen la misma cuenta, pero aún no están unidos.
          <form method="post" action="<?= url('titulacion/constancias/captura/ligar') ?>" style="display:inline"><?= $ocultos($d) ?><button class="btn btn-mini btn-primario" type="submit">Son la misma persona: unirlos</button></form></div>
      <?php endif; ?>
      <?php if (!$p && $h): ?><details><summary>Etapa 1 · Agregar la revisión del protocolo</summary><?= $formProtocolo($d, false) ?></details><?php endif; ?>
      <details <?= !$et['previo'] && ($p || $h) ? 'open' : '' ?>><summary>Etapa 2 · <?= $et['previo'] ? 'Corregir el examen previo' : 'Registrar el examen previo' ?></summary><?= $formPrevio($d, false, $h ?: $p) ?></details>
      <?php if (!$h || !$h['fecha_examen']): ?><details><summary>Etapa 3 · Registrar el examen definitivo</summary><?= $formExamen($d, false, $modalidades) ?></details>
      <?php else: ?><p class="texto-secundario">El examen definitivo se corrige con doble clic en el <a href="<?= url('titulacion/constancias/historial?q=' . rawurlencode((string) $d['alumno'])) ?>">historial</a>.</p><?php endif; ?>
    </div>
  <?php endforeach; ?>

  <div class="cap-exp" style="background:var(--gris-50,#fafafa)">
    <strong>3. ¿No es ninguno de los anteriores? Crear un expediente nuevo</strong>
    <p class="texto-secundario">Elige la etapa con la que empieza. Si ya hay coincidencias arriba, marca «Es otra persona» solo si realmente es un homónimo.</p>
    <?php $nd = $d0 + ['protocolo_id' => '', 'historico_id' => ''];
          $nuevoNombre = '<div class="cap-rejilla"><label>Alumno (apellidos y nombre) <input name="alumno" value="' . e($b['alumno']) . '" maxlength="160" required></label><label>Cuenta <input name="cuenta" value="' . e($b['cuenta']) . '" maxlength="12" inputmode="numeric"></label></div>'; ?>
    <?php foreach ([['Etapa 1 · Empieza con la revisión del protocolo', 'formProtocolo'], ['Etapa 2 · Empieza con el examen previo', 'formPrevio'], ['Etapa 3 · Empieza con el examen definitivo', 'formExamen']] as [$rotulo, $fn]): ?>
      <details><summary><?= e($rotulo) ?></summary>
        <?php $html = $fn === 'formProtocolo' ? $formProtocolo(['carrera' => $b['carrera']], (bool) $expedientes) : ($fn === 'formPrevio' ? $formPrevio(['carrera' => $b['carrera']], (bool) $expedientes) : $formExamen(['carrera' => $b['carrera']], (bool) $expedientes, $modalidades));
              echo preg_replace('/(<input type="hidden" name="carrera"[^>]*>)/', '$1' . str_replace('$', '\$', $nuevoNombre), $html, 1); ?>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="tarjeta">
  <h2>Protocolos capturados e históricos</h2>
  <p class="texto-secundario">Incluye los protocolos aprobados del sistema anterior (2004–2021) con todos los revisores que dictaminaron. Sus constancias se generan por lotes: filtra, y pulsa «Generar los pendientes del filtro». Cada constancia pasa a la cola de firmas.</p>
  <form method="get" action="<?= url('titulacion/constancias/captura') ?>" class="tit-campos" style="margin-bottom:10px">
    <label>Buscar <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Alumno, cuenta, título o revisor"></label>
    <label>Licenciatura <select name="carrera"><option value="">Todas</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= $f['carrera'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Constancias <select name="estado"><option value="">Todas</option><option value="pendientes" <?= $f['estado'] === 'pendientes' ? 'selected' : '' ?>>Sin generar</option><option value="generadas" <?= $f['estado'] === 'generadas' ? 'selected' : '' ?>>Generadas</option></select></label>
    <label>Aprobado desde <input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
    <label>Aprobado hasta <input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
    <div><button class="btn btn-primario" type="submit">Filtrar</button> <a class="btn" href="<?= url('titulacion/constancias/captura') ?>">Limpiar</a></div>
  </form>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px">
    <span class="texto-secundario"><?= (int) $total ?> protocolos<?= $total > 50 ? ' · página ' . (int) $pagina . ' de ' . (int) ceil($total / 50) : '' ?> · <?= count($pendientes) ?> sin constancias en este filtro</span>
    <span><button type="button" class="btn btn-primario" id="gen-lote" <?= $pendientes ? '' : 'disabled' ?>>Generar los pendientes del filtro (<?= count($pendientes) ?>)</button> <span id="gen-estado" class="texto-secundario" role="status"></span></span>
  </div>
  <?php if (!$protocolos): ?><div class="ux-empty">Sin protocolos con ese filtro.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla" data-no-tools>
    <thead><tr><th>Aprobado</th><th>Lic.</th><th>Alumno</th><th>Título</th><th>Revisores</th><th>Constancias</th></tr></thead>
    <tbody>
    <?php foreach ($protocolos as $p): $revs = HistoricoProtocolos::revisores($p); ?>
      <tr>
        <td><?= e((string) $p['fecha_aprobacion']) ?></td>
        <td><?= e(Catalogo::carreras()[$p['carrera']]['siglas'][0] ?? $p['carrera']) ?></td>
        <td><?= e($p['alumno']) ?></td>
        <td><?= e($p['titulo']) ?></td>
        <td title="<?= e(implode('; ', array_column($revs, 'nombre'))) ?>"><?= count($revs) ?>: <?= e(implode('; ', array_slice(array_column($revs, 'nombre'), 0, 3))) ?><?= count($revs) > 3 ? '…' : '' ?></td>
        <td style="white-space:nowrap">
          <?php if ($p['generado_en']): ?><span class="tit-chip ok">Generadas</span>
          <?php else: ?>
            <form method="post" action="<?= url('titulacion/constancias/captura/protocolo/' . (int) $p['id'] . '/generar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-primario" type="submit">Generar</button></form>
            <form method="post" action="<?= url('titulacion/constancias/captura/protocolo/' . (int) $p['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este registro?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Eliminar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($total > 50): $qs = static fn(array $x): string => http_build_query(array_filter($x + $f, static fn($v): bool => $v !== '' && $v !== null)); ?>
    <div class="acciones-formulario">
      <?php if ($pagina > 1): ?><a class="btn" href="<?= url('titulacion/constancias/captura?' . $qs(['p' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
      <?php if ($pagina * 50 < $total): ?><a class="btn" href="<?= url('titulacion/constancias/captura?' . $qs(['p' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<script>
(function () {
  var b = document.getElementById('gen-lote'), est = document.getElementById('gen-estado');
  if (!b) { return; }
  var ids = <?= json_encode($pendientes) ?>, csrf = <?= json_encode(Auth::csrfToken()) ?>, base = <?= json_encode(url('titulacion/constancias/captura/protocolo/')) ?>;
  b.addEventListener('click', function () {
    if (!ids.length || !confirm('¿Generar las constancias de ' + ids.length + ' protocolos? Cada uno genera una constancia por revisor y todas pasan a la cola de firmas. Puede tardar varios minutos: no cierres la página.')) { return; }
    b.disabled = true;
    var hechos = 0, constancias = 0, errores = 0, detener = false;
    function siguiente() {
      if (detener || hechos + errores >= ids.length) {
        est.textContent = 'Listo: ' + hechos + ' protocolos, ' + constancias + ' constancias' + (errores ? ', ' + errores + ' con error' : '') + '. Recarga para ver el estado.';
        b.disabled = false; return;
      }
      var fd = new FormData(); fd.append('_csrf', csrf); fd.append('json', '1');
      fetch(base + ids[hechos + errores] + '/generar', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (r) { if (r.ok) { hechos++; constancias += r.generadas; } else { errores++; if (errores >= 10) { detener = true; } } })
        .catch(function () { errores++; if (errores >= 10) { detener = true; } })
        .then(function () { est.textContent = (hechos + errores) + ' de ' + ids.length + '…'; siguiente(); });
    }
    siguiente();
  });
})();
</script>
