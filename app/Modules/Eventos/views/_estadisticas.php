<?php
// Recuadro de estadísticas de un aviso o evento (solo para quien puede consultarlas). Requiere $estad.
use App\Modules\Eventos\Estadisticas;
if (empty($estad)) return;
$siglasTot = array_sum($estad['por_programa']);
?>
<details class="card estad-caja" open>
  <summary>📊 Estadísticas de este <?= $estadTipo === 'evento' ? 'evento' : 'aviso' ?> <small class="texto-suave">· solo las ve personal autorizado</small></summary>
  <div class="estad-cifras">
    <div><b><?= (int) $estad['personas'] ?></b><span>personas con sesión</span></div>
    <div><b><?= (int) $estad['visitantes'] ?></b><span>visitantes sin cuenta</span></div>
    <div><b><?= (int) $estad['aperturas'] ?></b><span>aperturas en total</span></div>
    <?php if ($estadTipo === 'comunicado'): ?><div><b><?= (int) $estad['total_descargas'] ?></b><span>archivos abiertos o descargados</span></div><?php endif; ?>
  </div>
  <?php if ($estad['por_perfil']): ?>
    <p style="margin:10px 0 4px"><strong>Por perfil</strong> (una vez por persona):
      <?= e(implode(' · ', array_map(static fn($k, $v) => (Estadisticas::PERFILES[$k] ?? $k) . ': ' . $v, array_keys($estad['por_perfil']), $estad['por_perfil']))) ?></p>
  <?php endif; ?>
  <?php if ($estad['por_programa']): ?>
    <p style="margin:4px 0"><strong>Por carrera o programa:</strong> <?= e(implode(' · ', array_map(static fn($k, $v) => $k . ': ' . $v, array_keys($estad['por_programa']), $estad['por_programa']))) ?></p>
  <?php endif; ?>
  <?php if (!empty($estad['descargas'])): ?>
    <table class="estad-tabla"><thead><tr><th>Archivo</th><th>Aperturas o descargas</th><th>Personas con sesión</th></tr></thead><tbody>
      <?php foreach ($estad['descargas'] as $d): ?><tr><td><?= e(($d['grupo'] ? $d['grupo'] . ' · ' : '') . $d['etiqueta']) ?></td><td><?= (int) $d['total'] ?></td><td><?= (int) $d['personas'] ?></td></tr><?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  <p class="texto-suave" style="font-size:12px;margin:8px 0 0"><?= $estad['primera'] ? 'Primera apertura: ' . e(fecha_corta($estad['primera'])) . ' · última: ' . e(fecha_corta($estad['ultima'])) . ' · ' : 'Aún sin aperturas · ' ?><a href="<?= e(url('eventos/estadisticas')) ?>">Ver todos los avisos y eventos</a></p>
</details>
<style>
.estad-caja{margin-top:16px;padding:16px 18px}.estad-caja summary{cursor:pointer;font-weight:700}
.estad-cifras{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:12px}
.estad-cifras div{background:#f6fbf8;border:1px solid #d7eadf;border-radius:10px;padding:10px 12px}
.estad-cifras b{display:block;font-size:24px;color:var(--verde-900,#1f4d36)}.estad-cifras span{font-size:12.5px;color:#52606d}
.estad-tabla{width:100%;border-collapse:collapse;margin-top:10px;font-size:13.5px}.estad-tabla th,.estad-tabla td{border-bottom:1px solid #e5e7eb;padding:6px 8px;text-align:left}
</style>
