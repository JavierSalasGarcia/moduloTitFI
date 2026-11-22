// Sugerencias de profesores registrados (asesor, coasesor, jurado, responsable…): por nombre o por correo.
// Usa el componente SelectorPersonas (docs/SELECTOR_DE_PERSONAS.md): al teclear salen las coincidencias y, al elegir una,
// se rellenan el nombre, el correo, el grado y el género de la fila (los campos que existan con data-prof-*).
(function () {
  var url = (document.currentScript && document.currentScript.dataset.url) || '/titulacion/profesores';

  function par(rol) {
    return {
      nombre: document.querySelector('[data-prof="' + rol + '"]'),
      correo: document.querySelector('[data-prof-correo="' + rol + '"]'),
      grado: document.querySelector('[data-prof-grado="' + rol + '"]'),
      genero: document.querySelector('[data-prof-genero="' + rol + '"]')
    };
  }

  function iniciar() {
    if (!window.SelectorPersonas) { return; }
    document.querySelectorAll('[data-prof], [data-prof-correo]').forEach(function (el) {
      var rol = el.getAttribute('data-prof') || el.getAttribute('data-prof-correo');
      var porNombre = el.hasAttribute('data-prof');
      window.SelectorPersonas.sugerir(el, {
        url: url,
        min: 2,
        formato: function (p) { return porNombre ? p.nombre : p.email; },
        alElegir: function (p) {
          var f = par(rol);
          if (f.nombre) { f.nombre.value = p.nombre; }
          if (f.correo) { f.correo.value = p.email; }
          if (f.grado) { f.grado.value = p.grado || ''; }
          if (f.genero) { f.genero.value = p.genero || ''; }
        }
      });
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
})();
