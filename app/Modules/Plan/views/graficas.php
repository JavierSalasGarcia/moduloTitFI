<div class="plan-contenedor plan-graficas">
  <p class="plan-ceja">Plan de Desarrollo 2026–2030 · Evidencia estadística</p>
  <h1>Gráficas de indicadores</h1>
  <p class="plan-graficas-intro">Todas las series de los tableros SPDI de la Facultad de Ingeniería. Elige el indicador (categoría de datos), el ámbito y el tipo de gráfica; las gráficas se dibujan con los datos vigentes del sitio.</p>
  <div class="plan-filtros plan-graficas-filtros">
    <label>Indicador
      <select id="gr-categoria">
        <option value="">Todos los indicadores</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= e($c['id']) ?>"><?= e($c['id'] . ' · ' . $c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Ámbito
      <select id="gr-ambito"><option value="">Facultad de Ingeniería</option></select>
    </label>
    <label>Tipo de gráfica
      <select id="gr-tipo"><option value="linea">Líneas</option><option value="barra">Barras</option></select>
    </label>
    <label>Buscar en las gráficas
      <input type="search" id="gr-buscar" placeholder="p. ej. mujeres, TCMA, 2025">
    </label>
  </div>
  <p class="plan-estado" id="gr-estado" role="status" aria-live="polite"></p>
  <div id="gr-resultado"></div>
  <p class="plan-fuente">Fuente: Sistema de Planeación y Desarrollo Institucional [SPDI], Tableros de variables e indicadores, Universidad Autónoma del Estado de México. Cada tarjeta incluye la tabla de datos que la origina.</p>
  <a class="plan-btn plan-btn-claro" href="<?= e(url('plan')) ?>">Volver al Plan de Desarrollo</a>
</div>
<link rel="stylesheet" href="<?= e(url('assets/css/plan-graficas.css?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/css/plan-graficas.css'))) ?>">
<script src="<?= e(url('assets/js/plan-graficas.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/plan-graficas.js'))) ?>" data-datos="<?= e(url('plan/graficas/datos')) ?>" defer></script>
