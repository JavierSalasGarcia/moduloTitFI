<?php
/**
 * Vista de Gestión de un Sitio de Teams específico con Explorador de Carpetas Local
 */
?>
<div style="margin-bottom: 20px;">
  <a href="<?= url('teams') ?>" class="btn" style="border: 1px solid var(--gris-100); display: inline-flex; align-items: center; gap: 6px;">
    <?= icono('flecha-izq', 16) ?> Regresar a Explorar Teams
  </a>
</div>

<div style="margin-bottom: 30px;">
  <!-- Indexar Carpetas en Lote / Local Folder Picker -->
  <section class="tarjeta" style="background: var(--blanco); border-radius: var(--radio); box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <header class="tarjeta-cabecera" style="padding: 16px; border-bottom: 1px solid var(--gris-100); display: flex; justify-content: space-between; align-items: center;">
      <h2 style="margin: 0; font-size: 16px; color: var(--verde-900); display: flex; align-items: center; gap: 6px;">
        <?= icono('lista', 18) ?> Importador / Indexador de Carpetas
      </h2>
      <span id="soporte-api" style="font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 12px;"></span>
    </header>
    <div class="tarjeta-cuerpo" style="padding: 16px;">
      
      <!-- Zona para Navegador con soporte File System Access API -->
      <div id="zona-picker-local" style="display: none; text-align: center; padding: 20px; background: #fafafa; border: 2px dashed var(--gris-100); border-radius: var(--radio); margin-bottom: 16px;">
        <p style="margin: 0 0 14px 0; font-size: 14px; color: var(--texto);">
          Selecciona tu carpeta local sincronizada (ej: OneDrive o Teams) para escanear sus subcarpetas y elegir cuáles indexar.
        </p>
        <button type="button" id="btn-seleccionar-directorio" onclick="seleccionarDirectorio()" class="btn btn-primario" style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; padding: 10px 18px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
          Seleccionar Carpeta Sincronizada
        </button>
        <div id="cargando-scan" style="display: none; margin-top: 12px; font-size: 13px; color: var(--verde-700); font-weight: bold;">
          Escaneando carpetas de forma recursiva... por favor espera.
        </div>
      </div>

      <!-- Agregar Enlace Externo (de otro equipo) -->
      <div style="border-top: 1px solid var(--gris-100); padding-top: 16px; margin-top: 16px; margin-bottom: 16px;">
        <h3 style="margin: 0 0 12px 0; font-size: 14px; color: var(--verde-900); display: flex; align-items: center; gap: 6px;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; color: var(--oro-texto);"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
          Agregar Enlace Externo (de otro Equipo / SharePoint)
        </h3>
        <form method="POST" action="<?= url('teams/sitio/' . $sitio['id'] . '/carpeta') ?>" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
          <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
          
          <div style="flex: 2; min-width: 250px;">
            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; color: var(--texto);">Enlace Completo de SharePoint:</label>
            <input type="url" name="ruta_relativa" required placeholder="https://alumnouaemex.sharepoint.com/sites/OTRO_SITIO/Shared Documents/..." style="width: 100%; padding: 6px 10px; border: 1px solid var(--gris-100); border-radius: 4px; font-size: 13px;">
          </div>
          
          <div style="flex: 1; min-width: 180px;">
            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px; color: var(--texto);">Descripción (Metadato):</label>
            <input type="text" name="descripcion" required placeholder="Ej: Planeación de otro departamento" style="width: 100%; padding: 6px 10px; border: 1px solid var(--gris-100); border-radius: 4px; font-size: 13px;">
          </div>
          
          <button type="submit" class="btn btn-primario" style="display: inline-flex; align-items: center; justify-content: center; height: 32px; width: 32px; padding: 0;" title="Agregar Enlace Externo">
            <?= icono('mas', 14) ?>
          </button>
        </form>
      </div>

      <!-- Zona de Fallback (Copiado manual) si el navegador no lo soporta -->
      <div id="zona-fallback-copiado" style="display: none;">
        <div style="background: var(--verde-050); padding: 12px; border-radius: var(--radio); margin-bottom: 14px; font-size: 12px; color: var(--verde-900);">
          <strong>Tu navegador no soporta el explorador de carpetas local. Puedes copiar las rutas manualmente:</strong>
          <ol style="margin: 6px 0 0; padding-left: 18px; line-height: 1.4;">
            <li>Mantén presionado <strong>Shift (Mayús)</strong>, haz clic derecho en tus carpetas locales sincronizadas y selecciona <strong>"Copiar como ruta de acceso"</strong>.</li>
            <li>Pégalas a continuación.</li>
          </ol>
        </div>

        <form method="POST" action="<?= url('teams/sitio/' . $sitio['id'] . '/indexar-lote') ?>">
          <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
          <textarea name="lote" rows="6" placeholder="Pega las rutas aquí (una por línea)..." style="width: 100%; padding: 8px; border: 1px solid var(--gris-100); border-radius: var(--radio); font-family: monospace; font-size: 11px; resize: vertical;"></textarea>
          <button type="submit" class="btn btn-primario" style="margin-top: 10px; width: 100%;">Procesar e Indexar Lote</button>
        </form>
      </div>

      <!-- Formulario para enviar los resultados seleccionados del scan local -->
      <form id="form-scan-resultados" method="POST" action="<?= url('teams/sitio/' . $sitio['id'] . '/indexar-lote') ?>" style="display: none; margin-top: 20px;">
        <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
        <input type="hidden" name="sincronizar_completo" value="1">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 10px;">
          <input type="text" id="filtro-scan" placeholder="Filtrar carpetas encontradas por nombre..." oninput="filtrarScan()" style="flex: 1; padding: 6px 10px; border: 1px solid var(--gris-100); border-radius: 4px; font-size: 13px;">
          <div style="font-size: 12px; color: var(--gris-600); white-space: nowrap;">
            <button type="button" onclick="marcarTodosScan(true)" class="btn btn-mini" style="margin-right: 4px;">Marcar Todas</button>
            <button type="button" onclick="marcarTodosScan(false)" class="btn btn-mini">Desmarcar Todas</button>
          </div>
        </div>

        <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--gris-100); border-radius: var(--radio); margin-bottom: 16px; background: #fafafa;">
          <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
            <thead>
              <tr style="background: var(--blanco); border-bottom: 2px solid var(--gris-100); position: sticky; top: 0; z-index: 10;">
                <th style="padding: 8px 10px; width: 8%; text-align: center;">Indexar</th>
                <th style="padding: 8px 10px; width: 42%;">Ruta de Carpeta Local</th>
                <th style="padding: 8px 10px; width: 50%;">Descripción / Metadatos (Opcional)</th>
              </tr>
            </thead>
            <tbody id="tbody-resultados-scan">
              <!-- Cargado dinámicamente -->
            </tbody>
          </table>
        </div>

        <button type="submit" class="btn btn-primario" style="width: 100%; font-size: 14px; padding: 10px; display: inline-flex; justify-content: center; align-items: center; gap: 6px;">
          <?= icono('ok', 14) ?> Indexar Carpetas Seleccionadas (<span id="contador-seleccionados">0</span>)
        </button>
      </form>

    </div>
  </section>
