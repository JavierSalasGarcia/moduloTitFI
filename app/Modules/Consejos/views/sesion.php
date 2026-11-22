<?php
/** @var array $r @var string $consejo @var array $asuntos */
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$gob = $consejo === 'gobierno';
$terminada = !empty($r[$consejo . '_fin']);
$nombre = $gob ? 'Consejo de Gobierno' : 'Consejo Académico';
$desde = 'sesion:' . $consejo;
?>
<style>
.cns-ses{display:grid;grid-template-columns:230px 1fr;gap:16px;align-items:start}
.cns-lado{position:sticky;top:12px;max-height:calc(100vh - 24px);overflow:auto;padding:10px}
.cns-lado h3{margin:10px 0 4px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--gris-600)}
.cns-lado button{display:flex;gap:8px;align-items:center;width:100%;text-align:left;border:0;background:none;padding:6px 8px;border-radius:6px;cursor:pointer;font:inherit;font-weight:700}
.cns-lado button.act{background:var(--verde-050);color:var(--verde-900)}
.cns-lado .cns-nuevos{margin-top:10px;border-top:1px solid var(--gris-100);padding-top:8px;font-size:12px;color:var(--gris-600)}
.cns-lado .cns-nuevos summary{cursor:pointer}
.cns-lado .cns-nuevos div{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.cns-pt{width:10px;height:10px;border-radius:50%;background:#cbd5e1;flex:none}
.cns-pt.si{background:#16a34a}.cns-pt.no{background:#dc2626}
.cns-caso{display:flex;flex-direction:column;gap:14px}
.cns-caso-cab{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center}
.cns-caso h2{margin:0;font-size:1.4em;color:var(--verde-900)}
.cns-cols{display:grid;grid-template-columns:1fr;gap:14px}
.cns-col{display:flex;flex-direction:column;gap:12px;min-width:0}
@media (min-width:1200px){.cns-ses:not(.proy) .cns-cols{grid-template-columns:1.2fr 1fr}}
.cns-caja{border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px 16px;background:#fff}
.cns-caja>h3{margin:0 0 6px;font-size:.8em;text-transform:uppercase;letter-spacing:.05em;color:var(--gris-600)}
.cns-decide{display:flex;flex-wrap:wrap;gap:8px}
.cns-decide label{display:flex;gap:8px;align-items:center;border:2px solid var(--gris-100);border-radius:var(--radio);padding:10px 16px;cursor:pointer;font-weight:700;margin:0;font-size:1em}
.cns-decide label:has(input:checked){border-color:var(--verde-700);background:var(--verde-050)}
.cns-decide label.neg:has(input:checked){border-color:#dc2626;background:#fdecec}
.cns-caso textarea{width:100%;min-height:150px;font:inherit;line-height:1.5}
.cns-frases{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.cns-nav{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}
.cns-nav .cns-bot{display:flex;gap:8px;align-items:center}
.cns-estado-guardado{font-size:13px;color:var(--gris-600)}
.cns-estado-guardado.sucio{color:#b45309;font-weight:700}
.cns-solo-proy{display:none}
@media (max-width:860px){.cns-ses{grid-template-columns:1fr}.cns-lado{position:static;max-height:none}}

/* Modo proyección: la pantalla arranca directamente con el caso, ocupa todo el alto, es horizontal (dos columnas) y el tamaño del
   texto se ajusta a la pantalla (--fs lo calcula el script para que el caso quepa sin desplazarse). */
.cns-ses.proy{position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;gap:1vh;padding:1.4vh 1.6vw;background:#fff;overflow:hidden;font-size:var(--fs,18px);line-height:1.35}
.cns-ses.proy .cns-lado,.cns-ses.proy .cns-solo-gestion{display:none}
.cns-ses.proy .cns-solo-proy{display:inline-flex}
.cns-ses.proy .cns-main{display:flex;flex-direction:column;flex:1;min-height:0;gap:1vh}
.cns-ses.proy #caso{flex:1;min-height:0;gap:1vh;border:0;box-shadow:none;padding:0;background:transparent}
.cns-ses.proy .cns-caso h2{font-size:1.3em}
.cns-ses.proy .cns-cols{flex:1;min-height:0;grid-template-columns:1fr 1.05fr;gap:1.4vw}
.cns-ses.proy .cns-col{min-height:0;overflow:auto}
.cns-ses.proy .cns-col-der .cns-caja.crece{flex:1;min-height:0;display:flex;flex-direction:column}
.cns-ses.proy .cns-caja{padding:.6em .9em}
.cns-ses.proy .cns-caja.sin-titulo>h3{display:none}
.cns-ses.proy .cns-caja>h3{font-size:.62em}
.cns-ses.proy .cns-doc,.cns-ses.proy .cns-doc p,.cns-ses.proy .cns-doc li,.cns-ses.proy .cns-doc td,.cns-ses.proy .cns-doc th{font-size:1em}
.cns-ses.proy .cns-doc table{font-size:.92em}
.cns-ses.proy textarea{flex:1;min-height:5em;font-size:1em;resize:none}
.cns-ses.proy #nota{flex:none;min-height:0;height:3.2em}
.cns-ses.proy .cns-caja.ref{flex:none;max-height:30%;overflow:auto}
.cns-ses.proy .cns-decide label{padding:.45em .9em;font-size:1em}
.cns-ses.proy .cns-nav{flex:none}
.cns-ses.proy .cns-nav .btn{min-height:0;padding:.35em .9em;font-size:.7em}
</style>
<div class="cns-pila">
  <section class="tarjeta cns-solo-gestion" id="cab-gestion">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
        <h1>Sesión del <?= e($nombre) ?></h1>
        <p class="cns-sub"><?= $gob ? 'Son los mismos casos que vio el Consejo Académico (se crearon al preparar la reunión): se proyecta cada uno con su acuerdo y se redacta el del Consejo de Gobierno.' : 'Se proyecta cada caso y se redacta el acuerdo del Consejo Académico.' ?> Al elegir la decisión el acuerdo se redacta solo; edítalo y pulsa <strong>Guardar</strong> (o <strong>Deshacer</strong> para volver a lo guardado).</p>
      </div>
      <div class="cns-acciones">
        <button class="btn btn-primario" type="button" id="btn-proy">🖥️ Modo proyección</button>
        <a class="btn" href="<?= e(url('consejos/reunion/' . $r['id'] . '/acta/' . $consejo)) ?>">Acta en Word</a>
      </div>
    </div>
    <?php if ($terminada): ?>
      <p class="flash flash-alerta" style="margin:10px 0 0">La sesión terminó el <?= e(date('d/m/Y \a \l\a\s H:i', strtotime((string) $r[$consejo . '_fin']))) ?> h. Para corregir un acuerdo, reábrela desde el panel de la reunión.</p>
    <?php endif; ?>
  </section>

  <?php if (!$asuntos): ?>
    <section class="tarjeta"><p class="cns-sub">Esta reunión aún no tiene casos. Se crean al preparar la reunión: <a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">vuelve a la reunión y agrégalos</a>.</p></section>
  <?php else: ?>
  <div class="cns-ses" id="ses">
    <nav class="tarjeta cns-lado" id="lado" aria-label="Casos"></nav>
    <div class="cns-main">
      <section class="tarjeta cns-caso" id="caso" aria-live="polite"></section>
      <div class="cns-nav" id="nav">
        <button class="btn" type="button" id="ant">← Anterior</button>
        <span class="cns-estado-guardado" id="estado" role="status"></span>
        <span class="cns-bot">
          <button class="btn" type="button" id="deshacer" title="Vuelve a lo último que se guardó en este caso">↶ Deshacer</button>
          <button class="btn btn-primario" type="button" id="guardar" title="Guardar (Ctrl+S)">💾 Guardar</button>
          <button class="btn btn-mini cns-solo-proy" type="button" id="btn-salir-proy">Salir</button>
        </span>
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
  const REUNION = <?= (int) $r['id'] ?>, DESDE = '<?= e($desde) ?>';
  const URL_GUARDAR = id => '<?= e(url('consejos/asunto')) ?>/' + id + '/acuerdo';
  const URL_NUEVO = g => '<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo')) ?>?grupo=' + g + '&desde=' + encodeURIComponent(DESDE);
  const URL_EDITAR = id => '<?= e(url('consejos/asunto')) ?>/' + id + '/editar?desde=' + encodeURIComponent(DESDE);
  const CSRF = '<?= e(Auth::csrfToken()) ?>';
  const GRUPOS = {profesor: 'Casos de profesores', alumno: 'Casos de alumnos', general: 'Asuntos generales'};
  const TIPOS = {profesor: 'Caso de profesor', alumno: 'Caso de alumno', general: 'Asunto general'};

  /* ---- Cómo se redactan los acuerdos. La fórmula del Consejo Académico es «El H. Consejo Académico avala la solicitud de …» y se repite
     parte de lo que dice el caso, sin el «<Persona>, <cargo>, solicita» del principio: materia(a) toma lo que se solicita (hasta el fin de esa oración).
     Consejo de Gobierno: «Se aprueba el Acuerdo del H. Consejo Académico.» (+ lo que se precise) / «No se aprueba el aval del H. Consejo Académico en virtud de que …» */
  const ABREV = new Set(['dr', 'dra', 'mtro', 'mtra', 'ing', 'lic', 'prof', 'm', 'c', 'sr', 'sra', 'núm', 'num', 'art', 'etc', 'depto', 'dpto', 'en', 'ca']);
  function materia(a) {
    const d = document.createElement('div'); d.innerHTML = a.solicitud || ''; d.querySelectorAll('table').forEach(t => t.remove());
    const t = (d.textContent || '').replace(/\s+/g, ' ').trim();
    const m = t.match(/\b(?:solicitan?|presenta|informa(?:\s+sobre)?|plantea|propone)\b[:,]?\s+(.+)$/iu);
    if (!m) return '';
    let r = m[1];
    r = r.replace(/^(?:a|ante)\s+los\s+h\.?\s*h\.?\s*consejos\s+acad[eé]mico\s+y\s+de\s+gobierno\s*,?\s*/iu, '');   // «solicita a los H.H. Consejos Académico y de Gobierno,»
    r = r.replace(/\s+(?:de|a)\s+los\s+h\.?\s*h\.?\s*consejos\s+acad[eé]mico\s+y\s+de\s+gobierno\b/iu, '');               // «el aval de los H.H. Consejos … del dictamen»
    r = r.replace(/^se\s+avalen?\s+/iu, '');
    r = r.replace(/^(?:la|su|el)\s+(?=(?:autorizaci[oó]n|aprobaci[oó]n|aval|validaci[oó]n|designaci[oó]n|revalidaci[oó]n|reconocimiento))/iu, '');   // «la autorización para…» → «autorización para…»
    const re = /([^\s.]+)\.\s+(?=\S)/g; let x;
    while ((x = re.exec(r))) {            // se queda con la primera oración (sin cortar en «Dr.», «Ing.», «M. en I.»…)
      const w = x[1].toLowerCase();
      const abreviatura = /^[a-záéíóúñ]+$/.test(w) && (w.length < 4 || ABREV.has(w));   // «Dr.», «Ing.», «M. en I.»…; «2027-B.» o «Facultad.» sí cierran la oración
      if (!abreviatura) { r = r.slice(0, x.index + x[1].length); break; }
    }
    return r.replace(/[\s.,;:]+$/, '');
  }
  const FRASES = GOB
    ? {aprueba: () => 'Se aprueba el Acuerdo del H. Consejo Académico.', no_aprueba: () => 'No se aprueba el aval del H. Consejo Académico en virtud de que '}
    : {avala: a => { const m = materia(a); return 'El H. Consejo Académico avala la solicitud de ' + (m ? m + '.' : ''); },
       no_avala: a => { const m = materia(a); return 'El H. Consejo Académico no avala la solicitud de ' + (m ? m + ' ' : '') + 'en virtud de que '; }};
  const frase = (a, dec) => FRASES[dec] ? FRASES[dec](a) : '';
  /* Al elegir la decisión el acuerdo cambia a la frase que le corresponde. Lo que se haya escrito para la decisión anterior no se pierde:
     se recuerda por decisión, así que al volver a elegirla reaparece tal como estaba (y «Deshacer» regresa a lo último guardado). */
  function cambiarFrase(a, dec) {
    a._mem = a._mem || {};
    const previa = a[CD] || 'pendiente', actual = (a[CA] || '');
    if (actual.trim() !== '' && actual.trim() !== frase(a, previa).trim()) a._mem[previa] = actual;
    return a._mem[dec] !== undefined ? a._mem[dec] : frase(a, dec);
  }
  const OPCIONES = GOB
    ? [['aprueba', 'Se aprueba el acuerdo del Consejo Académico', ''], ['no_aprueba', 'No se aprueba', 'neg']]
    : [['avala', 'Se avala la solicitud', ''], ['no_avala', 'No se avala', 'neg']];
  const RAPIDAS = GOB
    ? [['Se da por enterado el H. Consejo de Gobierno.', null], ['No es competencia del H. Consejo de Gobierno ', null]]
    : [['Se da por enterado el H. Consejo Académico.', null]];
  const CD = GOB ? 'aprob' : 'aval', CA = GOB ? 'acuerdoGobierno' : 'acuerdoAcademico';

  const lado = document.getElementById('lado'), caso = document.getElementById('caso'), estado = document.getElementById('estado');
  const bGuardar = document.getElementById('guardar'), bDeshacer = document.getElementById('deshacer'), ses = document.getElementById('ses');
  let i = 0, guardando = false;
  DATOS.forEach(a => { a._b = {d: a[CD], a: a[CA] || '', n: a.nota || ''}; });
  const sucio = a => a[CD] !== a._b.d || (a[CA] || '') !== a._b.a || (GOB && (a.nota || '') !== a._b.n);
  const h = (tag, attrs = {}, ...hijos) => { const e = document.createElement(tag); Object.entries(attrs).forEach(([k, v]) => k === 'class' ? e.className = v : e.setAttribute(k, v)); hijos.forEach(x => e.append(x)); return e; };

  function refrescar(mensaje) {
    const a = DATOS[i], s = sucio(a);
    bGuardar.disabled = BLOQUEADA || !s || guardando; bDeshacer.disabled = BLOQUEADA || !s || guardando;
    estado.className = 'cns-estado-guardado' + (s ? ' sucio' : '');
    if (mensaje !== undefined) estado.textContent = mensaje; else if (s) estado.textContent = 'Cambios sin guardar';
    else if (!estado.textContent.startsWith('Guardado')) estado.textContent = '';
  }

  function pintarLado() {
    lado.innerHTML = ''; let g = '';
    DATOS.forEach((a, k) => {
      if (a.grupo !== g) { g = a.grupo; lado.append(h('h3', {}, GRUPOS[g])); }
      const dec = a[CD], b = h('button', {type: 'button', class: k === i ? 'act' : ''}, h('span', {class: 'cns-pt ' + (dec === 'avala' || dec === 'aprueba' ? 'si' : (dec === 'no_avala' || dec === 'no_aprueba' ? 'no' : ''))}), a.clave + (a.origen === 'gobierno' ? ' · nuevo' : ''));
      b.addEventListener('click', () => ir(k)); lado.append(b);
    });
    if (!BLOQUEADA) {
      // Los casos se crean al preparar la reunión; agregar uno durante la sesión es la excepción, por eso va recogido.
      const nu = h('details', {class: 'cns-nuevos'}, h('summary', {}, 'Caso excepcional…')), en = h('div');
      [['profesor', '+ Profesor'], ['alumno', '+ Alumno'], ['general', '+ General']].forEach(([g2, t]) => en.append(h('a', {class: 'btn btn-mini', href: URL_NUEVO(g2)}, t)));
      nu.append(en); lado.append(nu);
    }
  }

  function pintarCaso() {
    const a = DATOS[i]; caso.innerHTML = '';
    const cab = h('div', {class: 'cns-caso-cab'}, h('h2', {}, a.clave + ' · ' + TIPOS[a.grupo]));
    if (a.origen === 'gobierno') cab.append(h('span', {class: 'cns-etq', title: 'Se presentó directamente al Consejo de Gobierno'}, 'Nuevo en el Consejo de Gobierno'));
    if (!BLOQUEADA) cab.append(h('a', {class: 'btn btn-mini cns-solo-gestion', href: URL_EDITAR(a.id)}, 'Editar el caso'));
    caso.append(cab);

    const izq = h('div', {class: 'cns-col cns-col-izq'}), der = h('div', {class: 'cns-col cns-col-der'});
    const sol = h('div', {class: 'cns-caja sin-titulo'}, h('h3', {}, 'Solicitud')); const d1 = h('div', {class: 'cns-doc'}); d1.innerHTML = a.solicitud; sol.append(d1); izq.append(sol);
    if (a.antecedentes) { const an = h('div', {class: 'cns-caja sin-titulo'}, h('h3', {}, 'Documentos y/o antecedentes')); const d2 = h('div', {class: 'cns-doc'}); d2.innerHTML = a.antecedentes; an.append(d2); izq.append(an); }
    if (a.observacion) { const ob = h('div', {class: 'cns-caja cns-solo-gestion', style: 'background:#fffbeb'}, h('h3', {}, 'Observación de la Subdirección (no se proyecta)')); ob.append(h('div', {style: 'white-space:pre-wrap'}, a.observacion)); izq.append(ob); }

    if (GOB && a.origen !== 'gobierno') {
      const ac = h('div', {class: 'cns-caja ref'}, h('h3', {}, 'Acuerdo del H. Consejo Académico'));
      const et = {avala: ['Avalado', 'ok'], no_avala: ['No avalado', 'no'], pendiente: ['Sin decisión', '']}[a.aval];
      ac.append(h('p', {style: 'margin:0 0 6px'}, h('span', {class: 'cns-etq ' + et[1]}, et[0])));
      ac.append(h('div', {style: 'white-space:pre-wrap'}, a.acuerdoAcademico || '(sin acuerdo capturado)')); der.append(ac);
    }
    const mio = h('div', {class: 'cns-caja crece'}, h('h3', {}, GOB ? 'Acuerdo del H. Consejo de Gobierno' : 'Acuerdo del H. Consejo Académico'));
    const dec = h('div', {class: 'cns-decide', role: 'radiogroup', 'aria-label': 'Decisión'});
    const ta = h('textarea', {id: 'acuerdo', placeholder: 'Redacta el acuerdo…', 'aria-label': 'Acuerdo'}); ta.value = a[CA] || ''; ta.disabled = BLOQUEADA;
    OPCIONES.forEach(([v, txt, cls]) => {
      const r = h('input', {type: 'radio', name: 'decision', value: v}); r.checked = a[CD] === v; r.disabled = BLOQUEADA;
      r.addEventListener('change', () => {
        a[CA] = ta.value; const nuevo = cambiarFrase(a, v); a[CD] = v; a[CA] = nuevo; ta.value = nuevo;
        ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
        pintarLado(); refrescar();
      });
      dec.append(h('label', {class: cls}, r, txt));
    });
    mio.append(dec);
    ta.addEventListener('input', () => { a[CA] = ta.value; refrescar(); });
    mio.append(h('div', {style: 'margin-top:10px;display:flex;flex-direction:column;flex:1;min-height:0'}, ta));
    if (!BLOQUEADA) {
      const fr = h('div', {class: 'cns-frases cns-solo-gestion'}, h('small', {class: 'cns-ayuda'}, 'Frases frecuentes:'));
      RAPIDAS.forEach(([txt, d]) => {
        const b = h('button', {type: 'button', class: 'btn btn-mini'}, txt.trim());
        b.addEventListener('click', () => { a[CA] = txt; ta.value = txt; if (d) { a[CD] = d; pintarCaso(); pintarLado(); } ta.focus(); ta.setSelectionRange(txt.length, txt.length); refrescar(); });
        fr.append(b);
      });
      mio.append(fr);
    }
    if (GOB) {
      const nota = h('textarea', {id: 'nota', placeholder: 'Nota adicional (opcional): se agrega al acuerdo en el acta y en el dictamen.', style: 'min-height:4.5em', 'aria-label': 'Nota adicional'}); nota.value = a.nota || ''; nota.disabled = BLOQUEADA;
      nota.addEventListener('input', () => { a.nota = nota.value; refrescar(); });
      mio.append(h('div', {style: 'margin-top:8px;flex:none'}, h('small', {class: 'cns-ayuda'}, 'Nota adicional'), nota));
    }
    der.append(mio);
    caso.append(h('div', {class: 'cns-cols'}, izq, der));

    document.getElementById('ant').disabled = i === 0; document.getElementById('sig').disabled = i === DATOS.length - 1;
    refrescar('');
    if (!BLOQUEADA && !ses.classList.contains('proy')) setTimeout(() => ta.focus({preventScroll: true}), 0);
    requestAnimationFrame(ajustar);
  }

  /* Tamaño del texto en proyección: parte de un tamaño proporcional a la pantalla y lo reduce hasta que el caso quepa sin desplazarse. */
  function ajustar() {
    if (!ses.classList.contains('proy')) { ses.style.removeProperty('--fs'); return; }
    let fs = Math.max(15, Math.min(36, Math.min(innerWidth / 56, innerHeight / 30)));
    ses.style.setProperty('--fs', fs + 'px');
    const cols = [...ses.querySelectorAll('.cns-col')];
    for (let k = 0; k < 40 && fs > 12 && cols.some(c => c.scrollHeight > c.clientHeight + 2); k++) { fs *= 0.94; ses.style.setProperty('--fs', fs + 'px'); }
  }

  function guardar() {
    const a = DATOS[i];
    if (BLOQUEADA || !sucio(a) || guardando) return Promise.resolve(!sucio(a));
    const f = new FormData();
    f.append('_csrf', CSRF); f.append('consejo', GOB ? 'gobierno' : 'academico'); f.append('decision', a[CD]); f.append('acuerdo', a[CA] || ''); if (GOB) f.append('nota', a.nota || '');
    guardando = true; refrescar('Guardando…');
    return fetch(URL_GUARDAR(a.id), {method: 'POST', body: f, headers: {'Accept': 'application/json'}})
      .then(r => r.json().then(d => ({ok: r.ok && d.ok, d})))
      .then(({ok, d}) => { guardando = false; if (ok) { a._b = {d: a[CD], a: a[CA] || '', n: a.nota || ''}; refrescar('Guardado ✓ ' + d.hora); } else refrescar('⚠️ ' + (d.error || 'No se pudo guardar')); return ok; })
      .catch(() => { guardando = false; refrescar('⚠️ Sin conexión: no se guardó'); return false; });
  }

  function deshacer() {
    const a = DATOS[i]; if (BLOQUEADA || !sucio(a)) return;
    a[CD] = a._b.d; a[CA] = a._b.a; a._mem = null; if (GOB) a.nota = a._b.n;
    pintarLado(); pintarCaso(); refrescar('Se volvió a lo último guardado.');
  }

  async function ir(k) { if (k < 0 || k >= DATOS.length || k === i) return; if (!(await guardar())) return; i = k; pintarLado(); pintarCaso(); window.scrollTo({top: 0}); }
  document.getElementById('ant').addEventListener('click', () => ir(i - 1));
  document.getElementById('sig').addEventListener('click', () => ir(i + 1));
  bGuardar.addEventListener('click', guardar); bDeshacer.addEventListener('click', deshacer);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); guardar(); return; }
    if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); ir(i + 1); }
    if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); ir(i - 1); }
    if (ses.classList.contains('proy') && !/^(TEXTAREA|INPUT)$/.test(document.activeElement?.tagName || '')) {
      if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); ir(i + 1); }
      if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); ir(i - 1); }
    }
  });
  window.addEventListener('beforeunload', e => { if (sucio(DATOS[i])) { e.preventDefault(); e.returnValue = ''; } });
  window.addEventListener('resize', ajustar);

  const proyectar = on => {
    ses.classList.toggle('proy', on); ajustar();
    if (on && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
    else if (!on && document.fullscreenElement) document.exitFullscreen();
  };
  document.getElementById('btn-proy').addEventListener('click', () => proyectar(!ses.classList.contains('proy')));
  document.getElementById('btn-salir-proy').addEventListener('click', () => proyectar(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && ses.classList.contains('proy')) proyectar(false); });

  // Caso a mostrar al regresar de agregar o editar uno (?caso=ID) y «Proyectar» desde la reunión (?proyectar=1: la pantalla completa la pide el botón).
  const p = new URLSearchParams(location.search), k0 = DATOS.findIndex(a => String(a.id) === p.get('caso'));
  if (k0 >= 0) i = k0;
  if (p.get('proyectar') === '1') ses.classList.add('proy');
  pintarLado(); pintarCaso();
})();
</script>
