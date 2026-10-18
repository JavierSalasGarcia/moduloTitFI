<?php
/**
 * Vista de Explorar Teams con Vista de Árbol, Modal de Metadatos y Copiado de Enlace
 */
$sitiosJson = [];
foreach ($sitios as $s) {
    $sitiosJson[] = [
        'id' => $s['id'],
        'nombre' => $s['nombre'],
        'url_base' => $s['url_base'],
        'carpetas' => $carpetasPorSitio[$s['id']] ?? []
    ];
}
?>
<div class="tarjeta">
  <div class="tarjeta-cuerpo">
    <!-- Selector de cuenta activa Microsoft Teams -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radio); padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 18px;">🔄</span>
        <strong style="color: #334155; font-size: 13.5px;">Cuenta Activa de Microsoft Teams:</strong>
        <select id="selector-cuenta-teams" onchange="cambiarCuentaTeams(this.value)" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #cbd5e1; cursor: pointer; background: #fff; font-weight: 500; font-size: 13px; color: #1e293b;">
          <?php foreach ($cuentas as $cuenta): ?>
            <option value="<?= e($cuenta['microsoft_email']) ?>" <?= $activeEmail === $cuenta['microsoft_email'] ? 'selected' : '' ?>>
              <?= e($cuenta['microsoft_email']) ?> (<?= str_contains((string)($cuenta['microsoft_email'] ?? ''), 'investigacion') ? 'Coordinación' : 'Personal/Profesor' ?>)
            </option>
          <?php endforeach; ?>
        </select>
        
        <?php if ($activeEmail !== \App\Core\Auth::usuario()['email']): ?>
          <button type="button" class="btn btn-mini btn-peligro" onclick="desvincularCuentaTeams('<?= e($activeEmail) ?>')" style="padding: 4px 8px; font-size: 11px; margin-left: 5px;">Desvincular Cuenta</button>
        <?php endif; ?>
      </div>
      <button type="button" class="btn btn-mini" onclick="vincularNuevaCuentaTeams()" style="border-color: #0f6cbd; color: #0f6cbd; font-weight: 600;">+ Vincular otra cuenta</button>
    </div>

    <p class="texto-secundario">
      Busca rápidamente entre tus carpetas de Teams usando palabras clave de sus descripciones, o navega en forma de árbol por cada grupo registrado. 
      Toca una carpeta para abrirla en Teams. Usa «Detalles» para consultar su descripción.
    </p>

    <div class="buscador-grande" style="margin: 20px 0; display: flex; gap: 8px;">
      <input type="text" id="buscar-input" placeholder="Comienza a escribir para buscar por descripción, carpeta o sitio de Teams..." style="flex: 1; padding: 10px; border: 1px solid var(--gris-100); border-radius: var(--radio); font-size: 14px;">
      <button onclick="limpiarBuscador()" class="btn" style="border: 1px solid var(--gris-100); display: inline-flex; align-items: center;">Limpiar</button>
    </div>

    <section id="seccion-resultados-busqueda" style="margin-top: 30px; margin-bottom: 20px; display: none;">
      <h3 id="titulo-resultados-busqueda" style="border-bottom: 2px solid var(--verde-050); padding-bottom: 8px; color: var(--verde-900);">Resultados</h3>
      <div id="lista-resultados-busqueda" style="display: grid; gap: 12px; margin-top: 15px;"></div>
    </section>
  </div>
</div>

