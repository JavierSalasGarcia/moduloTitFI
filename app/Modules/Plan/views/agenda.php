<div class="plan-contenedor plan-agenda-pagina">
  <p class="plan-ceja">FI 360 · Consulta a coordinaciones de licenciatura</p>
  <h1>Agenda previa de la reunión</h1>
  <p>Estos asuntos se comparten con anticipación para que cada coordinación reúna datos, ejemplos y propuestas. La reunión complementará las encuestas y las fuentes documentales; los acuerdos se incorporarán al FODA sólo después de registrarlos y verificar su alcance.</p>
  <ol class="plan-agenda-lista">
    <?php foreach ($instrumentos['agenda'] as $item): ?>
      <li id="<?= e($item['id']) ?>"><strong><?= e($item['id'] . ' · ' . $item['tema']) ?></strong><p><?= e($item['orientacion']) ?></p></li>
    <?php endforeach; ?>
  </ol>
  <p class="plan-fuente">Referencia: FI 360, Programa de Trabajo 2026–2030, ejes Académico, Científico, Tecnológico, Social, Financiero y Humano; Manual de Organización FI, funciones de las coordinaciones de docencia (pp. 49–66).</p>
  <a class="plan-btn plan-btn-claro" href="<?= e(url('plan') . '?tab=encuestas') ?>">Volver al Plan de Desarrollo</a>
</div>
