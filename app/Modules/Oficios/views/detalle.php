<?php use App\Core\Auth; ?>
<p><a href="<?= url('oficios') ?>">← Oficios enviados</a></p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:20px;align-items:start">
  <section class="tarjeta">
    <h2><?= e($o['asunto']) ?></h2>
    <p class="texto-suave"><?= e($tipos[$o['tipo']] ?? '') ?> · <span class="monoespacio"><?= e($o['folio']) ?></span> · <?= e(date('d/m/Y', strtotime($o['fecha']))) ?></p>
    <dl>
      <dt>Coordinación o departamento</dt><dd><?= e($o['departamento']) ?></dd>
      <dt>Dirigido a</dt><dd><?= e($o['destinatarios']) ?></dd>
      <?php if ($o['firmante'] !== ''): ?><dt>Firmante</dt><dd><?= e($o['firmante']) ?></dd><?php endif; ?>
      <?php if ($o['notas']): ?><dt>Notas internas</dt><dd><?= nl2br(e($o['notas'])) ?></dd><?php endif; ?>
      <dt>Archivado por</dt><dd><?= e($o['registrado_por']) ?> · <?= e(fecha_corta($o['creado_en'])) ?></dd>
      <dt>Huella SHA-256</dt><dd class="monoespacio" style="word-break:break-all"><?= e($o['sha256']) ?></dd>
    </dl>
    <p>
      <a class="btn btn-primario" href="<?= url('oficios/' . $o['id'] . '/archivo') ?>" target="_blank" rel="noopener">Abrir PDF</a>
      <span class="texto-suave"><?= e($o['archivo_nombre']) ?></span>
    </p>
  </section>

  <section class="tarjeta">
    <h2>Editar datos</h2>
    <form method="post" action="<?= url('oficios/' . $o['id']) ?>" enctype="multipart/form-data" class="formulario">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <?php include __DIR__ . '/_campos.php'; ?>
      <label>Reemplazar PDF (opcional)
        <input type="file" name="archivo" accept="application/pdf">
      </label>
      <button class="btn btn-primario">Guardar cambios</button>
    </form>
  </section>
</div>