<div class="rejilla-2col" id="rejilla-principal" style="margin-top: 20px; display: grid; grid-template-columns: 1fr; gap: 20px;">
  <!-- Listado de Sitios Sincronizados con Árbol -->
  <section class="tarjeta" style="background: var(--blanco); border-radius: var(--radio); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <header class="tarjeta-cabecera" style="padding: 16px; border-bottom: 1px solid var(--gris-100); display: flex; justify-content: space-between; align-items: center;">
      <h2 style="margin: 0; font-size: 16px; color: var(--verde-900); display: flex; align-items: center; gap: 6px;">
        <?= icono('portafolio', 18) ?> Explorador de Grupos y Carpetas
      </h2>
      <button id="btn-agregar-sitio-toggle" onclick="toggleFormularioSitio()" class="btn btn-mini btn-primario" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; font-weight: bold;" title="Agregar Sitio">
        <?= icono('mas', 14) ?>
      </button>
    </header>
    <div class="tarjeta-cuerpo" style="padding: 16px;">
      <?php if (empty($sitios)): ?>
        <p class="texto-suave" style="text-align: center; padding: 20px 0;">No tienes sitios registrados. Usa «Agregar sitio» para vincular tu primer grupo..</p>
      <?php else: ?>
        <ul style="list-style: none; padding: 0; margin: 0; display: grid; gap: 8px;">
          <?php foreach ($sitios as $s): ?>
            <li style="border: 1px solid var(--gris-100); border-radius: var(--radio); padding: 8px 12px; background: #fafafa; position: relative;">
              <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 4px;">
                <div style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;" onclick="toggleSitio(<?= $s['id'] ?>)">
                  <span id="sitio-toggle-<?= $s['id'] ?>" style="color: var(--gris-600); transition: transform 0.2s; font-size: 14px; font-weight: bold; width: 14px; text-align: center;">▸</span>
                  <strong style="font-size: 16px; color: var(--verde-900);"><?= e($s['nombre']) ?></strong>
                  <button onclick="event.stopPropagation(); copiarEnlace('<?= e($s['url_base']) ?>', this)" title="Copiar Enlace Base" style="border: none; background: none; cursor: pointer; color: var(--gris-600); display: inline-flex; align-items: center; padding: 4px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#eee';" onmouseout="this.style.background='none';">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                  </button>
                </div>
                <div style="display: flex; gap: 4px; align-items: center;">
                  <button type="button" onclick="iniciarEditarSitio(<?= $s['id'] ?>, '<?= e($s['nombre']) ?>', '<?= e($s['url_base']) ?>')" class="btn btn-mini btn-primario" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; padding: 0; background: var(--oro-texto); border-color: var(--oro-texto);" title="Editar nombre y enlace base">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                  </button>
                  <a href="<?= url('teams/sitio/' . $s['id']) ?>" class="btn btn-mini btn-primario" style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; padding: 0;" title="Gestionar carpetas">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                  </a>
                  <form method="POST" action="<?= url('teams/sitio/' . $s['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Seguro que deseas eliminar este sitio y desindexar todas sus carpetas?');" style="display: inline;">
                    <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
                    <button type="submit" class="btn btn-mini" style="border: 1px solid var(--error); color: var(--error); background: none; display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; padding: 0;" title="Eliminar sitio">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                  </form>
                </div>
              </div>

              <!-- Listado colapsable de metadatos (integrado, sin caja interna) -->
              <div id="arbol-sitio-<?= $s['id'] ?>" class="arbol-carpetas" style="margin-top: 4px; display: none; padding-left: 14px; max-height: 250px; overflow-y: auto;">
                <span class="texto-suave" style="font-style: italic; font-size: 11px; padding: 2px 6px; display: block;">Cargando metadatos...</span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <!-- Formulario de Agregar / Editar Sitio (Oculto por defecto) -->
  <section id="columna-formulario-sitio" class="tarjeta" style="background: var(--blanco); border-radius: var(--radio); box-shadow: 0 1px 3px rgba(0,0,0,0.05); height: fit-content; display: none;">
    <header class="tarjeta-cabecera" style="padding: 16px; border-bottom: 1px solid var(--gris-100); display: flex; justify-content: space-between; align-items: center;">
      <h2 style="margin: 0; font-size: 16px; color: var(--verde-900); display: flex; align-items: center; gap: 6px;">
        <?= icono('mas', 18) ?> <span id="titulo-formulario-sitio-texto">Registrar Sitio de Teams</span>
      </h2>
      <button type="button" onclick="toggleFormularioSitio()" style="border: none; background: none; cursor: pointer; color: var(--gris-600); font-size: 20px; display: inline-flex; align-items: center; justify-content: center; padding: 4px; line-height: 1;" title="Cerrar Formulario">
        &times;
      </button>
    </header>
    <div class="tarjeta-cuerpo" style="padding: 16px;">
      <form id="form-sitio-teams" method="POST" action="<?= url('teams/sitio') ?>" style="display: flex; flex-direction: column; gap: 14px;">
        <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
        <div>
          <label style="display: block; font-weight: bold; margin-bottom: 4px; color: var(--texto);">Nombre del Grupo o Sitio <span class="req">*</span></label>
          <input type="text" id="form-sitio-teams-nombre" name="nombre" required placeholder="Ej: DCTBC - Departamento de Control" style="width: 100%; padding: 8px; border: 1px solid var(--gris-100); border-radius: var(--radio); font-size: 13px;">
          <small class="texto-suave">Un nombre identificable para organizar tus carpetas.</small>
        </div>

        <div>
          <label style="display: block; font-weight: bold; margin-bottom: 4px; color: var(--texto);">Enlace Base de SharePoint/Teams <span class="req">*</span></label>
          <input type="url" id="form-sitio-teams-url-base" name="url_base" required placeholder="https://alumnouaemex.sharepoint.com/sites/..." style="width: 100%; padding: 8px; border: 1px solid var(--gris-100); border-radius: var(--radio); font-size: 13px;">
          <small class="texto-suave">La URL base de la sección "Documentos" o "Shared Documents" de tu sitio en SharePoint o Teams.</small>
        </div>

        <button type="submit" class="btn btn-primario" style="margin-top: 6px; width: 100%; display: inline-flex; justify-content: center; align-items: center; gap: 6px;">
          <?= icono('ok', 14) ?> <span id="form-sitio-teams-submit-btn-texto">Agregar Sitio de Teams</span>
        </button>
      </form>
    </div>
  </section>
