<?php
$catalogJson = json_encode($pilares, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$instrumentosJson = json_encode($instrumentos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$usuarioPlan = \App\Core\Auth::usuario();
?>
<div class="plan-contenedor" id="plan-app" data-base="<?= e(url('plan')) ?>" data-can-edit="<?= $puedeEditar ? '1' : '0' ?>" data-can-manage-contacts="<?= $puedeGestionarContactos ? '1' : '0' ?>" data-author-name="<?= e((string)($usuarioPlan['nombre'] ?? '')) ?>" data-author-email="<?= e((string)($usuarioPlan['email'] ?? '')) ?>" data-csrf="<?= e($csrf) ?>">
  <div class="plan-encabezado">
    <div>
      <p class="plan-ceja">Planeación participativa · Borrador de trabajo</p>
      <h1>Plan de Desarrollo de Ingeniería</h1>
      <p>Consulta el PRDI de Rectoría, la propuesta para la Facultad y tus revisiones. Cada cifra conserva su fuente y los factores FODA se muestran separados de las propuestas de acción.</p>
    </div>
    <div class="plan-sello" aria-label="Periodo 2026 a 2030">2026 <span>—</span> 2030</div>
  </div>

  <section class="plan-filtros" aria-label="Seleccionar proyecto">
    <label>Pilar <select id="plan-pilar"></select></label>
    <label>Proyecto <select id="plan-proyecto"></select></label>
  </section>
  <div class="plan-estatus-acceso"><a class="plan-btn plan-btn-primario" href="<?= e(url('plan/estatus')) ?>">ESTATUS</a><span>Avance de todos los proyectos, responsables e insumos.</span></div>
  <p id="plan-estado" class="plan-estado" role="status" aria-live="polite">Selecciona un proyecto para comenzar.</p>
  <?php if (!$puedeEditar): ?><p class="plan-aviso-privacidad">La consulta del plan es pública. Para redactar o guardar cambios, <a href="<?= e(url('login')) ?>">inicia sesión con una cuenta de personal</a>.</p><?php endif; ?>

  <div id="plan-detalle" hidden>
    <div class="plan-proyecto-titulo"><div><p id="plan-pilar-etiqueta" class="plan-ceja"></p><h2 id="plan-nombre"></h2></div><span id="plan-revision-badge" class="plan-etiqueta">Propuesta inicial</span></div>
    <nav class="plan-ruta" aria-label="Ruta de trabajo del proyecto">
      <div class="plan-ruta-grupo"><strong>1 · Diagnóstico</strong><div>
        <button type="button" class="activo" data-tab="matriz" data-target="plan-seccion-foda">Matriz FODA</button>
        <button type="button" data-tab="matriz" data-target="plan-seccion-diagnostico">Diagnóstico</button>
      </div></div>
      <div class="plan-ruta-grupo"><strong>2 · Matriz de consistencia</strong><div>
        <button type="button" data-tab="matriz" data-target="plan-seccion-objetivo-general">Objetivo general</button>
        <button type="button" data-tab="matriz" data-target="plan-form-politicas">Políticas</button>
        <button type="button" data-tab="matriz" data-target="plan-form-especificos">Objetivos específicos, estrategias y metas</button>
      </div></div>
      <div class="plan-ruta-grupo"><strong>3 · Sustento y seguimiento</strong><div>
        <button type="button" data-tab="evidencia" data-target="plan-tab-evidencia">Estadísticas</button>
        <?php if ($puedeEditar): ?><button type="button" data-tab="encuestas" data-target="plan-tab-encuestas">Cuestionarios y agenda</button><?php endif; ?>
        <button type="button" data-tab="historial" data-target="plan-tab-historial">Historial</button>
      </div></div>
    </nav>

    <section id="plan-tab-matriz" class="plan-tab">
      <p class="plan-ayuda">La columna de Rectoría es referencia institucional. La propuesta FI permanece intacta; tus cambios se guardan como revisiones aparte. Cada política pertenece a un objetivo particular; cada objetivo puede tener varias políticas y estrategias, y cada estrategia tiene su propia meta.</p>
      <section id="plan-seccion-foda" class="plan-foda-destacado" aria-labelledby="plan-foda-titulo">
        <div class="plan-bloque-cabeza"><div><h3 id="plan-foda-titulo">Matriz FODA del proyecto</h3><p>Las fortalezas y debilidades se apoyan en series internas. Oportunidades y amenazas requieren evidencia externa antes de afirmarse.</p></div></div>
        <p class="plan-foda-deslizar">En pantallas pequeñas, desliza la matriz para ver los cuatro cuadrantes.</p>
        <div id="plan-foda-etiquetas" class="plan-foda-columnas">
          <div><h4>Propuesta FI basada en datos</h4><div id="plan-foda-propuesta"></div><h4>Síntesis en prosa de la propuesta</h4><p id="plan-foda-prosa-propuesta" class="plan-foda-prosa"></p></div>
          <div><h4>Tu matriz editable</h4><p class="plan-fuente">Una observación por línea; conserva la distinción entre factores internos y externos.</p><div id="plan-foda-editor"></div>
            <div class="plan-foda-eleccion"><label><input type="checkbox" id="plan-usar-mi-foda"> Usar mi versión de la matriz FODA como base para editar mi diagnóstico</label>
              <p><strong>Checkpoint en Status:</strong> al guardar, registra que elegiste tu FODA como sustento del diagnóstico. La edición de la matriz y la edición del diagnóstico se comprueban como checkpoints separados.</p>
              <button type="button" class="plan-btn plan-btn-claro" id="plan-foda-insertar-diagnostico">Añadir lectura de mi FODA al diagnóstico</button><span id="plan-foda-insertar-estado" role="status"></span></div>
            <label class="plan-foda-prosa-etiqueta" for="plan-foda-prosa-editor">Tu párrafo FODA<textarea id="plan-foda-prosa-editor" rows="9"></textarea></label>
            <h4>Resultados de la reunión de coordinaciones</h4><p class="plan-fuente">Registra únicamente acuerdos y hallazgos verificados. Esta síntesis se mostrará en Status; cuando el proyecto requiera asuntos de agenda, registrarlos es parte del checkpoint «Insumos listos». Omite datos personales o sensibles.</p>
            <label>Fecha de la reunión<input type="date" id="plan-reunion-fecha"></label>
            <label>Resumen por asuntos de la agenda<textarea id="plan-reunion-resultados" rows="7" maxlength="10000" placeholder="Ejemplo: {AG-02} Hallazgos, datos mostrados, acuerdos y pendientes."></textarea></label>
            <button type="button" class="plan-btn plan-btn-primario" id="plan-foda-ir-guardar">Guardar y seguir</button><span id="plan-foda-guardar-estado" class="plan-estado" role="status" aria-live="polite"></span></div>
        </div>
        <div class="plan-foda-bandera">
          <label><input type="checkbox" id="plan-foda-bandera"> Mi matriz FODA y su lectura están listas para revisión metodológica</label>
          <p><strong>Checkpoint en Status:</strong> al guardar, registra que entregaste este insumo para revisión y suma al avance de tu proyecto. No interpreta automáticamente las respuestas; cuando exista una versión ya revisada, se mostrará aquí.</p>
          <p id="plan-foda-bandera-estado" role="status" aria-live="polite"></p>
        </div>
        <div id="plan-foda-interpretacion" class="plan-foda-interpretacion" hidden></div>
      </section>
      <div id="plan-seccion-redaccion" class="plan-columnas">
        <article class="plan-panel plan-rectoria"><div class="plan-panel-cabeza"><span class="plan-punto"></span><h3>PRDI de Rectoría</h3><span>Referencia</span></div>
          <h4>Objetivo general</h4><p id="plan-prdi-objetivo"></p>
          <details><summary>Diagnóstico institucional</summary><p id="plan-prdi-diagnostico"></p></details>
          <details><summary>Objetivos, políticas, estrategias y metas del PRDI</summary><div id="plan-prdi-detalle"></div></details>
        </article>
        <article class="plan-panel plan-propuesta"><div class="plan-panel-cabeza"><span class="plan-punto"></span><h3>Propuesta FI</h3><span>Borrador inicial</span></div><div id="plan-propuesta-contenido"></div></article>
        <article class="plan-panel plan-edicion"><div class="plan-panel-cabeza"><span class="plan-punto"></span><h3>Tu versión</h3><span id="plan-version-label">Igual a la propuesta</span></div>
          <form id="plan-form">
            <label id="plan-seccion-diagnostico" class="plan-ancla-seccion">Diagnóstico propio, sustentado en la matriz FODA<textarea name="diagnostico" rows="18" required></textarea></label>
            <label id="plan-seccion-objetivo-general" class="plan-ancla-seccion">Objetivo general<textarea name="objetivo_general" rows="4" required></textarea></label>
            <div id="plan-form-politicas"></div>
            <div id="plan-form-especificos"></div>
            <div class="plan-autor">
              <label>Nombre de quien edita<input name="autor_nombre" type="text" maxlength="120" autocomplete="name" readonly required></label>
              <label>Correo de quien edita<input name="autor_correo" type="email" maxlength="190" autocomplete="email" readonly required></label>
              <small>La revisión se atribuye a la cuenta de personal que inició sesión y se conserva con fecha.</small>
            </div>
            <button class="plan-btn plan-btn-primario" type="submit">Guardar y seguir</button>
          </form>
        </article>
      </div>
    </section>

    <section id="plan-tab-evidencia" class="plan-tab" hidden>
      <div class="plan-bloque-cabeza"><div><h3>Indicadores del proyecto</h3><p>Elige categoría, ámbito y serie. La tabla conserva el dato original; la gráfica agrupa por periodo para lectura rápida.</p></div><button type="button" class="plan-btn plan-btn-primario" id="plan-jpg">Descargar gráfica JPG</button></div>
      <div class="plan-selectores">
        <label>Categoría <select id="plan-categoria"></select></label>
        <label>Ámbito <select id="plan-ambito"></select></label>
        <label>Indicador <select id="plan-indicador"></select></label>
        <label>Campo numérico <select id="plan-metrica"></select></label>
        <label>Vista <select id="plan-vista"><option value="historico">Serie temporal del ámbito elegido</option><option value="extremos">Facultad y extremos de licenciaturas</option></select></label>
      </div>
      <div id="plan-sin-datos" class="plan-vacio" hidden>Este proyecto no tiene una serie numérica propia en los tableros consultados. Su meta preliminar es de diagnóstico o implementación.</div>
      <div class="plan-grafica-ajustes"><button type="button" class="plan-btn plan-btn-claro" id="plan-ajustar">Ajustar elementos</button><button type="button" class="plan-btn plan-btn-claro" id="plan-restablecer">Restablecer posiciones</button><span id="plan-ajuste-estado" role="status" aria-live="polite"></span></div>
      <div class="plan-grafica"><canvas id="plan-canvas" width="1200" height="700" aria-label="Gráfica del indicador seleccionado" role="img"></canvas></div>
      <p id="plan-analisis-comparativo" class="plan-foda-prosa"></p>
      <div class="plan-pie-figura">
        <label for="plan-grafica-nota">Texto sugerido para el pie de figura (sin número)</label>
        <textarea id="plan-grafica-nota" rows="4" readonly></textarea>
        <div><button type="button" class="plan-btn plan-btn-claro" id="plan-copiar-pie">Copiar texto</button><span id="plan-copiar-estado" role="status" aria-live="polite"></span></div>
      </div>
      <div class="plan-tabla-scroll"><table id="plan-tabla"><caption>Tabla 1. Datos publicados para el indicador seleccionado</caption><thead></thead><tbody></tbody></table></div>
      <p id="plan-conciliacion" class="plan-fuente"></p>
      <div id="plan-cruce-seccion" hidden><h3>Cruces de indicadores académicos</h3><p id="plan-cruce-analisis" class="plan-foda-prosa"></p>
        <div class="plan-selectores"><label>Comparación <select id="plan-cruce-tipo"><option value="dispersion">Reprobación, abandono y eficiencia por carrera</option><option value="tendencia">Reprobación, abandono y eficiencia: tendencia de Facultad</option></select></label>
          <button type="button" class="plan-btn plan-btn-primario" id="plan-cruce-jpg">Descargar cruce JPG</button></div>
        <div class="plan-grafica-ajustes"><button type="button" class="plan-btn plan-btn-claro" id="plan-cruce-ajustar">Ajustar elementos</button><button type="button" class="plan-btn plan-btn-claro" id="plan-cruce-restablecer">Restablecer posiciones</button><span id="plan-cruce-ajuste-estado" role="status" aria-live="polite"></span></div>
        <div class="plan-grafica"><canvas id="plan-cruce-canvas" width="1200" height="680" role="img" aria-label="Cruce de indicadores académicos"></canvas></div>
        <p id="plan-cruce-fuente" class="plan-fuente"></p></div>
    </section>

    <?php if ($puedeEditar): ?><section id="plan-tab-encuestas" class="plan-tab" hidden>
      <div class="plan-bloque-cabeza"><div><h3>Cuestionarios de consulta</h3><p>Enlaces públicos individuales. El correo es opcional en C01–C04, C16 y C17 para recibir invitación a una reunión; las respuestas abiertas y los correos no se publican.</p></div></div>
      <p id="plan-encuestas-corte" class="plan-ayuda" role="status"></p>
      <div id="plan-encuestas-lista" class="plan-encuestas-lista"></div>
      <h3>Participación en los cuestionarios</h3>
      <p class="plan-ayuda">La figura compara respuestas recibidas y metas de participación. Describe cobertura de consulta; no representa por sí misma a toda la comunidad.</p>
      <div class="plan-grafica-ajustes"><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-avance-ajustar">Ajustar elementos</button><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-avance-restablecer">Restablecer posiciones</button><span id="plan-encuesta-avance-ajuste-estado" role="status" aria-live="polite"></span></div>
      <div class="plan-grafica"><canvas id="plan-encuesta-avance-canvas" width="1200" height="900" role="img" aria-label="Participación en los cuestionarios del plan"></canvas></div>
      <div class="plan-pie-figura"><label for="plan-encuesta-avance-pie">Texto sugerido para el pie de figura</label><textarea id="plan-encuesta-avance-pie" rows="3" readonly></textarea><div><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-avance-copiar">Copiar texto</button><button type="button" class="plan-btn plan-btn-primario" id="plan-encuesta-avance-jpg">Descargar gráfica JPG</button><span id="plan-encuesta-avance-copiar-estado" role="status"></span></div></div>
      <h3>Análisis de respuestas</h3>
      <p class="plan-ayuda">Las figuras cruzan dos preguntas para describir patrones de respuesta. Sólo se muestran agregados con al menos cinco respuestas; no prueban causalidad ni sustituyen la interpretación metodológica.</p>
      <div class="plan-selectores">
        <label>Cuestionario <select id="plan-encuesta-seleccion"></select></label>
        <label>Segmento <select id="plan-encuesta-segmento"></select></label>
        <label>Cruce de preguntas <select id="plan-encuesta-cruce"></select></label>
      </div>
      <p id="plan-encuesta-grafica-estado" class="plan-estado" role="status"></p>
      <div class="plan-grafica-ajustes"><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-ajustar">Ajustar elementos</button><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-restablecer">Restablecer posiciones</button><span id="plan-encuesta-ajuste-estado" role="status" aria-live="polite"></span></div>
      <div class="plan-grafica"><canvas id="plan-encuesta-canvas" width="1200" height="820" role="img" aria-label="Cruce de respuestas de cuestionarios"></canvas></div>
      <div class="plan-pie-figura"><label for="plan-encuesta-pie">Texto sugerido para el pie de figura</label><textarea id="plan-encuesta-pie" rows="3" readonly></textarea><div><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-copiar-pie">Copiar texto</button><button type="button" class="plan-btn plan-btn-primario" id="plan-encuesta-jpg">Descargar gráfica JPG</button><span id="plan-encuesta-copiar-estado" role="status"></span></div></div>
      <div class="plan-pie-figura"><label for="plan-encuesta-lectura">Lectura descriptiva sugerida para el diagnóstico</label><textarea id="plan-encuesta-lectura" rows="5" readonly></textarea><div><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-copiar-lectura">Copiar lectura</button><button type="button" class="plan-btn plan-btn-claro" id="plan-encuesta-insertar-lectura">Añadir al diagnóstico</button><span id="plan-encuesta-lectura-estado" role="status"></span></div></div>
      <div class="plan-bloque-cabeza"><div><h3>Agenda anticipada para coordinaciones de licenciatura</h3><p>Comparte estos asuntos antes de la reunión para que cada coordinación prepare datos, ejemplos y propuestas.</p></div><div class="plan-filtros-acciones"><a class="plan-btn plan-btn-claro" href="<?= e(url('plan/agenda')) ?>" target="_blank" rel="noopener">Abrir agenda pública</a><button type="button" class="plan-btn plan-btn-claro" id="plan-agenda-copiar">Copiar invitación y agenda</button></div></div>
      <p id="plan-agenda-copiar-estado" class="plan-estado" role="status"></p>
      <ol class="plan-agenda-lista"><?php foreach ($instrumentos['agenda'] as $item): ?><li><strong><?= e($item['id'] . ' · ' . $item['tema']) ?></strong><p><?= e($item['orientacion']) ?></p></li><?php endforeach; ?></ol>
    </section><?php endif; ?>

    <section id="plan-tab-historial" class="plan-tab" hidden>
      <h3>Revisiones de este borrador</h3><p>Las revisiones se guardan en el servidor y se asocian a tu cuenta. Puedes consultar y continuar el mismo historial al iniciar sesión desde otro navegador o dispositivo. Cada versión conserva la fecha y la identidad de quien la guardó; el PRDI y la propuesta FI no se sobrescriben. Si existían revisiones antiguas vinculadas a este navegador, el sistema incorpora automáticamente a tu cuenta únicamente las que te pertenecen.</p><ol id="plan-revisiones"></ol>
    </section>
  </div>
</div>
<script type="application/json" id="plan-catalogo"><?= $catalogJson ?></script>
<script type="application/json" id="plan-instrumentos"><?= $instrumentosJson ?></script>
<?php if ($puedeEditar): ?>
<script src="<?= e(url('assets/js/plan-encuestas.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/plan-encuestas.js'))) ?>" defer></script>
<?php endif; ?>
