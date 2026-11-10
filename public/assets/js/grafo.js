/* Grafo de relaciones de una persona: disposición por fuerzas, arrastre, zoom y resalte de vecinos (al estilo de Obsidian).
 * Sin bibliotecas. Lee el JSON de data-grafo (GrafoPersona::datos); los textos se asignan con textContent. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var cont = document.querySelector('[data-grafo]');
  if (!cont) { return; }
  var datos;
  try { datos = JSON.parse(cont.getAttribute('data-grafo')); } catch (e) { return; }
  if (!datos || !datos.nodes || !datos.nodes.length) { return; }

  var W = cont.clientWidth || 900, H = cont.clientHeight || 620;
  var svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('class', 'grafo-svg');
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-label', datos.titulo || 'Grafo de relaciones');
  var vista = document.createElementNS(NS, 'g');
  var capaAristas = document.createElementNS(NS, 'g');
  var capaNodos = document.createElementNS(NS, 'g');
  vista.appendChild(capaAristas); vista.appendChild(capaNodos); svg.appendChild(vista);
  cont.appendChild(svg);
  var tip = document.createElement('div');
  tip.className = 'grafo-tip'; tip.hidden = true; cont.appendChild(tip);

  var porId = {};
  var nodos = datos.nodes.map(function (n, i) {
    var a = (i / datos.nodes.length) * 6.2832;
    var o = { id: n.id, tipo: n.tipo, label: n.label, detalle: n.detalle || '', enlace: n.enlace || '', color: n.color,
      x: W / 2 + Math.cos(a) * 160 * (n.tipo === 'item' ? 1.8 : 1) + (Math.random() - .5) * 20,
      y: H / 2 + Math.sin(a) * 160 * (n.tipo === 'item' ? 1.8 : 1) + (Math.random() - .5) * 20,
      vx: 0, vy: 0, grado: 0, fijo: false };
    porId[n.id] = o; return o;
  });
  var aristas = [];
  datos.links.forEach(function (l) {
    var a = porId[l[0]], b = porId[l[1]];
    if (a && b) { aristas.push({ a: a, b: b }); a.grado++; b.grado++; }
  });
  nodos.forEach(function (n) {
    n.r = n.tipo === 'persona' ? 15 : n.tipo === 'grupo' ? 6 + Math.min(8, n.grado * 0.9) : 4.5;
    n.el = document.createElementNS(NS, 'g');
    n.el.setAttribute('class', 'grafo-n grafo-n-' + n.tipo);
    var c = document.createElementNS(NS, 'circle');
    c.setAttribute('r', n.r); c.setAttribute('fill', n.color);
    var t = document.createElementNS(NS, 'text');
    t.setAttribute('y', n.r + 12); t.setAttribute('text-anchor', 'middle');
    var max = n.tipo === 'item' ? 26 : 38;
    var corto = n.label.length > max ? n.label.slice(0, max - 1) + '…' : n.label;
    t.textContent = corto;
    n.el.appendChild(c); n.el.appendChild(t);
    capaNodos.appendChild(n.el);
  });
  aristas.forEach(function (e) {
    e.el = document.createElementNS(NS, 'line');
    e.el.setAttribute('class', 'grafo-a');
    capaAristas.appendChild(e.el);
  });

  // Fuerzas.
  var alfa = 1, corre = false;
  function paso() {
    var i, j, a, b, dx, dy, d2, d, f;
    for (i = 0; i < nodos.length; i++) {
      a = nodos[i];
      for (j = i + 1; j < nodos.length; j++) {
        b = nodos[j]; dx = a.x - b.x; dy = a.y - b.y; d2 = dx * dx + dy * dy + 0.01;
        if (d2 > 250000) { continue; }
        f = (a.tipo === 'item' && b.tipo === 'item' ? 3200 : 6500) / d2; d = Math.sqrt(d2);
        dx = dx / d * f; dy = dy / d * f; a.vx += dx; a.vy += dy; b.vx -= dx; b.vy -= dy;
      }
      a.vx += (W / 2 - a.x) * 0.003; a.vy += (H / 2 - a.y) * 0.003;
    }
    aristas.forEach(function (e) {
      var largo = e.a.tipo === 'persona' || e.b.tipo === 'persona' ? 150 : 78;
      dx = e.b.x - e.a.x; dy = e.b.y - e.a.y; d = Math.sqrt(dx * dx + dy * dy) || 1;
      f = (d - largo) * 0.06; dx = dx / d * f; dy = dy / d * f;
      e.a.vx += dx; e.a.vy += dy; e.b.vx -= dx; e.b.vy -= dy;
    });
    nodos.forEach(function (n) {
      if (n.fijo) { n.vx = n.vy = 0; return; }
      n.vx *= 0.82; n.vy *= 0.82;
      n.x += n.vx * alfa; n.y += n.vy * alfa;
    });
    alfa *= 0.994;
  }
  function pinta() {
    aristas.forEach(function (e) {
      e.el.setAttribute('x1', e.a.x); e.el.setAttribute('y1', e.a.y); e.el.setAttribute('x2', e.b.x); e.el.setAttribute('y2', e.b.y);
    });
    nodos.forEach(function (n) { n.el.setAttribute('transform', 'translate(' + n.x.toFixed(1) + ',' + n.y.toFixed(1) + ')'); });
  }
  function ciclo() {
    paso(); pinta();
    if (alfa > 0.02) { requestAnimationFrame(ciclo); if (!manual && alfa < 0.5) { encajar(); } } else { corre = false; if (!manual) { encajar(); } }
  }
  function despertar(v) { alfa = Math.max(alfa, v || 0.5); if (!corre) { corre = true; requestAnimationFrame(ciclo); } }
  
  // Zoom y desplazamiento. «encajar» centra todo el grafo en el lienzo mientras la persona no lo haya movido a mano.
  var z = 1, tx = 0, ty = 0, manual = false;
  function encajar() {
    var x0 = 1e9, y0 = 1e9, x1 = -1e9, y1 = -1e9;
    nodos.forEach(function (n) { x0 = Math.min(x0, n.x - 60); y0 = Math.min(y0, n.y - 20); x1 = Math.max(x1, n.x + 60); y1 = Math.max(y1, n.y + 30); });
    W = cont.clientWidth || W; H = cont.clientHeight || H;
    var nz = Math.max(0.35, Math.min(1.6, Math.min(W / (x1 - x0), H / (y1 - y0))));
    z = nz; tx = W / 2 - (x0 + x1) / 2 * nz; ty = H / 2 - (y0 + y1) / 2 * nz; aplicar();
  }
  function aplicar() { vista.setAttribute('transform', 'translate(' + tx + ',' + ty + ') scale(' + z + ')'); svg.classList.toggle('grafo-lejos', z < 0.75); }
  function acercar(f, cx, cy) {
    manual = true;
    var nz = Math.max(0.3, Math.min(3.5, z * f)); f = nz / z;
    tx = cx - (cx - tx) * f; ty = cy - (cy - ty) * f; z = nz; aplicar();
  }
  function punto(ev) { var r = svg.getBoundingClientRect(); return { x: ev.clientX - r.left, y: ev.clientY - r.top }; }
  svg.addEventListener('wheel', function (ev) { ev.preventDefault(); var p = punto(ev); acercar(ev.deltaY < 0 ? 1.12 : 1 / 1.12, p.x, p.y); }, { passive: false });
  var barra = document.createElement('div');
  barra.className = 'grafo-barra';
  [['+', 'Acercar', function () { acercar(1.25, W / 2, H / 2); }], ['−', 'Alejar', function () { acercar(1 / 1.25, W / 2, H / 2); }],
   ['⟲', 'Reiniciar vista y disposición', function () { manual = false; alfa = 1; nodos.forEach(function (n) { n.fijo = false; }); despertar(1); }]
  ].forEach(function (b) {
    var x = document.createElement('button'); x.type = 'button'; x.textContent = b[0]; x.title = b[1]; x.setAttribute('aria-label', b[1]);
    x.addEventListener('click', b[2]); barra.appendChild(x);
  });
  cont.appendChild(barra);

  // Arrastre de nodos y del lienzo; clic en un nodo con enlace abre su sección.
  var arrastre = null;
  function aMundo(p) { return { x: (p.x - tx) / z, y: (p.y - ty) / z }; }
  svg.addEventListener('pointerdown', function (ev) {
    var g = ev.target.closest ? ev.target.closest('.grafo-n') : null;
    var nodo = null;
    if (g) { nodo = nodos.filter(function (n) { return n.el === g; })[0] || null; }
    arrastre = { nodo: nodo, p: punto(ev), movido: false, tx: tx, ty: ty };
    if (!nodo) { manual = true; }
    svg.setPointerCapture(ev.pointerId);
    if (nodo) { nodo.fijo = true; despertar(0.6); }
  });
  svg.addEventListener('pointermove', function (ev) {
    var p = punto(ev);
    if (!arrastre) { return; }
    var dx = p.x - arrastre.p.x, dy = p.y - arrastre.p.y;
    if (Math.abs(dx) + Math.abs(dy) > 3) { arrastre.movido = true; }
    if (arrastre.nodo) { var m = aMundo(p); arrastre.nodo.x = m.x; arrastre.nodo.y = m.y; despertar(0.4); pinta(); }
    else { tx = arrastre.tx + dx; ty = arrastre.ty + dy; aplicar(); }
  });
  svg.addEventListener('pointerup', function () {
    if (arrastre && arrastre.nodo) {
      if (!arrastre.movido && arrastre.nodo.enlace) { window.location.href = arrastre.nodo.enlace; }
      if (arrastre.nodo.tipo !== 'persona') { arrastre.nodo.fijo = false; }
    }
    arrastre = null;
  });

  // Resalte de vecinos y rótulo con el detalle.
  function resaltar(n) {
    var vec = {}; vec[n.id] = true;
    aristas.forEach(function (e) { if (e.a === n) { vec[e.b.id] = true; } if (e.b === n) { vec[e.a.id] = true; } });
    svg.classList.add('grafo-resalte');
    nodos.forEach(function (m) { m.el.classList.toggle('grafo-on', !!vec[m.id]); });
    aristas.forEach(function (e) { e.el.classList.toggle('grafo-on', e.a === n || e.b === n); });
  }
  function apagar() {
    svg.classList.remove('grafo-resalte'); tip.hidden = true;
    nodos.forEach(function (m) { m.el.classList.remove('grafo-on'); });
    aristas.forEach(function (e) { e.el.classList.remove('grafo-on'); });
  }
  nodos.forEach(function (n) {
    n.el.addEventListener('pointerenter', function (ev) {
      resaltar(n);
      tip.textContent = '';
      var s = document.createElement('strong'); s.textContent = n.label; tip.appendChild(s);
      if (n.detalle) { var d = document.createElement('span'); d.textContent = n.detalle; tip.appendChild(d); }
      if (n.enlace) { var o = document.createElement('em'); o.textContent = 'Clic para abrir'; tip.appendChild(o); }
      tip.hidden = false;
    });
    n.el.addEventListener('pointermove', function (ev) {
      var r = cont.getBoundingClientRect();
      tip.style.left = Math.min(ev.clientX - r.left + 14, r.width - tip.offsetWidth - 8) + 'px';
      tip.style.top = (ev.clientY - r.top + 14) + 'px';
    });
    n.el.addEventListener('pointerleave', apagar);
  });
  aplicar();
  // La disposición se calcula de una vez (rápido y estable) y solo después se anima lo que mueva la persona.
  for (var k = 0; k < 420; k++) { paso(); }
  pinta(); encajar();
})();
