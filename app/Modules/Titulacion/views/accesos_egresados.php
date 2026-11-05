<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\AccesoEgresados;
include __DIR__ . '/_estilos.php';
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$csrf = e(Auth::csrfToken());
$pre = ['coincide' => 'Coincide con los registros', 'media' => 'Coincide a medias', 'ninguna' => 'No aparece'];
?>
<style>
  .eg-tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
  .eg-tabs a{padding:6px 12px;border:1px solid #cbd5e1;border-radius:999px;text-decoration:none;color:inherit;font-size:14px}
  .eg-tabs a.activa{background:#0f3d6e;color:#fff;border-color:#0f3d6e}
  .eg-tabla{width:100%;border-collapse:collapse;font-size:14px}
  .eg-tabla th{text-align:left;background:#f1f5f9;padding:6px 8px}
  .eg-tabla td{padding:6px 8px;border-top:1px solid #e2e8f0;vertical-align:top}
</style>
<h1>Accesos de egresados</h1>
<p class="texto-secundario">Personas sin correo institucional que piden acceder solo al módulo de Titulación. Nada se activa sin tu aprobación.</p>
<nav class="eg-tabs">
  <?php foreach (AccesoEgresados::ESTADOS as $k => $n): ?>
    <a href="<?= url('titulacion/accesos-egresados?estado=' . $k) ?>" class="<?= $k === $estado ? 'activa' : '' ?>"><?= e($n) ?> (<?= (int) ($conteos[$k] ?? 0) ?>)</a>
  <?php endforeach; ?>
</nav>
<table class="eg-tabla">
  <thead><tr><th>Solicitó</th><th>Persona</th><th>Cuenta</th><th>Licenciatura</th><th>Correo</th><th>Prevalidación</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filas as $s): ?>
    <tr>
      <td><?= e(date('d/m/Y H:i', strtotime((string) $s['creado_en']))) ?></td>
      <td><?= e($s['nombre']) ?></td>
      <td><?= e($s['cuenta']) ?></td>
      <td><?= e($carreras[$s['carrera']] ?? $s['carrera']) ?></td>
      <td><?= e($s['email']) ?></td>
      <td><?= e($pre[(string) $s['prevalidacion']] ?? '—') ?></td>
      <td style="white-space:nowrap">
        <a class="btn" href="<?= url('titulacion/accesos-egresados/' . (int) $s['id']) ?>">Revisar</a>
        <?php if ($s['estado'] === 'pendiente' && $s['prevalidacion'] === 'coincide'): ?>
          <form method="post" action="<?= url('titulacion/accesos-egresados/' . (int) $s['id'] . '/aprobar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-primario" type="submit" onclick="return confirm('¿Aprobar el acceso de <?= e(addslashes((string) $s['nombre'])) ?>? Coincide con los registros del departamento.')">Aprobar</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$filas): ?><tr><td colspan="7">No hay solicitudes en este estado.</td></tr><?php endif; ?>
  </tbody>
</table>

<h2 style="margin-top:28px">Cuentas de egresado</h2>
<table class="eg-tabla">
  <thead><tr><th>Persona</th><th>Cuenta</th><th>Correo</th><th>Validada</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cuentas as $c): ?>
    <tr>
      <td><?= e($c['nombre']) ?></td><td><?= e((string) $c['egresado_cuenta']) ?></td><td><?= e($c['email']) ?></td>
      <td><?= $c['egresado_validado_en'] ? e(date('d/m/Y', strtotime((string) $c['egresado_validado_en']))) : '' ?></td>
      <td><?= $c['egresado_vence'] ? e(date('d/m/Y', strtotime((string) $c['egresado_vence']))) : '' ?></td>
      <td><?= (int) $c['activo'] === 1 ? 'Activa' : 'Desactivada' ?></td>
      <td><?php if ((int) $c['activo'] === 1): ?><form method="post" action="<?= url('titulacion/accesos-egresados/cuenta/' . (int) $c['id'] . '/desactivar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn" type="submit" onclick="return confirm('¿Desactivar este acceso?')">Desactivar</button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$cuentas): ?><tr><td colspan="7">Aún no hay cuentas de egresado.</td></tr><?php endif; ?>
  </tbody>
</table>
