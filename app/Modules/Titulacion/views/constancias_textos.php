<?php
use App\Core\Auth;
use App\Modules\Titulacion\Services\Constancias;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Redacción de cada constancia. Los marcadores entre llaves se sustituyen al generarla con los datos del profesor, del pasante y del trabajo. Separa párrafos con una línea en blanco. Los cambios valen para las constancias que se generen después de guardarlos; las ya generadas no cambian.</p>
</div></div>

<details class="tarjeta" style="margin-bottom:14px">
  <summary><strong>Marcadores disponibles</strong></summary>
  <div class="tabla-contenedor"><table class="tabla">
    <?php foreach (Constancias::MARCADORES as $m => $d): ?><tr><td><code><?= e($m) ?></code></td><td><?= e($d) ?></td></tr><?php endforeach; ?>
  </table></div>
</details>

<?php foreach ($textos as $tipo => $t): ?>
<details class="tarjeta" id="<?= e($tipo) ?>" style="margin-bottom:12px" <?= $abierto === $tipo ? 'open' : '' ?>>
  <summary><strong><?= e(Constancias::TIPOS[$tipo][0]) ?></strong> · firma <?= e(Constancias::EMISORES[Constancias::TIPOS[$tipo][2]]) ?><?= $t['editado'] ? ' · <span class="tit-chip rev">editado</span>' : '' ?></summary>
  <form class="formulario" method="post" action="<?= url('titulacion/constancias/textos/' . $tipo) ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Asunto (a la derecha, bajo el número de oficio; vacío para omitirlo) <input name="asunto" maxlength="80" value="<?= e($t['asunto']) ?>"></label>
    <label>Introducción (antes del nombre del profesor) <textarea name="intro" rows="3" maxlength="2000" required><?= e($t['intro']) ?></textarea></label>
    <label>Cuerpo (después del nombre del profesor) <textarea name="cuerpo" rows="5" maxlength="4000" required><?= e($t['cuerpo']) ?></textarea></label>
    <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="tabla" value="1" <?= $t['tabla'] ? 'checked' : '' ?>> Incluir la tabla del trabajo (pasante, modalidad y clave, fecha, título)</label>
    <label>Cierre <textarea name="cierre" rows="3" maxlength="2000"><?= e($t['cierre']) ?></textarea></label>
    <div class="acciones-formulario">
      <button class="btn btn-primario" type="submit">Guardar</button>
      <button class="btn" type="submit" formaction="<?= url('titulacion/constancias/textos/' . $tipo . '/muestra') ?>" formtarget="_blank">Vista previa (PDF)</button>
      <?php if ($t['editado']): ?><button class="btn" type="submit" formaction="<?= url('titulacion/constancias/textos/' . $tipo . '/restaurar') ?>" formnovalidate onclick="return confirm('¿Restaurar el texto original de esta constancia?')">Restaurar original</button><?php endif; ?>
    </div>
  </form>
</details>
<?php endforeach; ?>
