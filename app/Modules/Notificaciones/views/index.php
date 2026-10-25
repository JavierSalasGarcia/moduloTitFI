<?php
use App\Core\Auth;
?>
<style>
.ntf{display:grid;grid-template-columns:minmax(0,2fr) minmax(250px,1fr);gap:18px;align-items:start}
.ntf-item{display:block;padding:9px 10px;border-bottom:1px solid var(--gris-100);text-decoration:none;color:var(--texto)}
.ntf-item:hover,.ntf-item.sel{background:var(--verde-050)}
.ntf-item.nueva strong{color:var(--verde-900)}
.ntf-item.nueva::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--oro);margin-right:6px}
.ntf-item time{display:block;font-size:12px;color:var(--gris-600)}
@media (max-width:900px){.ntf{grid-template-columns:1fr}}
</style>
<div class="ntf">
  <section class="tarjeta">
    <?php if ($abierta): ?>
      <h2 style="margin-top:0"><?= e($abierta['titulo']) ?></h2>
      <p class="texto-secundario"><?= e(fecha_corta((string) $abierta['creado_en'])) ?></p>
      <div style="white-space:pre-wrap;line-height:1.55"><?= e((string) $abierta['cuerpo']) ?></div>
      <?php if ($abierta['enlace']): ?><p style="margin-top:14px"><a class="btn btn-primario" href="<?= e((string) $abierta['enlace']) ?>">Ir</a></p><?php endif; ?>
    <?php elseif (!$lista): ?>
      <div class="ux-empty">No tienes notificaciones.</div>
    <?php else: ?>
      <p class="texto-secundario">Todos los avisos de la plataforma llegan aquí; el correo solo te alerta. Elige una notificación para leerla.</p>
    <?php endif; ?>
  </section>
  <aside class="tarjeta" style="padding:0">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 12px 6px">
      <h2 style="font-size:16px;margin:0">Bandeja</h2>
      <?php if (array_filter($lista, static fn(array $n): bool => $n['leida_en'] === null)): ?>
        <form method="post" action="<?= url('notificaciones/leidas') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Marcar todo como leído</button></form>
      <?php endif; ?>
    </div>
    <?php foreach ($lista as $n): ?>
      <a class="ntf-item <?= $n['leida_en'] === null ? 'nueva' : '' ?> <?= $abierta && (int) $abierta['id'] === (int) $n['id'] ? 'sel' : '' ?>" href="<?= url('notificaciones/' . (int) $n['id']) ?>">
        <strong><?= e($n['titulo']) ?></strong><time><?= e(fecha_corta((string) $n['creado_en'])) ?></time></a>
    <?php endforeach; ?>
  </aside>
</div>
