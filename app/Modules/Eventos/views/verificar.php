<?php $estados = ['validada' => 'Asistencia validada', 'pendiente' => 'En revisión (aún no validada)', 'rechazada' => 'No validada']; ?>
<section class="tarjeta" style="max-width:640px;margin:0 auto">
  <h2 style="margin-top:0">Verificación de asistencia</h2>
  <?php if (!$a): ?>
    <p>No existe una asistencia con el folio <strong><?= e($folio) ?></strong>. Revisa que esté bien escrito.</p>
  <?php else: ?>
    <p style="font-size:18px"><strong><?= e($estados[$a['estado']]) ?></strong></p>
    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:6px 14px">
      <dt>Folio</dt><dd style="margin:0"><?= e($a['folio']) ?></dd>
      <dt>Persona</dt><dd style="margin:0"><?= e($a['nombre']) ?></dd>
      <?php if ($a['programa']): ?><dt>Carrera</dt><dd style="margin:0"><?= e($a['programa']) ?></dd><?php endif; ?>
      <dt>Evento</dt><dd style="margin:0"><?= e($a['titulo']) ?></dd>
      <dt>Fecha</dt><dd style="margin:0"><?= e(fecha_corta($a['inicio'])) ?><?= $a['fin'] ? ' — ' . e(date('H:i', strtotime((string) $a['fin']))) : '' ?></dd>
      <?php if ($a['organizador']): ?><dt>Organiza</dt><dd style="margin:0"><?= e($a['organizador']) ?></dd><?php endif; ?>
      <?php if ($a['horas']): ?><dt>Horas</dt><dd style="margin:0"><?= e((string) (float) $a['horas']) ?></dd><?php endif; ?>
      <dt>Registro</dt><dd style="margin:0"><?= e(['qr' => 'Código QR en el evento', 'clave' => 'Palabra clave de la sesión', 'constancia' => 'Constancia revisada por Difusión Cultural'][$a['metodo']] ?? '') ?> · <?= e(fecha_corta($a['creado_en'])) ?></dd>
    </dl>
  <?php endif; ?>
  <p class="texto-suave" style="font-size:12px;margin-top:14px">Facultad de Ingeniería · UAEMEX. Esta página confirma el registro de asistencia en Traza FI.</p>
</section>
