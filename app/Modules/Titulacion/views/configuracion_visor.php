<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$datos = [
    'clave' => $clave,
    'spec' => $spec,
    'variables' => $variables,
    'urlPlantilla' => url('titulacion/configuracion/formatos/' . $clave . '/plantilla'),
    'urlGuardar' => url('titulacion/configuracion/formatos/' . $clave . '/spec'),
    'urlProbar' => url('titulacion/configuracion/formatos/' . $clave . '/probar'),
    'urlWorker' => url('assets/vendor/pdfjs/pdf.worker.min.js'),
    'csrf' => Auth::csrfToken(),
];
?>
<style>
.vz{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:14px;align-items:start}
.vz-barra{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px}
.vz-barra select,.vz-barra input{max-width:230px}
.vz-lienzo{overflow:auto;border:1px solid var(--gris-100);background:#e8e8e4;padding:12px;max-height:80vh;border-radius:8px}
.vz-hoja{position:relative;margin:0 auto;box-shadow:0 1px 6px rgba(0,0,0,.25);background:#fff;touch-action:none}
.vz-hoja canvas{display:block}
.vz-capa{position:absolute;inset:0}
.vz-caja{position:absolute;border:1.5px solid #1D4ED8;background:rgba(59,130,246,.16);font-size:10px;line-height:1.1;color:#1e3a8a;overflow:hidden;cursor:move;user-select:none}
.vz-caja span{pointer-events:none;padding:0 2px;white-space:nowrap}
.vz-caja.sel{border-color:#B45309;background:rgba(245,158,11,.25);z-index:5}
.vz-caja.sub{border-color:#B91C1C;background:rgba(239,68,68,.15);color:#7f1d1d}
.vz-asa{position:absolute;right:-1px;bottom:-1px;width:10px;height:10px;background:#1D4ED8;cursor:nwse-resize}
.vz-marca{position:absolute;width:16px;height:16px;margin:-8px 0 0 -8px;border:2px solid #15803D;border-radius:50%;background:rgba(34,197,94,.25);cursor:move;font-size:9px;display:grid;place-items:center;color:#14532d;user-select:none}
.vz-marca.sel{border-color:#B45309;background:rgba(245,158,11,.35);z-index:5}
.vz-lado{display:flex;flex-direction:column;gap:12px;max-height:84vh;overflow:auto}
.vz-lista button{display:block;width:100%;text-align:left;border:0;background:none;padding:6px 8px;border-radius:6px;cursor:pointer;font-size:13px}
.vz-lista button:hover{background:var(--verde-050)}
.vz-lista button.sel{background:var(--oro-050);font-weight:700}
.vz-lista small{color:var(--gris-600)}
.vz-prop{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.vz-prop label{font-size:12px}
.vz-prop input,.vz-prop select{width:100%}
.vz-msg{font-size:13px;padding:6px 10px;border-radius:6px}
.vz-msg.ok{background:#F0FDF4;color:var(--ok)}.vz-msg.mal{background:#FEF2F2;color:var(--error)}
@media(max-width:980px){.vz{grid-template-columns:1fr}.vz-lado{max-height:none}}
</style>

<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/configuracion') ?>">← Formatos</a></p>
    <p class="texto-secundario"><strong><?= e($f['nombre']) ?></strong> · arrastra los recuadros sobre la plantilla; el borde inferior derecho cambia el tamaño. Las coordenadas están en puntos desde la esquina superior izquierda.</p>
  </div>
</div>

<div class="vz-barra">
  <button class="btn btn-primario" type="button" id="vz-guardar">Guardar coordenadas</button>
  <button class="btn" type="button" id="vz-probar">Vista previa con datos de ejemplo</button>
  <span style="flex:1"></span>
  <button class="btn btn-mini" type="button" id="vz-prev" aria-label="Página anterior">‹</button>
  <span id="vz-pag" class="texto-secundario"></span>
  <button class="btn btn-mini" type="button" id="vz-next" aria-label="Página siguiente">›</button>
  <label class="texto-secundario" style="display:flex;gap:6px;align-items:center">Zoom
    <input type="range" id="vz-zoom" min="0.8" max="2.2" step="0.1" value="1.3" style="width:110px"></label>
</div>
<div id="vz-aviso" class="vz-msg" hidden role="status"></div>

<div class="vz">
  <div class="vz-lienzo"><div class="vz-hoja" id="vz-hoja"><canvas id="vz-canvas"></canvas><div class="vz-capa" id="vz-capa"></div></div></div>
  <aside class="vz-lado">
    <section class="tarjeta">
      <h2 style="font-size:15px">Añadir campo</h2>
      <div style="display:flex;gap:6px"><select id="vz-var" aria-label="Dato a imprimir" style="flex:1"></select><button class="btn btn-mini btn-primario" type="button" id="vz-add">Añadir</button></div>
      <p class="texto-secundario" style="font-size:12px;margin:6px 0 0">Las casillas («Casilla: …») se colocan haciendo clic sobre la hoja, una por opción.</p>
      <button class="btn btn-mini" type="button" id="vz-add-sub" style="margin-top:6px">Añadir nombre del Subdirector</button>
    </section>
    <section class="tarjeta vz-lista">
      <h2 style="font-size:15px">Campos de esta página</h2>
      <div id="vz-lista"></div>
    </section>
    <section class="tarjeta" id="vz-prop-caja" hidden>
      <h2 style="font-size:15px">Propiedades</h2>
      <div id="vz-prop"></div>
    </section>
  </aside>
</div>

<script type="application/json" id="vz-datos"><?= json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= url('assets/vendor/pdfjs/pdf.min.js') ?>"></script>
<script>
(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('vz-datos').textContent);
  var spec = { campos: JSON.parse(JSON.stringify(D.spec.campos || [])), subdirector: JSON.parse(JSON.stringify(D.spec.subdirector || [])) };
  var V = D.variables, paginas = D.spec.paginas || 1, pagina = 0, escala = 1.3, sel = null, colocando = null, sucio = false, pdf = null;
  var $ = function (id) { return document.getElementById(id); };
  var capa = $('vz-capa'), canvas = $('vz-canvas'), hoja = $('vz-hoja');
  pdfjsLib.GlobalWorkerOptions.workerSrc = D.urlWorker;

  function r1(n) { return Math.round(n * 10) / 10; }
  function aviso(txt, ok) { var a = $('vz-aviso'); a.hidden = !txt; a.textContent = txt || ''; a.className = 'vz-msg ' + (ok ? 'ok' : 'mal'); }
  function marcar() { sucio = true; }
  window.addEventListener('beforeunload', function (e) { if (sucio) { e.preventDefault(); e.returnValue = ''; } });

  // ---- variables
  var sv = $('vz-var');
  Object.keys(V).forEach(function (k) { var o = document.createElement('option'); o.value = k; o.textContent = V[k].label; sv.appendChild(o); });

  // ---- página PDF
  function dibujarPagina() {
    if (!pdf) return;
    pdf.getPage(pagina + 1).then(function (pg) {
      var vp = pg.getViewport({ scale: escala });
      canvas.width = vp.width; canvas.height = vp.height;
      hoja.style.width = vp.width + 'px'; hoja.style.height = vp.height + 'px';
      return pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    }).then(pintar);
    $('vz-pag').textContent = 'Página ' + (pagina + 1) + ' de ' + paginas;
  }

  // ---- arrastre
  function arrastrar(el, obtener, mover, redimensionar) {
    el.addEventListener('pointerdown', function (ev) {
      if (ev.button !== 0) return;
      ev.preventDefault(); ev.stopPropagation();
      var esAsa = ev.target.classList.contains('vz-asa');
      seleccionar(obtener.sel);
      var x0 = ev.clientX, y0 = ev.clientY, base = obtener.get();
      el.setPointerCapture(ev.pointerId);
      function mv(e) {
        var dx = (e.clientX - x0) / escala, dy = (e.clientY - y0) / escala;
        if (esAsa && redimensionar) redimensionar(base, dx, dy); else mover(base, dx, dy);
        marcar(); pintar(true);
      }
      function up() { el.removeEventListener('pointermove', mv); el.removeEventListener('pointerup', up); pintar(); }
      el.addEventListener('pointermove', mv); el.addEventListener('pointerup', up);
    });
  }

  function caja(x, y, w, h, etiqueta, clases, handle) {
    var d = document.createElement('div');
    d.className = 'vz-caja ' + (clases || '');
    d.style.left = x * escala + 'px'; d.style.top = y * escala + 'px';
    d.style.width = w * escala + 'px'; d.style.height = h * escala + 'px';
    var s = document.createElement('span'); s.textContent = etiqueta; d.appendChild(s);
    if (handle) { var a = document.createElement('i'); a.className = 'vz-asa'; d.appendChild(a); }
    capa.appendChild(d); return d;
  }

  function pintar(sinPanel) {
    capa.innerHTML = '';
    spec.campos.forEach(function (c, i) {
      if (c.p !== pagina) return;
      var v = V[c.k] || { label: c.k };
      var esSel = sel && sel.t === 'c' && sel.i === i;
      if (c.tipo === 'texto') {
        var d = caja(c.x, c.y, c.w, c.h, v.label, esSel ? 'sel' : '', true);
        arrastrar(d, { sel: { t: 'c', i: i }, get: function () { return { x: c.x, y: c.y, w: c.w, h: c.h }; } },
          function (b, dx, dy) { c.x = r1(b.x + dx); c.y = r1(b.y + dy); },
          function (b, dx, dy) { c.w = Math.max(6, r1(b.w + dx)); c.h = Math.max(6, r1(b.h + dy)); });
      } else if (c.tipo === 'parrafo') {
        c.cajas.forEach(function (b, j) {
          var d = caja(b[0], b[1], b[2], b[3], v.label + ' · renglón ' + (j + 1), esSel ? 'sel' : '', true);
          arrastrar(d, { sel: { t: 'c', i: i }, get: function () { return { x: b[0], y: b[1], w: b[2], h: b[3] }; } },
            function (o, dx, dy) { b[0] = r1(o.x + dx); b[1] = r1(o.y + dy); },
            function (o, dx, dy) { b[2] = Math.max(10, r1(o.w + dx)); b[3] = Math.max(6, r1(o.h + dy)); });
        });
      } else if (c.tipo === 'marca') {
        Object.keys(c.m || {}).forEach(function (op) {
          var xy = c.m[op];
          var d = document.createElement('div'); d.className = 'vz-marca' + (esSel ? ' sel' : '');
          d.style.left = xy[0] * escala + 'px'; d.style.top = xy[1] * escala + 'px';
          d.title = (v.opciones && v.opciones[op]) || op; d.textContent = 'X'; capa.appendChild(d);
          arrastrar(d, { sel: { t: 'c', i: i }, get: function () { return { x: xy[0], y: xy[1] }; } },
            function (b, dx, dy) { xy[0] = r1(b.x + dx); xy[1] = r1(b.y + dy); });
        });
      }
    });
    spec.subdirector.forEach(function (b, i) {
      if (b.p !== pagina) return;
      var esSel = sel && sel.t === 's' && sel.i === i;
      var d = caja(b.x, b.y, b.w, b.h, 'Nombre del Subdirector', 'sub' + (esSel ? ' sel' : ''), true);
      arrastrar(d, { sel: { t: 's', i: i }, get: function () { return { x: b.x, y: b.y, w: b.w, h: b.h }; } },
        function (o, dx, dy) { b.x = r1(o.x + dx); b.y = r1(o.y + dy); },
        function (o, dx, dy) { b.w = Math.max(20, r1(o.w + dx)); b.h = Math.max(6, r1(o.h + dy)); });
    });
    if (sinPanel !== true) { lista(); propiedades(); } else { actualizarNumeros(); }
  }

  // ---- lista y propiedades
  function nombreCampo(c) { var v = V[c.k]; return v ? v.label : c.k; }
  function lista() {
    var cont = $('vz-lista'); cont.innerHTML = '';
    var n = 0;
    spec.campos.forEach(function (c, i) {
      if (c.p !== pagina) return; n++;
      var b = document.createElement('button'); b.type = 'button';
      b.className = sel && sel.t === 'c' && sel.i === i ? 'sel' : '';
      b.innerHTML = '';
      b.appendChild(document.createTextNode(nombreCampo(c) + ' '));
      var s = document.createElement('small'); s.textContent = '(' + c.tipo + ')'; b.appendChild(s);
      b.addEventListener('click', function () { seleccionar({ t: 'c', i: i }); pintar(); });
      cont.appendChild(b);
    });
    spec.subdirector.forEach(function (c, i) {
      if (c.p !== pagina) return; n++;
      var b = document.createElement('button'); b.type = 'button'; b.className = sel && sel.t === 's' && sel.i === i ? 'sel' : '';
      b.textContent = 'Nombre del Subdirector (se sustituye desde Ajustes)';
      b.addEventListener('click', function () { seleccionar({ t: 's', i: i }); pintar(); });
      cont.appendChild(b);
    });
    if (!n) cont.innerHTML = '<p class="texto-secundario" style="font-size:13px">Sin campos en esta página.</p>';
  }
  function seleccionar(s) { sel = s; colocando = null; }

  function entrada(etq, valor, fn, tipo, paso) {
    var l = document.createElement('label'); l.textContent = etq;
    var i = document.createElement('input'); i.type = tipo || 'number'; i.step = paso || '0.5'; i.value = valor;
    i.addEventListener('input', function () { fn(i.value); marcar(); pintar(true); });
    l.appendChild(i); return l;
  }
  function actualizarNumeros() { /* los inputs numéricos se refrescan al volver a pintar el panel */
    var p = document.activeElement; if (p && p.closest && p.closest('#vz-prop')) return; propiedades();
  }

  function propiedades() {
    var caja = $('vz-prop-caja'), cont = $('vz-prop'); cont.innerHTML = '';
    if (!sel) { caja.hidden = true; return; }
    var c = sel.t === 'c' ? spec.campos[sel.i] : spec.subdirector[sel.i];
    if (!c) { caja.hidden = true; return; }
    caja.hidden = false;
    var g = document.createElement('div'); g.className = 'vz-prop';
    var lp = document.createElement('label'); lp.textContent = 'Página';
    var sp = document.createElement('select');
    for (var p = 0; p < paginas; p++) { var o = document.createElement('option'); o.value = p; o.textContent = p + 1; if (p === c.p) o.selected = true; sp.appendChild(o); }
    sp.addEventListener('change', function () { c.p = parseInt(sp.value, 10); pagina = c.p; marcar(); dibujarPagina(); });
    lp.appendChild(sp); g.appendChild(lp);
    if (sel.t === 'c') {
      var lv = document.createElement('label'); lv.textContent = 'Dato';
      var s2 = document.createElement('select');
      Object.keys(V).filter(function (k) { return (V[k].tipo === 'marca') === (c.tipo === 'marca'); }).forEach(function (k) {
        var o = document.createElement('option'); o.value = k; o.textContent = V[k].label; if (k === c.k) o.selected = true; s2.appendChild(o);
      });
      s2.addEventListener('change', function () { c.k = s2.value; marcar(); pintar(); });
      lv.appendChild(s2); g.appendChild(lv);
    }
    if (c.tipo === 'texto' || sel.t === 's') {
      g.appendChild(entrada('X', c.x, function (v) { c.x = parseFloat(v) || 0; }));
      g.appendChild(entrada('Y', c.y, function (v) { c.y = parseFloat(v) || 0; }));
      g.appendChild(entrada('Ancho', c.w, function (v) { c.w = Math.max(6, parseFloat(v) || 6); }));
      g.appendChild(entrada('Alto', c.h, function (v) { c.h = Math.max(6, parseFloat(v) || 6); }));
      g.appendChild(entrada('Tamaño de letra', c.s, function (v) { c.s = Math.min(24, Math.max(5, parseFloat(v) || 10)); }));
    }
    if (c.tipo === 'texto') {
      var la = document.createElement('label'); la.textContent = 'Alineación';
      var sa = document.createElement('select');
      [['L', 'Izquierda'], ['C', 'Centro'], ['R', 'Derecha']].forEach(function (a) { var o = document.createElement('option'); o.value = a[0]; o.textContent = a[1]; if (c.a === a[0]) o.selected = true; sa.appendChild(o); });
      sa.addEventListener('change', function () { c.a = sa.value; marcar(); });
      la.appendChild(sa); g.appendChild(la);
    }
    if (sel.t === 's') {
      var lf = document.createElement('label'); lf.textContent = 'Negrita'; var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = !!c.negrita;
      cb.addEventListener('change', function () { c.negrita = cb.checked; marcar(); }); lf.appendChild(cb); g.appendChild(lf);
    }
    cont.appendChild(g);
    if (c.tipo === 'parrafo') {
      g.appendChild(entrada('Tamaño de letra', c.s, function (v) { c.s = Math.min(24, Math.max(5, parseFloat(v) || 10)); }));
      var row = document.createElement('div'); row.style.marginTop = '8px';
      var ba = document.createElement('button'); ba.type = 'button'; ba.className = 'btn btn-mini'; ba.textContent = 'Añadir renglón';
      ba.addEventListener('click', function () { var u = c.cajas[c.cajas.length - 1]; c.cajas.push([u[0], r1(u[1] + u[3] + 4), u[2], u[3]]); marcar(); pintar(); });
      var bq = document.createElement('button'); bq.type = 'button'; bq.className = 'btn btn-mini'; bq.textContent = 'Quitar último';
      bq.addEventListener('click', function () { if (c.cajas.length > 1) { c.cajas.pop(); marcar(); pintar(); } });
      row.appendChild(ba); row.appendChild(document.createTextNode(' ')); row.appendChild(bq); cont.appendChild(row);
    }
    if (c.tipo === 'marca') {
      var v = V[c.k], ul = document.createElement('div'); ul.style.marginTop = '8px';
      Object.keys(v.opciones || {}).forEach(function (op) {
        var f = document.createElement('div'); f.style.cssText = 'display:flex;gap:6px;align-items:center;font-size:12px;margin:3px 0';
        var t = document.createElement('span'); t.style.flex = '1'; t.textContent = v.opciones[op] + (c.m[op] ? ' ✓' : '');
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-mini' + (colocando === op ? ' btn-primario' : ''); b.textContent = colocando === op ? 'Haz clic en la hoja…' : (c.m[op] ? 'Recolocar' : 'Colocar');
        b.addEventListener('click', function () { colocando = colocando === op ? null : op; propiedades(); });
        f.appendChild(t); f.appendChild(b);
        if (c.m[op]) { var q = document.createElement('button'); q.type = 'button'; q.className = 'btn btn-mini'; q.textContent = '✕'; q.title = 'Quitar'; q.addEventListener('click', function () { delete c.m[op]; marcar(); pintar(); }); f.appendChild(q); }
        ul.appendChild(f);
      });
      cont.appendChild(ul);
    }
    var del = document.createElement('button'); del.type = 'button'; del.className = 'btn btn-mini btn-peligro'; del.style.marginTop = '10px'; del.textContent = 'Eliminar este campo';
    del.addEventListener('click', function () {
      if (sel.t === 'c') spec.campos.splice(sel.i, 1); else spec.subdirector.splice(sel.i, 1);
      sel = null; marcar(); pintar();
    });
    cont.appendChild(del);
  }

  // clic sobre la hoja para colocar casillas
  capa.addEventListener('click', function (ev) {
    if (!colocando || !sel || sel.t !== 'c') return;
    var c = spec.campos[sel.i]; if (c.tipo !== 'marca') return;
    var rc = capa.getBoundingClientRect();
    c.m[colocando] = [r1((ev.clientX - rc.left) / escala), r1((ev.clientY - rc.top) / escala)];
    colocando = null; marcar(); pintar();
  });
  capa.addEventListener('pointerdown', function (ev) { if (ev.target === capa && !colocando) { sel = null; pintar(); } });

  document.addEventListener('keydown', function (ev) {
    if (!sel || /INPUT|SELECT|TEXTAREA/.test((ev.target.tagName || ''))) return;
    var paso = ev.shiftKey ? 5 : 0.5, dx = 0, dy = 0;
    if (ev.key === 'ArrowLeft') dx = -paso; else if (ev.key === 'ArrowRight') dx = paso; else if (ev.key === 'ArrowUp') dy = -paso; else if (ev.key === 'ArrowDown') dy = paso; else return;
    ev.preventDefault();
    var c = sel.t === 'c' ? spec.campos[sel.i] : spec.subdirector[sel.i]; if (!c) return;
    if (c.tipo === 'texto' || sel.t === 's') { c.x = r1(c.x + dx); c.y = r1(c.y + dy); }
    else if (c.tipo === 'parrafo') c.cajas.forEach(function (b) { b[0] = r1(b[0] + dx); b[1] = r1(b[1] + dy); });
    else if (c.tipo === 'marca') Object.keys(c.m).forEach(function (k) { c.m[k][0] = r1(c.m[k][0] + dx); c.m[k][1] = r1(c.m[k][1] + dy); });
    marcar(); pintar();
  });

  // ---- añadir
  $('vz-add').addEventListener('click', function () {
    var k = sv.value, v = V[k], y = 90 + (spec.campos.filter(function (c) { return c.p === pagina; }).length % 14) * 22, c;
    if (v.tipo === 'marca') c = { k: k, tipo: 'marca', p: pagina, m: {} };
    else c = { k: k, tipo: 'texto', p: pagina, x: 90, y: y, w: 190, h: 14, s: 10, a: 'L' };
    spec.campos.push(c); sel = { t: 'c', i: spec.campos.length - 1 }; marcar(); pintar();
  });
  $('vz-add-sub').addEventListener('click', function () {
    spec.subdirector.push({ p: pagina, x: 90, y: 160, w: 122, h: 12, s: 11, negrita: true, fondo: [255, 255, 255] });
    sel = { t: 's', i: spec.subdirector.length - 1 }; marcar(); pintar();
  });

  // ---- guardar y probar
  function cuerpo() {
    var fd = new FormData(); fd.append('_csrf', D.csrf); fd.append('spec', JSON.stringify(spec)); return fd;
  }
  $('vz-guardar').addEventListener('click', function () {
    aviso('Guardando…', true);
    fetch(D.urlGuardar, { method: 'POST', body: cuerpo(), credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (x) { aviso(x.j.mensaje || 'Listo.', x.ok && x.j.ok); if (x.ok && x.j.ok) sucio = false; })
      .catch(function () { aviso('No se pudo guardar. Revisa tu conexión e inténtalo de nuevo.', false); });
  });
  $('vz-probar').addEventListener('click', function () {
    var f = document.createElement('form'); f.method = 'post'; f.action = D.urlProbar; f.target = '_blank';
    [['_csrf', D.csrf], ['spec', JSON.stringify(spec)]].forEach(function (p) { var i = document.createElement('input'); i.type = 'hidden'; i.name = p[0]; i.value = p[1]; f.appendChild(i); });
    document.body.appendChild(f); f.submit(); document.body.removeChild(f);
  });

  $('vz-prev').addEventListener('click', function () { if (pagina > 0) { pagina--; dibujarPagina(); } });
  $('vz-next').addEventListener('click', function () { if (pagina < paginas - 1) { pagina++; dibujarPagina(); } });
  $('vz-zoom').addEventListener('input', function (e) { escala = parseFloat(e.target.value); dibujarPagina(); });
  escala = parseFloat($('vz-zoom').value);

  pdfjsLib.getDocument({ url: D.urlPlantilla, withCredentials: true }).promise.then(function (d) { pdf = d; paginas = d.numPages; dibujarPagina(); })
    .catch(function () { aviso('No se pudo cargar la plantilla para mostrarla.', false); });
})();
</script>
