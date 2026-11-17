<?php
use App\Modules\Titulacion\Services\Manuales;
include __DIR__ . '/_estilos.php';
$sin = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'sin_trabajo');
$con = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'con_trabajo');
$celda = static function (string $actor, string $opcion): string {
    if (!Manuales::valido($actor, $opcion)) {
        return '<td class="tit-man-na" aria-label="No participa">—</td>';
    }
    if (!Manuales::existe($actor, $opcion)) {
        return '<td class="tit-man-na">Pronto</td>';
    }
    return '<td><a href="' . e(url(Manuales::url($actor, $opcion))) . '" download>PDF</a></td>';
};
$tabla = static function (array $grupo, array $actores, string $titulo) use ($celda): void { ?>
  <section class="tarjeta tit-man-tabla">
    <h2><?= e($titulo) ?></h2>
    <div class="tabla-contenedor">
      <table class="tabla" data-no-tools>
        <thead><tr><th scope="col">Opción</th><?php foreach ($actores as $a): ?><th scope="col"><?= e(Manuales::ACTORES[$a][0]) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($grupo as $clave => $m): ?>
          <tr><td class="tit-man-op"><a href="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>"><?= e($m['nombre']) ?></a></td>
            <?php foreach ($actores as $a) { echo $celda($a, (string) $clave); } ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php };
?>
<style>
.tit-man-tabla table{font-size:13px}
.tit-man-tabla th,.tit-man-tabla td{padding-left:6px;padding-right:6px}
.tit-man-tabla th{white-space:normal;vertical-align:bottom}
.tit-man-tabla td{text-align:center;white-space:nowrap}
.tit-man-tabla td.tit-man-op{text-align:left;white-space:normal;font-weight:600}
@media (min-width:760px){.tit-man-tabla table{table-layout:fixed;width:100%}.tit-man-tabla thead th:first-child{width:19%}.tit-man-tabla th{font-size:12px;hyphens:manual;padding-left:4px!important;padding-right:4px!important;text-align:center}}
.tit-man-tabla td a{font-weight:700}
.tit-man-na{color:var(--gris-400,#999)}
.tit-man-todos{display:flex;flex-wrap:wrap;gap:6px 18px;margin:6px 0 0;padding:0;list-style:none}
</style>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/licenciatura') ?>">← Opciones de titulación</a></p>
    <p style="margin:6px 0 0;max-width:720px">Cada manual explica, con pantallas reales, lo que hace una persona en una opción de titulación. No necesitas iniciar ningún trámite para descargarlos: compara las opciones con calma antes de elegir.</p>
  </div>
</div>

<?php $tabla($sin, Manuales::SIN_TRABAJO, 'Sin trabajo escrito'); ?>
<?php $tabla($con, array_keys(Manuales::ACTORES), 'Con trabajo escrito'); ?>

<section class="tarjeta">
  <h2>Un manual por persona, con todas las opciones</h2>
  <p class="texto-secundario" style="margin:0">Reúne lo que se hace igual en todas las opciones y, después, lo propio de cada una.</p>
  <ul class="tit-man-todos">
    <?php foreach (Manuales::ACTORES as $a => [$corto, $largo]): if (!Manuales::existe($a, 'consolidado')) continue; ?>
      <li><a href="<?= url(Manuales::url($a, 'consolidado')) ?>" download><?= e($largo) ?> (PDF)</a></li>
    <?php endforeach; ?>
  </ul>
</section>
