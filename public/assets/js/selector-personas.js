/* SelectorPersonas — selector de personas estilo Microsoft Teams. Documentación: docs/SELECTOR_DE_PERSONAS.md
 *
 * Todo cuadro donde se capturen nombres de personas debe usar este componente (vista `app/Views/selector_personas.php`):
 * al teclear salen las coincidencias por nombre o correo, al elegir una queda como elemento con una «×» para quitarla
 * y el cuadro sigue disponible para la siguiente. Se activa solo con el atributo `data-selector-personas`.
 *
 * API pública: window.SelectorPersonas.agregar(contenedor, {id, nombre, email}), .quitar(contenedor, clave), .valores(contenedor)
 * y .mencionar(lienzo, contenedor) para escribir «@nombre» dentro de un texto y sumar a la persona al selector.
 */
(() => {
  'use strict';
  const el = (tag, cls, txt) => { const n = document.createElement(tag); if (cls) n.className = cls; if (txt !== undefined) n.textContent = txt; return n; };
  const estados = new WeakMap();

  function montar(raiz) {
    if (estados.has(raiz)) return estados.get(raiz);
    const d = raiz.dataset;
    const max = parseInt(d.max || '0', 10) || 0;
    const libre = d.libre === '1';
    const valorDe = d.valor || 'id'; // id | nombre | email: qué viaja en el formulario
    const campo = d.campo || 'personas[]';
    const url = d.url;
    const elegidos = new Map();
    const caja = el('div', 'sp-caja');
    const q = el('input', 'sp-q'); q.type = 'text'; q.autocomplete = 'off'; q.setAttribute('role', 'combobox'); q.setAttribute('aria-expanded', 'false');
    q.placeholder = d.placeholder || 'Escribe un nombre o correo…'; if (d.etiqueta) q.setAttribute('aria-label', d.etiqueta);
    const lista = el('ul', 'sp-lista'); lista.hidden = true; lista.setAttribute('role', 'listbox');
    const estado = el('span', 'sp-estado'); estado.setAttribute('role', 'status'); estado.setAttribute('aria-live', 'polite');
    raiz.replaceChildren(caja, lista, estado);
    caja.append(q);
    let opciones = [], activa = -1, temporizador = null, peticion = null, turno = 0;

    const claveDe = p => String(valorDe === 'nombre' ? p.nombre : valorDe === 'email' ? (p.email || p.nombre) : (p.id ?? p.nombre)).toLowerCase();
    const valorForm = p => valorDe === 'nombre' ? p.nombre : valorDe === 'email' ? (p.email || p.nombre) : String(p.id ?? p.nombre);

    function pintarElegidos() {
      caja.querySelectorAll('.sp-chip').forEach(c => c.remove());
      elegidos.forEach((p, k) => {
        const c = el('span', 'sp-chip'); c.dataset.clave = k; if (p.id) c.dataset.id = p.id; c.title = p.email || '';
        c.append(document.createTextNode(p.nombre));
        const h = el('input'); h.type = 'hidden'; h.name = campo; h.value = valorForm(p); c.append(h);
        const x = el('button', 'sp-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Quitar a ' + p.nombre);
        x.addEventListener('click', () => { elegidos.delete(k); pintarElegidos(); q.focus(); raiz.dispatchEvent(new CustomEvent('sp:quitar', {detail: p, bubbles: true})); });
        c.append(x); caja.insertBefore(c, q);
      });
      q.hidden = max > 0 && elegidos.size >= max;
      q.placeholder = elegidos.size ? '' : (d.placeholder || 'Escribe un nombre o correo…');
    }

    function agregar(p, silencioso) {
      p = {id: p.id || 0, nombre: String(p.nombre || '').trim(), email: p.email || ''};
      if (!p.nombre) return false;
      const k = claveDe(p);
      if (elegidos.has(k)) return false;
      if (max === 1) elegidos.clear(); else if (max > 0 && elegidos.size >= max) { estado.textContent = 'El máximo es de ' + max + ' personas.'; return false; }
      elegidos.set(k, p); pintarElegidos();
      if (!silencioso) raiz.dispatchEvent(new CustomEvent('sp:agregar', {detail: p, bubbles: true}));
      return true;
    }
    function cerrar() { lista.hidden = true; lista.replaceChildren(); opciones = []; activa = -1; q.setAttribute('aria-expanded', 'false'); }
    function resaltar(i) {
      const li = [...lista.children]; if (!li.length) return;
      activa = (i + li.length) % li.length;
      li.forEach((n, j) => { n.classList.toggle('sp-activa', j === activa); n.setAttribute('aria-selected', j === activa ? 'true' : 'false'); });
      li[activa].scrollIntoView({block: 'nearest'});
    }
    function elegir(i) {
      const o = opciones[i]; if (!o) return;
      agregar(o); q.value = ''; cerrar(); q.focus();
    }
    function mostrar(personas, texto) {
      lista.replaceChildren(); opciones = [];
      personas.filter(p => !elegidos.has(claveDe(p))).forEach(p => opciones.push(p));
      if (libre && texto && !opciones.some(p => p.nombre.toLowerCase() === texto.toLowerCase())) opciones.push({id: 0, nombre: texto, email: '', libre: true});
      opciones.forEach((p, i) => {
        const li = el('li', 'sp-op'); li.setAttribute('role', 'option');
        li.append(el('span', 'sp-nombre', p.libre ? 'Agregar «' + p.nombre + '» tal cual' : p.nombre));
        if (p.email) li.append(el('small', 'sp-correo', p.email));
        li.addEventListener('mousedown', e => { e.preventDefault(); elegir(i); });
        lista.append(li);
      });
      lista.hidden = !opciones.length; q.setAttribute('aria-expanded', opciones.length ? 'true' : 'false');
      estado.textContent = opciones.length ? opciones.length + ' coincidencia' + (opciones.length > 1 ? 's' : '') + '.' : '';
      if (opciones.length) resaltar(0);
    }
    async function buscar(texto) {
      const t = ++turno; peticion?.abort(); peticion = new AbortController();
      try {
        const r = await fetch(url + (url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(texto), {signal: peticion.signal, headers: {Accept: 'application/json'}});
        if (!r.ok) throw new Error();
        const j = await r.json(); if (t !== turno) return;
        mostrar(j.personas || [], texto);
      } catch (e) { if (e.name !== 'AbortError' && t === turno) { mostrar([], texto); estado.textContent = 'No se pudo buscar. Intenta de nuevo.'; } }
    }
    q.addEventListener('input', () => {
      clearTimeout(temporizador); const v = q.value.trim();
      if (v.length < 2) { turno++; peticion?.abort(); if (libre && v) mostrar([], v); else cerrar(); return; }
      temporizador = setTimeout(() => buscar(v), 180);
    });
    q.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { e.preventDefault(); resaltar(activa + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); resaltar(activa - 1); }
      else if (e.key === 'Enter') { if (!lista.hidden && activa >= 0) { e.preventDefault(); elegir(activa); } else if (libre && q.value.trim()) { e.preventDefault(); agregar({nombre: q.value.trim()}); q.value = ''; cerrar(); } else e.preventDefault(); }
      else if (e.key === 'Escape') { cerrar(); }
      else if (e.key === 'Backspace' && !q.value && elegidos.size) { const k = [...elegidos.keys()].pop(); const p = elegidos.get(k); elegidos.delete(k); pintarElegidos(); raiz.dispatchEvent(new CustomEvent('sp:quitar', {detail: p, bubbles: true})); }
    });
    q.addEventListener('blur', () => setTimeout(cerrar, 120));
    caja.addEventListener('click', e => { if (e.target === caja) q.focus(); });

    let inicial = []; try { inicial = JSON.parse(d.seleccion || '[]'); } catch (_) { /* sin selección previa */ }
    inicial.forEach(p => agregar(typeof p === 'string' ? {nombre: p} : p, true));
    pintarElegidos();
    const api = {agregar, valores: () => [...elegidos.values()], quitar: k => { elegidos.delete(String(k).toLowerCase()); pintarElegidos(); }, tiene: p => elegidos.has(claveDe(p)), url};
    estados.set(raiz, api); return api;
  }

  /* «@nombre» dentro de un lienzo (contenteditable): sugiere personas y, al elegir una, deja su nombre en el texto
     y la suma al selector (sin quitarla del texto si luego se quita del selector). */
  function mencionar(lienzo, raiz) {
    const sel = montar(raiz);
    const lista = el('ul', 'sp-lista sp-lista-mencion'); lista.hidden = true; lista.setAttribute('role', 'listbox');
    lienzo.insertAdjacentElement('afterend', lista);
    let opciones = [], activa = -1, rango = null, turno = 0, temporizador = null;
    const cerrar = () => { lista.hidden = true; lista.replaceChildren(); opciones = []; activa = -1; rango = null; };
    const resaltar = i => { const li = [...lista.children]; if (!li.length) return; activa = (i + li.length) % li.length; li.forEach((n, j) => n.classList.toggle('sp-activa', j === activa)); };
    function insertar(i) {
      const p = opciones[i]; if (!p || !rango) return;
      const s = getSelection(); s.removeAllRanges(); s.addRange(rango);
      document.execCommand('insertText', false, p.nombre + ' ');
      sel.agregar(p); cerrar();
    }
    function consulta() {
      const s = getSelection(); if (!s.rangeCount || !s.isCollapsed) return null;
      const n = s.anchorNode; if (!n || n.nodeType !== 3 || !lienzo.contains(n)) return null;
      const antes = n.data.slice(0, s.anchorOffset);
      const m = /(?:^|[\s(])@([^@\n]{0,40})$/.exec(antes); if (!m) return null;
      const r = document.createRange(); r.setStart(n, s.anchorOffset - m[1].length - 1); r.setEnd(n, s.anchorOffset);
      return {texto: m[1].trim(), rango: r};
    }
    lienzo.addEventListener('input', () => {
      const c = consulta(); clearTimeout(temporizador);
      if (!c || c.texto.length < 2) { cerrar(); return; }
      rango = c.rango;
      temporizador = setTimeout(async () => {
        const t = ++turno;
        try {
          const r = await fetch(sel.url + (sel.url.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(c.texto), {headers: {Accept: 'application/json'}});
          const j = await r.json(); if (t !== turno) return;
          lista.replaceChildren(); opciones = (j.personas || []).slice(0, 8);
          opciones.forEach((p, i) => { const li = el('li', 'sp-op'); li.append(el('span', 'sp-nombre', p.nombre), el('small', 'sp-correo', p.email || '')); li.addEventListener('mousedown', e => { e.preventDefault(); insertar(i); }); lista.append(li); });
          lista.hidden = !opciones.length; if (opciones.length) resaltar(0);
        } catch (_) { cerrar(); }
      }, 180);
    });
    lienzo.addEventListener('keydown', e => {
      if (lista.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); resaltar(activa + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); resaltar(activa - 1); }
      else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); insertar(activa); }
      else if (e.key === 'Escape') { e.preventDefault(); cerrar(); }
    });
    lienzo.addEventListener('blur', () => setTimeout(cerrar, 150));
  }

  window.SelectorPersonas = {
    montar,
    mencionar,
    agregar: (raiz, p) => montar(raiz).agregar(p),
    quitar: (raiz, k) => montar(raiz).quitar(k),
    valores: raiz => montar(raiz).valores(),
  };
  const iniciar = () => document.querySelectorAll('[data-selector-personas]').forEach(montar);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