</div>

<!-- Modal de Metadatos de Carpeta -->
<div id="modal-metadatos" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
  <div style="background:var(--blanco); padding:24px; border-radius:var(--radio); width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.15); position:relative;">
    <button onclick="cerrarModal()" style="position:absolute; top:12px; right:12px; border:none; background:none; cursor:pointer; color:var(--gris-600); font-size: 18px; padding: 4px; display: flex; align-items: center; justify-content: center;">
      &times;
    </button>
    <h3 id="modal-titulo" style="color:var(--verde-900); margin:0 0 10px 0; font-size: 16px; font-weight: bold;"></h3>
    <span class="monoespacio" id="modal-ruta" style="display: block; font-size:11px; color:var(--gris-600); margin-bottom:14px; word-break: break-all;"></span>
    
    <div style="background:#fafafa; padding:12px; border-radius:var(--radio); border:1px solid var(--gris-100); min-height:60px; margin-bottom: 20px;">
      <p id="modal-descripcion" style="margin:0; font-size:13px; color:var(--texto); white-space:pre-wrap;"></p>
    </div>
    
    <div style="display:flex; justify-content:flex-end; gap:8px; align-items:center;">
      <button onclick="cerrarModal(); window.location.href='<?= url('tickets/nuevo') ?>?teams_carpeta_id=' + currentModalId" class="btn" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; padding:0; border:1px solid var(--gris-100);" title="Agregar Ticket / Tarea a esta Carpeta">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
      </button>
      <button id="modal-btn-copiar" onclick="copiarEnlaceModal()" class="btn" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; padding:0; border:1px solid var(--gris-100);" title="Copiar Datos para Compartir">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
      </button>
      <a id="modal-btn-abrir" href="#" target="_blank" class="btn btn-primario" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; padding:0;" title="Abrir en Teams/SharePoint">
        <?= icono('entrada', 16) ?>
      </a>
    </div>
  </div>
