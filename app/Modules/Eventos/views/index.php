<?php use App\Core\Auth; ?>

<nav class="pestanas" aria-label="Secciones de avisos y eventos" style="margin-bottom: 20px; display: flex; gap: 8px; border-bottom: 1px solid var(--gris-100); padding-bottom: 8px;">
  <a class="pestana activa" href="#" id="tab-btn-avisos" onclick="switchTab('avisos'); return false;" style="font-weight: 600; font-size: 14px; padding: 6px 12px; border-radius: 4px; color: var(--texto); text-decoration: none;">
    📢 Avisos oficiales
  </a>
  <a class="pestana" href="#" id="tab-btn-eventos" onclick="switchTab('eventos'); return false;" style="font-weight: 600; font-size: 14px; padding: 6px 12px; border-radius: 4px; color: var(--texto); text-decoration: none;">
    📅 Eventos y convocatorias
  </a>
</nav>

<!-- TAB 1: AVISOS OFICIALES -->
<div id="tab-content-avisos">
  <nav class="pestanas" aria-label="Vigencia de avisos"><a class="pestana <?= !$avisosArchivo?'activa':'' ?>" href="<?= url('eventos') ?>">Vigentes</a><a class="pestana <?= $avisosArchivo?'activa':'' ?>" href="<?= url('eventos?avisos=archivo') ?>">Archivo de avisos</a></nav>
  <div class="barra-acciones" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
    <div class="buscador" style="max-width: 400px; margin: 0; flex: 1;">
      <?= icono('buscar', 16) ?>
      <input type="text" id="buscador-comunicados" placeholder="Buscar comunicado por título, contenido o emisor..." autocomplete="off">
    </div>
    
    <?php if ($puedeEmitirComunicados): ?>
      <details class="card" style="margin: 0;">
        <summary style="cursor:pointer;font-weight:700;color:var(--verde-900)">+ Publicar comunicado oficial</summary>
        <form method="post" action="<?= url('comunicados') ?>" class="formulario" style="margin-top:12px">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          
          <label>Vigente hasta (opcional)<input type="date" name="vence_en"><small class="ayuda-form">Después de esta fecha se mostrará en el archivo.</small></label>
          <label>Título del Comunicado <span class="req">*</span>
            <input type="text" name="titulo" required maxlength="180" placeholder="Ej. Suspensión de actividades por contingencia clima">
          </label>
          
          <label>Alcance para estudiantes <span class="req">*</span>
            <select name="alcance" onchange="toggleCarrera(this.value)">
              <option value="todos">Todos los alumnos</option>
              <option value="licenciatura">Solo Licenciatura</option>
              <option value="posgrado">Solo Posgrado</option>
              <option value="programa">Por carrera/programa específico...</option>
            </select>
          </label>
          
          <label id="carrera-select-wrapper" style="display: none;">Carrera / Programa específico
            <select name="programa">
              <option value="" disabled selected>-- Elige carrera --</option>
              <?php foreach ($programas as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['siglas']) ?> · <?= e($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          
          <label>Contenido <span class="req">*</span>
            <textarea name="contenido" rows="5" required placeholder="Escribe el mensaje oficial para la comunidad estudiantil..."></textarea>
          </label>
          
          <div class="acciones-formulario">
            <button type="submit" class="btn btn-primario">Publicar comunicado</button>
          </div>
        </form>
      </details>
    <?php endif; ?>
  </div>

  <?php if (!$comunicados): ?>
    <div class="card" id="sin-comunicados-banner"><p class="texto-suave">No hay comunicados publicados.</p></div>
  <?php else: ?>
    <div class="card" id="sin-comunicados-banner" style="display: none;"><p class="texto-suave">No se encontraron comunicados que coincidan con tu búsqueda.</p></div>
  <?php endif; ?>

  <div id="contenedor-comunicados">
    <?php foreach ($comunicados as $c): ?>
      <article class="card comunicado-card" data-categoria="<?= e($c['categoria']) ?>" style="margin-bottom: 16px;">
        <header class="tarjeta-cabecera columna-movil">
          <div>
            <h2 style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 15px; margin: 0;">
              <span class="comunicado-titulo-text"><?= e($c['titulo']) ?></span>
              <?php if ($c['categoria'] === 'Importantes'): ?>
                <span class="badge badge-oro" style="background: #fef3c7; color: #d97706; border-color: #fde68a;">⭐ Importante</span>
              <?php elseif ($c['categoria'] === 'Ocultar'): ?>
                <span class="badge" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;">👁️ Oculto</span>
              <?php endif; ?>
            </h2>
            <small class="texto-suave comunicado-meta-text" style="display: block; margin-top: 4px;"><?= e($c['depto']) ?> · <?= e($c['autor']) ?> · <?= e(fecha_corta($c['creado_en'])) ?></small>
          </div>
          <div class="grupo-chips">
            <?php if ($c['alcance'] === 'programa'): ?>
              <span class="badge badge-verde">Programa <?= e($c['siglas']) ?></span>
            <?php else: ?>
              <span class="badge badge-oro"><?= e(ucfirst($c['alcance'])) ?></span>
            <?php endif; ?>
            <?php if (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId()): ?>
              <form method="post" action="<?= url('comunicados/' . $c['id'] . '/eliminar') ?>" data-confirm="¿Retirar este comunicado?">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button type="submit" class="btn btn-mini btn-peligro">Retirar</button>
              </form>
            <?php endif; ?>
          </div>
        </header>
        <p class="comunicado-contenido-text" style="margin: 8px 0 0; line-height: 1.5; font-size: 13.5px;"><?= nl2br(e($c['contenido'])) ?></p>
        <p class="ayuda-form"><?= !empty($c['vence_en']) ? ($avisosArchivo?'Archivado · venció el ':'Vigente hasta ').e($c['vence_en']) : 'Sin fecha límite publicada' ?></p>

        <?php if ($puedeEmitirComunicados && (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId())): ?>
          <div style="margin-top: 12px; display: flex; align-items: center; gap: 8px; font-size: 12px; border-top: 1px dashed var(--gris-100); padding-top: 10px;">
            <span style="color: var(--gris-600); font-weight: 500;">Categoría del Comunicado:</span>
            <form method="post" action="<?= url('comunicados/' . $c['id'] . '/categoria') ?>" class="ux-toolbar">
              <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
              <select name="categoria" aria-label="Categoría del aviso">
                <option value="General" <?= $c['categoria'] === 'General' ? 'selected' : '' ?>>General</option>
                <option value="Importantes" <?= $c['categoria'] === 'Importantes' ? 'selected' : '' ?>>Importantes (Destacar)</option>
                <option value="Ocultar" <?= $c['categoria'] === 'Ocultar' ? 'selected' : '' ?>>Ocultar (Borrador/Oculto)</option>
              </select>
              <label>Vigente hasta<input type="date" name="vence_en" value="<?= e($c['vence_en']??'') ?>"></label><button class="btn" type="submit">Guardar vigencia</button>
            </form>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<!-- TAB 2: EVENTOS Y CONVOCATORIAS -->
<div id="tab-content-eventos" style="display: none;">
  <div class="barra-acciones" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
    <div class="buscador" style="max-width: 400px; margin: 0; flex: 1;">
      <?= icono('buscar', 16) ?>
      <input type="search" id="buscador-eventos" placeholder="Buscar eventos por título, lugar o departamento…" autocomplete="off">
    </div>
    
    <div style="display: flex; gap: 8px; align-items: center;">
      <a class="btn" href="<?= url('eventos/biblioteca') ?>">Biblioteca de eventos concluidos</a>
      
      <?php if ($puedeEmitir): ?>
        <details class="card" style="margin: 0;">
          <summary style="cursor:pointer;font-weight:700;color:var(--verde-900)">+ Publicar aviso de evento</summary>
          <form id="form-evento" method="post" action="<?= url('eventos') ?>" class="formulario" style="margin-top:12px">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

            <div class="ruta-flujo" style="margin-bottom: 15px; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; padding: 12px; border-radius: var(--radio); font-size: 12.5px; line-height: 1.45;">
              <strong>📢 Nota sobre destinatarios:</strong> A diferencia de los **Tickets** (que exigen una acción concreta, respuesta y carga de evidencia de cumplimiento), el envío de avisos a través de **Para** y **CC** tiene un propósito <strong>estrictamente informativo y de difusión</strong> para la comunidad seleccionada.
            </div>

            <label>Para <span class="req">*</span>
              <div class="autocomplete-container" id="container-para">
                <input type="text" class="autocomplete-input" id="input-para" placeholder="Escribe el nombre de una persona, departamento o coordinación..." autocomplete="off">
                <div class="suggestions-dropdown" id="dropdown-para"></div>
              </div>
              <p class="ayuda-form">Elige uno o varios destinatarios (personas, coordinaciones o departamentos) para este aviso.</p>
            </label>

            <label>CC (Con copia)
              <div class="autocomplete-container" id="container-cc">
                <input type="text" class="autocomplete-input" id="input-cc" placeholder="Escribe personas o departamentos para enviar copia..." autocomplete="off">
                <div class="suggestions-dropdown" id="dropdown-cc"></div>
              </div>
              <p class="ayuda-form">Personas o departamentos que recibirán copia informativa de este aviso.</p>
            </label>

            <div class="form-rejilla">
              <label>Título <span class="req">*</span>
                <input type="text" name="titulo" required maxlength="180">
              </label>
              <label>Categoría
                <select name="categoria">
                  <?php foreach ($categorias as $clave => $nombre): ?>
                    <option value="<?= $clave ?>"><?= e($nombre) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Inicio <span class="req">*</span>
                <input type="datetime-local" name="inicio" required>
              </label>
              <label>Fin (opcional)
                <input type="datetime-local" name="fin">
              </label>
              <label>Lugar
                <input type="text" name="lugar" maxlength="160" placeholder="Aula Magna, Edificio B…">
              </label>
            </div>
            <label>Descripción <span class="req">*</span>
              <textarea name="descripcion" rows="2" required></textarea>
            </label>
            <div class="acciones-formulario">
              <button type="submit" class="btn btn-primario">Publicar aviso</button>
            </div>
          </form>
        </details>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$eventos): ?>
    <div class="card" id="sin-eventos-banner"><p class="texto-suave">No hay eventos próximos publicados.</p></div>
  <?php else: ?>
    <div class="card" id="sin-eventos-banner" style="display: none;"><p class="texto-suave">No se encontraron eventos que coincidan con tu búsqueda.</p></div>
  <?php endif; ?>

  <div id="contenedor-eventos">
    <?php foreach ($eventos as $ev): ?>
      <article class="card evento-fila" style="margin-bottom: 16px;">
        <div class="evento-fecha" aria-hidden="true">
          <span class="evento-dia"><?= (int) date('d', strtotime($ev['inicio'])) ?></span>
          <span class="evento-mes"><?= ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][(int) date('n', strtotime($ev['inicio'])) - 1] ?></span>
        </div>
        <div style="flex:1;min-width:0">
          <h2 style="font-size:15px;margin:0"><a class="evento-titulo-link" href="<?= url('eventos/' . $ev['id']) ?>"><?= e($ev['titulo']) ?></a></h2>
          <p class="texto-suave evento-meta-text" style="margin:2px 0 6px">
            <?= e(fecha_corta($ev['inicio'])) ?><?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?> · <?= e($ev['depto']) ?>
          </p>
          <div class="grupo-chips">
            <span class="badge badge-oro"><?= e($categorias[$ev['categoria']] ?? 'Otros') ?></span>
            <span class="texto-suave"><?= (int) $ev['interesados'] ?> en su calendario</span>
          </div>
        </div>
        <form method="post" action="<?= url('eventos/' . $ev['id'] . '/suscribir') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <input type="hidden" name="volver" value="eventos">
          <button type="submit" class="btn <?= $ev['suscrito'] ? '' : 'btn-primario' ?> btn-mini">
            <?= $ev['suscrito'] ? '✓ En mi calendario' : '+ Agregar a mi calendario' ?>
          </button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<!-- ESTILOS Y JAVASCRIPT GLOBAL PARA PESTAÑAS Y AUTOCOMPLETADOS -->
