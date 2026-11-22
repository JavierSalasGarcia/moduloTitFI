<?php /** Editor de solicitudes: pegado desde Word, herramientas, plantillas y menciones. Usa $plantillas y el formulario #form-asunto. */ ?>
<script>
(() => {
  const BLOQUES = new Set(['P','DIV','UL','OL','TABLE','H1','H2','H3','H4','H5','H6','BLOCKQUOTE','PRE','CENTER']);
  const DESCARTAR = new Set(['SCRIPT','STYLE','HEAD','TITLE','META','LINK','XML','OBJECT','IFRAME','SVG','IMG','CANVAS','NOSCRIPT','TEMPLATE']);
  const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  // HTML de Word → HTML mínimo (el servidor lo vuelve a limpiar: esto solo mejora lo que se ve al pegar).
  function inline(n) {
    let s = '';
    n.childNodes.forEach(h => {
      if (h.nodeType === 3) { s += esc(h.nodeValue.replace(/ /g,' ').replace(/\s+/g,' ')); return; }
      if (h.nodeType !== 1 || DESCARTAR.has(h.tagName)) return;
      if (h.tagName === 'BR') { s += '<br>'; return; }
      if (BLOQUES.has(h.tagName)) { s += inline(h) + '<br>'; return; }
      let d = inline(h); if (!d.replace(/<[^>]*>/g,'').trim()) { s += d; return; }
      const st = (h.getAttribute('style') || '').toLowerCase();
      if (h.tagName === 'U' || st.includes('underline')) d = '<u>' + d + '</u>';
      if (h.tagName === 'I' || h.tagName === 'EM' || st.includes('italic')) d = '<i>' + d + '</i>';
      if (h.tagName === 'B' || h.tagName === 'STRONG' || /font-weight:\s*(bold|[6-9]00)/.test(st)) d = '<b>' + d + '</b>';
      s += d;
    });
    return s;
  }
  function bloques(n) {
    let out = '', pend = '';
    const vaciar = () => { if (pend.replace(/<[^>]*>/g,'').trim()) out += '<p>' + pend.trim() + '</p>'; pend = ''; };
    n.childNodes.forEach(h => {
      if (h.nodeType === 3) { pend += esc(h.nodeValue.replace(/ /g,' ').replace(/\s+/g,' ')); return; }
      if (h.nodeType !== 1 || DESCARTAR.has(h.tagName)) return;
      const t = h.tagName;
      if (t === 'UL' || t === 'OL') {
        vaciar(); let li = '';
        h.querySelectorAll(':scope > li').forEach(x => { const c = inline(x).trim(); if (c.replace(/<[^>]*>/g,'').trim()) li += '<li>' + c + '</li>'; });
        if (li) out += '<' + t.toLowerCase() + '>' + li + '</' + t.toLowerCase() + '>';
      } else if (t === 'TABLE') {
        vaciar(); let f = '';
        h.querySelectorAll('tr').forEach(tr => {
          if (tr.closest('table') !== h) return;
          let c = '';
          tr.querySelectorAll(':scope > td, :scope > th').forEach(td => {
            const tg = td.tagName.toLowerCase(); let at = '';
            ['colspan','rowspan'].forEach(a => { const v = parseInt(td.getAttribute(a), 10); if (v > 1) at += ' ' + a + '="' + Math.min(v, 30) + '"'; });
            c += '<' + tg + at + '>' + (bloques(td) || '') + '</' + tg + '>';
          });
          if (c) f += '<tr>' + c + '</tr>';
        });
        if (f) out += '<table><tbody>' + f + '</tbody></table>';
      } else if (BLOQUES.has(t)) {
        vaciar();
        if ([...h.children].some(x => BLOQUES.has(x.tagName))) out += bloques(h);
        else { const c = inline(h).trim(); if (c.replace(/<[^>]*>/g,'').trim()) out += /^H\d$/.test(t) ? '<p><b>' + c + '</b></p>' : '<p>' + c + '</p>'; }
      } else { pend += inline({childNodes: [h]}); }
    });
    vaciar(); return out;
  }
  function limpiarPegado(html) {
    const m = html.match(/<!--StartFragment-->([\s\S]*?)<!--EndFragment-->/);
    const doc = new DOMParser().parseFromString(m ? m[1] : html, 'text/html');
    return bloques(doc.body);
  }

  document.querySelectorAll('.cns-lienzo[data-herr]').forEach(ed => {
    ed.addEventListener('paste', ev => {
      const cd = ev.clipboardData; if (!cd) return;
      const html = cd.getData('text/html'); const txt = cd.getData('text/plain');
      ev.preventDefault();
      let limpio = html ? limpiarPegado(html) : '';
      if (!limpio.replace(/<[^>]*>/g,'').trim() && !/<table/.test(limpio)) limpio = txt.split(/\r?\n/).filter(l => l.trim()).map(l => '<p>' + esc(l.trim()) + '</p>').join('');
      if (ed.hasAttribute('data-solo-texto')) {
        const d = document.createElement('div'); d.innerHTML = limpio;
        limpio = [...d.querySelectorAll('p, li')].map(x => x.innerHTML.trim()).filter(Boolean).map(x => '<p>' + x + '</p>').join('');
      }
      document.execCommand('insertHTML', false, limpio);
    });
    ed.addEventListener('drop', e => e.preventDefault());
  });

  // Herramientas: negrita, cursiva, lista y tablas.
  const celdaActual = () => { const s = getSelection(); let n = s && s.anchorNode; while (n && n.nodeType !== 1) n = n.parentNode; return n ? n.closest('td,th') : null; };
  document.querySelectorAll('.cns-herr').forEach(b => b.addEventListener('mousedown', e => e.preventDefault()));
  document.querySelectorAll('[data-cmd]').forEach(btn => btn.addEventListener('click', () => {
    const cmd = btn.dataset.cmd, ed = btn.closest('.cns-herr').nextElementSibling;
    ed.focus();
    if (['bold','italic','insertUnorderedList','insertOrderedList'].includes(cmd)) { document.execCommand(cmd); return; }
    if (cmd === 'mencionar') { document.execCommand('insertText', false, '@'); return; }
    if (cmd === 'tabla') {
      const f = parseInt(prompt('¿Cuántas filas?', '3'), 10), c = parseInt(prompt('¿Cuántas columnas?', '3'), 10);
      if (!(f > 0 && c > 0)) return;
      let t = '<table><tbody>'; for (let i = 0; i < Math.min(f, 40); i++) { t += '<tr>'; for (let j = 0; j < Math.min(c, 12); j++) t += '<td><br></td>'; t += '</tr>'; }
      document.execCommand('insertHTML', false, t + '</tbody></table><p><br></p>'); return;
    }
    const td = celdaActual(); if (!td || !ed.contains(td)) { alert('Haz clic dentro de una celda de la tabla.'); return; }
    const tr = td.parentElement, tabla = td.closest('table'), idx = [...tr.children].indexOf(td);
    if (cmd === 'fila+') { const n = tr.cloneNode(true); n.querySelectorAll('td,th').forEach(x => { x.innerHTML = '<br>'; x.removeAttribute('rowspan'); }); tr.after(n); }
    else if (cmd === 'fila-') { if (tabla.rows.length > 1) tr.remove(); else tabla.remove(); }
    else if (cmd === 'col+') { [...tabla.rows].forEach(r => { const c = r.insertCell(Math.min(idx + 1, r.cells.length)); c.innerHTML = '<br>'; }); }
    else if (cmd === 'col-') { [...tabla.rows].forEach(r => { if (r.cells.length > 1 && r.cells[idx]) r.deleteCell(idx); }); }
    else if (cmd === 'tabla-') { if (confirm('¿Borrar toda la tabla?')) tabla.remove(); }
  }));

  // Plantillas de solicitud por tipo de trámite.
  const PLANTILLAS = <?= json_encode($plantillas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const selPl = document.getElementById('plantilla');
  if (selPl) selPl.addEventListener('change', () => {
    const p = PLANTILLAS[selPl.value]; if (!p) return;
    const sol = document.getElementById('ed-solicitud'), ant = document.getElementById('ed-antecedentes');
    if ((sol.textContent.trim() || (ant && ant.textContent.trim())) && !confirm('Esto reemplaza la solicitud y los antecedentes que ya escribiste. ¿Continuar?')) { selPl.value = ''; return; }
    sol.innerHTML = p.solicitud; if (ant) ant.innerHTML = p.antecedentes; sol.focus();
    const gs = document.getElementById('grupo-sel'); if (gs && p.grupo) gs.value = p.grupo;
    const cl = document.getElementById('plantilla-clave'); if (cl) cl.value = p.clave;
  });

  // Al enviar, el contenido de los lienzos viaja en campos ocultos.
  const form = document.getElementById('form-asunto');
  form.addEventListener('submit', ev => {
    const antEd = document.getElementById('ed-antecedentes');
    const pend = (document.getElementById('ed-solicitud').textContent + ' ' + (antEd ? antEd.textContent : '')).match(/\[[^\]]{2,60}\]/g);
    if (pend && !confirm('Aún hay datos entre corchetes sin llenar: ' + pend.slice(0, 3).join(', ') + (pend.length > 3 ? '…' : '') + '\n¿Guardar así?')) { ev.preventDefault(); return; }
    document.getElementById('in-solicitud').value = document.getElementById('ed-solicitud').innerHTML;
    if (antEd) document.getElementById('in-antecedentes').value = antEd.innerHTML;
  });

  // Menciones con @ en la solicitud y en los antecedentes: suman a la persona a «Profesores mencionados».
  document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('personas');
    if (!sel || !window.SelectorPersonas) return;
    ['ed-solicitud', 'ed-antecedentes'].forEach(i => SelectorPersonas.mencionar(document.getElementById(i), sel));
  });
})();
</script>