</div>

<!-- Listado de Carpetas Indexadas -->
<section class="tarjeta" style="background: var(--blanco); border-radius: var(--radio); box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-top: 20px;">
  <header class="tarjeta-cabecera" style="padding: 16px; border-bottom: 1px solid var(--gris-100); display: flex; justify-content: space-between; align-items: center;">
    <h2 style="margin: 0; font-size: 16px; color: var(--verde-900);">
      Carpetas Indexadas en este Sitio (<?= count($carpetas) ?>)
    </h2>
    <button type="button" id="btn-reindexar-cabecera" onclick="seleccionarDirectorio()" class="btn btn-mini btn-primario" style="display: none; align-items: center; gap: 6px;" title="Reindexar/Sincronizar carpetas del sitio">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path></svg> Reindexar
    </button>
  </header>
  <div class="tarjeta-cuerpo" style="padding: 16px;">
    <?php if (empty($carpetas)): ?>
      <p class="texto-suave" style="text-align: center; padding: 30px 0;">No hay carpetas indexadas en este sitio todavía.</p>
    <?php else: ?>
      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
          <thead>
            <tr style="border-bottom: 2px solid var(--gris-100); color: var(--verde-900);">
              <th style="padding: 10px; font-weight: bold; width: 45%;">Ruta de Carpeta</th>
              <th style="padding: 10px; font-weight: bold; width: 40%;">Descripción</th>
              <th style="padding: 10px; font-weight: bold; width: 15%; text-align: right;">
                <div style="display: inline-flex; align-items: center; gap: 8px; justify-content: flex-end; width: 100%;">
                  <input type="checkbox" id="master-select-eliminar" onchange="seleccionarTodasCarpetasEliminar(this.checked)" style="width: 15px; height: 15px; cursor: pointer;" title="Seleccionar todas para desindexar">
                  <span>Acciones</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($carpetas as $c): 
              $isUrl = str_starts_with($c['ruta_relativa'], 'http://') || str_starts_with($c['ruta_relativa'], 'https://');
              if ($isUrl) {
                  $urlFinal = $c['ruta_relativa'];
                  $tempName = explode('?', $c['ruta_relativa'])[0];
                  $tempName = rtrim($tempName, '/');
                  $nombreMostrar = urldecode(basename($tempName));
              } else {
                  $urlCarpetas = explode('/', $c['ruta_relativa']);
                  $urlEncodedPath = implode('/', array_map('rawurlencode', $urlCarpetas));
                  $urlFinal = $sitio['url_base'] . '/' . $urlEncodedPath;
                  $nombreMostrar = basename($c['ruta_relativa']);
              }
            ?>
              <tr style="border-bottom: 1px solid var(--gris-100); vertical-align: top;">
                <td style="padding: 12px 10px;">
                  <strong style="display: block; font-size: 14px; color: var(--texto);"><?= e($nombreMostrar) ?></strong>
                  <span class="monoespacio texto-suave" style="font-size: 11px; display: block; word-break: break-all; margin-top: 4px;"><?= e($c['ruta_relativa']) ?></span>
                </td>
                <td style="padding: 12px 10px;">
                  <form method="POST" action="<?= url('teams/carpeta/' . $c['id'] . '/descripcion') ?>" class="form-descripcion-inline" style="display: flex; gap: 6px; align-items: stretch;">
                    <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
                    <textarea name="descripcion" placeholder="Escribe descripción y presiona Guardar..." style="flex: 1; padding: 6px; border: 1px solid var(--gris-100); border-radius: 4px; font-size: 12px; height: 38px; resize: vertical;"><?= e($c['descripcion']) ?></textarea>
                    <button type="submit" class="btn btn-mini btn-primario" style="height: 38px; width: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Guardar descripción">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                    </button>
                  </form>
                </td>
                <td style="padding: 12px 10px; text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 10px; align-items: center;">
                    <input type="checkbox" class="select-carpeta-eliminar" value="<?= $c['id'] ?>" style="width: 16px; height: 16px; cursor: pointer;" title="Seleccionar para desindexar">
                    <a href="<?= $urlFinal ?>" target="_blank" class="btn btn-mini btn-primario" style="display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; padding: 0;" title="Abrir carpeta en Teams/SharePoint">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Barra de acciones múltiples para desindexar al estilo phpMyAdmin -->
      <div id="bulk-delete-bar" style="display: none; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 12px; padding: 8px 12px; background: #fff5f5; border: 1px solid #fee2e2; border-radius: var(--radio);">
        <span style="font-size: 12px; color: var(--error); font-weight: bold;">
          Seleccionadas: <span id="bulk-delete-count">0</span> carpeta(s)
        </span>
        <button type="button" onclick="eliminarCarpetasSeleccionadas()" class="btn btn-mini" style="border: 1px solid var(--error); color: var(--error); background: #fff; display: inline-flex; align-items: center; gap: 6px; font-weight: bold;" title="Desindexar todas las seleccionadas">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
          Desindexar
        </button>
      </div>

      <!-- Formulario oculto para el envío bulk -->
      <form id="form-bulk-delete" method="POST" action="<?= url('teams/sitio/' . $sitio['id'] . '/desindexar-bulk') ?>" style="display: none;">
        <input type="hidden" name="_csrf" value="<?= \App\Core\Auth::csrfToken() ?>">
        <div id="bulk-delete-ids-inputs"></div>
      </form>
    <?php endif; ?>
  </div>
