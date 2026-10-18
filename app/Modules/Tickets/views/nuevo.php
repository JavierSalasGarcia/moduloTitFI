<?php use App\Core\Auth; ?>
<section class="card" style="max-width:720px">
  <form method="post" action="<?= url('tickets') ?>" class="formulario" enctype="multipart/form-data" data-draft="solicitud">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

    <?php if (!empty($carpetaVinculada)): ?>
      <div class="ruta-flujo" style="margin-bottom: 15px; background: #e8f5e9; border: 1px solid #d1fae5; color: var(--verde-900); padding: 12px; border-radius: var(--radio);">
        <div style="display: flex; align-items: center; gap: 6px; font-weight: bold;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
          Carpeta de Teams vinculada:
        </div>
        <span style="font-size: 13px; display: block; margin-top: 4px; font-weight: bold;"><?= e(basename($carpetaVinculada['ruta_relativa'])) ?></span>
        <span style="font-size: 11px; display: block; word-break: break-all; color: var(--gris-600); margin-top: 2px;"><?= e($carpetaVinculada['ruta_relativa']) ?></span>
        <input type="hidden" name="teams_carpeta_id" value="<?= $carpetaVinculada['id'] ?>">
      </div>
    <?php endif; ?>

    <label>Para <span class="req">*</span>
      <div class="autocomplete-container" id="container-para">
        <?php if ($deptoDirecto): ?>
          <div class="chip-recipient" data-key="departamento_<?= (int)$deptoDirecto['id'] ?>">
            <span>🏢 <?= e($deptoDirecto['nombre']) ?></span>
            <button type="button" class="remove-btn" aria-label="Quitar destinatario">&times;</button>
            <input type="hidden" name="para_type[]" value="departamento">
            <input type="hidden" name="para_id[]" value="<?= (int) $deptoDirecto['id'] ?>">
          </div>
        <?php endif; ?>
        <input type="text" class="autocomplete-input" id="input-para" placeholder="Escribe el nombre de una persona, departamento o coordinación..." autocomplete="off">
        <div class="suggestions-dropdown" id="dropdown-para"></div>
      </div>
      <p class="ayuda-form">Elige uno o varios destinatarios (personas, coordinaciones o departamentos). El ticket se canalizará a las áreas destino elegidas.</p>
    </label>

    <label>CC (Con copia)
      <div class="autocomplete-container" id="container-cc">
        <input type="text" class="autocomplete-input" id="input-cc" placeholder="Escribe personas o departamentos para enviar copia..." autocomplete="off">
        <div class="suggestions-dropdown" id="dropdown-cc"></div>
      </div>
      <p class="ayuda-form">Miembros o departamentos que recibirán copia informativa de este ticket.</p>
    </label>

    <?= \App\Core\RecipientGroups::selector('grupos_para','Añadir grupos a Para') ?>
    <?= \App\Core\RecipientGroups::selector('grupos_cc','Añadir grupos a CC') ?>
    <p class="ayuda-form">Si el grupo no tiene personas adscritas a un área, añade también el departamento que atenderá tu solicitud.</p>
    <label>Asunto <span class="req">*</span>
      <input type="text" name="asunto" required maxlength="180" placeholder="Ej. Constancia de estudios con promedio">
    </label>

    <label>Descripción <span class="req">*</span>
      <textarea name="descripcion" required rows="5" placeholder="Describe tu trámite o problema: número de cuenta, grupo, fechas, equipo afectado…"><?= e($ref ?? '') ?></textarea>
    </label>

    <label>Prioridad
      <select name="prioridad">
        <option value="baja">Baja</option>
        <option value="media" selected>Media</option>
        <option value="alta">Alta</option>
      </select>
    </label>

    <label>Archivos Adjuntos (Uno o varios)
      <input type="file" name="archivos[]" multiple style="margin-top: 6px; font-size: 13px; cursor: pointer; width: 100%;">
      <p class="ayuda-form">Puedes subir múltiples imágenes, PDFs o documentos comprimidos como soporte.</p>
    </label>

    <div class="acciones-formulario">
      <a class="btn" href="<?= url('tickets') ?>">Cancelar</a>
      <button type="submit" class="btn btn-primario">Enviar solicitud</button>
    </div>
  </form>

<?php
$opcionesList = [];
foreach ($departamentos as $d) {
    $opcionesList[] = [
        'type' => 'departamento',
        'id' => $d['id'],
        'nombre' => $d['nombre'],
        'subtext' => 'Departamento / Coordinación'
    ];
}
foreach ($usuarios as $u) {
    $opcionesList[] = [
        'type' => 'usuario',
        'id' => $u['id'],
        'nombre' => $u['nombre'],
        'subtext' => 'Usuario · ' . ucfirst($u['rol']) . ($u['email'] ? ' (' . $u['email'] . ')' : '')
    ];
}
?>

