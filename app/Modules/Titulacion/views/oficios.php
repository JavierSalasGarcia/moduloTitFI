<?php
use App\Modules\Titulacion\Services\NumeroOficio;
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Cada oficio que genera la plataforma toma el consecutivo anual de quien lo firma. Siguientes números:
    <?php foreach ($siguientes as $s => $txt): ?><strong><?= e($txt) ?></strong> (<?= e(NumeroOficio::SERIES[$s]) ?>)<?= $s === array_key_last($siguientes) ? '.' : '; ' ?><?php endforeach; ?></p>
</div></div>
<section class="tarjeta">
  <form method="get" action="<?= url('titulacion/oficios') ?>" class="tit-campos" style="margin-bottom:12px">
    <label>Año <input type="number" name="anio" min="2020" max="2100" value="<?= (int) $anio ?>"></label>
    <label>Serie <select name="serie"><option value="">Todas</option>
      <?php foreach (NumeroOficio::SERIES as $k => $n): ?><option value="<?= e($k) ?>" <?= $serie === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    <label>Buscar <input type="search" name="q" value="<?= e($q) ?>" placeholder="Número, asunto, destinatario o ID"></label>
    <div><button class="btn btn-primario" type="submit">Filtrar</button></div>
  </form>
  <?php if (!$oficios): ?><div class="ux-empty">Sin oficios.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Número</th><th>Fecha</th><th>Dirigido a</th><th>Asunto</th><th>Trámite</th><th>Generó</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($oficios as $o): ?>
      <tr>
        <td><strong><?= e($o['texto']) ?></strong></td>
        <td><?= e(fecha_corta((string) $o['creado_en'])) ?></td>
        <td><?= e($o['dirigido_a']) ?></td>
        <td><?= e($o['asunto']) ?></td>
        <td><a href="<?= url('titulacion/ruta/' . (int) $o['tramite_id']) ?>"><?= e($o['folio']) ?></a></td>
        <td><?= e((string) ($o['generado_nombre'] ?? 'Plataforma')) ?></td>
        <td><?php if ($o['documento_id']): ?><a class="btn btn-mini" href="<?= url('titulacion/ruta/' . (int) $o['tramite_id'] . '/documento/' . (int) $o['documento_id']) ?>" target="_blank" rel="noopener">PDF</a>
          <?php elseif ($o['verificacion'] && $o['guardado']): ?><a class="btn btn-mini" href="<?= url('verificar/' . $o['verificacion']) ?>" target="_blank" rel="noopener">Verificar</a>
          <?php else: ?><span class="tit-chip rev">No emitido</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
