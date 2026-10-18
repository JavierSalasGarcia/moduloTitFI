<div class="barra-acciones">
  <a class="btn btn-mini" href="<?= url('reuniones') ?>">← Regresar</a>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 24px;">
  <p class="texto-secundario">Completa los datos, elige horarios e invita a las personas.</p>
  
  <form method="post" enctype="multipart/form-data" action="<?= url('reuniones') ?>" data-wizard data-draft="reunion">
    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <fieldset class="ux-step" data-title="Datos"><legend>Datos de la reunión</legend>
    
    <?php if (($usuario['rol'] ?? '') === 'coordinador'): ?>
      <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569;">
        Organizador
        <select name="organizador_id" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; background: #fff;">
          <option value="<?= (int) $usuario['id'] ?>">Yo: <?= e($usuario['nombre']) ?></option>
          <?php foreach ($usuarios as $usuarioItem): if ((int) $usuarioItem['id'] === (int) $usuario['id']) continue; ?>
            <option value="<?= (int) $usuarioItem['id'] ?>"><?= e($usuarioItem['nombre']) ?> · <?= e($usuarioItem['email']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569;">
      Asunto
      <input type="text" name="asunto" required maxlength="180" placeholder="Asunto de la reunión" style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
    </label>

    <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569;">
      Descripción
      <textarea name="descripcion" rows="3" placeholder="Descripción que se enviará en la invitación..." style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none; resize: vertical;"></textarea>
    </label>

    </fieldset><fieldset class="ux-step" data-title="Horarios"><legend>Elige fechas y horarios</legend>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
      <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569;">
        Fecha inicial de rango
        <input type="date" name="rango_inicio" id="rango_inicio" required style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
      </label>
      <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569;">
        Fecha final de rango
        <input type="date" name="rango_fin" id="rango_fin" required style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
      </label>
    </div>

    <!-- CALENDARIO VISUAL DE SELECCIÓN DE HORARIOS -->
    <div style="margin-top: 10px;">
      <span style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;">Horarios propuestos (Haz clic en los bloques de 60 minutos para seleccionar)</span>
      
      <div id="calendario-contenedor" style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; background: #f8fafc; min-height: 100px;">
        <p id="calendario-mensaje" style="color: #64748b; text-align: center; margin: 20px 0;">Selecciona el rango de fechas arriba para mostrar la cuadrícula de horarios.</p>
        <div id="calendario-grid" style="display: none; overflow-x: auto; gap: 12px; padding-bottom: 8px;"></div>
      </div>
    </div>

    </fieldset><fieldset class="ux-step" data-title="Invitados"><legend>Personas invitadas</legend>
    <?= \App\Core\RecipientGroups::selector('grupos_invitados','Invitar a grupos guardados') ?>
    <!-- INVITADOS DINÁMICOS -->
    <div style="margin-top: 10px;">
      <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; color: #475569; position: relative;">
        Buscar y añadir invitados (Internos o Externos)
        <input type="text" id="buscador-invitados" placeholder="Escribe el nombre o correo del invitado..." autocomplete="off" style="padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;">
        
        <!-- Dropdown de autocompletado -->
        <div id="sugerencias-contenedor" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); z-index: 100; max-height: 200px; overflow-y: auto; margin-top: 4px;"></div>
      </label>

      <!-- Botón dinámico para añadir externo -->
      <div id="boton-externo-contenedor" style="margin-top: 8px; display: none;">
        <button type="button" id="btn-add-externo" class="btn" style="background: #ffedd5; color: #ea580c; border: 1px solid #fdba74; font-weight: 600; width: 100%; text-align: left; padding: 8px 12px; border-radius: 8px;">
          + Añadir a <span id="correo-externo-texto"></span> (Invitado Externo)
        </button>
      </div>

      <!-- Lista de invitados agregados -->
      <div id="lista-invitados-agregados" style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;"></div>
    </div>

    </fieldset>
    <!-- Campos ocultos para enviar al backend -->
    <input type="hidden" name="horarios_json" id="horarios_json" value="[]">
    <div id="hidden-inputs-invitados"></div>
    <input type="hidden" name="invitados_externos" id="invitados_externos" value="">

    <!-- Envío -->
    <button class="btn btn-primario" type="submit" style="padding: 12px; font-size: 16px; font-weight: 600; border-radius: 8px; margin-top: 12px; background: #2563eb; color: #fff;">Crear Convocatoria</button>
  </form>
