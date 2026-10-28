<?php
use App\Core\Auth;
use App\Modules\Formularios\Formularios;
$csrf = e(Auth::csrfToken());
$base = 'formularios/' . (int) $f['id'];
$visibles = array_values(array_filter($campos, static fn(array $c): bool => (($c['tipo'] ?? '') !== 'confirmar' || !empty($c['mostrar'])) && ($c['tipo'] ?? '') !== 'fijo'));
$grupoCampo = null;
foreach ($campos as $c) { if ($c['k'] === $f['agrupar_por']) { $grupoCampo = $c; } }
$porGrupo = [];
foreach ($respuestas as $r) { $porGrupo[(string) ($r['datos'][$f['agrupar_por']] ?? '')][] = $r; }
$enlace = rtrim((string) \App\Core\App::config('app.url_publica', ''), '/') . url('f/' . $f['slug']);
?>
<style>.frm-tabla td{font-size:13px;vertical-align:top}.frm-tabla input,.frm-tabla select{font-size:13px;width:100%;min-width:120px}</style>
<section class="tarjeta" style="margin-bottom:14px">
  <div class="tarjeta-cabecera"><h2><?= count($respuestas) ?> registro(s)</h2>
    <a class="btn btn-primario" href="<?= url_archivo($base . '/excel') ?>">Descargar Excel</a></div>
  <p>Enlace público para compartir: <a href="<?= e($enlace) ?>" target="_blank" rel="noopener"><?= e($enlace) ?></a></p>
  <?php if ($grupoCampo): ?><p class="texto-secundario"><?php foreach ($grupoCampo['opciones'] as $k => $n): ?><?= e($n) ?>: <strong><?= count($porGrupo[$k] ?? []) ?></strong> · <?php endforeach; ?></p><?php endif; ?>
  <?php if (Formularios::puedeAdministrar()): ?>
  <details><summary>Ajustes del formulario</summary>
    <form method="post" action="<?= url($base . '/ajustes') ?>" class="formulario" style="margin-top:8px">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Estado <select name="estado"><?php foreach (['borrador' => 'Borrador (enlace inactivo)', 'abierto' => 'Abierto', 'cerrado' => 'Cerrado'] as $k => $n): ?><option value="<?= $k ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></label>
      <label>Cierra automáticamente el (opcional) <input type="datetime-local" name="cierra_en" value="<?= $f['cierra_en'] ? date('Y-m-d\TH:i', strtotime((string) $f['cierra_en'])) : '' ?>"></label>
      <fieldset><legend>Pueden consultar, descargar y corregir los registros</legend>
        <?php foreach (Formularios::AUTORIDADES as $k => $n): ?><label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="acceso[]" value="<?= e($k) ?>" <?= in_array($k, Formularios::autoridades($f), true) ? 'checked' : '' ?>> <?= e($n) ?> (según Organización)</label><?php endforeach; ?>
      </fieldset>
      <label>Otras personas (correos, uno por línea) <textarea name="responsables" rows="3"><?= e((string) $f['responsables']) ?></textarea></label>
      <button class="btn btn-primario btn-mini" type="submit">Guardar ajustes</button>
    </form>
  </details>
  <?php else: ?>
  <p class="texto-secundario">Puedes consultar, descargar y corregir los registros. Eliminar registros y cambiar los ajustes del formulario corresponde a la administración.</p>
  <?php endif; ?>
</section>

<?php foreach ($porGrupo as $g => $lista): ?>
<section class="tarjeta" style="margin-bottom:14px">
  <h3><?= e($grupoCampo ? Formularios::valor($grupoCampo, (string) $g) : 'Registros') ?> (<?= count($lista) ?>)</h3>
  <div class="tabla-contenedor"><table class="tabla frm-tabla">
    <thead><tr><th>#</th><?php foreach ($visibles as $c): if ($c['k'] === $f['agrupar_por']) { continue; } ?><th><?= e($c['label']) ?></th><?php endforeach; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $i => $r): ?>
      <tr id="r<?= (int) $r['id'] ?>">
        <td><?= $i + 1 ?></td>
        <?php foreach ($visibles as $c): if ($c['k'] === $f['agrupar_por']) { continue; } ?>
          <?php if (($c['tipo'] ?? '') === 'evidencia'): $ev = Formularios::evidencia($f, $c, $r); ?>
            <td><?php if ($ev['modo'] !== ''): ?><a href="<?= e($ev['url']) ?>" <?= $ev['modo'] === 'enlace' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= $ev['modo'] === 'archivo' ? 'Descargar .zip' : 'Abrir enlace' ?></a><?php endif; ?></td>
          <?php else: ?><td><?= e(Formularios::valor($c, (string) ($r['datos'][$c['k']] ?? ''))) ?></td><?php endif; ?>
        <?php endforeach; ?>
        <td style="white-space:nowrap">
          <details><summary>Corregir</summary>
            <form method="post" action="<?= url($base . '/respuestas/' . (int) $r['id']) ?>" class="formulario" style="min-width:260px">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <?php foreach ($visibles as $c): if (in_array($c['tipo'] ?? '', ['evidencia', 'confirmar'], true)) { continue; } $v = (string) ($r['datos'][$c['k']] ?? ''); ?>
                <label><?= e($c['label']) ?>
                <?php if (($c['tipo'] ?? '') === 'select'): ?><select name="d[<?= e($c['k']) ?>]"><?php foreach ($c['opciones'] as $k => $n): ?><option value="<?= e((string) $k) ?>" <?= (string) $k === $v ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
                <?php elseif (($c['tipo'] ?? '') === 'fecha'): ?><input type="date" name="d[<?= e($c['k']) ?>]" value="<?= e($v) ?>">
                <?php else: ?><input name="d[<?= e($c['k']) ?>]" value="<?= e($v) ?>"><?php endif; ?></label>
              <?php endforeach; ?>
              <button class="btn btn-mini btn-primario" type="submit">Guardar</button>
            </form>
            <?php if (Formularios::puedeAdministrar()): ?><form method="post" action="<?= url($base . '/respuestas/' . (int) $r['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar este registro?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Eliminar</button></form><?php endif; ?>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>
