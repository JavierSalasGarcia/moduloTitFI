<?php
use App\Core\Auth;
use App\Modules\Espacios\Agenda;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$hoy = date('Y-m-d');
$semanaAnt = $lunes->modify('-7 days')->format('Y-m-d');
$semanaSig = $lunes->modify('+7 days')->format('Y-m-d');
$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$corta = static fn(DateTimeImmutable $d): string => (int) $d->format('j') . ' ' . $meses[(int) $d->format('n')];
?>
<div class="esp-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0">Uso de los auditorios y salas de la Facultad. Registran la ocupación las personas que el administrador del módulo autorizó para cada espacio; los exámenes de titulación se apartan solos.</p>
  </div>
  <?php if ($esAdmin): ?><a class="btn btn-mini" href="<?= url('espacios/admin') ?>">Administrar espacios</a><?php endif; ?>
</div>

<?php if (!$espacios): ?>
  <div class="ux-empty">Aún no hay espacios registrados.</div>
<?php else: ?>
<section class="tarjeta" style="margin-bottom:16px">
  <form method="get" action="<?= url('espacios') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <label>Espacio
      <select name="s" onchange="this.form.submit()">
        <?php foreach ($espacios as $e): ?><option value="<?= (int) $e['id'] ?>" <?= (int) $e['id'] === $sel ? 'selected' : '' ?>><?= e($e['nombre']) ?><?= in_array((int) $e['id'], $autorizados, true) ? ' ✓' : '' ?></option><?php endforeach; ?>
      </select></label>
    <label>Semana del <input type="date" name="semana" value="<?= e($lunes->format('Y-m-d')) ?>" onchange="this.form.submit()"></label>
    <a class="btn btn-mini" href="<?= url('espacios?s=' . $sel . '&semana=' . $semanaAnt) ?>">← Anterior</a>
    <a class="btn btn-mini" href="<?= url('espacios?s=' . $sel . '&semana=' . $hoy) ?>">Hoy</a>
    <a class="btn btn-mini" href="<?= url('espacios?s=' . $sel . '&semana=' . $semanaSig) ?>">Siguiente →</a>
  </form>
  <?php if ($espacio): ?>
    <p class="texto-secundario" style="margin:10px 0 0"><strong><?= e($espacio['nombre']) ?></strong><?= $espacio['ubicacion'] ? ' · ' . e($espacio['ubicacion']) : '' ?><?= $espacio['capacidad'] ? ' · cupo ' . (int) $espacio['capacidad'] : '' ?><?= $espacio['descripcion'] ? ' · ' . e($espacio['descripcion']) : '' ?></p>
  <?php endif; ?>
</section>

<div class="esp-semana" aria-label="Ocupación de la semana">
  <?php for ($i = 0; $i < 7; $i++): $d = $lunes->modify('+' . $i . ' days'); $f = $d->format('Y-m-d'); $hor = $horario[$i + 1] ?? []; ?>
    <div class="esp-dia <?= $f === $hoy ? 'hoy' : '' ?>">
      <h3><?= e(Agenda::DIAS[$i + 1]) ?> <?= e($corta($d)) ?></h3>
      <p class="horario"><?= $hor ? e(implode(', ', array_map(static fn(array $h): string => $h['inicio'] . '–' . $h['fin'], $hor))) : 'Sin servicio' ?></p>
      <?php foreach ($porDia[$f] ?? [] as $r): ?>
        <div class="esp-uso <?= $r['origen'] === 'titulacion' ? 'tit' : '' ?>">
          <b><?= e(substr((string) $r['hora_inicio'], 0, 5)) ?>–<?= e(substr((string) $r['hora_fin'], 0, 5)) ?></b>
          <?= e($r['descripcion']) ?>
          <small>Responsable: <?= e($r['responsable']) ?></small>
          <small>Registró: <?= e($r['registro_nombre']) ?></small>
          <?php if (\App\Modules\Espacios\EspaciosAccess::puedeCancelar($r) && ($r['origen'] !== 'titulacion' || $esAdmin) && $f >= $hoy): ?>
            <form method="post" action="<?= url('espacios/reservas/' . (int) $r['id'] . '/cancelar') ?>" onsubmit="return confirm('¿Cancelar este registro y liberar el horario?')">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Cancelar</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endfor; ?>
</div>

<?php if ($puedeRegistrar): ?>
<section class="tarjeta" style="margin-top:16px">
  <h2>Registrar ocupación de <?= e($espacio['nombre']) ?></h2>
  <form class="formulario" method="post" action="<?= url('espacios/registrar') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="espacio" value="<?= (int) $sel ?>">
    <div class="esp-campos">
      <label>Fecha <input type="date" name="fecha" min="<?= e($hoy) ?>" required value="<?= e(max($hoy, $lunes->format('Y-m-d'))) ?>"></label>
      <label>Hora de inicio <input type="time" name="inicio" step="900" required></label>
      <label>Hora de término <input type="time" name="fin" step="900" required></label>
      <label class="ancho">Descripción del evento <input type="text" name="descripcion" maxlength="500" required></label>
      <label>Responsable del evento (quien lo solicitó)
        <input type="text" name="responsable" maxlength="160" required data-prof="resp" list="dl-prof-resp" autocomplete="off"></label>
      <label>Correo del responsable (opcional)
        <input type="email" name="responsable_email" maxlength="120" data-prof-correo="resp" list="dl-prof-resp-c" autocomplete="off"></label>
      <datalist id="dl-prof-resp"></datalist><datalist id="dl-prof-resp-c"></datalist>
    </div>
    <p class="texto-secundario" style="font-size:13px">Quedará registrado que tú (<?= e((string) (Auth::usuario()['nombre'] ?? '')) ?>) capturaste el evento. El sistema no permite empalmes ni horarios fuera del horario de uso del espacio.</p>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Registrar</button></div>
  </form>
</section>
<script src="<?= url('assets/js/titulacion-profesores.js') ?>" data-url="<?= url('espacios/personas') ?>" defer></script>
<?php elseif ($sel): ?>
  <p class="texto-secundario" style="margin-top:12px">Solo consulta: para registrar la ocupación de este espacio pide autorización a su administrador.</p>
<?php endif; ?>
<?php endif; ?>
