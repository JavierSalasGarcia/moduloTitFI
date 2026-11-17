<?php
use App\Modules\Posgrado\Services\Manuales;

include __DIR__ . '/_estilos.php';
?>
<style>
.pos-man-tabla table{font-size:13.5px}
.pos-man-tabla th{white-space:normal;vertical-align:bottom}
.pos-man-tabla td{text-align:center;white-space:nowrap}
.pos-man-tabla td.pos-man-op{text-align:left;white-space:normal;font-weight:600}
@media (min-width:760px){.pos-man-tabla table{table-layout:fixed;width:100%}.pos-man-tabla thead th:first-child{width:19%}.pos-man-tabla th{font-size:12px;hyphens:manual;padding-left:4px!important;padding-right:4px!important;text-align:center}}
.pos-man-tabla td a{font-weight:700}
.pos-man-na{color:var(--gris-400,#999)}
</style>
<div class="pos-pila">
  <div class="pos-cabecera">
    <div>
      <p class="pos-volver"><a href="<?= url('posgrado') ?>">← Posgrado: obtener mi grado</a></p>
      <p style="max-width:720px">Cada manual explica, con pantallas reales, lo que hace una persona en una modalidad. No necesitas iniciar ningún trámite para descargarlos.</p>
    </div>
  </div>
  <section class="tarjeta pos-man-tabla">
    <div class="tabla-contenedor">
      <table class="tabla" data-no-tools>
        <thead><tr><th scope="col">Modalidad</th><?php foreach (Manuales::ACTORES as $a => [$corto]): ?><th scope="col"><?= e($corto) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($modalidades as $clave => $nombre): ?>
          <tr><td class="pos-man-op"><a href="<?= url('posgrado/modalidad/' . $clave) ?>"><?= e($nombre) ?></a></td>
            <?php foreach (array_keys(Manuales::ACTORES) as $a): ?>
              <?php if (Manuales::existe($a, (string) $clave)): ?><td><a href="<?= url(Manuales::url($a, (string) $clave)) ?>" download>PDF</a></td><?php else: ?><td class="pos-man-na">Pronto</td><?php endif; ?>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="tarjeta">
    <h2 style="margin-top:0">Un manual por persona, con todas las modalidades</h2>
    <ul class="pos-lista">
      <?php foreach (Manuales::ACTORES as $a => [, $largo]): if (!Manuales::existe($a, 'consolidado')) continue; ?>
        <li style="padding:4px 0"><a href="<?= url(Manuales::url($a, 'consolidado')) ?>" download><?= e($largo) ?> (PDF)</a></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
