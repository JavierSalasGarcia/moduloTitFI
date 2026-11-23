<?php
/** @var array $o @var array $historial @var bool $puedeFirmar */
use App\Core\Auth;
use App\Modules\Oficios\Services\OficiosSub;
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$acciones = ['creado' => 'Creado', 'editado' => 'Editado', 'enviado' => 'Enviado a firma', 'devuelto' => 'Devuelto por el titular', 'firmado' => 'Firmado', 'cancelado' => 'Cancelado'];
$edita = in_array($o['estado'], ['borrador', 'en_firma'], true);
?>
<p style="margin:0 0 10px"><a href="<?= e(url('oficios/subdireccion')) ?>">← Oficios de la Subdirección</a></p>
<section class="card">
  <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
    <h2 style="margin:0;font-size:20px"><?= e($o['numero_texto'] ?: 'Borrador sin número') ?></h2>
    <span class="chip"><?= e(OficiosSub::ESTADOS[$o['estado']] ?? $o['estado']) ?></span>
  </div>
  <?php if ($o['estado'] === 'borrador' && $o['observacion']): ?><p style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:8px;padding:10px 12px;margin:12px 0 0"><strong>El titular lo devolvió:</strong> <?= e($o['observacion']) ?></p><?php endif; ?>
  <dl class="datos-lista" style="margin:14px 0 0;display:grid;grid-template-columns:max-content 1fr;gap:6px 16px">
    <dt><strong>Asunto</strong></dt><dd style="margin:0"><?= e($o['asunto']) ?></dd>
    <dt><strong>Destinatarios</strong></dt><dd style="margin:0"><?php foreach ($o['dest'] as $d): ?><?= e($d['nombre']) ?><?= $d['cargo'] ? ' — ' . e($d['cargo']) : '' ?><br><?php endforeach; ?></dd>
    <dt><strong>Fecha</strong></dt><dd style="margin:0"><?= $o['fecha'] ? e(date('d/m/Y', strtotime((string) $o['fecha']))) : '—' ?></dd>
    <dt><strong>Redactó</strong></dt><dd style="margin:0"><?= e($o['autor']) ?></dd>
    <?php if ($o['estado'] === 'firmado'): ?>
      <dt><strong>Firma</strong></dt><dd style="margin:0">e.firma del SAT · certificado No. <?= e((string) $o['cert_serie']) ?><br><small class="texto-suave"><?= e((string) $o['cert_nombre']) ?> · vigencia <?= e((string) $o['cert_vigencia']) ?></small></dd>
      <dt><strong>Firmado</strong></dt><dd style="margin:0"><?= e(date('d/m/Y H:i', strtotime((string) $o['firmado_en']))) ?></dd>
      <dt><strong>Cadena del certificado</strong></dt><dd style="margin:0"><?= e((string) $o['cadena_estado']) ?></dd>
      <dt><strong>Huella (SHA-256)</strong></dt><dd style="margin:0;word-break:break-all"><small class="monoespacio"><?= e((string) $o['sha256']) ?></small></dd>
      <dt><strong>Verificación</strong></dt><dd style="margin:0"><a href="<?= e(OficiosSub::urlVerificacion((string) $o['codigo'])) ?>" target="_blank" rel="noopener">Página pública de verificación</a></dd>
    <?php endif; ?>
  </dl>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
    <a class="btn btn-primario" href="<?= e(url('oficios/subdireccion/' . (int) $o['id'] . '/pdf')) ?>" target="_blank" rel="noopener"><?= $o['estado'] === 'firmado' ? 'Descargar el oficio firmado (PDF)' : 'Ver la vista previa (PDF)' ?></a>
    <?php if ($edita): ?><a class="btn" href="<?= e(url('oficios/subdireccion/' . (int) $o['id'] . '/editar')) ?>">Editar</a><?php endif; ?>
    <?php if ($o['estado'] === 'borrador'): ?>
      <form method="post" action="<?= e(url('oficios/subdireccion/' . (int) $o['id'] . '/enviar')) ?>" onsubmit="return confirm('Se asignará el siguiente número de oficio y quedará en firma del titular. ¿Enviar?')"><?= $csrf ?><button class="btn btn-primario" type="submit">Enviar a firma</button></form>
    <?php endif; ?>
    <?php if ($o['estado'] === 'en_firma' && $puedeFirmar): ?>
      <a class="btn btn-primario" href="<?= e(url('oficios/subdireccion/' . (int) $o['id'] . '/firmar')) ?>">Firmar con e.firma</a>
    <?php endif; ?>
    <?php if ($edita): ?>
      <form method="post" action="<?= e(url('oficios/subdireccion/' . (int) $o['id'] . '/cancelar')) ?>" onsubmit="var m = prompt('Motivo de la cancelación:'); if (!m) return false; this.motivo.value = m; return true;"><?= $csrf ?><input type="hidden" name="motivo"><button class="btn btn-mini btn-peligro" type="submit">Cancelar oficio</button></form>
    <?php endif; ?>
  </div>
</section>
<?php if ($historial): ?>
<section class="card">
  <h3 style="margin:0 0 8px;font-size:16px">Historial</h3>
  <ul style="margin:0;padding-left:18px">
    <?php foreach ($historial as $h): ?><li><strong><?= e($acciones[$h['accion']] ?? $h['accion']) ?></strong> · <?= e(date('d/m/Y H:i', strtotime((string) $h['creado_en']))) ?><?= $h['nombre'] ? ' · ' . e($h['nombre']) : '' ?><?= $h['nota'] ? ' — ' . e($h['nota']) : '' ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