</section>

<script>
// Lista de carpetas indexadas en el servidor
const carpetasIndexadas = <?= json_encode($carpetas) ?>;

// Detección de soporte para File System Access API
const soportePicker = typeof window.showDirectoryPicker === 'function';
const sopBadge = document.getElementById('soporte-api');
const btnReindexar = document.getElementById('btn-reindexar-cabecera');

if (soportePicker) {
  sopBadge.textContent = 'Explorador Activo';
  sopBadge.style.background = 'var(--verde-050)';
  sopBadge.style.color = 'var(--verde-900)';
  document.getElementById('zona-picker-local').style.display = 'block';
  if (btnReindexar) {
    btnReindexar.style.display = 'inline-flex';
  }
} else {
  sopBadge.textContent = 'Modo Manual';
  sopBadge.style.background = 'var(--oro-050)';
  sopBadge.style.color = 'var(--oro-texto)';
  document.getElementById('zona-fallback-copiado').style.display = 'block';
  if (btnReindexar) {
    btnReindexar.style.display = 'none';
  }
}

// Limpiador de rutas en Javascript equivalente al del servidor PHP
function limpiarRutaJS(ruta) {
  let r = ruta.replace(/\\/g, '/');

  const tokens = ['Shared Documents', 'Documentos compartidos', 'Documentos'];
  for (const t of tokens) {
    const idx = r.toLowerCase().indexOf('/' + t.toLowerCase());
    if (idx !== -1) {
      return r.substring(idx + t.length + 2).replace(/^\/+|\/+$/g, '');
    }
    if (r.toLowerCase().startsWith(t.toLowerCase())) {
      return r.substring(t.length).replace(/^\/+|\/+$/g, '');
    }
  }

  const partes = r.split('/');
  for (let i = 0; i < partes.length; i++) {
    if (partes[i].includes(' - ')) {
      const sub = partes[i].split(' - ');
      const canal = sub[sub.length - 1].trim();
      if (['documents', 'documentos', 'documentos compartidos'].includes(canal.toLowerCase())) {
        r = partes.slice(i + 1).join('/');
      } else {
        const resto = partes.slice(i);
        resto[0] = canal;
        r = resto.join('/');
      }
      break;
    }
  }

  return r.replace(/^\/+|\/+$/g, '');
}