<style>
.pestana {
  border: 1px solid transparent;
  border-bottom: none;
  cursor: pointer;
  transition: all 0.2s;
}
.pestana:hover {
  background: var(--gris-050);
}
.pestana.activa {
  background: var(--verde-050) !important;
  color: var(--verde-900) !important;
  border: 1px solid #d1fae5 !important;
  border-bottom: 2px solid var(--verde-700) !important;
}

/* Estilos de Autocompletado */
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
// Switch entre pestañas
function switchTab(name) {
  const btnAvisos = document.getElementById('tab-btn-avisos');
  const btnEventos = document.getElementById('tab-btn-eventos');
  const contentAvisos = document.getElementById('tab-content-avisos');
  const contentEventos = document.getElementById('tab-content-eventos');

  if (name === 'avisos') {
    btnAvisos.classList.add('activa');
    btnEventos.classList.remove('activa');
    contentAvisos.style.display = 'block';
    contentEventos.style.display = 'none';
  } else {
    btnAvisos.classList.remove('activa');
    btnEventos.classList.add('activa');
    contentAvisos.style.display = 'none';
    contentEventos.style.display = 'block';
  }
  btnAvisos.setAttribute('aria-current', name === 'avisos' ? 'page' : 'false');
  btnEventos.setAttribute('aria-current', name === 'eventos' ? 'page' : 'false');
}

