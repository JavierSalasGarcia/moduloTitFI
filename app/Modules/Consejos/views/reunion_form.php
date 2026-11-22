<?php
/** @var array $r @var bool $nueva */
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$v = static fn(string $c) => e((string) ($r[$c] ?? ''));
$hora = static fn(string $c) => e(substr((string) ($r[$c] ?? ''), 0, 5));
$urlPersonas = url('consejos/personas') . '?alumnos=1';
$lineas = static fn(string $c): array => array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($r[$c] ?? '')) ?: []), 'strlen'));
$selector = static function (string $campo, array $seleccion, int $max, string $etiqueta, string $ph) use ($urlPersonas): void {
    $url = $urlPersonas; $valor = 'nombre'; $libre = true; $placeholder = $ph;
    include dirname(__DIR__, 3) . '/Views/selector_personas.php';
};
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
      <div><span class="cns-etiq">Presidente de los Consejos</span><?php $selector('presidente', $lineas('presidente'), 1, 'Presidente de los Consejos', 'Escribe su nombre o correo…'); ?></div>
      <div><span class="cns-etiq">Secretario de los Consejos</span><?php $selector('secretario', $lineas('secretario'), 1, 'Secretario de los Consejos', 'Escribe su nombre o correo…'); ?></div>
    </div>

    <?php foreach (['academico' => 'Consejo Académico', 'gobierno' => 'Consejo de Gobierno'] as $k => $nombre): ?>
      <fieldset style="border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px 14px;margin:0 0 14px">
        <legend style="font-weight:800;color:var(--verde-900);padding:0 6px"><?= e($nombre) ?></legend>
        <div class="cns-fila">
          <label>Fecha de la sesión<input type="date" name="<?= $k ?>_fecha" value="<?= $v($k . '_fecha') ?>"></label>
          <label><span data-etq-hora><?= $r['tipo_sesion'] === 'extraordinaria' ? 'Hora de la convocatoria única' : 'Hora de la segunda convocatoria' ?></span><input type="time" name="<?= $k ?>_hora" value="<?= $hora($k . '_hora') ?>" data-hora-conv><span class="cns-ayuda" data-ayuda-conv>Ordinaria: la primera convocatoria se anota media hora antes. Extraordinaria: solo hay una convocatoria.</span></label>
        </div>
        <label>Lugar (como va en el acta)<input type="text" name="<?= $k ?>_lugar" maxlength="160" value="<?= $v($k . '_lugar') ?>" placeholder="la Sala de Usos Múltiples del Edificio “C”"></label>
        <span class="cns-etiq">Consejeros presentes</span>
        <span class="cns-ayuda">Escribe parte del nombre o del correo y elige de la lista; se agregan uno por uno y se quitan con la «×». Si alguien no tiene cuenta, escribe su nombre completo (con título) y pulsa Enter.</span>
        <?php $selector('consejeros_' . $k . '[]', array_map(static fn($n) => ['nombre' => $n], $lineas('consejeros_' . $k)), 0, 'Consejeros del ' . $nombre, 'Escribe un nombre o correo…'); ?>
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
<script>
(function () {
  var t = document.querySelector('select[name=tipo_sesion]');
  if (!t) return;
  var pon = function () { document.querySelectorAll('[data-etq-hora]').forEach(function (e) { e.textContent = t.value === 'extraordinaria' ? 'Hora de la convocatoria única' : 'Hora de la segunda convocatoria'; }); };
  t.addEventListener('change', pon);
})();
</script>
