<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
$rot = ['sin_iniciar' => ['pend', 'Por integrar'], 'en_integracion' => ['rev', 'En integración'], 'verificado' => ['ok', 'Verificado'], 'enviado' => ['na', 'Enviado']];
$listos = array_filter($filas, static fn(array $f): bool => in_array($f['exp_estado'], ['verificado', 'enviado'], true));
?>
<div class="tit-cabecera">
  <div>
    <h1>Expedientes para Servicios Escolares</h1>
    <p class="texto-secundario">Cuando concluye la evaluación profesional, el Departamento de Titulación verifica el expediente con el formato oficial de integración de su opción de titulación. Los expedientes verificados se exportan en una carpeta comprimida para enviarla al Departamento de Servicios Escolares de la Administración Central.</p>
  </div>
</div>

<section class="tarjeta">
  <?php if (!$filas): ?>
    <div class="ux-empty">Aún no hay trámites con evaluación concluida.</div>
  <?php else: ?>
  <form method="post" action="<?= url('titulacion/expedientes/exportar') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="tabla-contenedor">
    <table class="tabla">
      <thead><tr><th></th><th>Folio</th><th>Pasante</th><th>Opción</th><th>Resultado</th><th>Expediente</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($filas as $f): $m = $modalidades[$f['modalidad']]['nombre'] ?? $f['modalidad']; [$cl, $et] = $rot[$f['exp_estado']]; $apto = in_array($f['exp_estado'], ['verificado', 'enviado'], true); ?>
        <tr>
          <td><?php if ($apto): ?><input type="checkbox" name="ids[]" value="<?= (int) $f['id'] ?>" aria-label="Incluir <?= e($f['folio']) ?>" <?= $f['exp_estado'] === 'verificado' ? 'checked' : '' ?>><?php endif; ?></td>
          <td><?= e($f['folio']) ?></td>
          <td><?= e((string) $f['alumno_nombre']) ?></td>
          <td><?= e($m) ?></td>
          <td><?= e((string) $f['resultado']) ?></td>
          <td><span class="tit-chip <?= $cl ?>"><?= e($et) ?></span><?php if ($f['enviado_en']): ?> <span class="texto-secundario"><?= e(fecha_corta($f['enviado_en'])) ?></span><?php endif; ?></td>
          <td><a class="btn btn-mini" href="<?= url('titulacion/expedientes/' . (int) $f['id']) ?>"><?= $apto ? 'Ver' : 'Verificar' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="acciones-formulario" style="margin-top:12px">
      <button class="btn btn-primario" type="submit" <?= $listos ? '' : 'disabled' ?>>Exportar carpeta comprimida para Servicios Escolares</button>
      <span class="texto-secundario">Incluye, por expediente, los documentos en el orden del formato, el formato llenado y un índice con huellas SHA-256. Los verificados pasan a «Enviado».</span>
    </div>
  </form>
  <?php endif; ?>
</section>

<?php if ($lotes): ?>
<section class="tarjeta" style="margin-top:18px">
  <h2 style="font-size:16px">Envíos anteriores</h2>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Lote</th><th>Fecha</th><th>Por</th><th>Expedientes</th><th>Huella SHA-256</th></tr></thead>
    <tbody><?php foreach ($lotes as $l): ?>
      <tr><td><?= (int) $l['id'] ?></td><td><?= e(fecha_corta($l['creado_en'])) ?></td><td><?= e($l['autor']) ?></td><td><?= (int) $l['total'] ?> · <?= e($l['folios']) ?></td><td><code><?= e(substr((string) $l['huella'], 0, 16)) ?>…</code></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