</div>


<style>
/* Estilos para el árbol de carpetas colapsable estilo menú sidebar */
.arbol-carpetas ul {
  list-style-type: none;
  padding-left: 12px;
  margin: 2px 0;
  display: none; /* Colapsado por defecto */
}
.arbol-carpetas > ul {
  padding-left: 0;
  display: block; /* Raíz siempre visible */
}
.arbol-carpetas li {
  margin: 2px 0;
  font-size: 13px;
}
.nodo-elemento {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 6px;
  border-radius: 4px;
  cursor: pointer;
  user-select: none;
  color: var(--texto);
  transition: background 0.15s;
}
.nodo-elemento:hover {
  background: var(--verde-050);
}
.nodo-elemento.tiene-hijos {
  font-weight: bold;
  color: var(--oro-texto);
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}
.nodo-elemento.indexado {
  color: #1e293b;
  font-weight: normal;
}
.nodo-desc {
  font-size: 11px;
  color: var(--gris-600);
  font-weight: normal;
  font-style: italic;
  margin-left: 6px;
  opacity: 0.85;
}
.nodo-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 12px;
  height: 12px;
  cursor: pointer;
  color: var(--oro-texto);
  font-size: 10px;
  transition: transform 0.2s;
}
</style>

<script>
const sitiosData = <?= json_encode($sitiosJson) ?>;

function copiarEnlace(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<span style="font-size: 11px; color: var(--ok); font-weight: bold;">Copiado!</span>';
    setTimeout(() => {
      btn.innerHTML = originalContent;
    }, 1500);
  });
}

let currentModalId = null;
let currentModalUrl = '';
let currentModalNombre = '';
let currentModalDescripcion = '';

function abrirModal(id, nombre, ruta, descripcion, urlFinal) {
  currentModalId = id;
  currentModalUrl = urlFinal;
  currentModalNombre = nombre;
  currentModalDescripcion = descripcion || '';
  document.getElementById('modal-titulo').textContent = nombre;
  document.getElementById('modal-ruta').textContent = ruta;
  document.getElementById('modal-descripcion').textContent = descripcion || 'Sin descripción asignada.';
  document.getElementById('modal-btn-abrir').href = urlFinal;
  document.getElementById('modal-metadatos').style.display = 'flex';
}

function copiarEnlaceModal() {
  if (currentModalUrl) {
    const descText = currentModalDescripcion ? '\n' + currentModalDescripcion : '';
    const textoCopiar = `*${currentModalNombre}*${descText}\nEnlace: ${currentModalUrl}`;
    
    const btn = document.getElementById('modal-btn-copiar');
    const originalText = btn.innerHTML;
    navigator.clipboard.writeText(textoCopiar).then(() => {
      btn.innerHTML = '<span style="font-size: 14px; font-weight: bold; color: var(--ok);">✓</span>';
      setTimeout(() => { btn.innerHTML = originalText; }, 1500);
    });
  }
}

function cerrarModal() {
  document.getElementById('modal-metadatos').style.display = 'none';
}

// Cerrar modal al hacer clic fuera del recuadro
document.getElementById('modal-metadatos').addEventListener('click', function(e) {
  if (e.target === this) {
    cerrarModal();
  }
});

function resaltarCoincidencia(texto, query) {
  if (!query || !texto) return escapeHtml(texto);
  const textoEscapado = escapeHtml(texto);
  const queryEscapada = escapeHtml(query).replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
  const regex = new RegExp(`(${queryEscapada})`, 'gi');
  return textoEscapado.replace(regex, '<mark style="background-color: #d1fae5; color: inherit; padding: 0 2px; border-radius: 2px; font-weight: bold;">$1</mark>');
}

