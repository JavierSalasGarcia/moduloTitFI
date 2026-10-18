(() => {
  document.querySelectorAll('[data-identidad-url]').forEach(form => {
    const email = form.querySelector('[name="email"]');
    const nombre = form.querySelector('[name="nombre"]');
    const status = form.querySelector('[data-identidad-estado]');
    let version = 0;
    let alias = nombre.value;
    email.addEventListener('input', () => {
      version++;
      if (nombre.readOnly) nombre.value = alias;
      nombre.readOnly = false;
      status.textContent = 'El nombre oficial se consultará con este correo.';
    });
    nombre.addEventListener('input', () => { if (!nombre.readOnly) alias = nombre.value; });
    email.addEventListener('blur', async () => {
      if (!email.value || !email.checkValidity()) return;
      const request = ++version;
      status.textContent = 'Consultando el registro de usuarios…';
      try {
        const body = new URLSearchParams({email: email.value, csrf: form.dataset.identidadCsrf});
        const response = await fetch(form.dataset.identidadUrl, {method: 'POST', body, cache: 'no-store'});
        const data = await response.json();
        if (request !== version) return;
        if (!response.ok) throw new Error(data.error || 'No se pudo consultar el registro.');
        nombre.readOnly = data.registrado;
        nombre.value = data.registrado ? data.nombre_real : alias;
        status.textContent = data.registrado
          ? 'Nombre oficial del usuario registrado. No se modifica desde Fila.'
          : 'No se encontró un usuario registrado con este correo. Puedes escribir un alias opcional; no se guardará como nombre oficial.';
      } catch (error) {
        if (request === version) status.textContent = error.message;
      }
    });
  });
})();
