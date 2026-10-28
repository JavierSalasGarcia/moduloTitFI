<?php
/** @var array $resumen */
$dia = static function (string $f): string {
    $t = strtotime($f);
    return ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][(int) date('w', $t)] . ' ' . date('d/m', $t) . ' · ' . date('H:i', $t);
};
?>
<section class="inicio-resumen" aria-label="Resumen de lo que te toca">
  <h2 class="inicio-resumen-titulo">Tu resumen</h2>
  <div class="inicio-resumen-rejilla">

    <article class="inicio-bloque">
      <header><h3>Por hacer <?php if ($resumen['total_pendientes']): ?><span class="inicio-cuenta"><?= (int) $resumen['total_pendientes'] ?></span><?php endif; ?></h3><a href="<?= url('notificaciones') ?>">Ver todo</a></header>
      <?php if (!$resumen['pendientes']): ?><p class="inicio-vacio">Nada pendiente.</p><?php else: ?>
      <ul>
        <?php foreach ($resumen['pendientes'] as $p): ?>
          <li><a href="<?= url((string) $p['enlace']) ?>"><small><?= e((string) $p['modulo']) ?></small><?= e((string) $p['texto']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($resumen['total_pendientes'] > count($resumen['pendientes'])): ?><p class="inicio-mas"><a href="<?= url('notificaciones') ?>">y <?= (int) ($resumen['total_pendientes'] - count($resumen['pendientes'])) ?> más</a></p><?php endif; ?>
      <?php endif; ?>
    </article>

    <article class="inicio-bloque">
      <header><h3>Notificaciones sin leer <?php if ($resumen['total_notificaciones']): ?><span class="inicio-cuenta"><?= (int) $resumen['total_notificaciones'] ?></span><?php endif; ?></h3><a href="<?= url('notificaciones') ?>">Bandeja</a></header>
      <?php if (!$resumen['notificaciones']): ?><p class="inicio-vacio">Estás al día.</p><?php else: ?>
      <ul>
        <?php foreach ($resumen['notificaciones'] as $n): ?>
          <li><a href="<?= url('notificaciones/' . (int) $n['id']) ?>"><small><?= e(fecha_corta((string) $n['creado_en'])) ?></small><?= e((string) $n['titulo']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($resumen['total_notificaciones'] > count($resumen['notificaciones'])): ?><p class="inicio-mas"><a href="<?= url('notificaciones') ?>">y <?= (int) ($resumen['total_notificaciones'] - count($resumen['notificaciones'])) ?> más</a></p><?php endif; ?>
      <?php endif; ?>
    </article>

    <article class="inicio-bloque">
      <header><h3>Próximos eventos</h3><a href="<?= url('eventos') ?>">Ver agenda</a></header>
      <?php if (!$resumen['eventos']): ?><p class="inicio-vacio">Sin eventos en los próximos 30 días.</p><?php else: ?>
      <ul>
        <?php foreach ($resumen['eventos'] as $e): ?>
          <li><a href="<?= url('eventos/' . (int) $e['id']) ?>"><small><?= e($dia((string) $e['inicio'])) ?><?= $e['lugar'] ? ' · ' . e((string) $e['lugar']) : '' ?></small><?= e((string) $e['titulo']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </article>

    <article class="inicio-bloque">
      <header><h3>Avisos recientes</h3><a href="<?= url('eventos') ?>">Ver avisos</a></header>
      <?php if (!$resumen['avisos']): ?><p class="inicio-vacio">Sin avisos nuevos.</p><?php else: ?>
      <ul>
        <?php foreach ($resumen['avisos'] as $c): ?>
          <li><a href="<?= url('eventos') ?>"><small><?= e(fecha_corta((string) $c['creado_en'])) ?> · <?= e((string) $c['depto']) ?></small><?= e((string) $c['titulo']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </article>

  </div>
</section>