function getNombreLimpio(ruta) {
  let temp = ruta;
  if (temp.startsWith('http://') || temp.startsWith('https://')) {
    if (temp.includes('?')) {
      temp = temp.split('?')[0];
    }
    temp = temp.replace(/\/$/, "");
    const partes = temp.split('/');
    temp = partes[partes.length - 1] || ruta;
  } else {
    if (temp.includes('?')) {
      temp = temp.split('?')[0];
    }
    const partes = temp.split('/');
    temp = partes[partes.length - 1];
  }
  return decodeURIComponent(temp);
}

function toggleSitio(sitioId) {
  const el = document.getElementById(`arbol-sitio-${sitioId}`);
  const toggleBtn = document.getElementById(`sitio-toggle-${sitioId}`);
  if (el.style.display === 'none' || el.style.display === '') {
    el.style.display = 'block';
    toggleBtn.style.transform = 'rotate(90deg)';
    toggleBtn.innerHTML = '▼';
  } else {
    el.style.display = 'none';
    toggleBtn.style.transform = 'none';
    toggleBtn.innerHTML = '▸';
  }
}

let originalBtnHtml = '';
function toggleFormularioSitio() {
  const colForm = document.getElementById('columna-formulario-sitio');
  const rejilla = document.getElementById('rejilla-principal');
  const btn = document.getElementById('btn-agregar-sitio-toggle');
  
  if (!originalBtnHtml && btn) {
    originalBtnHtml = btn.innerHTML;
  }
  
  if (colForm.style.display === 'none' || colForm.style.display === '') {
    colForm.style.display = 'block';
    rejilla.style.gridTemplateColumns = '1.2fr 0.8fr';
    if (btn) {
      btn.innerHTML = '&times;';
      btn.style.background = 'var(--gris-600)';
      btn.style.fontSize = '18px';
    }
  } else {
    colForm.style.display = 'none';
    rejilla.style.gridTemplateColumns = '1fr';
    if (btn) {
      btn.innerHTML = originalBtnHtml;
      btn.style.background = '';
      btn.style.fontSize = '';
    }
    resetearFormularioSitio();
  }
}

function iniciarEditarSitio(id, nombre, urlBase) {
  const colForm = document.getElementById('columna-formulario-sitio');
  if (colForm.style.display === 'none' || colForm.style.display === '') {
    toggleFormularioSitio();
  }
  
  document.getElementById('titulo-formulario-sitio-texto').textContent = 'Editar Sitio de Teams';
  document.getElementById('form-sitio-teams').action = `<?= url('teams/sitio') ?>/${id}/editar`;
  document.getElementById('form-sitio-teams-nombre').value = nombre;
  document.getElementById('form-sitio-teams-url-base').value = urlBase;
  document.getElementById('form-sitio-teams-submit-btn-texto').textContent = 'Guardar Cambios';
}

function resetearFormularioSitio() {
  document.getElementById('titulo-formulario-sitio-texto').textContent = 'Registrar Sitio de Teams';
  document.getElementById('form-sitio-teams').action = '<?= url('teams/sitio') ?>';
  document.getElementById('form-sitio-teams-nombre').value = '';
  document.getElementById('form-sitio-teams-url-base').value = '';
  document.getElementById('form-sitio-teams-submit-btn-texto').textContent = 'Agregar Sitio de Teams';
}

