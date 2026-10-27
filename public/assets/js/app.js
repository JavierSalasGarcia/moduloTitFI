/**
 * Plataforma FI · Interacciones globales mínimas (JS progresivo).
 * Menú lateral, confirmaciones y filas-enlace. Sin lógica de negocio.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Limpieza de recibos guardados por versiones anteriores. Los recibos de
    // votación ya no deben persistir asociados a este navegador.
    try {
      for (var i = localStorage.length - 1; i >= 0; i--) {
        var clave = localStorage.key(i);
        if (clave && clave.indexOf('fi_voto_') === 0) localStorage.removeItem(clave);
      }
    } catch (e) {}

    var boton = document.getElementById('btn-menu-movil');
    var menu = document.getElementById('menu-lateral');

    // The shared accessible navigation is initialized in ux.js.

    document.querySelectorAll('.menu-seccion-titulo').forEach(function (titulo) {
      titulo.addEventListener('click', function () {
        var seccion = titulo.closest('.menu-seccion');
        if (!seccion) return;
        seccion.classList.toggle('colapsada');
        titulo.setAttribute('aria-expanded', seccion.classList.contains('colapsada') ? 'false' : 'true');
      });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (formulario) {
      formulario.addEventListener('submit', function (evento) {
        var mensaje = formulario.getAttribute('data-confirm') || '¿Confirmas esta acción?';
        if (!window.confirm(mensaje)) evento.preventDefault();
      });
    });

    // Filas de tabla que navegan al detalle (el <a> interno sigue funcionando sin JS)
    document.querySelectorAll('.fila-enlace[data-href]').forEach(function (fila) {
      fila.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, form')) return;
        window.location.href = fila.dataset.href;
      });
    });

  });
})();
