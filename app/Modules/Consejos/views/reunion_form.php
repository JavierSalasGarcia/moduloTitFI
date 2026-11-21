<?php
/** @var array $r @var bool $nueva */
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$v = static fn(string $c) => e((string) ($r[$c] ?? ''));
$hora = static fn(string $c) => e(substr((string) ($r[$c] ?? ''), 0, 5));
$conv = ['primera' => 'Primera convocatoria', 'segunda' => 'Segunda convocatoria', 'unica' => 'Única convocatoria'];
?>
<div class="cns-pila">
  <form class="tarjeta cns-form" method="post" action="<?= e(url($nueva ? 'consejos' : 'consejos/reunion/' . $r['id'])) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="cns-cab" style="margin-bottom:12px"><h1><?= $nueva ? 'Nueva reunión de Consejos' : 'Datos de la reunión' ?></h1></div>
    <div class="cns-fila">
      <label>Nombre de la reunión <span class="req">*</span><input type="text" name="etiqueta" required maxlength="80" value="<?= $v('etiqueta') ?>" placeholder="Noviembre 2026"></label>
      <label>Tipo de sesión<select name="tipo_sesion"><option value="ordinaria"<?= $r['tipo_sesion'] === 'ordinaria' ? ' selected' : '' ?>>Ordinaria</option><option value="extraordinaria"<?= $r['tipo_sesion'] === 'extraordinaria' ? ' selected' : '' ?>>Extraordinaria</option></select></label>
    </div>
    <div class="cns-fila">
      <label>Presidente de los Consejos<input type="text" name="presidente" maxlength="160" value="<?= $v('presidente') ?>" placeholder="Dr. Nombre Apellidos"></label>
      <label>Secretario de los Consejos<input type="text" name="secretario" maxlength="160" value="<?= $v('secretario') ?>" placeholder="Dr. Nombre Apellidos"></label>
    </div>

    <?php foreach (['academico' => 'Consejo Académico', 'gobierno' => 'Consejo de Gobierno'] as $k => $nombre): ?>
      <fieldset style="border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px 14px;margin:0 0 14px">
        <legend style="font-weight:800;color:var(--verde-900);padding:0 6px"><?= e($nombre) ?></legend>
        <div class="cns-fila">
          <label>Fecha de la sesión<input type="date" name="<?= $k ?>_fecha" value="<?= $v($k . '_fecha') ?>"></label>
          <label>Hora de inicio<input type="time" name="<?= $k ?>_hora" value="<?= $hora($k . '_hora') ?>"></label>
          <label>Convocatoria<select name="<?= $k ?>_convocatoria"><?php foreach ($conv as $ck => $cn): ?><option value="<?= e($ck) ?>"<?= ($r[$k . '_convocatoria'] ?? 'segunda') === $ck ? ' selected' : '' ?>><?= e($cn) ?></option><?php endforeach; ?></select></label>
        </div>
        <label>Lugar (como va en el acta)<input type="text" name="<?= $k ?>_lugar" maxlength="160" value="<?= $v($k . '_lugar') ?>" placeholder="la Sala de Usos Múltiples del Edificio “C”"></label>
        <label>Consejeros (uno por renglón, con su título)<textarea name="consejeros_<?= $k ?>" rows="6" placeholder="Dra. Nombre Apellidos&#10;Mtro. Nombre Apellidos&#10;C. Nombre del consejero alumno"><?= $v('consejeros_' . $k) ?></textarea></label>
        <label>Actas anteriores que se aprueban (una por renglón)<textarea name="actas_anteriores_<?= $k ?>" rows="3" placeholder="ordinaria del día 9 de octubre de 2026 a las 09:00 horas en segunda convocatoria"><?= $v('actas_anteriores_' . $k) ?></textarea>
          <span class="cns-ayuda">En el acta saldrá: «Se adjuntó el acta de la sesión … y se preguntó si existían observaciones. Acuerdo: Se aprueba el acta.»</span></label>
      </fieldset>
    <?php endforeach; ?>

    <div class="cns-acciones">
      <button class="btn btn-primario" type="submit"><?= $nueva ? 'Crear la reunión' : 'Guardar' ?></button>
      <a class="btn" href="<?= e(url($nueva ? 'consejos' : 'consejos/reunion/' . $r['id'])) ?>">Cancelar</a>
    </div>
  </form>
</div>
