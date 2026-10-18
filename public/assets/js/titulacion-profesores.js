// Sugerencias de profesores registrados al capturar asesor/coasesor: por nombre o por correo.
(function () {
  var inputs = document.querySelectorAll('[data-prof], [data-prof-correo]');
  if (!inputs.length) { return; }
  var url = (document.currentScript && document.currentScript.dataset.url) || '/titulacion/profesores';
  var cache = {}, temporizador;

  function par(rol) {
    return {
      nombre: document.querySelector('[data-prof="' + rol + '"]'),
      correo: document.querySelector('[data-prof-correo="' + rol + '"]')
    };
  }
  function buscar(q, cb) {
    if (q.length < 3) { cb([]); return; }
    if (cache[q]) { cb(cache[q]); return; }
    fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (d) { cache[q] = d; cb(d); })
      .catch(function () { cb([]); });
  }
  inputs.forEach(function (el) {
    var rol = el.getAttribute('data-prof') || el.getAttribute('data-prof-correo');
    var porNombre = el.hasAttribute('data-prof');
    var lista = document.getElementById('dl-prof-' + rol + (porNombre ? '' : '-c'));
    el.addEventListener('input', function () {
      clearTimeout(temporizador);
      temporizador = setTimeout(function () {
        buscar(el.value.trim(), function (datos) {
          if (!lista) { return; }
          lista.innerHTML = '';
          datos.forEach(function (p) {
            var o = document.createElement('option');
            o.value = porNombre ? p.nombre : p.email;
            o.label = porNombre ? p.email : p.nombre;
            o.dataset.nombre = p.nombre; o.dataset.email = p.email;
            lista.appendChild(o);
          });
        });
      }, 250);
    });
    el.addEventListener('change', function () {
      if (!lista) { return; }
      var o = Array.prototype.find.call(lista.options, function (x) { return x.value === el.value; });
      if (!o) { return; }
      var p = par(rol);
      if (p.nombre) { p.nombre.value = o.dataset.nombre; }
      if (p.correo) { p.correo.value = o.dataset.email; }
    });
  });
})();
