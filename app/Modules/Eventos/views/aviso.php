<?php
use App\Core\Auth;
use App\Modules\Comunicados\Avisos;
$etiquetaTipo = ['aviso' => 'Aviso oficial', 'convocatoria' => 'Convocatoria', 'examenes' => 'Exámenes'];
$borrador = $c['categoria'] === 'Ocultar';
?>
<?php include __DIR__ . '/_cintillo.php'; ?>

<?php if ($comoVisitante && Auth::id()): ?>
  <p class="flash flash-alerta" role="status">Vista previa: así lo verá una persona sin iniciar sesión<?= $borrador ? ' cuando lo publiques' : '' ?>. <a href="<?= e(url('eventos/aviso/' . $c['id'])) ?>">Volver a la vista de gestión</a></p>
<?php endif; ?>

<p style="margin:0 0 10px"><a href="<?= e(url('eventos')) ?>">← Todos los avisos</a></p>

<article class="card" style="padding:22px">
  <div class="grupo-chips" style="gap:6px;margin-bottom:8px">
    <span class="badge"><?= e($etiquetaTipo[$c['clase'] ?? 'aviso'] ?? 'Aviso oficial') ?></span>
    <?php if ($c['categoria'] === 'Importantes'): ?><span class="badge" style="background:#fef3c7;color:#b45309">⭐ Importante</span><?php endif; ?>
    <?php if ($borrador && !$comoVisitante): ?><span class="badge" style="background:#f1f5f9;color:#475569">Borrador · aún no publicado</span><?php endif; ?>
  </div>
  <h1 style="margin:0 0 6px;font-size:22px;color:var(--verde-900,#1f4d36)"><?= e($c['titulo']) ?></h1>
  <p class="texto-suave" style="margin:0 0 16px"><?= e($c['depto']) ?> · <?= e(fecha_corta($c['creado_en'])) ?> · <?= e(Avisos::destino($c)) ?><?= !empty($c['vence_en']) ? ' · Vigente hasta ' . e($c['vence_en']) : '' ?></p>
  <div style="font-size:15px;line-height:1.6"><?= nl2br(e($c['contenido'])) ?></div>

  <?php if ($adjuntos): ?>
    <h2 style="font-size:16px;margin:22px 0 10px">Archivos</h2>
    <div class="adjuntos-aviso">
      <?php foreach ($adjuntos as $a): ?>
        <div class="adjunto-fila">
          <a class="adjunto-boton" href="<?= e(url('eventos/aviso/' . $c['id'] . '/archivo/' . $a['id'])) ?>" target="_blank" rel="noopener">
            <span aria-hidden="true">📄</span> <strong><?= e($a['etiqueta']) ?></strong> <small class="texto-suave">PDF · <?= number_format($a['bytes'] / 1024, 0) ?> KB</small>
          </a>
          <a class="btn btn-mini" href="<?= e(url('eventos/aviso/' . $c['id'] . '/archivo/' . $a['id'] . '?descargar=1')) ?>" aria-label="Descargar <?= e($a['etiqueta']) ?>">Descargar</a>
          <?php if ($gestiona): ?>
            <form method="post" action="<?= e(url('eventos/aviso/' . $c['id'] . '/adjuntos/' . $a['id'] . '/quitar')) ?>" data-confirm="¿Retirar «<?= e($a['etiqueta']) ?>»?"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini btn-peligro" type="submit">Quitar</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>

<?php if ($gestiona): ?>
  <section class="card" style="margin-top:16px">
    <h2 style="font-size:16px;margin:0 0 10px">Gestión del aviso</h2>
    <p class="texto-suave" style="margin:0 0 12px">
      Estado: <strong><?= $borrador ? 'Borrador (solo tu área lo ve)' : 'Publicado' ?></strong> ·
      Consulta sin sesión: <strong><?= (int) $c['publico'] === 1 ? 'sí, es público' : 'no, solo con sesión' ?></strong>
    </p>
    <div class="grupo-chips" style="gap:8px;margin-bottom:14px">
      <a class="btn" href="<?= e(url('eventos/aviso/' . $c['id'] . '?como=visitante')) ?>">👁 Ver como visitante</a>
      <?php foreach ([$borrador ? 'publicar' : 'borrador', (int) $c['publico'] === 1 ? 'privado' : 'publico'] as $acc): ?>
        <form method="post" action="<?= e(url('eventos/aviso/' . $c['id'] . '/estado')) ?>" <?= $acc === 'publicar' ? 'data-confirm="¿Publicar este aviso? Lo verán sus destinatarios' . ((int) $c['publico'] === 1 ? ' y cualquier visitante' : '') . '."' : '' ?>>
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="accion" value="<?= $acc ?>">
          <button class="btn <?= $acc === 'publicar' ? 'btn-primario' : '' ?>" type="submit"><?= ['publicar' => '✓ Publicar', 'borrador' => 'Regresar a borrador', 'publico' => '🌐 Permitir consulta sin sesión', 'privado' => 'Solo con sesión'][$acc] ?></button>
        </form>
      <?php endforeach; ?>
    </div>
    <form method="post" action="<?= e(url('eventos/aviso/' . $c['id'] . '/adjuntos')) ?>" enctype="multipart/form-data" class="ux-toolbar" style="flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Etiqueta del archivo<input type="text" name="etiqueta" required maxlength="120" placeholder="Ej. Ingeniería Mecánica (IME)"></label>
      <label>PDF (hasta 20 MB)<input type="file" name="archivo" accept="application/pdf" required></label>
      <button class="btn btn-mini btn-primario" type="submit">+ Agregar archivo</button>
    </form>
  </section>
<?php endif; ?>

<style>
.adjuntos-aviso{display:flex;flex-direction:column;gap:8px}
.adjunto-fila{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.adjunto-boton{flex:1 1 280px;display:flex;gap:10px;align-items:center;border:1px solid var(--gris-200,#d6dbe1);border-radius:10px;padding:12px 14px;text-decoration:none;color:var(--verde-900,#1f4d36);background:#fbfdfc}
.adjunto-boton:hover{border-color:var(--verde-700,#2c6b4a);background:#f1f8f4}
.adjunto-fila form{margin:0}
</style>
