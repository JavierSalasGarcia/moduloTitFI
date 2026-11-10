<script>
// Subida por lotes: cada PDF va en su propia petición, en orden, para no depender de los límites de PHP por envío.
(function () {
  const caja = document.querySelector('[data-ingesta-lote]');
  if (!caja) return;
  const estado = caja.querySelector('[data-ingesta-estado]');
  window.ingestaSubirLote = async function (url, csrf, archivos) {
    const pdfs = Array.from(archivos).filter((f) => /\.pdf$/i.test(f.name));
    let bien = 0, repetidos = 0; const fallos = [];
    for (let i = 0; i < pdfs.length; i++) {
      estado.textContent = 'Subiendo ' + (i + 1) + ' de ' + pdfs.length + ': ' + pdfs[i].name;
      const datos = new FormData();
      datos.set('_csrf', csrf);
      datos.set('archivo', pdfs[i]);
      datos.set('ruta', pdfs[i].webkitRelativePath || pdfs[i].name);
      try {
        const r = await fetch(url, { method: 'POST', body: datos, headers: { 'Accept': 'application/json' } });
        const cuerpo = await r.json().catch(() => ({ ok: false, error: 'Respuesta no válida (' + r.status + ')' }));
        if (cuerpo.ok) { cuerpo.duplicado ? repetidos++ : bien++; } else { fallos.push(pdfs[i].name + ': ' + (cuerpo.error || 'error')); }
      } catch (e) { fallos.push(pdfs[i].name + ': sin conexión'); }
    }
    estado.textContent = bien + ' PDF indizado(s)' + (repetidos ? ', ' + repetidos + ' ya estaban' : '') + (fallos.length ? '. No se pudieron subir: ' + fallos.join('; ') : '.')
      + (archivos.length !== pdfs.length ? ' Se omitieron ' + (archivos.length - pdfs.length) + ' archivo(s) que no son PDF.' : '');
    return { bien, fallos };
  };
  const entrada = caja.querySelector('[data-ingesta-archivos]');
  if (entrada && caja.dataset.url) {
    entrada.addEventListener('change', async () => {
      entrada.disabled = true;
      const r = await window.ingestaSubirLote(caja.dataset.url, caja.dataset.csrf, entrada.files);
      if (r.bien > 0) { setTimeout(() => location.reload(), 1200); } else { entrada.disabled = false; }
    });
  }
})();
</script>
