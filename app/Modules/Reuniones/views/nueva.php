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
      <label style="font-weight: 600; color: #475569;">Buscar y añadir invitados (internos o externos)</label>
      <?php $campo = 'invitados[]'; $url = ''; $listaVar = 'usuariosReunion'; $seleccion = []; $id = 'sp-invitados'; $max = 0; $libre = 'correo'; $campoLibre = 'invitados_externos'; $valor = 'id';
            $placeholder = 'Escribe el nombre o correo del invitado… (un correo ajeno a la plataforma se añade como externo)'; $etiqueta = 'Personas invitadas';
            include dirname(__DIR__, 3) . '/Views/selector_personas.php'; unset($listaVar, $campoLibre); ?>
    </div>

    </fieldset>
    <!-- Campos ocultos para enviar al backend -->
    <input type="hidden" name="horarios_json" id="horarios_json" value="[]">

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

  window.usuariosReunion = usuariosDb;   // lista local para el selector de invitados

  const rangoInicio = document.getElementById('rango_inicio');
  const rangoFin = document.getElementById('rango_fin');
  const calMensaje = document.getElementById('calendario-mensaje');
  const calGrid = document.getElementById('calendario-grid');
  const inputHorarios = document.getElementById('horarios_json');
  
  const horariosSeleccionados = new Set();

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

  // Al recuperar un borrador
  inputHorarios.form.addEventListener('ux:draft-restored', (event) => {
    horariosSeleccionados.clear();
    const values=event.detail;
    try {JSON.parse(values.find(v=>v.name==='horarios_json')?.value||'[]').forEach(v=>horariosSeleccionados.add(v.inicio.replace(' ','T')));}catch{}
    // Invitados: el selector de personas (SelectorPersonas) se vuelve a llenar con lo guardado en el borrador.
    const sel = document.getElementById('sp-invitados'); SelectorPersonas.limpiar(sel);
    values.filter(v=>v.name==='invitados[]').forEach(v=>{const u=usuariosDb.find(u=>String(u.id)===String(v.value));if(u)SelectorPersonas.agregar(sel,u);});
    (values.find(v=>v.name==='invitados_externos')?.value||'').split(',').filter(Boolean).forEach(v=>SelectorPersonas.agregar(sel,{nombre:v,email:v,libre:true}));
    actualizarCalendario(); actualizarJsonHorarios();
  });
})();
</script>