function toggleCarrera(alcance) {
  const wrapper = document.getElementById('carrera-select-wrapper');
  if (wrapper) {
    wrapper.style.display = (alcance === 'programa') ? 'block' : 'none';
  }
}

// Inicializar pestaña activa
document.addEventListener('DOMContentLoaded', () => {
  const savedTab = new URLSearchParams(location.search).get('tab') === 'eventos' ? 'eventos' : 'avisos';
  switchTab(savedTab);
});
</script>

<?php if ($puedeEmitir):
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
<script>
const autocompleteOptions = <?= json_encode($opcionesList) ?>;

function initAutocomplete(fieldId, isCc) {
  const container = document.getElementById(`container-${fieldId}`);
  const input = document.getElementById(`input-${fieldId}`);
  const dropdown = document.getElementById(`dropdown-${fieldId}`);
  if (!container || !input) return;

  const selectedKeys = new Set();

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
      <span class="remove-btn">&times;</span>
      <input type="hidden" name="${fieldId}_type[]" value="${opt.type}">
      <input type="hidden" name="${fieldId}_id[]" value="${opt.id}">
    `;

    chip.querySelector('.remove-btn').addEventListener('click', () => {
      chip.remove();
      selectedKeys.delete(key);
    });

    container.insertBefore(chip, input);
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

  const formEv = document.getElementById('form-evento');
  if (formEv) {
    formEv.addEventListener('submit', function(e) {
      const paras = document.querySelectorAll('#container-para input[name="para_id[]"]');
      if (paras.length === 0) {
        e.preventDefault();
        alert('Debes agregar al menos un destinatario en el campo "Para".');
      }
    });
  }
});
</script>
<?php endif; ?>

<!-- BUSCADORES DINÁMICOS Y RESALTADORES -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  // 1. Buscador y Resaltado de Comunicados
  const inputCom = document.getElementById('buscador-comunicados');
  const bannerCom = document.getElementById('sin-comunicados-banner');
  const cardsCom = document.querySelectorAll('.comunicado-card');

  if (inputCom) {
    cardsCom.forEach(card => {
      const titleLink = card.querySelector('.comunicado-titulo-text');
      const metaText = card.querySelector('.comunicado-meta-text');
      const bodyText = card.querySelector('.comunicado-contenido-text');
      if (titleLink) titleLink.dataset.originalText = titleLink.textContent;
      if (metaText) metaText.dataset.originalText = metaText.textContent;
      if (bodyText) bodyText.dataset.originalText = bodyText.textContent;
    });

    inputCom.addEventListener('input', function() {
      const query = this.value.trim().toLowerCase();
      let visibles = 0;

      cardsCom.forEach(card => {
        const titleLink = card.querySelector('.comunicado-titulo-text');
        const metaText = card.querySelector('.comunicado-meta-text');
        const bodyText = card.querySelector('.comunicado-contenido-text');

        const oTitle = titleLink ? titleLink.dataset.originalText : '';
        const oMeta = metaText ? metaText.dataset.originalText : '';
        const oBody = bodyText ? bodyText.dataset.originalText : '';

        const fullText = (oTitle + ' ' + oMeta + ' ' + oBody).toLowerCase();

        if (query === '') {
          card.style.display = 'block';
          visibles++;
          if (titleLink) titleLink.innerHTML = escapeHtml(oTitle);
          if (metaText) metaText.innerHTML = escapeHtml(oMeta);
          if (bodyText) bodyText.innerHTML = escapeHtml(oBody);
        } else if (fullText.includes(query)) {
          card.style.display = 'block';
          visibles++;
          if (titleLink) titleLink.innerHTML = resaltarTexto(oTitle, query);
          if (metaText) metaText.innerHTML = resaltarTexto(oMeta, query);
          if (bodyText) bodyText.innerHTML = resaltarTexto(oBody, query);
        } else {
          card.style.display = 'none';
        }
      });

      if (bannerCom) {
        bannerCom.style.display = (visibles === 0) ? 'block' : 'none';
      }
    });
  }

  // 2. Buscador y Resaltado de Eventos
  const inputEv = document.getElementById('buscador-eventos');
  const bannerEv = document.getElementById('sin-eventos-banner');
  const cardsEv = document.querySelectorAll('.evento-fila');

  if (inputEv) {
    cardsEv.forEach(row => {
      const titleLink = row.querySelector('.evento-titulo-link');
      const metaPara = row.querySelector('.evento-meta-text');
      if (titleLink) titleLink.dataset.originalText = titleLink.textContent;
      if (metaPara) metaPara.dataset.originalText = metaPara.textContent;
    });

    inputEv.addEventListener('input', function() {
      const query = this.value.trim().toLowerCase();
      let visibles = 0;

      cardsEv.forEach(row => {
        const titleLink = row.querySelector('.evento-titulo-link');
        const metaPara = row.querySelector('.evento-meta-text');

        const titleText = titleLink ? titleLink.dataset.originalText : '';
        const metaText = metaPara ? metaPara.dataset.originalText : '';

        const fullText = (titleText + ' ' + metaText).toLowerCase();

        if (query === '') {
          row.style.display = 'flex';
          visibles++;
          if (titleLink) titleLink.innerHTML = escapeHtml(titleText);
          if (metaPara) metaPara.innerHTML = escapeHtml(metaText);
        } else if (fullText.includes(query)) {
          row.style.display = 'flex';
          visibles++;
          if (titleLink) titleLink.innerHTML = resaltarTexto(titleText, query);
          if (metaPara) metaPara.innerHTML = resaltarTexto(metaText, query);
        } else {
          row.style.display = 'none';
        }
      });

      if (bannerEv) {
        bannerEv.style.display = (visibles === 0) ? 'block' : 'none';
      }
    });
  }

  function resaltarTexto(texto, query) {
    if (!query || !texto) return escapeHtml(texto);
    const textoEscapado = escapeHtml(texto);
    const queryEscapada = escapeHtml(query).replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    const regex = new RegExp(`(${queryEscapada})`, 'gi');
    return textoEscapado.replace(regex, '<mark style="background-color: #d1fae5; color: inherit; padding: 0 2px; border-radius: 2px; font-weight: bold;">$1</mark>');
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
});
</script>
