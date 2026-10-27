<div class="barra-acciones">
  <p class="texto-suave">Oficios para justificar las faltas de alumnos, redactados sobre la hoja membretada de la Facultad. Los archivos que adjuntes se incluyen al final del mismo PDF.</p>
  <a class="btn btn-primario" href="<?= url('justificantes/nuevo') ?>"><?= icono('mas', 15) ?> Nuevo oficio</a>
</div>

<?php if (!$lista): ?>
  <section class="tarjeta"><p class="texto-suave">Aún no has generado oficios de justificación.</p></section>
<?php else: ?>
  <section class="tarjeta" style="overflow-x:auto">
    <table class="tabla">
      <thead><tr><th>Fecha</th><th>Oficio</th><th>Asunto</th><th>Dirigido a</th><th>Firma</th><th>Anexos</th><?php if ($admin): ?><th>Redactó</th><?php endif; ?><th></th></tr></thead>
      <tbody>
      <?php foreach ($lista as $o): ?>
        <tr<?= (int) $o['id'] === $nuevoId ? ' style="background:var(--verde-50,#eef6ef)"' : '' ?>>
          <td><?= e(fecha_corta((string) $o['fecha'])) ?></td>
          <td><?= e($o['numero'] !== '' ? $o['numero'] : '—') ?></td>
          <td><?= e($o['asunto']) ?></td>
          <td><?= e($o['destinatario'] !== '' ? $o['destinatario'] : 'Profesores') ?></td>
          <td><?= e($o['firmante_nombre']) ?></td>
          <td><?= (int) $o['anexos'] ?></td>
          <?php if ($admin): ?><td><?= e($o['redactor']) ?></td><?php endif; ?>
          <td><a class="btn btn-mini<?= (int) $o['id'] === $nuevoId ? ' btn-primario' : '' ?>" href="<?= url('justificantes/' . (int) $o['id'] . '/archivo') ?>" target="_blank" rel="noopener">Descargar PDF</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
<?php endif; ?>
