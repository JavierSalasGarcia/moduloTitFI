<?php use App\Core\Acceso; use App\Core\Auth; ?>
<?php if (!$migrada): ?>
  <div class="card"><p>Falta aplicar la migración <code>043_acceso_concesiones</code> (database/actualizar.sql). Mientras tanto no hay accesos especiales.</p></div>
<?php else: ?>
<p>Cada módulo se abre por rol. Aquí se asigna, <strong>a una persona concreta</strong>, un acceso que su rol no incluye. Siempre tiene fecha de término: el acceso caduca solo al terminar la función.</p>

<?php if ($porVencer): ?>
  <div class="card" role="status">
    <strong>Por vencer en 15 días o menos</strong>
    <ul>
      <?php foreach ($porVencer as $v): ?>
        <li><?= e($v['nombre']) ?> — <?= e(Acceso::etiqueta((string) $v['funcion'])) ?> — vence el <?= e($v['vigente_hasta']) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Conceder acceso</h2>
  <form method="post" action="<?= url('concesiones') ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <label>Correo de la persona (con cuenta en Traza FI)
      <input type="email" name="correo" required maxlength="120" autocomplete="off">
    </label>
    <label>Función
      <select name="funcion" required>
        <option value="">Elige…</option>
        <?php foreach ($funciones as $clave => $nombre): ?>
          <option value="<?= e($clave) ?>"><?= e($nombre) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Dependencia que lo concede
      <select name="departamento_id" <?= $esAdmin ? '' : 'required' ?>>
        <?php if ($esAdmin): ?><option value="">Toda la plataforma</option><?php endif; ?>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Vigente hasta (fin de la función)
      <input type="date" name="vigente_hasta" required min="<?= e(date('Y-m-d')) ?>">
    </label>
    <label>Motivo
      <input type="text" name="motivo" maxlength="255" placeholder="Por ejemplo: suplencia de la coordinación">
    </label>
    <button class="btn btn-primario">Conceder</button>
  </form>
</div>

<div class="card tabla-contenedor">
  <h2>Accesos concedidos</h2>
  <?php if (!$concesiones): ?>
    <p>No hay accesos especiales registrados.</p>
  <?php else: ?>
  <table class="tabla" data-no-tools>
    <thead><tr><th>Persona</th><th>Función</th><th>Dependencia</th><th>Vigente hasta</th><th>Concedió</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($concesiones as $c): ?>
        <tr>
          <td><?= e($c['nombre']) ?><br><small><?= e($c['email']) ?></small></td>
          <td><?= e(Acceso::etiqueta((string) $c['funcion'])) ?><?php if ($c['motivo'] !== ''): ?><br><small><?= e($c['motivo']) ?></small><?php endif; ?></td>
          <td><?= e($c['departamento'] ?? 'Toda la plataforma') ?></td>
          <td><?= e($c['vigente_hasta']) ?></td>
          <td><?= e($c['otorgante']) ?></td>
          <td><?= e(ucfirst($c['estado'])) ?></td>
          <td>
            <?php if ($c['estado'] === 'vigente'): ?>
              <form method="post" action="<?= url('concesiones/' . (int) $c['id'] . '/revocar') ?>" onsubmit="return confirm('¿Revocar este acceso ahora?')">
                <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
                <button class="btn">Revocar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
