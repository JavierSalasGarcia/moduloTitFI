/*
 * Lienzo del oficio de la Subdirección: solo negritas y cursivas; las tablas se pegan con Ctrl+V (sin controles para crearlas o editarlas).
 * Más el selector de destinatarios reutilizables. El servidor vuelve a limpiar todo el HTML (Consejos\Services\Html::limpiar).
 */
(function () {
  'use strict';
  var ed = document.getElementById('lienzo');
  var campo = document.getElementById('cuerpo_html');
  if (!ed || !campo) return;
  var BLOQUES = { P: 1, DIV: 1, UL: 1, OL: 1, TABLE: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1, BLOCKQUOTE: 1, PRE: 1, LI: 1 };
  var DESCARTAR = { SCRIPT: 1, STYLE: 1, HEAD: 1, TITLE: 1, META: 1, LINK: 1, XML: 1, OBJECT: 1, IFRAME: 1, SVG: 1, IMG: 1, CANVAS: 1, NOSCRIPT: 1, TEMPLATE: 1 };
  var esc = function (s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };

  function inline(n) {
    var s = '';
    n.childNodes.forEach(function (h) {
      if (h.nodeType === 3) { s += esc(h.nodeValue.replace(/ /g, ' ').replace(/\s+/g, ' ')); return; }
      if (h.nodeType !== 1 || DESCARTAR[h.tagName]) return;
      if (h.tagName === 'BR') { s += '<br>'; return; }
      if (BLOQUES[h.tagName]) { s += inline(h) + '<br>'; return; }
      var d = inline(h); if (!d.replace(/<[^>]*>/g, '').trim()) { s += d; return; }
      var st = (h.getAttribute('style') || '').toLowerCase();
      if (h.tagName === 'I' || h.tagName === 'EM' || st.indexOf('italic') > -1) d = '<i>' + d + '</i>';
      if (h.tagName === 'B' || h.tagName === 'STRONG' || /font-weight:\s*(bold|[6-9]00)/.test(st)) d = '<b>' + d + '</b>';
      s += d;
    });
    return s;
  }

  function bloques(n) {
    var out = '', pend = '';
    var vaciar = function () { if (pend.replace(/<[^>]*>/g, '').trim()) out += '<p>' + pend.trim() + '</p>'; pend = ''; };
    n.childNodes.forEach(function (h) {
      if (h.nodeType === 3) { pend += esc(h.nodeValue.replace(/ /g, ' ').replace(/\s+/g, ' ')); return; }
      if (h.nodeType !== 1 || DESCARTAR[h.tagName]) return;
      var t = h.tagName;
      if (t === 'TABLE') {
        vaciar(); var f = '';
        h.querySelectorAll('tr').forEach(function (tr) {
          if (tr.closest('table') !== h) return;
          var c = '';
          tr.querySelectorAll(':scope > td, :scope > th').forEach(function (td) {
            var tg = td.tagName.toLowerCase(), at = '';
            ['colspan', 'rowspan'].forEach(function (a) { var v = parseInt(td.getAttribute(a), 10); if (v > 1) at += ' ' + a + '="' + Math.min(v, 30) + '"'; });
            c += '<' + tg + at + '>' + (bloques(td) || '') + '</' + tg + '>';
          });
          if (c) f += '<tr>' + c + '</tr>';
        });
        if (f) out += '<table><tbody>' + f + '</tbody></table>';
      } else if (t === 'UL' || t === 'OL') {
        vaciar();
        h.querySelectorAll(':scope > li').forEach(function (li) { var c = inline(li).trim(); if (c.replace(/<[^>]*>/g, '').trim()) out += '<p>' + c + '</p>'; });
      } else if (BLOQUES[t]) {
        vaciar();
        if (Array.prototype.some.call(h.children, function (x) { return BLOQUES[x.tagName]; })) out += bloques(h);
        else { var c2 = inline(h).trim(); if (c2.replace(/<[^>]*>/g, '').trim()) out += (/^H\d$/.test(t) ? '<p><b>' + c2 + '</b></p>' : '<p>' + c2 + '</p>'); }
      } else { pend += inline({ childNodes: [h] }); }
    });
    vaciar(); return out;
  }

  function limpiarPegado(html) {
    var m = html.match(/<!--StartFragment-->([\s\S]*?)<!--EndFragment-->/);
    var doc = new DOMParser().parseFromString(m ? m[1] : html, 'text/html');
    return bloques(doc.body);
  }

  ed.addEventListener('paste', function (ev) {
    var cd = ev.clipboardData; if (!cd) return;
    var html = cd.getData('text/html'), txt = cd.getData('text/plain');
    ev.preventDefault();
    var limpio = html ? limpiarPegado(html) : '';
    if (!limpio.replace(/<[^>]*>/g, '').trim() && !/<table/.test(limpio)) limpio = txt.split(/\r?\n/).filter(function (l) { return l.trim(); }).map(function (l) { return '<p>' + esc(l.trim()) + '</p>'; }).join('');
    document.execCommand('insertHTML', false, limpio);
  });
  ed.addEventListener('drop', function (e) { e.preventDefault(); });
  ed.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && (e.key === 'u' || e.key === 'U')) e.preventDefault(); });

  document.querySelectorAll('[data-cmd]').forEach(function (b) {
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function () { ed.focus(); document.execCommand(b.dataset.cmd); });
  });

  // ---- destinatarios reutilizables
  var caja = document.getElementById('dest-chips');
  var buscar = document.getElementById('dest-buscar');
  var lista = document.getElementById('dest-lista');
  var urlBuscar = ed.dataset.urlDestinatarios;
  var csrf = ed.dataset.csrf;
  var temporizador = null;

  function chip(d) {
    if (!d.id || caja.querySelector('input[value="' + d.id + '"]')) return;
    var li = document.createElement('li');
    li.className = 'dest-chip';
    var t = document.createElement('span');
    t.textContent = d.nombre + (d.cargo ? ' · ' + d.cargo : '') + (d.dependencia ? ' · ' + d.dependencia : '');
    var i = document.createElement('input'); i.type = 'hidden'; i.name = 'destinatarios[]'; i.value = d.id;
    var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Quitar a ' + d.nombre); x.textContent = '×';
    x.addEventListener('click', function () { li.remove(); });
    li.append(t, i, x); caja.append(li);
  }
  (JSON.parse(caja.dataset.iniciales || '[]')).forEach(chip);

  function mostrar(r) {
    lista.textContent = '';
    r.forEach(function (d) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'dest-op';
      b.textContent = d.nombre + (d.cargo ? ' — ' + d.cargo : '') + (d.dependencia ? ' (' + d.dependencia + ')' : '');
      b.addEventListener('click', function () { chip(d); lista.hidden = true; buscar.value = ''; buscar.focus(); });
      lista.append(b);
    });
    lista.hidden = !r.length;
  }
  function cargar() {
    fetch(urlBuscar + '?q=' + encodeURIComponent(buscar.value.trim()), { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (d) { mostrar(d.destinatarios || []); }).catch(function () {});
  }
  buscar.addEventListener('input', function () { clearTimeout(temporizador); temporizador = setTimeout(cargar, 200); });
  buscar.addEventListener('focus', cargar);
  document.addEventListener('click', function (e) { if (!lista.contains(e.target) && e.target !== buscar) lista.hidden = true; });

  var nuevo = document.getElementById('dest-nuevo');
  document.getElementById('dest-nuevo-abrir').addEventListener('click', function () { nuevo.hidden = !nuevo.hidden; if (!nuevo.hidden) nuevo.querySelector('input').focus(); });
  document.getElementById('dest-nuevo-guardar').addEventListener('click', function () {
    var fd = new URLSearchParams();
    fd.set('_csrf', csrf);
    ['nombre', 'cargo', 'dependencia'].forEach(function (k) { fd.set(k, nuevo.querySelector('[name=' + k + ']').value); });
    var msg = document.getElementById('dest-nuevo-msg'); msg.textContent = '';
    fetch(nuevo.dataset.url, { method: 'POST', credentials: 'same-origin', body: fd, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
      .then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { msg.textContent = d.error || 'No se pudo guardar.'; return; }
        chip(d.destinatario); nuevo.querySelectorAll('input').forEach(function (i) { i.value = ''; }); nuevo.hidden = true;
      }).catch(function () { msg.textContent = 'No se pudo guardar.'; });
  });

  document.getElementById('form-oficio').addEventListener('submit', function (e) {
    campo.value = ed.innerHTML;
    if (!caja.querySelector('input')) { e.preventDefault(); alert('Elige al menos un destinatario.'); buscar.focus(); }
  });
})();
