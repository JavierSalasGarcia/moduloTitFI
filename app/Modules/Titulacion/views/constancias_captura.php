<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\HistoricoProtocolos;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$modalidades = array_map(static fn(array $m): string => $m['nombre'], array_filter(Catalogo::modalidades(), static fn(array $m): bool => $m['tipo'] === 'con_trabajo'));
$opcCarrera = static function () use ($carreras): string {
    $h = '<option value="" selected disabled>Elige…</option>';
    foreach ($carreras as $k => $n) { $h .= '<option value="' . e($k) . '">' . e($n) . '</option>'; }
    return $h;
};
?>
<style>
.cap-rejilla{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.cap-rejilla label{display:flex;flex-direction:column;gap:3px;font-size:13px}
.cap-rejilla .ancho{grid-column:1/-1}
.cap-grupo{border:1px solid var(--gris-100,#ddd);border-radius:8px;padding:10px;margin:10px 0}
.cap-grupo legend{font-size:13px;font-weight:600;padding:0 6px}
</style>

<div class="tit-cabecera"><div>
  <p class="texto-secundario">Para exámenes y protocolos revisados <strong>fuera del sistema</strong> (en papel), mientras se adopta el flujo de la plataforma. Cada captura queda en el <a href="<?= url('titulacion/constancias/historial') ?>">historial</a> o en la lista de abajo y, al generar sus constancias, pasa a la cola de firmas igual que cualquier otra. Solo el nombre del profesor es obligatorio: su correo se busca solo en el directorio. Los trámites hechos en la plataforma no se capturan aquí; sus constancias se generan solas.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:14px">
  <h2>Examen profesional realizado fuera del sistema</h2>
  <form method="post" action="<?= url('titulacion/constancias/captura/examen') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="cap-rejilla">
      <label>Licenciatura <select name="carrera" required><?= $opcCarrera() ?></select></label>
      <label>Alumno (apellidos y nombre) <input name="alumno" maxlength="160" required></label>
      <label>Cuenta <input name="cuenta" maxlength="12" inputmode="numeric"></label>
      <label>Acta <input name="acta" inputmode="numeric" pattern="[0-9]*" maxlength="8"></label>
      <label>Fecha del examen <input type="date" name="fecha_examen" required max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Aprobación del protocolo <input type="date" name="fecha_protocolo" max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Modalidad <select name="modalidad"><option value="">—</option><?php foreach ($modalidades as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label class="ancho">Título del trabajo <input name="titulo" maxlength="500" required></label>
    </div>
    <fieldset class="cap-grupo"><legend>Director, codirector y sinodales del acta</legend>
      <div class="cap-rejilla">
        <?php foreach (['director' => 'Director de tesis', 'codirector' => 'Codirector de tesis', 'presidente' => 'Presidente', 'secretario' => 'Secretario', 'vocal' => 'Vocal'] as $k => $n): ?>
          <label><?= e($n) ?> <input name="<?= e($k) ?>" maxlength="160" placeholder="Nombre con grado, p. ej. Dr. Juan Pérez"></label>
          <label>Correo (opcional) <input type="email" name="<?= e($k) ?>_email" maxlength="120"></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label style="display:flex;gap:6px;align-items:center;margin:8px 0"><input type="checkbox" name="generar" value="1" checked> Generar las constancias al guardar (si el registro está completo)</label>
    <button class="btn btn-primario" type="submit">Guardar examen</button>
  </form>
</section>

<section class="tarjeta" style="margin-bottom:14px">
  <h2>Protocolo revisado fuera del sistema</h2>
  <form method="post" action="<?= url('titulacion/constancias/captura/protocolo') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="cap-rejilla">
      <label>Licenciatura <select name="carrera" required><?= $opcCarrera() ?></select></label>
      <label>Alumno (apellidos y nombre) <input name="alumno" maxlength="160" required></label>
      <label>Cuenta <input name="cuenta" maxlength="12" inputmode="numeric"></label>
      <label>Director de tesis <input name="director" maxlength="160"></label>
      <label>Turnado a revisión el <input type="date" name="fecha_inicio" max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Aprobado el <input type="date" name="fecha_aprobacion" required max="<?= e(date('Y-m-d')) ?>"></label>
      <label class="ancho">Título del protocolo <input name="titulo" maxlength="500" required></label>
    </div>
    <fieldset class="cap-grupo"><legend>Revisores del protocolo (cada uno recibe su constancia)</legend>
      <div class="cap-rejilla">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <label>Revisor <?= $i ?> <input name="revisor<?= $i ?>" maxlength="160" <?= $i === 1 ? 'required' : '' ?> placeholder="Nombre con grado"></label>
          <label>Correo (opcional) <input type="email" name="revisor<?= $i ?>_email" maxlength="120"></label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <label style="display:flex;gap:6px;align-items:center;margin:8px 0"><input type="checkbox" name="generar" value="1" checked> Generar las constancias al guardar</label>
    <button class="btn btn-primario" type="submit">Guardar protocolo</button>
  </form>
</section>

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
