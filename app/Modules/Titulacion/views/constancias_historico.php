<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\Historico;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$modalidades = array_map(static fn(array $m): string => $m['nombre'], Catalogo::modalidades());
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$columnas = ['fecha_examen', 'carrera', 'alumno', 'titulo', 'director', 'codirector', 'presidente', 'secretario', 'vocal'];
$rotulos = ['fecha_examen' => 'Fecha del examen', 'carrera' => 'Lic.', 'alumno' => 'Alumno', 'titulo' => 'Título del trabajo', 'director' => 'Director de tesis',
    'codirector' => 'Codirector de tesis', 'presidente' => 'Revisor del trabajo escrito', 'secretario' => 'Revisor del trabajo escrito', 'vocal' => 'Revisor del trabajo escrito'];
$opciones = [
    'carrera' => array_map(static fn(array $c): string => $c['siglas'][0], Catalogo::carreras()), 'modalidad' => ['' => '—'] + $modalidades,
    'testimonio' => ['' => '—', 'satisfactorio' => 'Satisfactorio', 'sobresaliente' => 'Sobresaliente'], 'vez' => ['' => '—', 'primera' => 'Primera', 'segunda' => 'Segunda'],
];
$qs = static fn(array $extra): string => http_build_query(array_filter($extra + $f, static fn($v): bool => $v !== '' && $v !== null));
?>
<style>
.hist-tabla td{padding:2px 3px;vertical-align:top}
.hist-tabla input,.hist-tabla select{width:100%;min-width:90px;font-size:13px;padding:3px 4px;border:1px solid transparent;background:transparent;color:inherit}
.hist-tabla input[readonly]{cursor:default}
.hist-tabla select:disabled{pointer-events:none;opacity:1;appearance:none;-webkit-appearance:none}
.hist-tabla td.editando input,.hist-tabla td.editando select{border-color:var(--borde,#999);background:var(--superficie,#fff);cursor:text}
.hist-tabla td.ancho input{min-width:220px}
.hist-tabla td.falta{background:#FDE2E2;box-shadow:inset 0 0 0 2px #DC2626;animation:hist-parpadeo 1.1s steps(2,start) infinite}
.hist-tabla td.fuera{background:#FEF3C7;box-shadow:inset 0 0 0 2px #D97706}
.hist-tabla td.guardando{opacity:.6}
.hist-tabla td.supuesto{outline:2px dashed #D97706;outline-offset:-2px}
.hist-tabla td.sel,.hist-tabla th.sel{width:1%;text-align:center;vertical-align:middle}
.hist-tabla td.sel input{width:auto;min-width:0;cursor:pointer}
.hist-tabla td.sel input:disabled{cursor:not-allowed;opacity:.35}
.hist-tabla td.error{box-shadow:inset 0 0 0 2px #B45309}
@keyframes hist-parpadeo{50%{background:transparent;box-shadow:inset 0 0 0 2px transparent}}
@media (prefers-reduced-motion: reduce){.hist-tabla td.falta{animation:none}}
.hist-estado{white-space:nowrap;font-size:12px}
</style>

<div class="tit-cabecera"><div>
  <p class="texto-secundario"><strong>Constancias para profesores: solo hay cuatro</strong> (revisor del protocolo, revisor del trabajo escrito, director de tesis y codirector de tesis). Aquí se generan las de los trabajos escritos ya presentados: director, codirector y los tres sinodales del acta (presidente, secretario o vocal; todos son revisores del trabajo escrito, los suplentes no). Quien se tituló por EGEL, aprovechamiento o créditos no genera constancias, por eso no aparece. Solo se necesita la fecha del examen, el alumno, el título del trabajo y el nombre de cada profesor; su correo se busca solo en el directorio. <br>Registros anteriores a la plataforma, tomados del libro de actas. Para completar o corregir un dato, haz doble clic en la celda, escribe y sal de ella (Enter guarda; Esc cancela). En ámbar, las fechas fuera de rango (antes de 2000 o posteriores a hoy), que suelen ser errores de captura del libro. En rojo parpadeante, lo que falta para generar las constancias del director, codirector y sinodales (solo opciones con trabajo escrito). Al generarlas pasan a la cola de firmas y, ya firmadas, aparecen en la cuenta de cada profesor. Si corriges un dato de un registro con constancias ya generadas, las constancias afectadas se rehacen sobre el mismo archivo (mismo número de oficio y QR) y vuelven a firma.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:14px" data-disclosure="Agregar un registro que no está en la lista (no es una búsqueda)">
  <form method="post" action="<?= url('titulacion/constancias/historial/nuevo') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end"
        onsubmit="return confirm('Esto CREA un registro nuevo para «' + this.alumno.value.trim() + '» (' + this.carrera.selectedOptions[0].textContent + '). Para buscar usa el cuadro «Buscar» de abajo. ¿Crear el registro?')">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label style="flex:0 1 220px">Licenciatura <select name="carrera" required><option value="" selected disabled>Elige…</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label style="flex:1 1 260px">Nombre completo del alumno nuevo <input name="alumno" maxlength="160" required placeholder="Apellidos y nombre, como en el libro"></label>
    <label style="flex:0 1 120px">Acta <input name="acta" inputmode="numeric" pattern="[0-9]*" maxlength="8"></label>
    <button class="btn" type="submit">Crear registro</button>
  </form>
  <p class="texto-secundario" style="margin-top:6px">Solo para un registro anterior a la plataforma que no esté en la lista. Para encontrar uno existente usa «Buscar». Los trámites nuevos no se capturan aquí: sus constancias se generan solas.</p>
</section>

<section class="tarjeta">
  <form method="get" action="<?= url('titulacion/constancias/historial') ?>" class="tit-campos" style="margin-bottom:10px">
    <label>Buscar <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Alumno, cuenta, acta, profesor o título" title="Busca en todo el historial; Enter para buscar"></label>
    <label>Licenciatura <select name="carrera"><option value="">Todas</option><?php foreach ($carreras as $k => $n): ?><option value="<?= e($k) ?>" <?= $f['carrera'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Datos faltantes <select name="faltantes">
      <option value="">Todos los registros</option>
      <option value="cualquiera" <?= $f['faltantes'] === 'cualquiera' ? 'selected' : '' ?>>Con algún dato faltante</option>
      <option value="completos" <?= $f['faltantes'] === 'completos' ? 'selected' : '' ?>>Completos</option>
      <option value="fuera_rango" <?= $f['faltantes'] === 'fuera_rango' ? 'selected' : '' ?>>Fechas fuera de rango (antes de 2000 o posteriores a hoy)</option>
      <?php foreach (['fecha_examen' => 'la fecha del examen', 'alumno' => 'el alumno', 'titulo' => 'el título del trabajo', 'director' => 'ningún profesor'] as $k => $et): ?><option value="<?= e($k) ?>" <?= $f['faltantes'] === $k ? 'selected' : '' ?>>Falta <?= e($et) ?></option><?php endforeach; ?>
    </select></label>
    <label>Constancias <select name="estado"><option value="">Todas</option><option value="pendientes" <?= $f['estado'] === 'pendientes' ? 'selected' : '' ?>>Sin generar</option><option value="generadas" <?= $f['estado'] === 'generadas' ? 'selected' : '' ?>>Generadas</option></select></label>
    <div><button class="btn btn-primario" type="submit">Filtrar</button></div>
  </form>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:8px">
    <span class="texto-secundario"><?= (int) $total ?> registros<?= $total > 100 ? ' · página ' . (int) $pagina . ' de ' . (int) ceil($total / 100) : '' ?></span>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <form method="post" action="<?= url('titulacion/constancias/historial/titulos') ?>" onsubmit="return confirm('¿Buscar en los trámites de la plataforma el título de los registros que no lo tienen? No se cambia ningún título ya escrito.')">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <button class="btn" type="submit" title="Toma el título del trabajo definitivo (solicitud de revisión) o, si no hay, el del protocolo">Buscar títulos en la plataforma</button>
      </form>
      <button class="btn btn-primario" type="button" id="generar-sel" disabled>Generar las seleccionadas (<span id="n-sel">0</span>)</button>
      <form method="post" action="<?= url('titulacion/constancias/historial/generar') ?>" onsubmit="return confirm('¿Generar las constancias de todos los registros completos con trabajo escrito que aún no las tienen?')">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <button class="btn" type="submit">Generar todos los completos</button>
      </form>
    </div>
  </div>
  <?php if (!$filas): ?><div class="ux-empty">Sin registros<?= $f['faltantes'] || $f['q'] || $f['carrera'] || $f['estado'] ? ' con ese filtro' : '' ?>.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla hist-tabla" data-no-tools>
    <thead><tr><th class="sel"><input type="checkbox" id="sel-todos" aria-label="Seleccionar todos los que se pueden generar"></th><?php foreach ($columnas as $c): ?><th><?= e($rotulos[$c]) ?></th><?php endforeach; ?><th>Constancias</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $h): ?>
      <tr data-id="<?= (int) $h['id'] ?>">
        <td class="sel"><input type="checkbox" class="sel-fila" aria-label="Seleccionar este registro" <?= !$h['aplica'] || $h['faltan'] ? 'disabled' : '' ?>></td>
        <?php foreach ($columnas as $c): $tipo = Historico::CAMPOS[$c][1]; $v = (string) ($h[$c] ?? ''); ?>
          <td class="<?= in_array($c, $h['faltan'], true) ? 'falta' : (in_array($c, $h['fuera'], true) ? 'fuera' : '') ?><?= in_array($c, ['alumno', 'titulo', 'director', 'codirector', 'presidente', 'secretario', 'vocal'], true) || str_ends_with($c, '_email') ? ' ancho' : '' ?><?= $c === 'titulo' && $h['titulo_origen'] === 'protocolo' ? ' supuesto' : '' ?>" data-campo="<?= e($c) ?>"
            <?php if ($c === 'titulo' && ($h['titulo_origen'] === 'protocolo' || $h['titulo_protocolo'] !== '' || $h['titulo_origen'] === 'revision' || $h['titulo_origen'] === 'sistema_anterior')): ?>title="<?= e(($h['titulo_origen'] === 'protocolo' ? 'Tomado del protocolo: confirma que sea el título definitivo (edítalo si cambió). ' : ($h['titulo_origen'] === 'revision' ? 'Título del trabajo escrito en la solicitud de revisión. ' : ($h['titulo_origen'] === 'sistema_anterior' ? 'Del sistema de Titulación anterior. ' : ''))) . ($h['titulo_protocolo'] !== '' ? 'Título en el protocolo: ' . $h['titulo_protocolo'] : '')) ?>"<?php elseif ($c === 'modalidad' && $h['modalidad_origen'] !== ''): ?>title="En el libro: <?= e($h['modalidad_origen']) ?>"<?php elseif (str_ends_with($c, '_email') && $h['correos'] !== ''): ?>title="Correos en el libro: <?= e($h['correos']) ?>"<?php endif; ?>>
            <?php if (isset($opciones[$tipo])): ?>
              <select disabled aria-label="<?= e(Historico::CAMPOS[$c][0]) ?>"><?php foreach ($opciones[$tipo] as $k => $n): ?><option value="<?= e((string) $k) ?>" <?= (string) $k === $v ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
            <?php else: ?>
              <input readonly aria-label="<?= e(Historico::CAMPOS[$c][0]) ?>" type="<?= $tipo === 'fecha' ? 'date' : ($tipo === 'email' ? 'email' : 'text') ?>" value="<?= e($v) ?>" <?= $tipo === 'numero' ? 'inputmode="numeric"' : '' ?>>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="hist-estado">
          <?php if ($h['generado_en']): ?><span class="tit-chip ok">Generadas</span><?php endif; ?>
          <button type="button" class="btn btn-mini btn-primario" data-generar <?= !$h['aplica'] || $h['faltan'] ? 'hidden' : '' ?>><?= $h['generado_en'] ? 'Completar' : 'Generar' ?></button>
          <?php if (!$h['tramite_id']): ?><button type="button" class="btn btn-mini" data-eliminar title="Eliminar registro">Eliminar</button><?php endif; ?>
          <span data-nota class="texto-secundario"></span>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($total > 100): ?>
    <div class="acciones-formulario">
      <?php if ($pagina > 1): ?><a class="btn" href="<?= url('titulacion/constancias/historial?' . $qs(['p' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
      <?php if ($pagina * 100 < $total): ?><a class="btn" href="<?= url('titulacion/constancias/historial?' . $qs(['p' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<script>
(function () {
  var csrf = <?= json_encode(Auth::csrfToken()) ?>, base = <?= json_encode(url('titulacion/constancias/historial/')) ?>;
  function enviar(url, datos) {
    var fd = new FormData(); fd.append('_csrf', csrf);
    Object.keys(datos).forEach(function (k) { fd.append(k, datos[k]); });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function pintar(tr, fila) {
    tr.querySelectorAll('td[data-campo]').forEach(function (td) {
      var falta = fila.faltan.indexOf(td.dataset.campo) >= 0;
      td.classList.toggle('falta', falta);
      td.classList.toggle('fuera', !falta && (fila.fuera || []).indexOf(td.dataset.campo) >= 0);
    });
    var b = tr.querySelector('[data-generar]'); b.hidden = !fila.aplica || fila.faltan.length > 0;
    var cs = tr.querySelector('.sel-fila'); cs.disabled = b.hidden; if (b.hidden) { cs.checked = false; }
    actualizarSeleccion();
  }
  function bloquear(td, el) {
    td.classList.remove('editando');
    if (el.tagName === 'SELECT') { el.disabled = true; } else { el.readOnly = true; }
  }
  document.querySelectorAll('.hist-tabla td[data-campo]').forEach(function (td) {
    var el = td.querySelector('input,select'), previo = el.value;
    td.addEventListener('dblclick', function () {
      if (td.classList.contains('editando')) { return; }
      td.classList.add('editando');
      if (el.tagName === 'SELECT') { el.disabled = false; } else { el.readOnly = false; }
      el.focus();
      if (el.select && el.type === 'text') { el.select(); }
    });
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { el.value = previo; bloquear(td, el); el.blur(); }
      if (e.key === 'Enter') { e.preventDefault(); el.blur(); }
    });
    el.addEventListener('blur', function () {
      if (!td.classList.contains('editando')) { return; }
      bloquear(td, el);
      if (el.value === previo) { return; }
      var tr = td.closest('tr');
      td.classList.add('guardando'); td.classList.remove('error');
      enviar(base + tr.dataset.id + '/campo', { campo: td.dataset.campo, valor: el.value }).then(function (r) {
        td.classList.remove('guardando');
        if (!r.ok) { td.classList.add('error'); td.title = r.error; alert(r.error); el.value = previo; return; }
        previo = el.value; pintar(tr, r.fila);
        if (r.fila.regeneradas > 0) { tr.querySelector('[data-nota]').textContent = r.fila.regeneradas + ' constancia(s) corregida(s); vuelven a firma'; }
      }).catch(function () { td.classList.remove('guardando'); td.classList.add('error'); el.value = previo; });
    });
  });
  document.querySelectorAll('[data-eliminar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var tr = b.closest('tr');
      if (!confirm('¿Eliminar este registro del historial? Solo se permite mientras no tenga constancias generadas.')) { return; }
      enviar(base + tr.dataset.id + '/eliminar', {}).then(function (r) {
        if (!r.ok) { alert(r.error); return; }
        tr.remove();
      }).catch(function () { alert('No se pudo eliminar.'); });
    });
  });
  /** Genera las constancias de una fila; devuelve una promesa con true si salió bien. */
  function generarFila(tr, silencioso) {
    var b = tr.querySelector('[data-generar]'); b.disabled = true;
    return enviar(base + tr.dataset.id + '/generar', {}).then(function (r) {
      b.disabled = false;
      if (!r.ok) { tr.querySelector('[data-nota]').textContent = r.error; if (!silencioso) { alert(r.error); } return false; }
      tr.querySelector('[data-nota]').textContent = r.generadas + ' generada(s); en firma';
      b.textContent = 'Completar';
      var x = tr.querySelector('[data-eliminar]'); if (x) { x.remove(); }
      return true;
    }).catch(function () { b.disabled = false; if (!silencioso) { alert('No se pudo generar.'); } return false; });
  }
  document.querySelectorAll('[data-generar]').forEach(function (b) {
    b.addEventListener('click', function () { generarFila(b.closest('tr'), false); });
  });

  // Casillas: elegir qué registros generar.
  var selTodos = document.getElementById('sel-todos'), btnSel = document.getElementById('generar-sel'), nSel = document.getElementById('n-sel');
  function marcadas() { return Array.prototype.filter.call(document.querySelectorAll('.sel-fila'), function (c) { return c.checked && !c.disabled; }); }
  function actualizarSeleccion() {
    var habilitadas = Array.prototype.filter.call(document.querySelectorAll('.sel-fila'), function (c) { return !c.disabled; }), m = marcadas().length;
    nSel.textContent = m; btnSel.disabled = m === 0;
    if (selTodos) { selTodos.checked = m > 0 && m === habilitadas.length; selTodos.indeterminate = m > 0 && m < habilitadas.length; selTodos.disabled = habilitadas.length === 0; }
  }
  document.querySelectorAll('.sel-fila').forEach(function (c) { c.addEventListener('change', actualizarSeleccion); });
  if (selTodos) {
    selTodos.addEventListener('change', function () {
      document.querySelectorAll('.sel-fila').forEach(function (c) { if (!c.disabled) { c.checked = selTodos.checked; } });
      actualizarSeleccion();
    });
  }
  btnSel.addEventListener('click', function () {
    var filas = marcadas().map(function (c) { return c.closest('tr'); });
    if (!filas.length || !confirm('¿Generar las constancias de ' + filas.length + ' registro(s) seleccionado(s)? Quedan en la cola de firmas.')) { return; }
    btnSel.disabled = true;
    var ok = 0, mal = 0;
    filas.reduce(function (p, tr) {
      return p.then(function () { return generarFila(tr, true).then(function (b) { if (b) { ok++; tr.querySelector('.sel-fila').checked = false; } else { mal++; } }); });
    }, Promise.resolve()).then(function () {
      actualizarSeleccion();
      alert(ok + ' registro(s) generado(s)' + (mal ? '; ' + mal + ' con error (revisa la nota de cada fila).' : '.'));
    });
  });
  actualizarSeleccion();
})();
</script>
