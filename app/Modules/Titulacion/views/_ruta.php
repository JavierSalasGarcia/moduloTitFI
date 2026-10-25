<?php
/** Ruta de pasos. Variables: $pasos (Services\Ruta::pasos), $compacta (bool, opcional). */
$compacta ??= false;
$hechos = count(array_filter($pasos, static fn(array $p): bool => $p['estado'] === 'hecho'));
?>
<style>
.ruta{list-style:none;margin:0;padding:0;position:relative}
.ruta li{position:relative;padding:0 0 12px 34px}
.ruta li::before{content:"";position:absolute;left:11px;top:22px;bottom:-2px;width:2px;background:var(--gris-100)}
.ruta li:last-child::before{display:none}
.ruta .ic{position:absolute;left:0;top:0;width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700}
.ruta .hecho .ic{background:var(--ok);color:#fff}
.ruta .hecho::before{background:var(--ok)}
.ruta .actual .ic{background:var(--oro);color:#fff;box-shadow:0 0 0 4px var(--oro-050)}
.ruta .pendiente{opacity:.45}
.ruta .pendiente .ic{background:var(--gris-100);color:var(--gris-600)}
.ruta .t{font-size:14px;line-height:24px}
.ruta .actual .t{font-weight:700}
.ruta .e{display:block;font-size:11.5px;color:var(--gris-600);text-transform:uppercase;letter-spacing:.02em}
.ruta .n{font-size:12px;color:var(--gris-600)}
.ruta .a{display:flex;flex-wrap:wrap;gap:4px 10px;margin-top:2px;font-size:12.5px}
</style>
<p class="texto-secundario" style="margin:0 0 10px"><?= $hechos ?> de <?= count($pasos) ?> pasos completos</p>
<ol class="ruta" aria-label="Ruta del trámite">
  <?php $etapaAnt = null; foreach ($pasos as $i => $p): ?>
    <li class="<?= e($p['estado']) ?>">
      <span class="ic" aria-hidden="true"><?= $p['estado'] === 'hecho' ? '✓' : ($p['estado'] === 'actual' ? '●' : $i + 1) ?></span>
      <?php if (!$compacta && $p['etapa'] !== $etapaAnt): ?><span class="e"><?= e($p['etapa']) ?></span><?php endif; $etapaAnt = $p['etapa']; ?>
      <span class="t"><?= e($p['titulo']) ?></span>
      <span class="sr-only"><?= $p['estado'] === 'hecho' ? '(hecho)' : ($p['estado'] === 'actual' ? '(paso actual)' : '(pendiente)') ?></span>
      <?php if ($p['nota']): ?><span class="n"> · <?= e($p['nota']) ?></span><?php endif; ?>
      <?php if ($p['archivos']): ?><span class="a"><?php foreach ($p['archivos'] as $a): ?><a href="<?= url($a['url']) ?>" target="_blank" rel="noopener">⬇ <?= e($a['nombre']) ?> (PDF)</a><?php endforeach; ?></span><?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
