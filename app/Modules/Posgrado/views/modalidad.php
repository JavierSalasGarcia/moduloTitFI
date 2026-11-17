<?php
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Services\Manuales;

include __DIR__ . '/_estilos.php';
// Requisitos resumidos: los nombres por grupo, sin la ayuda detallada (esa aparece dentro del trámite).
$porGrupo = [];
foreach ($requisitos as $k => $r) {
    $porGrupo[$r['grupo']][] = $r['nombre'] . ($r['aplica'] === 'doctorado' ? ' (solo doctorado)' : ($r['aplica'] === 'art53' ? ' (solo si aplica el art. 53 del REA)' : ''));
}
$otros = array_values(array_filter(array_keys(Manuales::ACTORES), static fn(string $a): bool => $a !== 'egresado' && Manuales::existe($a, $clave)));
?>
<div class="pos-pila">
  <div class="pos-cabecera">
    <div>
      <p class="pos-volver"><a href="<?= url('posgrado') ?>">← Posgrado: obtener mi grado</a></p>
      <p style="max-width:720px"><?= e($descripcion) ?></p>
    </div>
  </div>

  <section class="tarjeta pos-manual" id="manual" aria-labelledby="pos-man-t">
    <h2 id="pos-man-t" style="margin-top:0">Manual de esta modalidad</h2>
    <p>Con pantallas reales, lo que harás en cada uno de los cuatro momentos. Descárgalo sin iniciar tu trámite.</p>
    <?php if (Manuales::existe('egresado', $clave)): ?>
      <a class="btn btn-primario" href="<?= url(Manuales::url('egresado', $clave)) ?>" download>Descargar el manual del egresado (PDF)</a>
    <?php else: ?>
      <p class="pos-nota">El manual de esta modalidad todavía no está disponible.</p>
    <?php endif; ?>
    <?php if ($otros): ?>
    <details class="pos-grupo" style="margin-top:12px">
      <summary>Manuales para otros participantes</summary>
      <ul class="pos-lista">
        <?php foreach ($otros as $a): ?>
          <li style="padding:4px 0"><a href="<?= url(Manuales::url($a, $clave)) ?>" download><?= e(Manuales::ACTORES[$a][1]) ?> (PDF)</a></li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
    <p class="texto-secundario" style="margin:10px 0 0;font-size:13px"><a href="<?= url('posgrado/manuales') ?>">Compara las modalidades: descarga sus manuales</a></p>
  </section>

  <section class="tarjeta">
    <h2 style="margin-top:0">Así será tu trámite</h2>
    <ol class="pos-lista">
      <?php foreach (Catalogo::momentos() as $n => $m): ?>
        <li style="padding:6px 0"><strong><?= (int) $n ?>. <?= e($m['titulo']) ?></strong><br><span class="texto-secundario"><?= e($m['texto']) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <details class="pos-momento">
    <summary>Requisitos, en resumen</summary>
    <div class="pos-momento-cuerpo">
      <?php foreach ($grupos as $g => $titulo): if (empty($porGrupo[$g])) continue; ?>
        <h3 class="pos-sub"><?= e($titulo) ?></h3>
        <ul style="margin:0 0 6px;padding-left:20px">
          <?php foreach ($porGrupo[$g] as $n): ?><li><?= e($n) ?></li><?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <p class="texto-secundario" style="font-size:13px">La fecha del examen debe quedar al menos a <?= (int) Catalogo::DIAS_MINIMOS ?> días hábiles (se sugieren <?= (int) Catalogo::DIAS_SUGERIDOS ?>).</p>
    </div>
  </details>

  <?php if ($puedeIniciar): ?>
    <p class="texto-secundario">La modalidad la eliges al capturar tus datos, dentro del trámite. <a href="<?= url('posgrado#comenzar') ?>">Ir a comenzar mi trámite</a></p>
  <?php endif; ?>
</div>