<style>
.autocomplete-container {
  border: 1px solid var(--gris-100);
  border-radius: var(--radio);
  padding: 6px;
  background: #fff;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  position: relative;
  min-height: 42px;
  box-sizing: border-box;
}
.autocomplete-container:focus-within {
  border-color: var(--verde-700);
  box-shadow: 0 0 0 2px var(--verde-050);
}
.autocomplete-input {
  border: none !important;
  outline: none !important;
  padding: 4px 8px !important;
  margin: 0 !important;
  flex: 1;
  min-width: 150px;
  font-size: 13px;
  box-shadow: none !important;
  background: transparent !important;
}
.chip-recipient {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--verde-050);
  color: var(--verde-900);
  border: 1px solid #d1fae5;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 500;
  user-select: none;
}
.chip-recipient-cc {
  background: #f1f5f9;
  color: #334155;
  border-color: #e2e8f0;
}
.chip-recipient .remove-btn {
  cursor: pointer;
  font-weight: bold;
  font-size: 14px;
  line-height: 1;
  color: var(--gris-600);
}
.chip-recipient .remove-btn:hover {
  color: var(--error);
}
.suggestions-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid var(--gris-100);
  border-radius: 0 0 var(--radio) var(--radio);
  max-height: 200px;
  overflow-y: auto;
  z-index: 100;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  margin-top: 2px;
  display: none;
}
.suggestion-item {
  padding: 8px 12px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  border-bottom: 1px solid rgba(0,0,0,0.02);
  text-align: left;
}
.suggestion-item:hover {
  background: var(--verde-050);
}
.suggestion-item strong {
  font-size: 13px;
  color: var(--texto);
}
.suggestion-item span {
  font-size: 11px;
  color: var(--gris-600);
}
</style>

<script>
const autocompleteOptions = <?= json_encode($opcionesList) ?>;

function initAutocomplete(fieldId, isCc) {
  const container = document.getElementById(`container-${fieldId}`);
  const input = document.getElementById(`input-${fieldId}`);
  const dropdown = document.getElementById(`dropdown-${fieldId}`);
  
  const selectedKeys = new Set();
  input.form.addEventListener('ux:draft-restored', (event) => {
    const types=event.detail.filter(v=>v.name===fieldId+'_type[]');
    const ids=event.detail.filter(v=>v.name===fieldId+'_id[]');
    container.querySelectorAll('.chip-recipient').forEach(n=>n.remove());selectedKeys.clear();
    ids.forEach((v,i)=>{const opt=autocompleteOptions.find(o=>String(o.id)===String(v.value)&&o.type===types[i]?.value);if(opt)addChip(opt);});
  });

  // Registrar chips pre-cargados
  const preloadedChips = container.querySelectorAll('.chip-recipient');
  preloadedChips.forEach(chip => {
    const key = chip.dataset.key;
    if (key) {
      selectedKeys.add(key);
      chip.querySelector('.remove-btn').addEventListener('click', () => {
        chip.remove();
        selectedKeys.delete(key);
        input.dispatchEvent(new Event('change', {bubbles:true}));
      });
    }
  });

  input.addEventListener('input', function() {
    const query = this.value.trim().toLowerCase();
    dropdown.innerHTML = '';
    
    if (query.length < 1) {
      dropdown.style.display = 'none';
      return;
    }

    const matches = autocompleteOptions.filter(opt => {
      const key = `${opt.type}_${opt.id}`;
      if (selectedKeys.has(key)) return false;
      return opt.nombre.toLowerCase().includes(query) || (opt.subtext && opt.subtext.toLowerCase().includes(query));
    });

    if (matches.length === 0) {
      dropdown.innerHTML = '<div style="padding: 10px; font-size: 12px; color: var(--gris-600); text-align: center;">Sin coincidencias.</div>';
    } else {
      matches.slice(0, 10).forEach(opt => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        div.innerHTML = `
          <strong>${opt.type === 'departamento' ? '🏢' : '👤'} ${escapeHtml(opt.nombre)}</strong>
          <span>${escapeHtml(opt.subtext)}</span>
        `;
        div.addEventListener('click', () => {
          addChip(opt);
          input.value = '';
          dropdown.style.display = 'none';
        });
        dropdown.appendChild(div);
      });
    }
    dropdown.style.display = 'block';
  });

  document.addEventListener('click', function(e) {
    if (!container.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });

  function addChip(opt) {
    const key = `${opt.type}_${opt.id}`;
    if (selectedKeys.has(key)) return;
    selectedKeys.add(key);

    const chip = document.createElement('div');
    chip.className = 'chip-recipient' + (isCc ? ' chip-recipient-cc' : '');
    chip.innerHTML = `
      <span>${opt.type === 'departamento' ? '🏢' : '👤'} ${escapeHtml(opt.nombre)}</span>
      <button type="button" class="remove-btn" aria-label="Quitar destinatario">&times;</button>
      <input type="hidden" name="${fieldId}_type[]" value="${opt.type}">
      <input type="hidden" name="${fieldId}_id[]" value="${opt.id}">
    `;

    chip.querySelector('.remove-btn').addEventListener('click', () => {
      chip.remove();
      selectedKeys.delete(key);
      input.dispatchEvent(new Event('change', {bubbles:true}));
    });

    container.insertBefore(chip, input);
    input.dispatchEvent(new Event('change', {bubbles:true}));
    input.focus();
  }
}

function escapeHtml(str) {
  return str.replace(/[&<>'"]/g, 
    tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag] || tag)
  );
}

document.addEventListener('DOMContentLoaded', () => {
  initAutocomplete('para', false);
  initAutocomplete('cc', true);

  document.querySelector('.formulario').addEventListener('submit', function(e) {
    const paras = document.querySelectorAll('input[name="para_id[]"]');
    if (paras.length === 0 && !document.querySelector('[name="grupos_para[]"]')?.selectedOptions.length) {
      e.preventDefault();
      alert('Debes agregar al menos un destinatario en el campo "Para".');
    }
  });
});
</script>
</section>
