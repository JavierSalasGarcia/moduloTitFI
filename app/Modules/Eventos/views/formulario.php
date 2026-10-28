<?php
use App\Core\Auth;
use App\Modules\Eventos\Clasificacion;
$v = $previo ?? $ev;
$val = static fn(string $k): string => (string) ($v[$k] ?? '');
$dt = static fn(string $k): string => !empty($v[$k]) ? date('Y-m-d\TH:i', strtotime((string) $v[$k])) : '';
$publicos = array_filter(explode(',', $val('publico')));
$progSel = $previo['programas'] ?? $programasEv;
$accion = $ev ? url('eventos/' . (int) $ev['id']) : url('eventos');
$deptoSel = (int) ($v['depto_id'] ?? Auth::departamentoId());
?>
<style>
.card .badge{text-transform:none}.formulario input[type=url]{display:block;width:100%;box-sizing:border-box}
.av-sec{margin-bottom:16px}.av-sec h3{margin:0 0 4px;font-size:15px}.av-sec>p.ayuda-form{margin-top:0}
.av-checks{display:flex;flex-wrap:wrap;gap:6px 16px}.av-checks label{display:flex;gap:6px;align-items:center;font-weight:400;margin:0}
.av-ia{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}
.av-post img{max-width:220px;border-radius:6px;border:1px solid var(--gris-100);display:block;margin-bottom:6px}
</style>
<p><a class="enlace" href="<?= url($ev ? 'eventos/' . (int) $ev['id'] : 'eventos') ?>"><?= icono('flecha-izq', 14) ?> Volver</a></p>
<form method="post" action="<?= $accion ?>" class="formulario" enctype="multipart/form-data" style="max-width:860px" id="form-aviso">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

  <section class="card av-sec">
    <h3>1. El aviso</h3>
    <label>Título <span class="req">*</span><input type="text" name="titulo" required maxlength="180" value="<?= e($val('titulo')) ?>" placeholder="Ej. Conferencia: Liderazgo"></label>
    <label>Descripción <span class="req">*</span><textarea name="descripcion" rows="4" required maxlength="5000" placeholder="Qué es, quién lo imparte y qué debe hacer la persona interesada. Breve."><?= e($val('descripcion')) ?></textarea></label>
    <div class="av-ia"><button type="button" class="btn btn-mini" id="btn-sugerir">Sugerir clasificación</button><small class="ayuda-form" id="sugerir-estado">Llena título y descripción; se proponen tema, formato, público, semestres, modalidad y puntos según la guía. Revísalos antes de publicar.</small></div>
    <div class="form-rejilla">
      <label>Formato<select name="categoria"><?php foreach ($categorias as $k => $n): ?><option value="<?= e($k) ?>" <?= $val('categoria') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Tema<select name="tema"><?php foreach (Clasificacion::TEMAS as $k => $n): ?><option value="<?= e($k) ?>" <?= ($val('tema') ?: 'otro') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <?php if (count($emisores) > 1): ?>
      <label>Publica<select name="depto_id"><?php foreach ($emisores as $em): ?><option value="<?= (int) $em['id'] ?>" <?= (int) $em['id'] === $deptoSel ? 'selected' : '' ?>><?= e($em['nombre']) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
    </div>
  </section>

  <section class="card av-sec">
    <h3>2. A quién va dirigido</h3>
    <p class="ayuda-form">Lo que dejes sin marcar no restringe: sin público marcado llega a toda la comunidad; sin carreras, a todas; sin semestres, a todos.</p>
    <fieldset><legend>Público</legend><div class="av-checks">
      <?php foreach (Clasificacion::PUBLICOS as $k => $n): ?><label><input type="checkbox" name="publico[]" value="<?= e($k) ?>" <?= in_array($k, $publicos, true) ? 'checked' : '' ?>> <?= e($n) ?></label><?php endforeach; ?>
    </div></fieldset>
    <fieldset><legend>Carreras</legend><div class="av-checks">
      <?php foreach ($programas as $p): ?><label title="<?= e($p['nombre']) ?>"><input type="checkbox" name="programas[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], array_map('intval', $progSel), true) ? 'checked' : '' ?>> <?= e($p['siglas']) ?></label><?php endforeach; ?>
    </div></fieldset>
    <div class="form-rejilla">
      <?php foreach (['semestre_min' => 'Desde el semestre', 'semestre_max' => 'Hasta el semestre'] as $k => $n): ?>
      <label><?= $n ?><select name="<?= $k ?>"><option value="">Cualquiera</option><?php for ($i = 1; $i <= Clasificacion::SEMESTRE_MAX; $i++): ?><option value="<?= $i ?>" <?= (int) $val($k) === $i ? 'selected' : '' ?>><?= $i ?>.°</option><?php endfor; ?></select></label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card av-sec">
    <h3>3. Cuándo y dónde</h3>
    <div class="form-rejilla">
      <label>Inicio <span class="req">*</span><input type="datetime-local" name="inicio" required value="<?= $dt('inicio') ?>"></label>
      <label>Fin<input type="datetime-local" name="fin" value="<?= $dt('fin') ?>"></label>
      <label>Fecha límite de registro<input type="datetime-local" name="fecha_limite" value="<?= $dt('fecha_limite') ?>"></label>
      <label>Modalidad<select name="modalidad"><option value="">Sin indicar</option><?php foreach (Clasificacion::MODALIDADES as $k => $n): ?><option value="<?= e($k) ?>" <?= $val('modalidad') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Lugar<input type="text" name="lugar" maxlength="160" value="<?= e($val('lugar')) ?>" placeholder="Auditorio, Edificio B…"></label>
      <label>Organiza o imparte<input type="text" name="organizador" maxlength="200" value="<?= e($val('organizador')) ?>" placeholder="Ponente, empresa o institución"></label>
    </div>
    <label>Enlace del evento (para conectarse o registrarse)<input type="url" name="enlace_evento" maxlength="500" value="<?= e($val('enlace_evento')) ?>" placeholder="https://"></label>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="requiere_registro" value="1" <?= $val('requiere_registro') === '1' ? 'checked' : '' ?>> Requiere registro previo en el enlace</label>
  </section>

  <section class="card av-sec">
    <h3>4. Publicación en Facebook</h3>
    <div class="form-rejilla">
      <label class="av-post">Imagen del post <span class="req">*</span>
        <?php if (!empty($ev['post_imagen'])): ?><img src="<?= url_archivo('eventos/' . (int) $ev['id'] . '/imagen') ?>" alt="Imagen actual del post"><small class="ayuda-form">Sube otra solo si quieres reemplazarla.</small><?php endif; ?>
        <input type="file" name="post_imagen" accept=".jpg,.jpeg,.png,image/jpeg,image/png" <?= empty($ev['post_imagen']) ? 'required' : '' ?>><small class="ayuda-form">JPG o PNG, máximo 5 MB.</small></label>
      <label>Enlace del post <span class="req">*</span><input type="url" name="post_url" maxlength="500" required value="<?= e($val('post_url')) ?>" placeholder="https://www.facebook.com/…"></label>
    </div>
  </section>

  <section class="card av-sec">
    <h3>5. Asistencia y evidencias para acreditación</h3>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="formacion_integral" value="1" id="chk-formacion" <?= $val('formacion_integral') === '1' ? 'checked' : '' ?>> Cuenta para la formación integral: los asistentes registran su asistencia (QR, palabra clave o constancia), suman puntos y queda evidencia por carrera</label>
    <div class="form-rejilla" id="campos-asistencia">
      <label>Horas de formación<input type="number" name="horas" min="0" max="99" step="0.5" value="<?= e($val('horas')) ?>"></label>
      <label>Puntos por asistir<input type="number" name="puntos" min="0" max="50" value="<?= e($val('puntos') !== '' ? $val('puntos') : '10') ?>"></label>
      <label>Palabra clave de la sesión<input type="text" name="clave_asistencia" maxlength="30" value="<?= e($val('clave_asistencia')) ?>" placeholder="Se genera si la dejas vacía"><small class="ayuda-form">Dila al final de la sesión; quien asistió la escribe en Traza FI.</small></label>
    </div>
    <label>Enlace de la grabación (opcional)<input type="url" name="grabacion_url" maxlength="500" value="<?= e($val('grabacion_url')) ?>" placeholder="https://"></label>
    <p class="ayuda-form">Fotografías, listas de asistencia y demás evidencias se suben en la ficha del evento, en «Bitácora de evidencias», también después de publicarlo.</p>
  </section>

  <?php if (!$ev): ?>
  <label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="notificar" value="1" checked> Avisar en su bandeja a quienes siguen este tema y a quienes va dirigido</label>
  <?php endif; ?>
  <div class="acciones-formulario"><button type="submit" class="btn btn-primario"><?= $ev ? 'Guardar cambios' : 'Publicar aviso' ?></button></div>