let carpetasEncontradas = [];

async function seleccionarDirectorio() {
  try {
    const dirHandle = await window.showDirectoryPicker();
    document.getElementById('cargando-scan').style.display = 'block';
    document.getElementById('btn-seleccionar-directorio').disabled = true;
    if (btnReindexar) btnReindexar.disabled = true;
    
    carpetasEncontradas = [];
    await escanearRecursivo(dirHandle, '');
    
    document.getElementById('cargando-scan').style.display = 'none';
    document.getElementById('btn-seleccionar-directorio').disabled = false;
    if (btnReindexar) btnReindexar.disabled = false;
    
    mostrarResultadosScan();
  } catch (err) {
    console.error(err);
    document.getElementById('cargando-scan').style.display = 'none';
    document.getElementById('btn-seleccionar-directorio').disabled = false;
    if (btnReindexar) btnReindexar.disabled = false;
    if (err.name !== 'AbortError') {
      alert('No se pudo leer la carpeta: ' + err.message);
    }
  }
}

async function escanearRecursivo(dirHandle, rutaActual, depth = 0) {
  if (depth > 4) return;
  
  for await (const entry of dirHandle.values()) {
    if (entry.kind === 'directory') {
      if (entry.name.startsWith('.') || entry.name.startsWith('_')) {
        continue;
      }
      const nuevaRuta = rutaActual ? `${rutaActual}/${entry.name}` : entry.name;
      carpetasEncontradas.push(nuevaRuta);
      await escanearRecursivo(entry, nuevaRuta, depth + 1);
    }
  }
}

function mostrarResultadosScan() {
  const tbody = document.getElementById('tbody-resultados-scan');
  tbody.innerHTML = '';
  
  if (carpetasEncontradas.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3" style="padding: 20px; text-align: center; color: var(--gris-600);">No se encontraron subcarpetas en el directorio seleccionado.</td></tr>';
    document.getElementById('form-scan-resultados').style.display = 'none';
    return;
  }
  
  carpetasEncontradas.sort();
  
  carpetasEncontradas.forEach((ruta, idx) => {
    const rutaLimpia = limpiarRutaJS(ruta);
    const indexada = carpetasIndexadas.find(c => c.ruta_relativa.toLowerCase() === rutaLimpia.toLowerCase());

    const tr = document.createElement('tr');
    tr.className = 'fila-scan';
    tr.style.borderBottom = '1px solid var(--gris-100)';
    tr.dataset.ruta = ruta.toLowerCase();
    
    let descValue = '';
    let isChecked = false;

    if (indexada) {
      descValue = indexada.descripcion || '';
      isChecked = true;
    } else {
      const partesRuta = ruta.split('/');
      descValue = partesRuta[partesRuta.length - 1] || ruta;
      isChecked = false; // Desmarcada por defecto si es nueva
    }
    
    tr.innerHTML = `
      <td style="padding: 8px; text-align: center;">
        <input type="checkbox" name="rutas[]" value="${escapeHtml(ruta)}" ${isChecked ? 'checked' : ''} onchange="actualizarContadorSeleccionados()">
      </td>
      <td style="padding: 8px; font-family: monospace; font-size: 11px; word-break: break-all; color: var(--texto);">
        ${escapeHtml(ruta)}
      </td>
      <td style="padding: 6px 8px;">
        <input type="text" name="descripciones[${escapeHtml(ruta)}]" value="${escapeHtml(descValue)}" placeholder="Escribe metadatos o una breve descripción..." style="width: 100%; padding: 4px 8px; border: 1px solid var(--gris-100); border-radius: 4px; font-size: 12px;">
      </td>
    `;
    tbody.appendChild(tr);
  });
  
  document.getElementById('form-scan-resultados').style.display = 'block';
  actualizarContadorSeleccionados();
  
  document.getElementById('form-scan-resultados').scrollIntoView({ behavior: 'smooth' });
}

