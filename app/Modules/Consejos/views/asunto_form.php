<?php
/** @var array $r @var array $a @var array $profesores @var array $grupos */
use App\Core\Auth;
use App\Modules\Consejos\Services\Texto;
include __DIR__ . '/_estilos.php';
$nuevo = (int) $a['id'] === 0;
$clave = Texto::clave($a['grupo'], (int) $a['numero']);
$accion = $nuevo ? 'consejos/reunion/' . $r['id'] . '/asunto' : 'consejos/asunto/' . $a['id'];
?>
<div class="cns-pila">
  <form class="tarjeta cns-form" method="post" action="<?= e(url($accion)) ?>" id="form-asunto">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="grupo" value="<?= e($a['grupo']) ?>">
    <div class="cns-cab" style="margin-bottom:12px">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
        <h1><?= $nuevo ? 'Nuevo ' . e(mb_strtolower($grupos[$a['grupo']])) . ' (' . e($clave) . ')' : 'Caso ' . e($clave) ?></h1>
      </div>
      <?php if (!$nuevo): ?><label style="margin:0;max-width:130px">Número<input type="text" name="numero" inputmode="numeric" pattern="\d{1,3}" value="<?= (int) $a['numero'] ?>"></label><?php endif; ?>
    </div>

    <label>Solicitud <span class="req">*</span>
      <span class="cns-ayuda">Escribe o pega el texto tal como irá en el acta («El Dr. …, profesor de esta Facultad, solicita a los H.H. Consejos…»).</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-solicitud" contenteditable="true" data-vacio="Escribe o pega aquí la solicitud…" data-herr="1"><?= $a['solicitud_html'] ?></div>
    <input type="hidden" name="solicitud_html" id="in-solicitud">

    <label style="margin-top:16px">Documentos y/o antecedentes
      <span class="cns-ayuda"><strong>Para poner una tabla: cópiala en Word (Ctrl+C) y pégala aquí (Ctrl+V).</strong> Se conservan las filas, columnas y celdas combinadas. También puedes escribir listas como «Oficio de solicitud».</span>
    </label>
    <?php include __DIR__ . '/_herramientas.php'; ?>
    <div class="cns-lienzo" id="ed-antecedentes" contenteditable="true" data-vacio="Pega aquí tablas y documentos de Word, o escribe la lista de antecedentes…" data-herr="1"><?= $a['antecedentes_html'] ?></div>
    <input type="hidden" name="antecedentes_html" id="in-antecedentes">

    <?php if ($a['grupo'] !== 'alumno'): ?>
    <label style="margin-top:16px">Profesores mencionados en el caso
      <span class="cns-ayuda">A quienes elijas aquí les aparecerá el caso (y su dictamen escaneado) en <strong>Comisiones</strong>. Se eligen a mano; el sistema no los deduce por el nombre.</span>
    </label>
    <?php else: ?>
    <label style="margin-top:16px">Profesores mencionados (opcional)
      <span class="cns-ayuda">Solo si el caso incluye comités o sínodos de profesores que deban verlo en <strong>Comisiones</strong>.</span>
    </label>
    <?php endif; ?>
    <div class="cns-personas" id="personas">
      <?php foreach ($profesores as $p): ?>
        <span class="cns-chip" data-id="<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?><input type="hidden" name="profesores[]" value="<?= (int) $p['id'] ?>"><button type="button" aria-label="Quitar a <?= e($p['nombre']) ?>">×</button></span>
      <?php endforeach; ?>
    </div>
    <input type="search" id="buscar-persona" placeholder="Escribe el nombre o correo del profesor…" autocomplete="off" style="max-width:420px">
    <div class="cns-sug" id="sugerencias" hidden></div>

    <label style="margin-top:16px">Observación de la Subdirección (opcional)
      <span class="cns-ayuda">No es parte del acuerdo: en el acta en Word sale como <strong>comentario</strong> junto a la clave del caso, con tu nombre como autor.</span>
      <textarea name="observacion" rows="3" maxlength="3000"><?= e((string) ($a['observacion'] ?? '')) ?></textarea>
    </label>

    <div class="cns-acciones" style="margin-top:18px">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <?php if ($nuevo): ?><button class="btn" type="submit" name="seguir" value="1">Guardar y agregar otro</button><?php endif; ?>
      <a class="btn" href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">Cancelar</a>
    </div>
  </form>
</div>

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

  // Al enviar, el contenido de los lienzos viaja en campos ocultos.
  const form = document.getElementById('form-asunto');
  form.addEventListener('submit', () => {
    document.getElementById('in-solicitud').value = document.getElementById('ed-solicitud').innerHTML;
    document.getElementById('in-antecedentes').value = document.getElementById('ed-antecedentes').innerHTML;
  });

  // Profesores mencionados.
  const caja = document.getElementById('personas'), q = document.getElementById('buscar-persona'), sug = document.getElementById('sugerencias');
  let t = null;
  caja.addEventListener('click', e => { const b = e.target.closest('button'); if (b) b.closest('.cns-chip').remove(); });
  q.addEventListener('input', () => {
    clearTimeout(t); const v = q.value.trim();
    if (v.length < 2) { sug.hidden = true; return; }
    t = setTimeout(async () => {
      try {
        const r = await fetch('<?= e(url('consejos/personas')) ?>?q=' + encodeURIComponent(v), {headers: {'Accept': 'application/json'}});
        const d = await r.json(); sug.innerHTML = '';
        (d.personas || []).forEach(p => {
          if (caja.querySelector('[data-id="' + p.id + '"]')) return;
          const b = document.createElement('button'); b.type = 'button'; b.textContent = p.nombre + ' · ' + p.email;
          b.addEventListener('click', () => {
            const c = document.createElement('span'); c.className = 'cns-chip'; c.dataset.id = p.id;
            c.append(document.createTextNode(p.nombre));
            const i = document.createElement('input'); i.type = 'hidden'; i.name = 'profesores[]'; i.value = p.id; c.append(i);
            const x = document.createElement('button'); x.type = 'button'; x.textContent = '×'; x.setAttribute('aria-label', 'Quitar a ' + p.nombre); c.append(x);
            caja.append(c); q.value = ''; sug.hidden = true;
          });
          sug.append(b);
        });
        sug.hidden = !sug.children.length;
      } catch (_) { sug.hidden = true; }
    }, 250);
  });
})();
</script>