</form>
<script>
(function () {
  var f = document.getElementById('form-aviso'), chk = document.getElementById('chk-formacion'), campos = document.getElementById('campos-asistencia');
  function asist() { campos.style.display = chk.checked ? '' : 'none'; }
  chk.addEventListener('change', asist); asist();
  var btn = document.getElementById('btn-sugerir'), estado = document.getElementById('sugerir-estado');
  btn.addEventListener('click', function () {
    var datos = new FormData();
    datos.append('_csrf', f._csrf.value); datos.append('titulo', f.titulo.value); datos.append('descripcion', f.descripcion.value);
    if (!f.titulo.value.trim()) { estado.textContent = 'Escribe primero el título y la descripción.'; return; }
    btn.disabled = true; estado.textContent = 'Clasificando…';
    fetch('<?= url('eventos/sugerir') ?>', { method: 'POST', body: datos, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (s) {
        if (!s.ok) { estado.textContent = 'No se pudo sugerir.'; return; }
        f.tema.value = s.tema; f.categoria.value = s.categoria; f.modalidad.value = s.modalidad || '';
        f.semestre_min.value = s.semestre_min || ''; f.semestre_max.value = s.semestre_max || '';
        f.querySelectorAll('input[name="publico[]"]').forEach(function (c) { c.checked = s.publico.indexOf(c.value) >= 0; });
        chk.checked = !!s.formacion_integral; asist(); if (s.puntos) { f.puntos.value = s.puntos; }
        estado.textContent = 'Sugerencia aplicada (' + (s.fuente === 'ia' ? 'IA con la guía' : 'reglas de la guía') + '). Revísala antes de publicar.';
      })
      .catch(function () { estado.textContent = 'No se pudo sugerir.'; })
      .finally(function () { btn.disabled = false; });
  });
})();
</script>
