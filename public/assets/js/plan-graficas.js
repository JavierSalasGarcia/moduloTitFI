(() => {
  const script = document.currentScript || document.querySelector('script[data-datos]');
  const base = script.dataset.datos;
  const $ = (id) => document.getElementById(id);
  const sel = { cat: $('gr-categoria'), amb: $('gr-ambito'), tipo: $('gr-tipo'), q: $('gr-buscar') };
  const out = $('gr-resultado'), estado = $('gr-estado');
  const ids = [...sel.cat.options].map((o) => o.value).filter(Boolean);
  const cache = {};
  // Paleta del editor de gráficas del Plan (verde, dorado, ladrillo) y complementarios del mismo tono.
  const colores = ['#486b55', '#b78a3b', '#8b5454', '#3e786e', '#795a25', '#6f7f9a', '#a7b6a5', '#384033'];
  const ANIO_INICIO = 2018;
  let contador = 0;
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const TIEMPO = /^(ciclo[ _]?escolar|a.o)$/i;
  const OCULTAR = /^(proyectos|longitud|latitud)$/i;
  const fmt = (n) => (typeof n === 'number' ? new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(n) : String(n ?? ''));
  const esPct = (etiqueta, fmts) => (fmts[etiqueta] || '').includes('%')
    || (!fmts[etiqueta] && !/_pct$/i.test(etiqueta) && /%|tcma|crecimiento/i.test(etiqueta));

  function cargar(id) {
    if (!cache[id]) {
      cache[id] = fetch(`${base}/${id}`, { headers: { Accept: 'application/json' } })
        .then((r) => { if (!r.ok) throw new Error(id); return r.json(); });
    }
    return cache[id];
  }

  function formatos(ind) {
    const map = {};
    for (const [modelo, etiqueta] of Object.entries(ind.campos_modelo || {})) map[etiqueta] = (ind.formatos || {})[modelo] || '';
    return map;
  }
  function escala(v, etiqueta, fmts) {
    return esPct(etiqueta, fmts) && Math.abs(v) < 5 ? v * 100 : v;
  }
  function darFormato(v, etiqueta, fmts, cat) {
    if (typeof v !== 'number') return v == null ? '—' : String(v);
    if (/_pct$/i.test(etiqueta)) return fmt(v) + ' %';
    if (esPct(etiqueta, fmts) && Math.abs(v) < 5) return fmt(v * 100) + ' %';
    if (!fmts[etiqueta] && /eficiencia|abandono|reprobad|titulaci/i.test(cat)) return fmt(v) + ' %';
    return fmt(v);
  }
  function unidad(metrica, cat, fmts) {
    if (/paridad/i.test(metrica)) return 'razón';
    const pct = /%|tasa|índice|indice|eficiencia|abandono|reprobaci[oó]n|titulaci[oó]n|crecimiento|porcentaje|pct/i;
    if (/solicitud|alumno|beca|personal|matr[ií]cula|ingreso|egresado|tutor|snii|servicio.social|participante/i.test(metrica) && !pct.test(metrica)) return 'personas';
    if (esPct(metrica, fmts) || pct.test(`${metrica} ${cat}`)) return '%';
    return 'unidades reportadas';
  }

  function construirSeries(ind, cat) {
    const claves = Object.keys(ind.registros[0]).filter((k) => !OCULTAR.test(k));
    const tk = claves.find((k) => TIEMPO.test(k));
    if (!tk) return null;
    // Las gráficas inician en 2018: se descartan periodos cuyo año (o primer año del ciclo) sea anterior.
    const filas = ind.registros.filter((r) => parseInt(String(r[tk]).slice(0, 4), 10) >= ANIO_INICIO);
    if (!filas.length) return null;
    const nums = claves.filter((k) => k !== tk && filas.some((r) => typeof r[k] === 'number'));
    const cats = claves.filter((k) => k !== tk && !nums.includes(k));
    if (!nums.length) return null;
    const fmts = formatos(ind);
    const tiempos = [...new Set(filas.map((r) => String(r[tk])))].sort();
    const series = new Map();
    for (const r of filas) {
      for (const n of nums) {
        if (typeof r[n] !== 'number') continue;
        const partes = cats.map((c) => r[c]).filter((x) => x !== '' && x != null);
        let nombre = partes.join(' · ');
        if (nums.length > 1) nombre = nombre ? `${nombre} – ${n}` : n;
        if (!nombre) nombre = n;
        if (!series.has(nombre)) series.set(nombre, { nombre, metrica: n, valores: {} });
        series.get(nombre).valores[String(r[tk])] = r[n];
      }
    }
    return { tk, nums, cats, fmts, tiempos, series: [...series.values()], cat };
  }

  // Mismo estilo que el editor de gráficas del Plan: lienzo 1200×700, cuadrícula #dce3da,
  // tipografía Inter / DM Sans, títulos de ejes, leyenda inferior y fuente reubicables.
  function dibujar(canvas, editor, modelo, tipo, idCat) {
    const ctx = canvas.getContext('2d'), W = canvas.width, H = canvas.height;
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);
    editor.begin();
    const left = 150, right = 1130, top = 55, bottom = 420, plotH = bottom - top;
    const n = modelo.series.length, nT = modelo.tiempos.length;
    const vals = [];
    modelo.series.forEach((s) => Object.values(s.valores).forEach((v) => vals.push(escala(v, s.metrica, modelo.fmts))));
    let rawMin = Math.min(...vals), rawMax = Math.max(...vals);
    if (tipo === 'barra') { rawMin = Math.min(0, rawMin); rawMax = Math.max(0, rawMax); }
    const pad = tipo === 'barra' ? (rawMax - rawMin || 1) * .06 : (rawMax - rawMin || Math.max(Math.abs(rawMax) * .1, 1)) * .14;
    let min = tipo === 'barra' && rawMin === 0 ? 0 : rawMin - pad;
    if (rawMin >= 0 && min < 0) min = 0; // magnitudes no negativas: la escala no baja de cero
    const max = rawMax + pad, range = max - min;
    const banda = (right - left) / nT;
    const xAt = (i) => (tipo === 'barra' ? left + banda * i + banda / 2 : (nT === 1 ? (left + right) / 2 : left + (right - left) * i / (nT - 1)));
    const yAt = (v) => bottom - (v - min) / range * plotH;
    const scale = editor.point('y-scale', 0, 0);
    ctx.strokeStyle = '#dce3da'; ctx.lineWidth = 1; ctx.fillStyle = '#526055'; ctx.font = '15px Inter, Arial'; ctx.textAlign = 'right';
    for (let i = 0; i <= 4; i++) {
      const y = bottom - i * plotH / 4;
      ctx.beginPath(); ctx.moveTo(left, y); ctx.lineTo(right, y); ctx.stroke();
      ctx.fillText(fmt(min + range * i / 4), left - 14 + scale.x, y + 5 + scale.y);
    }
    ctx.textAlign = 'left';
    editor.hit('y-scale', 'Escala vertical', 30 + scale.x, top - 14 + scale.y, left - 40, plotH + 30);
    if (tipo === 'barra' && min < 0) { ctx.strokeStyle = '#a7b6a5'; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(left, yAt(0)); ctx.lineTo(right, yAt(0)); ctx.stroke(); }
    const periods = editor.point('periods', 0, 0);
    modelo.series.forEach((s, si) => {
      const color = colores[si % colores.length];
      const pts = [];
      modelo.tiempos.forEach((t, i) => { if (s.valores[t] != null) pts.push({ i, v: escala(s.valores[t], s.metrica, modelo.fmts), raw: s.valores[t] }); });
      if (tipo === 'barra') {
        const bw = Math.min(70, banda * .8 / n);
        ctx.fillStyle = color;
        pts.forEach((p) => {
          const x = xAt(p.i) - bw * n / 2 + bw * si;
          ctx.fillRect(x, Math.min(yAt(p.v), yAt(0)), bw - 2, Math.max(1, Math.abs(yAt(p.v) - yAt(0))));
        });
      } else {
        ctx.strokeStyle = color; ctx.lineWidth = 4; ctx.lineJoin = 'round'; ctx.beginPath();
        pts.forEach((p, k) => (k ? ctx.lineTo(xAt(p.i), yAt(p.v)) : ctx.moveTo(xAt(p.i), yAt(p.v)))); ctx.stroke();
        pts.forEach((p, k) => {
          const ultimo = n === 1 && k === pts.length - 1;
          ctx.fillStyle = ultimo ? '#b78a3b' : color; ctx.beginPath(); ctx.arc(xAt(p.i), yAt(p.v), ultimo ? 6 : 4, 0, 2 * Math.PI); ctx.fill();
        });
      }
      // Etiquetas de valor: serie única en primero/último (o ≤5 puntos); varias series, sólo el último valor.
      pts.forEach((p, k) => {
        const mostrar = n === 1 ? (k === 0 || k === pts.length - 1 || pts.length <= 5) : (n <= 3 && k === pts.length - 1);
        if (!mostrar) return;
        ctx.fillStyle = n === 1 ? '#384033' : color; ctx.font = '700 13px "DM Sans", Arial'; ctx.textAlign = 'center';
        const y = tipo === 'barra' ? Math.min(yAt(p.v), yAt(0)) : yAt(p.v);
        ctx.fillText(darFormato(p.raw, s.metrica, modelo.fmts, modelo.cat), xAt(p.i) + (tipo === 'barra' ? bwOffset(n, banda, si) : 0), Math.max(22, y - 12));
      });
    });
    ctx.textAlign = 'left';
    modelo.tiempos.forEach((t, i) => {
      if (!(nT <= 8 || i === 0 || i === nT - 1 || i % Math.ceil(nT / 7) === 0)) return;
      ctx.save(); ctx.translate(xAt(i) + periods.x, bottom + 15 + periods.y); ctx.rotate(-Math.PI / 5);
      ctx.fillStyle = '#384033'; ctx.font = '13px Inter, Arial'; ctx.textAlign = 'right'; ctx.fillText(t, 0, 0); ctx.restore();
    });
    editor.hit('periods', 'Periodos del eje horizontal', left - 25 + periods.x, bottom + 2 + periods.y, right - left + 50, 78);
    // Títulos de ejes
    const xp = editor.point('x-title', W / 2, 515), yp = editor.point('y-title', 28, H / 2 - 25);
    const metrica = modelo.nums.length <= 2 ? modelo.nums.join(' / ') : 'series comparadas';
    ctx.fillStyle = '#334539'; ctx.font = '600 14px Inter, Arial'; ctx.textAlign = 'center';
    ctx.fillText(/^a/i.test(modelo.tk) ? 'Periodo (año)' : 'Periodo (ciclo escolar)', xp.x, xp.y);
    ctx.save(); ctx.translate(yp.x, yp.y); ctx.rotate(-Math.PI / 2);
    ctx.fillText(`${modelo.cat}: ${metrica.replaceAll('_', ' ')} (${unidad(modelo.nums[0], modelo.cat, modelo.fmts)})`, 0, 0, H - 125); ctx.restore(); ctx.textAlign = 'left';
    editor.hit('x-title', 'Título del eje horizontal', xp.x - 190, xp.y - 22, 380, 34);
    editor.hit('y-title', 'Título del eje vertical', yp.x - 18, 70, 38, H - 165);
    // Leyenda inferior (reubicable)
    const lg = editor.point('legend', 0, 0);
    ctx.font = '13px Inter, Arial';
    const items = modelo.series.map((s, i) => ({ nombre: s.nombre, color: colores[i % colores.length], w: Math.min(430, ctx.measureText(s.nombre).width) + 25 + 30 }));
    const filas = []; let fila = [], ancho = 0;
    items.forEach((it) => { if (fila.length && ancho + it.w > 1000) { filas.push(fila); fila = []; ancho = 0; } fila.push(it); ancho += it.w; });
    if (fila.length) filas.push(fila);
    let anchoMax = 0;
    filas.slice(0, 3).forEach((f, r) => {
      const total = f.reduce((a, b) => a + b.w, 0); anchoMax = Math.max(anchoMax, total);
      let x = (W - total) / 2 + lg.x; const y = 555 + r * 26 + lg.y;
      f.forEach((it) => { ctx.fillStyle = it.color; ctx.fillRect(x, y, 18, 4); ctx.fillStyle = '#334539'; ctx.fillText(it.nombre, x + 25, y + 6, it.w - 30); x += it.w; });
    });
    editor.hit('legend', 'Leyenda', (W - anchoMax) / 2 - 15 + lg.x, 535 + lg.y, anchoMax + 30, 26 * Math.min(3, filas.length) + 22);
    // Fuente
    const fp = editor.point('source', 88, H - 24);
    ctx.fillStyle = '#526055'; ctx.font = '12px Inter, Arial';
    const etiqueta = `Fuente: Sistema de Planeación y Desarrollo Institucional (SPDI, 2026b), tablero ${idCat}.`;
    ctx.fillText(etiqueta, fp.x, fp.y);
    editor.hit('source', 'Fuente', fp.x - 8, fp.y - 18, Math.min(760, ctx.measureText(etiqueta).width + 16), 28);
  }
  function bwOffset(n, banda, si) {
    const bw = Math.min(70, banda * .8 / n);
    return -bw * n / 2 + bw * si + bw / 2;
  }

  function tablaDatos(modelo) {
    let h = `<table><thead><tr><th>${esc(modelo.tk)}</th>${modelo.series.map((s) => `<th>${esc(s.nombre)}</th>`).join('')}</tr></thead><tbody>`;
    for (const t of modelo.tiempos) {
      h += `<tr><td>${esc(t)}</td>${modelo.series.map((s) => `<td>${s.valores[t] == null ? '—' : esc(darFormato(s.valores[t], s.metrica, modelo.fmts, modelo.cat))}</td>`).join('')}</tr>`;
    }
    return h + '</tbody></table>';
  }

  function tarjetaGrafica(ind, cat, tipo, idCat, ambito) {
    const modelo = construirSeries(ind, cat);
    if (!modelo) return null;
    const uid = `gr-${++contador}`;
    const card = document.createElement('article');
    card.className = 'gr-tarjeta';
    const titulo = modelo.nums.length <= 2 ? modelo.nums.join(' / ') : cat;
    card.dataset.texto = (titulo + ' ' + modelo.series.map((s) => s.nombre).join(' ') + ' ' + modelo.tiempos.join(' ')).toLowerCase();
    card.innerHTML = `<div class="plan-bloque-cabeza"><div><h3>${esc(titulo.replaceAll('_', ' '))}</h3><p class="gr-sub">Visual ${esc(ind.visual)}${modelo.cats.length ? ' · por ' + esc(modelo.cats.join(', ')) : ''} · ${esc(modelo.tiempos[0])} a ${esc(modelo.tiempos[modelo.tiempos.length - 1])}</p></div>`
      + `<button type="button" class="plan-btn plan-btn-primario" id="${uid}-jpg">Descargar gráfica JPG</button></div>`
      + `<div class="plan-grafica-ajustes"><button type="button" class="plan-btn plan-btn-claro" id="${uid}-aj">Ajustar elementos</button><button type="button" class="plan-btn plan-btn-claro" id="${uid}-rs">Restablecer posiciones</button><span id="${uid}-st" role="status" aria-live="polite"></span></div>`
      + `<div class="plan-grafica"><canvas id="${uid}-cv" width="1200" height="700" role="img" aria-label="${esc('Gráfica de ' + titulo + ': ' + cat + ', ' + (ambito || 'Facultad de Ingeniería'))}"></canvas></div>`;
    const canvas = card.querySelector('canvas');
    const editor = window.PlanChartEditor({
      canvas, toggle: card.querySelector(`#${uid}-aj`), reset: card.querySelector(`#${uid}-rs`), status: card.querySelector(`#${uid}-st`),
      storagePrefix: 'plan-chart-layout-v1',
      key: () => `grafs:${idCat}:${ind.visual}:${ambito || 'facultad'}:${sel.tipo.value}`,
      redraw: () => dibujar(canvas, editor, modelo, sel.tipo.value, idCat),
    });
    const redibujar = () => dibujar(canvas, editor, modelo, sel.tipo.value, idCat);
    redibujar();
    card.querySelector(`#${uid}-jpg`).addEventListener('click', () => {
      redibujar();
      const a = document.createElement('a');
      a.download = `${idCat}_${ind.visual}_${(ambito || 'facultad').replace(/\W+/g, '_')}.jpg`;
      a.href = canvas.toDataURL('image/jpeg', .94); a.click();
    });
    const d = document.createElement('details');
    d.innerHTML = `<summary>Ver datos</summary><div class="plan-tabla-scroll">${tablaDatos(modelo)}</div>`;
    card.appendChild(d);
    return card;
  }

  function tarjetaClaves(inds, cat) {
    const items = [];
    for (const ind of inds) {
      const fmts = formatos(ind), r = ind.registros[0] || {};
      for (const [k, v] of Object.entries(r)) if (!OCULTAR.test(k)) items.push([k, darFormato(v, k, fmts, cat)]);
    }
    if (!items.length) return null;
    const card = document.createElement('article');
    card.className = 'gr-tarjeta gr-ancho';
    card.dataset.texto = items.map((i) => i[0] + ' ' + i[1]).join(' ').toLowerCase();
    card.innerHTML = `<h3>Indicadores clave del último periodo</h3><div class="gr-kpis">${items.map(([k, v]) => `<div class="gr-kpi"><strong>${esc(v)}</strong><span>${esc(k)}</span></div>`).join('')}</div>`;
    return card;
  }

  function actualizarAmbitos(datos) {
    const actual = sel.amb.value, nombres = new Set();
    datos.forEach((d) => d && Object.keys(d.carreras || {}).forEach((n) => nombres.add(n)));
    sel.amb.innerHTML = '<option value="">Facultad de Ingeniería</option>'
      + [...nombres].sort().map((n) => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
    sel.amb.value = nombres.has(actual) ? actual : '';
  }

  function aplicarBusqueda() {
    const q = sel.q.value.trim().toLowerCase();
    let visibles = 0;
    out.querySelectorAll('.gr-tarjeta').forEach((c) => { const ok = !q || c.dataset.texto.includes(q); c.hidden = !ok; if (ok) visibles++; });
    out.querySelectorAll('.gr-bloque').forEach((b) => { b.hidden = !!q && !b.querySelector('.gr-tarjeta:not([hidden])'); });
    estado.textContent = `${visibles} tarjeta${visibles === 1 ? '' : 's'} mostrada${visibles === 1 ? '' : 's'}.`;
  }

  async function pintar() {
    const lista = sel.cat.value ? [sel.cat.value] : ids;
    estado.textContent = 'Cargando datos…';
    const params = new URLSearchParams();
    if (sel.cat.value) params.set('cat', sel.cat.value);
    if (sel.amb.value) params.set('ambito', sel.amb.value);
    if (sel.tipo.value !== 'linea') params.set('tipo', sel.tipo.value);
    try { history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : '')); } catch (e) { /* sin historial */ }
    const datos = await Promise.all(lista.map((id) => cargar(id).catch(() => null)));
    actualizarAmbitos(datos);
    out.innerHTML = '';
    for (const d of datos) {
      if (!d) continue;
      const seccion = sel.amb.value ? d.carreras[sel.amb.value] : d.facultad;
      const bloque = document.createElement('section');
      bloque.className = 'gr-bloque';
      bloque.innerHTML = `<div class="gr-seccion"><h2>${esc(d.id)} · ${esc(d.categoria)}</h2><small>${esc(sel.amb.value || 'Facultad de Ingeniería')}</small></div>`;
      const rejilla = document.createElement('div');
      rejilla.className = 'gr-rejilla';
      if (!seccion || !seccion.indicadores.length) {
        rejilla.innerHTML = '<p class="plan-vacio">Este indicador no publica datos para el ámbito elegido.</p>';
      } else {
        const unicos = seccion.indicadores.filter((i) => i.registros.length <= 1);
        const series = seccion.indicadores.filter((i) => i.registros.length > 1);
        const kpi = tarjetaClaves(unicos, d.categoria);
        if (kpi) rejilla.appendChild(kpi);
        for (const ind of series) { const c = tarjetaGrafica(ind, d.categoria, sel.tipo.value, d.id, sel.amb.value); if (c) rejilla.appendChild(c); }
      }
      bloque.appendChild(rejilla);
      out.appendChild(bloque);
    }
    aplicarBusqueda();
  }

  const url = new URLSearchParams(location.search);
  if (ids.includes(url.get('cat'))) sel.cat.value = url.get('cat');
  if (url.get('tipo') === 'barra') sel.tipo.value = 'barra';
  const ambInicial = url.get('ambito') || '';
  if (ambInicial) {
    sel.amb.insertAdjacentHTML('beforeend', `<option value="${esc(ambInicial)}">${esc(ambInicial)}</option>`);
    sel.amb.value = ambInicial;
  }
  sel.cat.addEventListener('change', pintar);
  sel.amb.addEventListener('change', pintar);
  sel.tipo.addEventListener('change', pintar);
  sel.q.addEventListener('input', aplicarBusqueda);
  pintar();
})();
