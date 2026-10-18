(() => {
  'use strict';

  const safeStorage = {
    read(key) {
      try { return JSON.parse(localStorage.getItem(key) || '{}'); }
      catch (_) { return {}; }
    },
    write(key, value) {
      try { localStorage.setItem(key, JSON.stringify(value)); }
      catch (_) { /* La edición sigue disponible durante la sesión. */ }
    },
    remove(key) {
      try { localStorage.removeItem(key); }
      catch (_) { /* Sin almacenamiento persistente, basta redibujar. */ }
    }
  };

  window.PlanChartEditor = function PlanChartEditor(options) {
    const canvas = typeof options.canvas === 'string' ? document.getElementById(options.canvas) : options.canvas;
    const toggle = typeof options.toggle === 'string' ? document.getElementById(options.toggle) : options.toggle;
    const reset = typeof options.reset === 'string' ? document.getElementById(options.reset) : options.reset;
    const status = typeof options.status === 'string' ? document.getElementById(options.status) : options.status;
    let active = false, drag = null, hits = [], layout = {}, loadedKey = '';
    const storageKey = () => `${options.storagePrefix}:${options.key()}`;
    const defaultMessage = 'Activa el ajuste y arrastra títulos, escalas, periodos, leyendas o fuente.';

    function load() {
      const key = storageKey();
      if (key !== loadedKey) { loadedKey = key; layout = safeStorage.read(key); }
    }
    function point(id, x, y) {
      load(); const delta = layout[id] || {x: 0, y: 0};
      return {x: x + Number(delta.x || 0), y: y + Number(delta.y || 0)};
    }
    function begin() { load(); hits = []; }
    function hit(id, label, x, y, width, height) { hits.push({id, label, x, y, width, height}); }
    function coordinates(event) {
      const rect = canvas.getBoundingClientRect();
      return {x: (event.clientX - rect.left) * canvas.width / rect.width, y: (event.clientY - rect.top) * canvas.height / rect.height};
    }
    function targetAt(position) {
      return [...hits].reverse().find(item => position.x >= item.x && position.x <= item.x + item.width && position.y >= item.y && position.y <= item.y + item.height);
    }
    function setStatus(message) { if (status) status.textContent = message || defaultMessage; }
    function setActive(value) {
      active = value; drag = null;
      canvas.classList.toggle('plan-canvas-ajuste', active);
      if (toggle) { toggle.setAttribute('aria-pressed', active ? 'true' : 'false'); toggle.textContent = active ? 'Terminar ajuste' : 'Ajustar elementos'; }
      setStatus(active ? 'Arrastra un elemento de texto. Los datos, colores y tamaños de letra permanecen bloqueados.' : defaultMessage);
    }
    function save() { safeStorage.write(storageKey(), layout); }

    canvas.addEventListener('pointerdown', event => {
      if (!active) return;
      const position = coordinates(event), target = targetAt(position);
      if (!target) { setStatus('Selecciona directamente un título, una escala, los periodos, la leyenda o la fuente.'); return; }
      const current = layout[target.id] || {x: 0, y: 0};
      drag = {target, start: position, original: {x: Number(current.x || 0), y: Number(current.y || 0)}};
      canvas.setPointerCapture(event.pointerId); canvas.classList.add('plan-canvas-arrastrando');
      setStatus(`Moviendo: ${target.label}.`); event.preventDefault();
    });
    canvas.addEventListener('pointermove', event => {
      if (!active) return;
      const position = coordinates(event);
      if (!drag) { canvas.style.cursor = targetAt(position) ? 'grab' : 'default'; return; }
      layout[drag.target.id] = {x: drag.original.x + position.x - drag.start.x, y: drag.original.y + position.y - drag.start.y};
      options.redraw(); event.preventDefault();
    });
    const finish = event => {
      if (!drag) return;
      save(); setStatus(`${drag.target.label} colocado. La posición se usará al descargar el JPG.`);
      drag = null; canvas.classList.remove('plan-canvas-arrastrando');
      if (event?.pointerId != null && canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    };
    canvas.addEventListener('pointerup', finish); canvas.addEventListener('pointercancel', finish);
    toggle?.addEventListener('click', () => setActive(!active));
    reset?.addEventListener('click', () => {
      safeStorage.remove(storageKey()); layout = {}; loadedKey = storageKey(); options.redraw();
      setStatus('Se restablecieron las posiciones recomendadas para esta gráfica.');
    });
    setStatus();
    return {begin, point, hit, isActive: () => active};
  };
})();