</div>

<script>
(() => {
  // Lista de usuarios interna del sistema
  const usuariosDb = <?= json_encode(array_map(static fn($u) => [
      'id' => (int) $u['id'],
      'nombre' => $u['nombre'],
      'email' => strtolower($u['email'])
  ], $usuarios)) ?>;

  const rangoInicio = document.getElementById('rango_inicio');
  const rangoFin = document.getElementById('rango_fin');
  const calMensaje = document.getElementById('calendario-mensaje');
  const calGrid = document.getElementById('calendario-grid');
  const inputHorarios = document.getElementById('horarios_json');
  
  const buscador = document.getElementById('buscador-invitados');
  const sugerencias = document.getElementById('sugerencias-contenedor');
  const botonExternoCont = document.getElementById('boton-externo-contenedor');
  const correoExternoTexto = document.getElementById('correo-externo-texto');
  const btnAddExterno = document.getElementById('btn-add-externo');
  
  const listaAgregados = document.getElementById('lista-invitados-agregados');
  const hiddenGuestsCont = document.getElementById('hidden-inputs-invitados');
  const hiddenExternosInput = document.getElementById('invitados_externos');

  const horariosSeleccionados = new Set();
  const invitadosInternos = new Set();
  const invitadosExternos = new Set();

  // HORAS DE 08:00 A 20:00 (12 bloques de 60 mins)
  const bloquesHoras = [];
  for (let h = 8; h < 20; h++) {
    const pad = (n) => String(n).padStart(2, '0');
    bloquesHoras.push({ inicio: `${pad(h)}:00`, fin: `${pad(h+1)}:00` });
  }

  // Actualizar calendario visual
  const actualizarCalendario = () => {
    const inicioStr = rangoInicio.value;
    const finStr = rangoFin.value;
    if (!inicioStr || !finStr || inicioStr > finStr) {
      calMensaje.style.display = 'block';
      calGrid.style.display = 'none';
      return;
    }

    const dInicio = new Date(inicioStr + 'T00:00:00');
    const dFin = new Date(finStr + 'T00:00:00');
    
    // Limitar rango visual a max 15 días para evitar colapsar la pantalla
    const diffDays = Math.ceil((dFin - dInicio) / (1000 * 60 * 60 * 24)) + 1;
    if (diffDays > 15) {
      calMensaje.textContent = 'El rango de fechas es muy amplio. Selecciona un rango de máximo 15 días para la selección visual.';
      calMensaje.style.display = 'block';
      calGrid.style.display = 'none';
      return;
    }

    calMensaje.style.display = 'none';
    calGrid.style.display = 'flex';
    calGrid.innerHTML = '';

    for (let i = 0; i < diffDays; i++) {
      const actual = new Date(dInicio);
      actual.setDate(dInicio.getDate() + i);
      const diaStr = actual.toISOString().split('T')[0];
      
      const columna = document.createElement('div');
      columna.style.flex = '1';
      columna.style.minWidth = '140px';
      columna.style.background = '#fff';
      columna.style.border = '1px solid #e2e8f0';
      columna.style.borderRadius = '8px';
      columna.style.overflow = 'hidden';

      // Header del día
      const header = document.createElement('div');
      header.style.background = '#f1f5f9';
      header.style.padding = '8px';
      header.style.textAlign = 'center';
      header.style.fontWeight = 'bold';
      header.style.fontSize = '12px';
      header.style.borderBottom = '1px solid #e2e8f0';
      header.style.color = '#334155';
      
      // Formato legible: Lunes 20, etc.
      const opciones = { weekday: 'short', day: 'numeric', month: 'short' };
      header.textContent = actual.toLocaleDateString('es-ES', opciones);
      columna.appendChild(header);

      // Bloques de horas
      bloquesHoras.forEach(b => {
        const slotKey = `${diaStr}T${b.inicio}:00`;
        const slotEndKey = `${diaStr}T${b.fin}:00`;
        
        const slot = document.createElement('button');
        slot.type = 'button';
        slot.style.display = 'block';
        slot.style.width = '100%';
        slot.style.padding = '10px 6px';
        slot.style.fontSize = '11px';
        slot.style.border = 'none';
        slot.style.background = horariosSeleccionados.has(slotKey) ? '#3b82f6' : 'transparent';
        slot.style.color = horariosSeleccionados.has(slotKey) ? '#fff' : '#475569';
        slot.style.borderBottom = '1px dashed #e2e8f0';
        slot.style.cursor = 'pointer';
        slot.style.transition = 'all 0.15s ease';
        slot.textContent = `${b.inicio} - ${b.fin}`;
        slot.setAttribute('aria-pressed', String(horariosSeleccionados.has(slotKey)));
        slot.setAttribute('aria-label', header.textContent + ' · ' + slot.textContent);

        slot.addEventListener('click', () => {
          if (horariosSeleccionados.has(slotKey)) {
            horariosSeleccionados.delete(slotKey);
            slot.style.background = 'transparent';
            slot.style.color = '#475569';
          } else {
            horariosSeleccionados.add(slotKey);
            slot.style.background = '#3b82f6';
            slot.style.color = '#fff';
          }
          actualizarJsonHorarios();
          slot.setAttribute('aria-pressed', String(horariosSeleccionados.has(slotKey)));
          inputHorarios.dispatchEvent(new Event('change', {bubbles:true}));
        });

        columna.appendChild(slot);
      });

      calGrid.appendChild(columna);
    }
  };

  const actualizarJsonHorarios = () => {
    const arr = Array.from(horariosSeleccionados).map(val => {
      const parts = val.split('T');
      const d = new Date(val);
      d.setHours(d.getHours() + 1);
      const finStr = d.toTimeString().split(' ')[0];
      return { inicio: val.replace('T', ' '), fin: parts[0] + ' ' + finStr };
    });
    inputHorarios.value = JSON.stringify(arr);
  };

  rangoInicio.addEventListener('change', actualizarCalendario);
  rangoFin.addEventListener('change', actualizarCalendario);

  // GESTIÓN DE INVITADOS
  const renderInvitados = () => {
    listaAgregados.innerHTML = '';
    hiddenGuestsCont.innerHTML = '';
    
    // Internos
    invitadosInternos.forEach(usr => {
      const chip = document.createElement('span');
      chip.style.display = 'inline-flex';
      chip.style.alignItems = 'center';
      chip.style.gap = '6px';
      chip.style.background = '#dbeafe';
      chip.style.color = '#1e40af';
      chip.style.padding = '4px 10px';
      chip.style.borderRadius = '20px';
      chip.style.fontSize = '13px';
      chip.style.fontWeight = '500';
      chip.innerHTML = `${usr.nombre} <button type="button" style="border:none;background:transparent;color:#1e40af;cursor:pointer;font-weight:bold;">&times;</button>`;
      chip.querySelector('button').addEventListener('click', () => {
        invitadosInternos.delete(usr);
        renderInvitados();
      });
      listaAgregados.appendChild(chip);

      // hidden input
      const hid = document.createElement('input');
      hid.type = 'hidden';
      hid.name = 'invitados[]';
      hid.value = usr.id;
      hiddenGuestsCont.appendChild(hid);
    });

    // Externos
    const arrExternos = Array.from(invitadosExternos);
    arrExternos.forEach(correo => {
      const chip = document.createElement('span');
      chip.style.display = 'inline-flex';
      chip.style.alignItems = 'center';
      chip.style.gap = '6px';
      chip.style.background = '#ffedd5';
      chip.style.color = '#c2410c';
      chip.style.padding = '4px 10px';
      chip.style.borderRadius = '20px';
      chip.style.fontSize = '13px';
      chip.style.fontWeight = '500';
      chip.innerHTML = `${correo} (Externo) <button type="button" style="border:none;background:transparent;color:#c2410c;cursor:pointer;font-weight:bold;">&times;</button>`;
      chip.querySelector('button').addEventListener('click', () => {
        invitadosExternos.delete(correo);
        renderInvitados();
      });
      listaAgregados.appendChild(chip);
    });

    hiddenExternosInput.value = arrExternos.join(',');
    hiddenExternosInput.dispatchEvent(new Event('change', {bubbles:true}));
  };

  // Buscador y coincidencia dinámica
  buscador.addEventListener('input', () => {
    const val = buscador.value.trim().toLowerCase();
    sugerencias.innerHTML = '';
    sugerencias.style.display = 'none';
    botonExternoCont.style.display = 'none';

    if (val === '') return;

    // Buscar internos
    const coincidencias = usuariosDb.filter(u => 
      (u.nombre.toLowerCase().includes(val) || u.email.includes(val)) &&
      ![...invitadosInternos].some(i => i.id === u.id)
    );

    if (coincidencias.length > 0) {
      sugerencias.style.display = 'block';
      coincidencias.slice(0, 5).forEach(u => {
        const item = document.createElement('div');
        item.style.padding = '10px 14px';
        item.style.cursor = 'pointer';
        item.style.borderBottom = '1px solid #f1f5f9';
        item.innerHTML = `<div style="font-weight:600;color:#1e293b;">${u.nombre}</div><div style="font-size:12px;color:#64748b;">${u.email}</div>`;
        item.addEventListener('click', () => {
          invitadosInternos.add(u);
          buscador.value = '';
          sugerencias.style.display = 'none';
          renderInvitados();
        });
        sugerencias.appendChild(item);
      });
    }

    // Verificar si es email externo al ingresar el '.' del dominio tras un '@'
    const matchEmail = val.match(/^([a-z0-9_\.-]+)@([a-z0-9\.-]+)\.([a-z\.]{2,6})$/);
    if (matchEmail) {
      // Verificar si ya existe en la base de datos interna o ya está agregado
      const existeEnDb = usuariosDb.some(u => u.email === val);
      if (!existeEnDb && !invitadosExternos.has(val)) {
        correoExternoTexto.textContent = val;
        botonExternoCont.style.display = 'block';
      }
    }
  });

  btnAddExterno.addEventListener('click', () => {
    const correo = buscador.value.trim().toLowerCase();
    if (correo) {
      invitadosExternos.add(correo);
      buscador.value = '';
      botonExternoCont.style.display = 'none';
      renderInvitados();
    }
  });

  // Cerrar sugerencias al hacer clic fuera
  inputHorarios.form.addEventListener('ux:draft-restored', (event) => {
    horariosSeleccionados.clear(); invitadosInternos.clear(); invitadosExternos.clear();
    const values=event.detail;
    try {JSON.parse(values.find(v=>v.name==='horarios_json')?.value||'[]').forEach(v=>horariosSeleccionados.add(v.inicio.replace(' ','T')));}catch{}
    values.filter(v=>v.name==='invitados[]').forEach(v=>{const u=usuariosDb.find(u=>String(u.id)===String(v.value));if(u)invitadosInternos.add(u);});
    (values.find(v=>v.name==='invitados_externos')?.value||'').split(',').filter(Boolean).forEach(v=>invitadosExternos.add(v));
    actualizarCalendario(); actualizarJsonHorarios(); renderInvitados();
  });
  // Cerrar sugerencias al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (e.target !== buscador) {
      sugerencias.style.display = 'none';
    }
  });
})();
</script>
