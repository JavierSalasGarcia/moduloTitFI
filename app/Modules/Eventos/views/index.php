<?php
use App\Core\Auth;
use App\Modules\Eventos\Clasificacion;
$siglasProg = array_column($programas, 'siglas', 'id');
$hayFiltro = $filtros['programa'] || $filtros['semestre'] || $filtros['tema'] !== '' || $filtros['categoria'] !== '' || $filtros['modalidad'] !== '';
?>

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
          
          <?php if (count($emisores) > 1): ?>
          <label>Emitir como
            <select name="depto_id">
              <?php foreach ($emisores as $em): ?><option value="<?= (int) $em['id'] ?>"><?= e($em['nombre']) ?></option><?php endforeach; ?>
            </select>
          </label>
          <?php endif; ?>

          <label>Dirigido a <span class="req">*</span>
            <select name="destinatarios">
              <?php foreach (\App\Modules\Comunicados\Avisos::DESTINATARIOS as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?>
            </select>
          </label>

          <label>Alcance <span class="req">*</span>
            <select name="alcance" onchange="toggleCarrera(this.value)">
              <option value="todos">Todos los programas</option>
              <option value="licenciatura">Solo Licenciatura</option>
              <option value="posgrado">Solo Posgrado</option>
              <option value="programa">Uno o varios programas específicos...</option>
            </select>
          </label>

          <fieldset id="carrera-select-wrapper" style="display: none; border:1px solid var(--gris-200); border-radius:6px; padding:8px 12px">
            <legend>Programas a los que aplica</legend>
            <small class="ayuda-form">Un solo aviso para varios programas: cada persona lo recibe una vez aunque pertenezca a más de uno.</small>
            <?php foreach ($programas as $p): ?>
              <label style="display:block;font-weight:400"><input type="checkbox" name="programas[]" value="<?= (int) $p['id'] ?>"> <?= e($p['siglas']) ?> · <?= e($p['nombre']) ?></label>
            <?php endforeach; ?>
          </fieldset>

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
            <span class="badge <?= $c['alcance'] === 'programa' ? 'badge-verde' : 'badge-oro' ?>" title="Destinatarios del aviso">Dirigido a: <?= e(\App\Modules\Comunicados\Avisos::destino($c)) ?></span>
            <?php if (Auth::tieneRol('admin') || in_array((int) $c['depto_id'], Auth::departamentoIds(), true)): ?>
              <form method="post" action="<?= url('comunicados/' . $c['id'] . '/eliminar') ?>" data-confirm="¿Retirar este comunicado?">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button type="submit" class="btn btn-mini btn-peligro">Retirar</button>
              </form>
            <?php endif; ?>
          </div>
        </header>
        <p class="comunicado-contenido-text" style="margin: 8px 0 0; line-height: 1.5; font-size: 13.5px;"><?= nl2br(e($c['contenido'])) ?></p>
        <p class="ayuda-form"><?= !empty($c['vence_en']) ? ($avisosArchivo?'Archivado · venció el ':'Vigente hasta ').e($c['vence_en']) : 'Sin fecha límite publicada' ?></p>

        <?php if ($puedeEmitirComunicados && (Auth::tieneRol('admin') || in_array((int) $c['depto_id'], Auth::departamentoIds(), true))): ?>
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
      
      <a class="btn" href="<?= url('eventos/expediente') ?>">Mi expediente</a>
      <?php if ($puedeEmitir): ?><a class="btn btn-primario" href="<?= url('eventos/nuevo') ?>">+ Publicar aviso de evento</a><?php endif; ?>
    </div>
  </div>

  <?php if ($perfil['grupo'] === 'alumno' && !$preferencias['vigente']): ?>
    <form method="post" action="<?= url('eventos/preferencias') ?>" class="card ux-toolbar" style="margin-bottom:14px;background:#FFFBEB;flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="volver" value="eventos">
      <?php foreach ($preferencias['temas'] as $t): ?><input type="hidden" name="temas[]" value="<?= e($t) ?>"><?php endforeach; ?>
      <?php if ($preferencias['notificar']): ?><input type="hidden" name="notificar" value="1"><?php endif; ?>
      <span><strong>¿En qué semestre estás este periodo (<?= e(Clasificacion::periodo()) ?>)?</strong> Así te mostramos los avisos que te tocan.</span>
      <select name="semestre" required><option value="">Elige…</option><?php for ($i = 1; $i <= Clasificacion::SEMESTRE_MAX; $i++): ?><option value="<?= $i ?>" <?= $preferencias['semestre'] === $i ? 'selected' : '' ?>><?= $i ?>.°</option><?php endfor; ?></select>
      <button class="btn btn-primario btn-mini" type="submit">Guardar</button>
    </form>
  <?php endif; ?>

  <form method="get" action="<?= url('eventos') ?>" class="card ux-toolbar" style="margin-bottom:14px;flex-wrap:wrap" id="filtros-eventos">
    <input type="hidden" name="tab" value="eventos">
    <nav class="pestanas" aria-label="Qué avisos ver" style="margin:0">
      <a class="pestana <?= $filtros['ver'] === 'para_mi' ? 'activa' : '' ?>" href="<?= url('eventos?tab=eventos&ver=para_mi') ?>">Para mí</a>
      <a class="pestana <?= $filtros['ver'] === 'todos' ? 'activa' : '' ?>" href="<?= url('eventos?tab=eventos&ver=todos') ?>">Todos</a>
    </nav>
    <input type="hidden" name="ver" value="<?= e($filtros['ver']) ?>">
    <label>Carrera<select name="programa"><option value="">Todas</option><?php foreach ($programas as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $filtros['programa'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['siglas']) ?></option><?php endforeach; ?></select></label>
    <label>Semestre<select name="semestre"><option value="">Todos</option><?php for ($i = 1; $i <= Clasificacion::SEMESTRE_MAX; $i++): ?><option value="<?= $i ?>" <?= $filtros['semestre'] === $i ? 'selected' : '' ?>><?= $i ?>.°</option><?php endfor; ?></select></label>
    <label>Tema<select name="tema"><option value="">Todos</option><?php foreach (Clasificacion::TEMAS as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['tema'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Formato<select name="categoria"><option value="">Todos</option><?php foreach ($categorias as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['categoria'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Modalidad<select name="modalidad"><option value="">Todas</option><?php foreach (Clasificacion::MODALIDADES as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['modalidad'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-mini btn-primario" type="submit">Filtrar</button>
    <?php if ($hayFiltro): ?><a class="btn btn-mini" href="<?= url('eventos?tab=eventos&ver=' . $filtros['ver']) ?>">Quitar filtros</a><?php endif; ?>
    <?php if ($filtros['ver'] === 'para_mi'): ?><small class="ayuda-form" style="flex-basis:100%">«Para mí» muestra lo dirigido a tu carrera y semestre<?= $perfil['temas'] ? ' en los temas que elegiste' : '' ?>. <a href="<?= url('perfil') ?>#preferencias-avisos">Elige tus temas en tu perfil</a>.</small><?php endif; ?>
  </form>

  <?php if (!$eventos): ?>
    <div class="card" id="sin-eventos-banner"><p class="texto-suave">No hay eventos próximos <?= $filtros['ver'] === 'para_mi' || $hayFiltro ? 'con estos filtros' : 'publicados' ?>.</p></div>
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
            <span class="badge"><?= e(Clasificacion::TEMAS[$ev['tema']] ?? 'Otros') ?></span>
            <?php if ($ev['modalidad']): ?><span class="badge"><?= e(Clasificacion::MODALIDADES[$ev['modalidad']]) ?></span><?php endif; ?>
            <?php if ((int) $ev['formacion_integral'] === 1): ?><span class="badge badge-verde">+<?= (int) $ev['puntos'] ?> puntos</span><?php endif; ?>
            <?php if ($ev['fecha_limite']): ?><span class="badge">Fecha límite: <?= e(date('d/m', strtotime((string) $ev['fecha_limite']))) ?></span><?php endif; ?>
            <span class="texto-suave"><?= e(Clasificacion::destino($ev, $siglasProg)) ?> · <?= (int) $ev['interesados'] ?> en su calendario</span>
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
.card .badge{text-transform:none}
@media (max-width:640px){.evento-fila{flex-wrap:wrap}.evento-fila>.evento-fecha{flex:0 0 auto}.evento-fila>div:not(.evento-fecha){flex:1 1 calc(100% - 90px)}.evento-fila>form{flex-basis:100%}.evento-fila>form .btn{width:100%}}
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