// Construir árbol de carpetas colapsable
function construirArbol(sitioId) {
  const sitio = sitiosData.find(s => s.id == sitioId);
  const contenedor = document.getElementById(`arbol-sitio-${sitioId}`);
  if (!contenedor || !sitio) return;

  if (!sitio.carpetas || sitio.carpetas.length === 0) {
    contenedor.innerHTML = '<span class="texto-suave" style="font-style: italic; font-size: 12px; padding-left: 8px;">Sin carpetas indexadas.</span>';
    return;
  }

  // Raíz virtual del árbol
  const raiz = {};

  // Mapear cada ruta a la estructura jerárquica
  sitio.carpetas.forEach(c => {
    const isUrl = c.ruta_relativa.startsWith('http://') || c.ruta_relativa.startsWith('https://');
    let partes = [];
    if (isUrl) {
      partes = [getNombreLimpio(c.ruta_relativa)];
    } else {
      partes = c.ruta_relativa.split('/');
    }

    let actual = raiz;
    partes.forEach((parte, index) => {
      const parteLimpia = getNombreLimpio(parte);
      if (!actual[parteLimpia]) {
        actual[parteLimpia] = {
          _nombre: parteLimpia,
          _ruta: isUrl ? c.ruta_relativa : partes.slice(0, index + 1).join('/'),
          _hijos: {}
        };
      }
      if (index === partes.length - 1) {
        actual[parteLimpia]._id = c.id;
        actual[parteLimpia]._descripcion = c.descripcion;
        actual[parteLimpia]._indexado = true;
      }
      actual = actual[parteLimpia]._hijos;
    });
  });

  // Generar HTML
  function renderNodo(nodo) {
    const li = document.createElement('li');
    const llavesHijos = Object.keys(nodo._hijos);
    const tieneHijos = llavesHijos.length > 0;
    
    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.alignItems = 'center';
    wrapper.style.gap = '2px';

    let toggleBtn = null;
    if (tieneHijos) {
      toggleBtn = document.createElement('span');
      toggleBtn.className = 'nodo-toggle';
      toggleBtn.innerHTML = '▶'; // Triángulo como en el menú de gestión
    } else {
      toggleBtn = document.createElement('span');
      toggleBtn.style.width = '12px'; // Espaciador de alineación
    }
    wrapper.appendChild(toggleBtn);
    
    const div = document.createElement('div');
    div.className = 'nodo-elemento' + (tieneHijos ? ' tiene-hijos' : ' indexado');
    div.style.flex = '1';
    
    // Mostrar el metadato (descripción) por defecto, fallback al nombre si no tiene
    const textoMostrar = (nodo._indexado && nodo._descripcion && nodo._descripcion.trim() !== '') 
      ? nodo._descripcion.trim() 
      : nodo._nombre;
    
    div.innerHTML = `<span style="white-space: nowrap;">${textoMostrar}</span>`;

    let ulHijos = null;
    if (tieneHijos) {
      ulHijos = document.createElement('ul');
      llavesHijos.sort().forEach(h => {
        ulHijos.appendChild(renderNodo(nodo._hijos[h]));
      });
      
      const toggle = (e) => {
        e.stopPropagation();
        const isCollapsed = ulHijos.style.display === 'none' || ulHijos.style.display === '';
        ulHijos.style.display = isCollapsed ? 'block' : 'none';
        if (toggleBtn) {
          if (isCollapsed) {
            toggleBtn.innerHTML = '▼';
          } else {
            toggleBtn.innerHTML = '▶';
          }
        }
      };

      if (toggleBtn) {
        toggleBtn.addEventListener('click', toggle);
      }
      
      // Expandir también al hacer clic sobre el nombre del elemento padre
      div.addEventListener('click', toggle);
    }

    if (nodo._indexado) {
      let urlFinal = '';
      if (nodo._ruta.startsWith('http://') || nodo._ruta.startsWith('https://')) {
        urlFinal = nodo._ruta;
      } else {
        const urlEncodedPath = nodo._ruta.split('/').map(encodeURIComponent).join('/');
        urlFinal = sitio.url_base + '/' + urlEncodedPath;
      }

      div.tabIndex = 0;
      div.setAttribute('role', 'link');
      div.setAttribute('aria-label', 'Abrir ' + nodo._nombre + ' en Teams');
      const openFolder = () => { if (/^https?:\/\//i.test(urlFinal)) window.open(urlFinal, '_blank', 'noopener'); };
      div.addEventListener('click', (e) => { e.stopPropagation(); openFolder(); });
      div.addEventListener('keydown', (e) => { if(e.target===div && (e.key==='Enter'||e.key===' ')){e.preventDefault();openFolder();} });
      const details = document.createElement('button');
      details.type = 'button'; details.className = 'btn'; details.textContent = 'Detalles';
      details.addEventListener('click', (e) => {e.stopPropagation();abrirModal(nodo._id, nodo._nombre, nodo._ruta, nodo._descripcion, urlFinal);});
      wrapper.appendChild(details);
    }

    wrapper.appendChild(div);
    li.appendChild(wrapper);
    
    if (ulHijos) {
      li.appendChild(ulHijos);
    }

    return li;
  }

  const ulRaiz = document.createElement('ul');
  Object.keys(raiz).sort().forEach(key => {
    ulRaiz.appendChild(renderNodo(raiz[key]));
  });

  contenedor.innerHTML = '';
  contenedor.appendChild(ulRaiz);
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

function limpiarBuscador() {
  const input = document.getElementById('buscar-input');
  input.value = '';
  document.getElementById('seccion-resultados-busqueda').style.display = 'none';
  document.getElementById('lista-resultados-busqueda').innerHTML = '';
  input.focus();
}

// Cargar árboles al cargar la página e inicializar buscador dinámico
document.addEventListener('DOMContentLoaded', () => {
  sitiosData.forEach(sitio => {
    construirArbol(sitio.id);
  });

  const buscarInput = document.getElementById('buscar-input');
  if (buscarInput) {
    buscarInput.focus();
    buscarInput.addEventListener('input', function(e) {
      const q = e.target.value.trim().toLowerCase();
      const seccion = document.getElementById('seccion-resultados-busqueda');
      const lista = document.getElementById('lista-resultados-busqueda');
      const titulo = document.getElementById('titulo-resultados-busqueda');
      
      if (q.length < 2) {
        seccion.style.display = 'none';
        lista.innerHTML = '';
        return;
      }
      
      const resultados = [];
      sitiosData.forEach(sitio => {
        sitio.carpetas.forEach(c => {
          const nombreLimpio = getNombreLimpio(c.ruta_relativa);
          const desc = c.descripcion || '';
          if (c.ruta_relativa.toLowerCase().includes(q) || desc.toLowerCase().includes(q) || sitio.nombre.toLowerCase().includes(q)) {
            let urlFinal = '';
            const isUrl = c.ruta_relativa.startsWith('http://') || c.ruta_relativa.startsWith('https://');
            if (isUrl) {
              urlFinal = c.ruta_relativa;
            } else {
              const urlEncodedPath = c.ruta_relativa.split('/').map(encodeURIComponent).join('/');
              urlFinal = sitio.url_base + '/' + urlEncodedPath;
            }
            resultados.push({
              id: c.id,
              sitioNombre: sitio.nombre,
              nombreLimpio: nombreLimpio,
              descripcion: desc,
              rutaRelativa: c.ruta_relativa,
              urlFinal: urlFinal
            });
          }
        });
      });
      
      titulo.innerHTML = `Resultados para «${escapeHtml(e.target.value)}» (${resultados.length} encontrados)`;
      lista.innerHTML = '';
      
      if (resultados.length === 0) {
        lista.innerHTML = '<p class="texto-suave" style="padding: 10px 0; text-align: center;">No se encontraron carpetas indexadas con esa descripción o nombre.</p>';
      } else {
        resultados.forEach(r => {
          const row = document.createElement('div');
          row.style.background = 'var(--blanco)';
          row.style.border = '1px solid var(--gris-100)';
          row.style.borderRadius = 'var(--radio)';
          row.style.padding = '8px 16px';
          row.style.display = 'flex';
          row.style.alignItems = 'center';
          row.style.justifyContent = 'space-between';
          row.style.gap = '12px';
          row.style.boxShadow = '0 1px 3px rgba(0,0,0,0.02)';
          
          const textoMostrar = r.descripcion && r.descripcion.trim() !== '' ? r.descripcion.trim() : r.nombreLimpio;
          const textoResaltado = resaltarCoincidencia(textoMostrar, e.target.value.trim());
          
          const isUrl = r.rutaRelativa.startsWith('http://') || r.rutaRelativa.startsWith('https://');
          const textoSecundario = isUrl ? r.nombreLimpio : r.rutaRelativa;
          const textoSecundarioResaltado = resaltarCoincidencia(textoSecundario, e.target.value.trim());
          
          row.innerHTML = `
            <div style="display:flex; align-items:center; gap:12px; min-width: 0; flex:1;">
              <span style="background:var(--oro-050); color:var(--oro-texto); font-size:11px; font-weight:bold; padding:2px 6px; border-radius:4px; white-space:nowrap;">${escapeHtml(r.sitioNombre)}</span>
              <div style="display:flex; flex-direction:column; min-width: 0;">
                <span style="font-size:13px; font-weight:bold; color:var(--texto); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(textoMostrar)}">${textoResaltado}</span>
                <span class="monoespacio texto-suave" style="font-size:11px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:2px;" title="${escapeHtml(textoSecundario)}">${textoSecundarioResaltado}</span>
              </div>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <button onclick="window.location.href='<?= url('tickets/nuevo') ?>?teams_carpeta_id=${r.id}'" class="btn" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; padding:0; border:1px solid var(--gris-100);" title="Agregar Ticket / Tarea a esta Carpeta">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
              </button>
              <button class="btn btn-mini btn-copiar-resultado" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; padding:0;" title="Copiar Enlace">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>
              <a href="${escapeHtml(r.urlFinal)}" target="_blank" class="btn btn-mini btn-primario" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; padding:0;" title="Abrir Carpeta">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
              </a>
            </div>
          `;
          
          row.querySelector('.btn-copiar-resultado').addEventListener('click', function(evt) {
            evt.preventDefault();
            const btn = this;
            navigator.clipboard.writeText(r.urlFinal).then(() => {
              const originalHtml = btn.innerHTML;
              btn.innerHTML = '<span style="font-size: 11px; font-weight: bold; color: var(--ok);">✓</span>';
              setTimeout(() => {
                btn.innerHTML = originalHtml;
              }, 1500);
            });
          });
          
          lista.appendChild(row);
        });
      }
      
      seccion.style.display = 'block';
    });
  }
});

function cambiarCuentaTeams(email) {
  window.location.href = `?active_account=${encodeURIComponent(email)}`;
}

function vincularNuevaCuentaTeams() {
  const email = prompt("Ingresa el correo institucional de Microsoft Teams que deseas vincular:", "");
  if (!email) return;
  if (!email.includes("@")) {
    alert("Por favor ingresa un correo institucional válido.");
    return;
  }
  
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= url("teams/vincular") ?>';
  
  const inputEmail = document.createElement('input');
  inputEmail.type = 'hidden';
  inputEmail.name = 'email';
  inputEmail.value = email;
  
  const inputCsrf = document.createElement('input');
  inputCsrf.type = 'hidden';
  inputCsrf.name = '_csrf';
  inputCsrf.value = '<?= e(\App\Core\Auth::csrfToken()) ?>';
  
  form.appendChild(inputEmail);
  form.appendChild(inputCsrf);
  document.body.appendChild(form);
  form.submit();
}

function desvincularCuentaTeams(email) {
  if (!confirm(`¿Estás seguro de que deseas desvincular la cuenta ${email}? Se eliminarán los grupos y carpetas indexados bajo esta cuenta.`)) {
    return;
  }
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '<?= url("teams/desvincular") ?>';
  
  const inputEmail = document.createElement('input');
  inputEmail.type = 'hidden';
  inputEmail.name = 'email';
  inputEmail.value = email;
  
  const inputCsrf = document.createElement('input');
  inputCsrf.type = 'hidden';
  inputCsrf.name = '_csrf';
  inputCsrf.value = '<?= e(\App\Core\Auth::csrfToken()) ?>';
  
  form.appendChild(inputEmail);
  form.appendChild(inputCsrf);
  document.body.appendChild(form);
  form.submit();
}
</script>
