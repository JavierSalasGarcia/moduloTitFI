<?php
/** Renderiza un campo de captura. Variables: $campo (definición), $valor (guardado), $nombre (atributo name). */
$k = $campo['k'];
$id = 'c-' . $k;
$tipo = $campo['tipo'] ?? 'texto';
$ancho = in_array($tipo, ['area', 'cronograma'], true) ? ' ancho' : '';
$req = !empty($campo['req']);
?>
<div class="tit-campo<?= $ancho ?>">
<?php if ($tipo === 'cronograma'): ?>
  <?php $filas = is_array($valor) && $valor ? array_values($valor) : [['a' => '', 'b' => []]]; while (count($filas) < 8) { $filas[] = ['a' => '', 'b' => []]; } ?>
  <strong><?= e($campo['label']) ?><?= $req ? ' <span class="req">*</span>' : '' ?></strong>
  <?php if (!empty($campo['ayuda'])): ?><span class="ayuda-form"><?= e($campo['ayuda']) ?></span><?php endif; ?>
  <div class="tabla-contenedor">
  <table class="tit-crono">
    <thead><tr><th>Actividad</th><?php for ($b = 1; $b <= 12; $b++): ?><th><?= $b ?></th><?php endfor; ?></tr></thead>
    <tbody>
    <?php foreach (array_slice($filas, 0, 8) as $i => $fila): ?>
      <tr>
        <td><input type="text" name="crono_a[<?= $i ?>]" maxlength="120" value="<?= e((string) ($fila['a'] ?? '')) ?>" aria-label="Actividad <?= $i + 1 ?>"></td>
        <?php for ($b = 1; $b <= 12; $b++): ?>
          <td><input type="checkbox" name="crono_b[<?= $i ?>][]" value="<?= $b ?>" aria-label="Bimestre <?= $b ?>, actividad <?= $i + 1 ?>" <?= in_array($b, (array) ($fila['b'] ?? []), true) ? 'checked' : '' ?>></td>
        <?php endfor; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php else: ?>
  <label for="<?= e($id) ?>"><?= e($campo['label']) ?><?= $req ? ' <span class="req">*</span>' : ' <span class="opcional">(opcional)</span>' ?></label>
  <?php if ($tipo === 'area'): ?>
    <textarea id="<?= e($id) ?>" name="<?= e($nombre) ?>" rows="<?= (int) ($campo['filas'] ?? 4) ?>" maxlength="<?= (int) ($campo['max'] ?? 1500) ?>" <?= $req ? 'required' : '' ?>><?= e((string) $valor) ?></textarea>
  <?php elseif ($tipo === 'select'): ?>
    <select id="<?= e($id) ?>" name="<?= e($nombre) ?>" <?= $req ? 'required' : '' ?>>
      <option value="">Elige…</option>
      <?php foreach ($campo['opciones'] as $v => $et): ?>
        <option value="<?= e((string) $v) ?>" <?= (string) $valor === (string) $v ? 'selected' : '' ?>><?= e($et) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <?php $htmlTipo = ['fecha' => 'date', 'hora' => 'time', 'email' => 'email', 'tel' => 'tel'][$tipo] ?? 'text'; ?>
    <input id="<?= e($id) ?>" type="<?= $htmlTipo ?>" name="<?= e($nombre) ?>" value="<?= e((string) $valor) ?>"
      <?= isset($campo['max']) ? 'maxlength="' . (int) $campo['max'] . '"' : '' ?>
      <?= !empty($campo['patron']) ? 'pattern="' . e((string) $campo['patron']) . '"' : '' ?> <?= $req ? 'required' : '' ?>>
  <?php endif; ?>
  <?php if (!empty($campo['ayuda'])): ?><span class="ayuda-form"><?= e($campo['ayuda']) ?></span><?php endif; ?>
<?php endif; ?>
</div>