function actualizarContadorSeleccionados() {
  const checkboxes = document.querySelectorAll('#tbody-resultados-scan input[name="rutas[]"]:checked');
  document.getElementById('contador-seleccionados').textContent = checkboxes.length;
}

function marcarTodosScan(marcar) {
  const checkboxes = document.querySelectorAll('#tbody-resultados-scan input[name="rutas[]"]');
  checkboxes.forEach(cb => {
    // Solo aplicar a las que están visibles según el filtro
    const fila = cb.closest('tr');
    if (fila.style.display !== 'none') {
      cb.checked = marcar;
    }
  });
  actualizarContadorSeleccionados();
}

function filtrarScan() {
  const query = document.getElementById('filtro-scan').value.trim().toLowerCase();
  const filas = document.querySelectorAll('#tbody-resultados-scan .fila-scan');
  
  filas.forEach(fila => {
    const ruta = fila.dataset.ruta;
    if (ruta.includes(query)) {
      fila.style.display = '';
    } else {
      fila.style.display = 'none';
    }
  });
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

// Enviar descripciones de la lista principal por AJAX
document.querySelectorAll('.form-descripcion-inline').forEach(form => {
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = form.querySelector('button');
    const txt = form.querySelector('textarea');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '...';
    btn.disabled = true;
    
    const formData = new FormData(form);
    
    fetch(form.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        btn.innerHTML = '<span style="font-size: 14px; font-weight: bold; color: var(--ok);">✓</span>';
        setTimeout(() => {
          btn.innerHTML = originalHtml;
          btn.disabled = false;
        }, 1500);
      } else {
        alert('Error al guardar la descripción');
        btn.innerHTML = originalHtml;
        btn.disabled = false;
      }
    })
    .catch(err => {
      alert('Error de conexión');
      btn.innerHTML = originalHtml;
      btn.disabled = false;
    });
  });
});

// Funciones para selección múltiple y eliminación en lote (phpMyAdmin style)
function actualizarBarraEliminar() {
  const checkboxes = document.querySelectorAll('.select-carpeta-eliminar:checked');
  const bar = document.getElementById('bulk-delete-bar');
  const countSpan = document.getElementById('bulk-delete-count');
  
  if (bar) {
    if (checkboxes.length > 0) {
      bar.style.display = 'flex';
      countSpan.textContent = checkboxes.length;
    } else {
      bar.style.display = 'none';
    }
  }
}

function seleccionarTodasCarpetasEliminar(checked) {
  const checkboxes = document.querySelectorAll('.select-carpeta-eliminar');
  checkboxes.forEach(cb => cb.checked = checked);
  actualizarBarraEliminar();
}

document.querySelectorAll('.select-carpeta-eliminar').forEach(cb => {
  cb.addEventListener('change', actualizarBarraEliminar);
});

function eliminarCarpetasSeleccionadas() {
  const checkboxes = document.querySelectorAll('.select-carpeta-eliminar:checked');
  if (checkboxes.length === 0) return;
  
  if (!confirm(`¿Estás seguro de que deseas desindexar las ${checkboxes.length} carpetas seleccionadas? (No se borrarán los archivos reales en Teams)`)) {
    return;
  }
  
  const form = document.getElementById('form-bulk-delete');
  const container = document.getElementById('bulk-delete-ids-inputs');
  container.innerHTML = '';
  
  checkboxes.forEach(cb => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids[]';
    input.value = cb.value;
    container.appendChild(input);
  });
  
  form.submit();
}
</script>
