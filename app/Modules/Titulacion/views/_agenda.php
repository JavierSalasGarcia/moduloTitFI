<?php
/** Solicitud de fecha, hora y sínodo del examen. Variables: $t, $etapa, $base, $editable. */
use App\Core\Auth;
use App\Modules\Titulacion\Services\AgendaExamen;
$cfg = AgendaExamen::config();
$espacio = AgendaExamen::espacio();
$minima = AgendaExamen::fechaMinima()->format('Y-m-d');
$borradorAg = $_SESSION['_agenda_borrador'][$t['id']] ?? [];
$listo = $etapa['propia_ok'] && $etapa['faltan'] === 0 && $etapa['rechazados'] === 0 && $etapa['por_firmas_ext'] === 0;
$examen = $etapa['agenda'] === 'previo' ? 'examen previo' : 'examen definitivo';
$etqs = [];
for ($i = 1; $i <= $cfg['propietarios']; $i++) { $etqs[] = 'Propietario · ' . \App\Modules\Titulacion\Services\AgendaExamen::FUNCIONES[\App\Modules\Titulacion\Services\AgendaExamen::funcion($i, (int) $cfg['propietarios'])]; }
for ($i = 1; $i <= $cfg['suplentes']; $i++) { $etqs[] = 'Suplente' . ($cfg['suplentes'] > 1 ? ' ' . $i : ''); }
?>
<h3 id="agenda">Fecha, hora y sínodo de tu <?= e($examen) ?></h3>
<p class="texto-secundario">El examen se realiza en <strong><?= e($espacio['nombre'] ?? 'el auditorio que defina Titulación') ?></strong> y se apartan <?= (int) round($cfg['duracion'] / 60 * 10) / 10 ?> horas. Puedes elegir a partir del <strong><?= e(date('d/m/Y', strtotime($minima))) ?></strong> (<?= (int) $cfg['dias'] ?> días hábiles después de hoy), en horario de <?= e($cfg['inicio']) ?> a <?= e($cfg['fin']) ?> h. Solo puedes apartar el espacio una vez para este examen.</p>
<?php if (!$editable): ?>
<?php elseif (!$listo): ?>
  <p class="tit-aviso">Completa los datos y sube todos los documentos de esta etapa para elegir la fecha.</p>
<?php elseif ($espacio === null): ?>
  <p class="tit-aviso">Titulación aún no configura el auditorio de exámenes. Avisa al Departamento de Evaluación Profesional.</p>
<?php else: ?>
  <form class="formulario" method="post" action="<?= url($base . '/agenda/' . $etapa['clave']) ?>" id="form-agenda" data-horarios="<?= e(url($base . '/agenda/' . $etapa['clave'])) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div class="tit-campos">
      <label>Fecha <input type="date" name="fecha" min="<?= e($minima) ?>" required data-fecha></label>
      <label>Hora de inicio
        <select name="hora" required data-hora><option value="">Elige primero la fecha</option></select></label>
    </div>
    <p class="texto-secundario" style="font-size:13px" data-aviso></p>
    <h4>Tu propuesta de sínodo</h4>
    <p class="texto-secundario" style="font-size:13px">Propón <?= (int) $cfg['propietarios'] ?> sinodales propietarios y <?= (int) $cfg['suplentes'] ?> suplente(s). Busca por nombre o correo; el Coordinador de Docencia de tu licenciatura los ratificará o modificará.</p>
    <?php foreach ($etqs as $i => $et): ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px">
        <label style="flex:1 1 240px"><?= e($et) ?> · nombre
          <input type="text" name="s[<?= $i ?>][nombre]" maxlength="160" required value="<?= e((string) ($borradorAg[$i]['nombre'] ?? '')) ?>" data-prof="ag<?= $i ?>" list="dl-prof-ag<?= $i ?>" autocomplete="off"></label>
        <label style="flex:0 1 110px">Grado
          <input type="text" name="s[<?= $i ?>][grado]" maxlength="30" value="<?= e((string) ($borradorAg[$i]['grado'] ?? '')) ?>" data-prof-grado="ag<?= $i ?>" placeholder="Dr., Mtra."></label>
        <label style="flex:0 1 130px">Género
          <select name="s[<?= $i ?>][genero]" required data-prof-genero="ag<?= $i ?>"><option value="">Elige…</option><option value="masculino" <?= ($borradorAg[$i]['genero'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option><option value="femenino" <?= ($borradorAg[$i]['genero'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option></select></label>
        <label style="flex:1 1 240px">Correo
          <input type="email" name="s[<?= $i ?>][email]" maxlength="120" required value="<?= e((string) ($borradorAg[$i]['email'] ?? '')) ?>" data-prof-correo="ag<?= $i ?>" list="dl-prof-ag<?= $i ?>-c" autocomplete="off"></label>
        <datalist id="dl-prof-ag<?= $i ?>"></datalist><datalist id="dl-prof-ag<?= $i ?>-c"></datalist>
      </div>
    <?php endforeach; ?>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit" onclick="return confirm('¿Enviar la solicitud? El espacio solo puede apartarse una vez para este examen.')">Enviar solicitud al Coordinador de Docencia</button></div>
  </form>
  <script>
  (function () {
    var f = document.getElementById('form-agenda');
    var fecha = f.querySelector('[data-fecha]'), hora = f.querySelector('[data-hora]'), aviso = f.querySelector('[data-aviso]');
    fecha.addEventListener('change', function () {
      hora.innerHTML = '<option value="">Buscando horarios…</option>';
      aviso.textContent = '';
      fetch(f.getAttribute('data-horarios') + '?fecha=' + encodeURIComponent(fecha.value), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          hora.innerHTML = '';
          if (d.error) { aviso.textContent = d.error; }
          if (!d.horas || !d.horas.length) {
            hora.innerHTML = '<option value="">Sin horarios libres ese día</option>';
            if (!d.error) { aviso.textContent = 'El espacio está ocupado ese día; prueba otra fecha.'; }
            return;
          }
          hora.appendChild(new Option('Elige la hora', ''));
          d.horas.forEach(function (h) { hora.appendChild(new Option(h + ' h', h)); });
        })
        .catch(function () { hora.innerHTML = '<option value="">No se pudo consultar</option>'; });
    });
  })();
  </script>
<?php endif; ?>
