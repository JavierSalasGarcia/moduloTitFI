<?php
/** @var array $r @var string $consejo @var array $asuntos */
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$gob = $consejo === 'gobierno';
$terminada = !empty($r[$consejo . '_fin']);
$nombre = $gob ? 'Consejo de Gobierno' : 'Consejo Académico';
?>
<style>
.cns-ses{display:grid;grid-template-columns:230px 1fr;gap:16px;align-items:start}
.cns-ses.proy{grid-template-columns:1fr}
.cns-ses.proy .cns-lado,.cns-ses.proy .cns-solo-gestion{display:none}
.cns-ses.proy .cns-caso{font-size:1.3rem}
.cns-ses.proy .cns-doc td,.cns-ses.proy .cns-doc th{font-size:1.05rem}
.cns-solo-proy{display:none}
.cns-ses.proy .cns-solo-proy{display:inline-flex}
.cns-lado{position:sticky;top:12px;max-height:calc(100vh - 24px);overflow:auto;padding:10px}
.cns-lado h3{margin:10px 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gris-600)}
.cns-lado button{display:flex;gap:8px;align-items:center;width:100%;text-align:left;border:0;background:none;padding:6px 8px;border-radius:6px;cursor:pointer;font:inherit;font-weight:700}
.cns-lado button.act{background:var(--verde-050);color:var(--verde-900)}
.cns-pt{width:10px;height:10px;border-radius:50%;background:#cbd5e1;flex:none}
.cns-pt.si{background:#16a34a}.cns-pt.no{background:#dc2626}
.cns-caso{display:flex;flex-direction:column;gap:14px}
.cns-caso h2{margin:0;font-size:1.4em;color:var(--verde-900)}
.cns-caja{border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px 16px;background:#fff}
.cns-caja>h3{margin:0 0 6px;font-size:.8em;text-transform:uppercase;letter-spacing:.05em;color:var(--gris-600)}
.cns-decide{display:flex;flex-wrap:wrap;gap:8px}
.cns-decide label{display:flex;gap:8px;align-items:center;border:2px solid var(--gris-100);border-radius:var(--radio);padding:10px 16px;cursor:pointer;font-weight:700;margin:0;font-size:1em}
.cns-decide label:has(input:checked){border-color:var(--verde-700);background:var(--verde-050)}
.cns-decide label.neg:has(input:checked){border-color:#dc2626;background:#fdecec}
.cns-caso textarea{width:100%;min-height:150px;font:inherit;line-height:1.5}
.cns-nav{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}
.cns-estado-guardado{font-size:13px;color:var(--gris-600)}
@media (max-width:860px){.cns-ses{grid-template-columns:1fr}.cns-lado{position:static;max-height:none}}
</style>
<div class="cns-pila">
  <section class="tarjeta cns-solo-gestion">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
        <h1>Sesión del <?= e($nombre) ?></h1>
        <p class="cns-sub"><?= $gob ? 'Se proyecta el caso con el acuerdo del Consejo Académico; se redacta el acuerdo del Consejo de Gobierno.' : 'Se proyecta cada caso y se redacta el acuerdo del Consejo Académico.' ?> Todo se guarda al momento.</p>
      </div>
      <div class="cns-acciones">
        <button class="btn" type="button" id="btn-proy">🖥️ Modo proyección</button>
        <a class="btn" href="<?= e(url('consejos/reunion/' . $r['id'] . '/acta/' . $consejo)) ?>">Acta en Word</a>
      </div>
    </div>
    <?php if ($terminada): ?>
      <p class="flash flash-alerta" style="margin:10px 0 0">La sesión terminó el <?= e(date('d/m/Y \a \l\a\s H:i', strtotime((string) $r[$consejo . '_fin']))) ?> h. Para corregir un acuerdo, reábrela desde el panel de la reunión.</p>
    <?php endif; ?>
  </section>

  <?php if (!$asuntos): ?>
    <section class="tarjeta"><p class="cns-sub">Esta reunión aún no tiene casos. <a href="<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo')) ?>">Agrega el primero</a>.</p></section>
  <?php else: ?>
  <div class="cns-ses" id="ses">
    <nav class="tarjeta cns-lado" id="lado" aria-label="Casos"></nav>
    <div>
      <section class="tarjeta cns-caso" id="caso" aria-live="polite"></section>
      <div class="cns-nav" style="margin-top:12px">
        <button class="btn" type="button" id="ant">← Anterior</button>
        <span class="cns-estado-guardado" id="estado" role="status"></span>
        <button class="btn btn-mini cns-solo-proy" type="button" id="btn-salir-proy">Salir de la proyección</button>
        <button class="btn btn-primario" type="button" id="sig">Siguiente →</button>
      </div>
      <?php if (!$terminada): ?>
      <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/sesion/' . $consejo . '/terminar')) ?>" class="tarjeta cns-solo-gestion cns-acciones" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <strong>Al concluir:</strong>
        <label class="cns-ayuda" style="margin:0">Fecha de terminación <input type="date" name="fecha" value="<?= e((string) ($r[$consejo . '_fecha'] ?: date('Y-m-d'))) ?>"></label>
        <label class="cns-ayuda" style="margin:0">Hora <input type="time" name="hora" value="<?= e(date('H:i')) ?>" required></label>
        <button class="btn btn-primario" type="submit">Terminar la sesión</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(() => {
  const DATOS = <?= json_encode($asuntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  if (!DATOS.length) return;
  const GOB = <?= $gob ? 'true' : 'false' ?>, BLOQUEADA = <?= $terminada ? 'true' : 'false' ?>;
  const URL_GUARDAR = id => '<?= e(url('consejos/asunto')) ?>/' + id + '/acuerdo';
  const CSRF = '<?= e(Auth::csrfToken()) ?>';
  const GRUPOS = {profesor: 'Casos de profesores', alumno: 'Casos de alumnos', general: 'Asuntos generales'};
  const PLANTILLAS = GOB
    ? {aprueba: 'Se aprueba el Acuerdo del H. Consejo Académico.', no_aprueba: 'No se aprueba el aval del H. Consejo Académico en virtud de que '}
    : {avala: 'El H. Consejo Académico avala ', no_avala: 'El H. Consejo Académico no avala la solicitud en virtud de que '};
  const OPCIONES = GOB
    ? [['aprueba', 'Se aprueba el acuerdo del Consejo Académico', ''], ['no_aprueba', 'No se aprueba', 'neg']]
    : [['avala', 'Se avala la solicitud', ''], ['no_avala', 'No se avala', 'neg']];
  const campoDecision = GOB ? 'aprob' : 'aval', campoAcuerdo = GOB ? 'acuerdoGobierno' : 'acuerdoAcademico';
  const lado = document.getElementById('lado'), caso = document.getElementById('caso'), estado = document.getElementById('estado');
  let i = 0, temporizador = null, pendiente = false, guardando = Promise.resolve();
  const h = (tag, attrs = {}, ...hijos) => { const e = document.createElement(tag); Object.entries(attrs).forEach(([k, v]) => k === 'class' ? e.className = v : e.setAttribute(k, v)); hijos.forEach(x => e.append(x)); return e; };

  function pintarLado() {
    lado.innerHTML = ''; let g = '';
    DATOS.forEach((a, k) => {
      if (a.grupo !== g) { g = a.grupo; lado.append(h('h3', {}, GRUPOS[g])); }
      const dec = a[campoDecision], b = h('button', {type: 'button', class: k === i ? 'act' : ''}, h('span', {class: 'cns-pt ' + (dec === 'avala' || dec === 'aprueba' ? 'si' : (dec === 'no_avala' || dec === 'no_aprueba' ? 'no' : ''))}), a.clave);
      b.addEventListener('click', () => ir(k)); lado.append(b);
    });
  }

  function pintarCaso() {
    const a = DATOS[i]; caso.innerHTML = '';
    caso.append(h('h2', {}, a.clave + ' · ' + ({profesor: 'Caso de profesor', alumno: 'Caso de alumno', general: 'Asunto general'})[a.grupo]));
    const sol = h('div', {class: 'cns-caja'}, h('h3', {}, 'Solicitud')); const d1 = h('div', {class: 'cns-doc'}); d1.innerHTML = a.solicitud; sol.append(d1); caso.append(sol);
    if (a.antecedentes) { const an = h('div', {class: 'cns-caja'}, h('h3', {}, 'Documentos y/o antecedentes')); const d2 = h('div', {class: 'cns-doc'}); d2.innerHTML = a.antecedentes; an.append(d2); caso.append(an); }
    if (a.observacion) { const ob = h('div', {class: 'cns-caja cns-solo-gestion', style: 'background:#fffbeb'}, h('h3', {}, 'Observación de la Subdirección (no se proyecta)')); ob.append(h('div', {style: 'white-space:pre-wrap'}, a.observacion)); caso.append(ob); }
    if (GOB) {
      const ac = h('div', {class: 'cns-caja'}, h('h3', {}, 'Acuerdo del H. Consejo Académico'));
      const et = {avala: ['Avalado', 'ok'], no_avala: ['No avalado', 'no'], pendiente: ['Sin decisión', '']}[a.aval];
      ac.append(h('p', {style: 'margin:0 0 6px'}, h('span', {class: 'cns-etq ' + et[1]}, et[0])));
      ac.append(h('div', {style: 'white-space:pre-wrap'}, a.acuerdoAcademico || '(sin acuerdo capturado)')); caso.append(ac);
    }
    const mio = h('div', {class: 'cns-caja'}, h('h3', {}, GOB ? 'Acuerdo del H. Consejo de Gobierno' : 'Acuerdo del H. Consejo Académico'));
    const dec = h('div', {class: 'cns-decide', role: 'radiogroup'});
    OPCIONES.forEach(([v, txt, cls]) => {
      const r = h('input', {type: 'radio', name: 'decision', value: v}); r.checked = a[campoDecision] === v; r.disabled = BLOQUEADA;
      r.addEventListener('change', () => { a[campoDecision] = v; ponerPlantilla(a, v); programar(true); pintarLado(); });
      dec.append(h('label', {class: cls}, r, txt));
    });
    mio.append(dec);
    const ta = h('textarea', {id: 'acuerdo', placeholder: 'Redacta el acuerdo…', 'aria-label': 'Acuerdo'}); ta.value = a[campoAcuerdo] || ''; ta.disabled = BLOQUEADA;
    ta.addEventListener('input', () => { a[campoAcuerdo] = ta.value; programar(false); });
    mio.append(h('div', {style: 'margin-top:10px'}, ta));
    if (GOB) {
      const nota = h('textarea', {id: 'nota', placeholder: 'Nota adicional (opcional): se agrega al acuerdo en el acta y en el dictamen.', style: 'min-height:80px', 'aria-label': 'Nota adicional'}); nota.value = a.nota || ''; nota.disabled = BLOQUEADA;
      nota.addEventListener('input', () => { a.nota = nota.value; programar(false); });
      mio.append(h('div', {style: 'margin-top:8px'}, h('small', {class: 'cns-ayuda'}, 'Nota adicional'), nota));
    }
    caso.append(mio);
    document.getElementById('ant').disabled = i === 0; document.getElementById('sig').disabled = i === DATOS.length - 1;
    estado.textContent = '';
    if (!BLOQUEADA) setTimeout(() => ta.focus({preventScroll: true}), 0);
  }

  function ponerPlantilla(a, v) {
    const actual = (a[campoAcuerdo] || '').trim(), conocidas = Object.values(PLANTILLAS).map(s => s.trim());
    if (actual === '' || conocidas.includes(actual)) {
      a[campoAcuerdo] = PLANTILLAS[v]; const ta = document.getElementById('acuerdo'); ta.value = a[campoAcuerdo]; ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
    }
  }

  function programar(ya) { pendiente = true; estado.textContent = 'Sin guardar…'; clearTimeout(temporizador); temporizador = setTimeout(guardar, ya ? 50 : 700); }

  function guardar() {
    if (!pendiente || BLOQUEADA) return guardando; pendiente = false; clearTimeout(temporizador);
    const a = DATOS[i], f = new FormData();
    f.append('_csrf', CSRF); f.append('consejo', GOB ? 'gobierno' : 'academico'); f.append('decision', a[campoDecision]); f.append('acuerdo', a[campoAcuerdo] || ''); if (GOB) f.append('nota', a.nota || '');
    estado.textContent = 'Guardando…';
    guardando = fetch(URL_GUARDAR(a.id), {method: 'POST', body: f, headers: {'Accept': 'application/json'}})
      .then(r => r.json().then(d => ({ok: r.ok && d.ok, d})))
      .then(({ok, d}) => { estado.textContent = ok ? 'Guardado ✓ ' + d.hora : '⚠️ ' + (d.error || 'No se pudo guardar'); if (!ok) pendiente = true; })
      .catch(() => { estado.textContent = '⚠️ Sin conexión: se reintentará'; pendiente = true; setTimeout(guardar, 4000); });
    return guardando;
  }

  async function ir(k) { if (k < 0 || k >= DATOS.length || k === i) return; await guardar(); i = k; pintarLado(); pintarCaso(); window.scrollTo({top: 0}); }
  document.getElementById('ant').addEventListener('click', () => ir(i - 1));
  document.getElementById('sig').addEventListener('click', () => ir(i + 1));
  document.addEventListener('keydown', e => { if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); ir(i + 1); } if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); ir(i - 1); } });
  window.addEventListener('beforeunload', e => { if (pendiente) { e.preventDefault(); e.returnValue = ''; } });
  const ses = document.getElementById('ses');
  document.getElementById('btn-proy').addEventListener('click', () => {
    ses.classList.toggle('proy');
    if (ses.classList.contains('proy') && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
    else if (document.fullscreenElement) document.exitFullscreen();
  });
  document.getElementById('btn-salir-proy').addEventListener('click', () => { ses.classList.remove('proy'); if (document.fullscreenElement) document.exitFullscreen(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && ses.classList.contains('proy')) ses.classList.remove('proy'); });
  // «Proyectar» desde la reunión abre la sesión ya en modo proyección (la pantalla completa la pide el botón: el navegador exige un clic).
  if (new URLSearchParams(location.search).get('proyectar') === '1') ses.classList.add('proy');
  pintarLado(); pintarCaso();
})();
</script>
