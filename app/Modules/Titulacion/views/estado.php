<?php
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\Mensajes;
include __DIR__ . '/_estilos.php';
$cargos = ['jefe_titulacion' => 'Vo. Bo. de la Jefatura de Evaluación Profesional', 'subdirector' => 'Firma de la Subdirección Académica', 'director' => 'Firma de la Dirección'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">En qué punto va cada uno de tus trámites de titulación y qué sigue.</p>
</div></div>
<?php if (!$tramites): ?><div class="ux-empty">No tienes trámites de titulación. Inícialo en Titulación → Licenciatura.</div><?php endif; ?>
<?php foreach ($tramites as $x): $t = $x['t']; $hechas = count(array_filter($x['flujo']['etapas'], static fn(array $e): bool => $e['situacion'] === 'completa')); $total = count($x['flujo']['etapas']); ?>
<section class="tarjeta" style="margin-bottom:16px">
  <div class="tarjeta-cabecera">
    <div>
      <h2 style="font-size:17px"><?= e(Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? $t['modalidad']) ?> · <?= e($t['folio']) ?></h2>
      <?php if ($t['titulo']): ?><p class="texto-secundario" style="margin:0">«<?= e((string) $t['titulo']) ?>»</p><?php endif; ?>
    </div>
    <span class="estado estado-<?= e($t['estado']) ?>"><?= e(Catalogo::estadosTramite()[$t['estado']] ?? $t['estado']) ?></span>
  </div>
  <div class="tit-barra" role="progressbar" aria-valuenow="<?= $hechas ?>" aria-valuemin="0" aria-valuemax="<?= $total ?>"><i style="width:<?= $total ? (int) round(100 * $hechas / $total) : 0 ?>%"></i></div>
  <p class="texto-secundario" style="margin:6px 0 10px"><?= $hechas ?> de <?= $total ?> etapas</p>
  <?php if ($x['actual']): $p = $x['actual']['paso'] ?? null; ?>
    <p><strong>Etapa actual:</strong> <?= e($x['actual']['titulo']) ?></p>
    <?php if ($p): ?><p class="<?= $p['turno'] === 'alumno' ? 'tit-aviso' : 'tit-ok' ?>"><strong><?= $p['turno'] === 'alumno' ? 'Te toca a ti:' : 'En proceso:' ?></strong> <?= e($p['texto']) ?></p><?php endif; ?>
  <?php elseif ($t['estado'] === 'concluido'): ?>
    <p class="tit-ok">Trámite concluido.</p>
  <?php endif; ?>

  <?php if ($x['ronda']): $r = $x['ronda']; ?>
    <h3>Registro del protocolo</h3>
    <?php if ($r['estado'] === 'dictaminada' && $r['dictamen'] === 'aprobado'): ?>
      <?php if ($x['oficio'] && empty($x['oficio']['subdir_en'])): ?>
        <p class="tit-aviso"><strong>Protocolo aprobado: oficio de aprobación del tema en proceso de firmas.</strong></p>
        <ul class="tit-lista-simple">
          <?php foreach ($x['firmas'] as $f): ?><li><?= e($cargos[$f['rol_firma']] ?? $f['rol_firma']) ?>: <?= $f['estado'] === 'firmado' ? 'firmado el ' . e(fecha_corta((string) $f['firmado_en'])) : 'pendiente' ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="tit-ok">Protocolo aprobado y oficio de aprobación del tema firmado. Concluyó el registro del protocolo.</p>
      <?php endif; ?>
    <?php elseif ($r['estado'] === 'dictaminada'): ?>
      <p class="tit-obs">Concluyó la <?= e(Mensajes::ordinal((int) $r['numero'])) ?> vuelta de revisión con observaciones: atiéndelas y envía tu protocolo corregido.</p>
    <?php else: ?>
      <p>Tu protocolo está en la <?= e(Mensajes::ordinal((int) $r['numero'])) ?> vuelta de revisión del comité.</p>
    <?php endif; ?>
  <?php endif; ?>
  <p style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-mini btn-primario" href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>">Abrir mi trámite</a>
    <a class="btn btn-mini" href="<?= url('titulacion/ruta/' . (int) $t['id']) ?>">Ver la ruta</a>
    <a class="btn btn-mini" href="<?= url('notificaciones') ?>">Notificaciones<?= $x['noLeidos'] ? ' (' . (int) $x['noLeidos'] . ' sin leer)' : '' ?></a>
  </p>
</section>
<?php endforeach; ?>
