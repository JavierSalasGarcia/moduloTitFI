<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$modalidades = array_map(static fn(array $m): string => $m['nombre'], array_filter(Catalogo::modalidades(), static fn(array $m): bool => $m['tipo'] === 'con_trabajo'));
$opcCarrera = static function () use ($carreras): string {
    $h = '<option value="" selected disabled>Elige…</option>';
    foreach ($carreras as $k => $n) { $h .= '<option value="' . e($k) . '">' . e($n) . '</option>'; }
    return $h;
};
?>
<style>
.cap-rejilla{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
.cap-rejilla label{display:flex;flex-direction:column;gap:3px;font-size:13px}
.cap-rejilla .ancho{grid-column:1/-1}
.cap-grupo{border:1px solid var(--gris-100,#ddd);border-radius:8px;padding:10px;margin:10px 0}
.cap-grupo legend{font-size:13px;font-weight:600;padding:0 6px}
</style>

<div class="tit-cabecera"><div>
  <p class="texto-secundario">Para exámenes y protocolos revisados <strong>fuera del sistema</strong> (en papel), mientras se adopta el flujo de la plataforma. Cada captura queda en el <a href="<?= url('titulacion/constancias/historial') ?>">historial</a> o en la lista de abajo y, al generar sus constancias, pasa a la cola de firmas igual que cualquier otra. Solo el nombre del profesor es obligatorio: su correo se busca solo en el directorio. Los trámites hechos en la plataforma no se capturan aquí; sus constancias se generan solas.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:14px">
  <h2>Examen profesional realizado fuera del sistema</h2>
  <form method="post" action="<?= url('titulacion/constancias/captura/examen') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="cap-rejilla">
      <label>Licenciatura <select name="carrera" required><?= $opcCarrera() ?></select></label>
      <label>Alumno (apellidos y nombre) <input name="alumno" maxlength="160" required></label>
      <label>Cuenta <input name="cuenta" maxlength="12" inputmode="numeric"></label>
      <label>Acta <input name="acta" inputmode="numeric" pattern="[0-9]*" maxlength="8"></label>
      <label>Fecha del examen <input type="date" name="fecha_examen" required max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Aprobación del protocolo <input type="date" name="fecha_protocolo" max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Modalidad <select name="modalidad"><option value="">—</option><?php foreach ($modalidades as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label class="ancho">Título del trabajo <input name="titulo" maxlength="500" required></label>
    </div>
    <fieldset class="cap-grupo"><legend>Director, codirector y sinodales del acta</legend>
      <div class="cap-rejilla">
        <?php foreach (['director' => 'Director de tesis', 'codirector' => 'Codirector de tesis', 'presidente' => 'Presidente', 'secretario' => 'Secretario', 'vocal' => 'Vocal'] as $k => $n): ?>
          <label><?= e($n) ?> <input name="<?= e($k) ?>" maxlength="160" placeholder="Nombre con grado, p. ej. Dr. Juan Pérez"></label>
          <label>Correo (opcional) <input type="email" name="<?= e($k) ?>_email" maxlength="120"></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label style="display:flex;gap:6px;align-items:center;margin:8px 0"><input type="checkbox" name="generar" value="1" checked> Generar las constancias al guardar (si el registro está completo)</label>
    <button class="btn btn-primario" type="submit">Guardar examen</button>
  </form>
</section>

<section class="tarjeta" style="margin-bottom:14px">
  <h2>Protocolo revisado fuera del sistema</h2>
  <form method="post" action="<?= url('titulacion/constancias/captura/protocolo') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="cap-rejilla">
      <label>Licenciatura <select name="carrera" required><?= $opcCarrera() ?></select></label>
      <label>Alumno (apellidos y nombre) <input name="alumno" maxlength="160" required></label>
      <label>Cuenta <input name="cuenta" maxlength="12" inputmode="numeric"></label>
      <label>Director de tesis <input name="director" maxlength="160"></label>
      <label>Turnado a revisión el <input type="date" name="fecha_inicio" max="<?= e(date('Y-m-d')) ?>"></label>
      <label>Aprobado el <input type="date" name="fecha_aprobacion" required max="<?= e(date('Y-m-d')) ?>"></label>
      <label class="ancho">Título del protocolo <input name="titulo" maxlength="500" required></label>
    </div>
    <fieldset class="cap-grupo"><legend>Revisores del protocolo (cada uno recibe su constancia)</legend>
      <div class="cap-rejilla">
        <?php for ($i = 1; $i <= 3; $i++): ?>
          <label>Revisor <?= $i ?> <input name="revisor<?= $i ?>" maxlength="160" <?= $i === 1 ? 'required' : '' ?> placeholder="Nombre con grado"></label>
          <label>Correo (opcional) <input type="email" name="revisor<?= $i ?>_email" maxlength="120"></label>
        <?php endfor; ?>
      </div>
    </fieldset>
    <label style="display:flex;gap:6px;align-items:center;margin:8px 0"><input type="checkbox" name="generar" value="1" checked> Generar las constancias al guardar</label>
    <button class="btn btn-primario" type="submit">Guardar protocolo</button>
  </form>
</section>

<section class="tarjeta">
  <h2>Protocolos capturados</h2>
  <?php if (!$protocolos): ?><div class="ux-empty">Aún no se captura ningún protocolo.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla" data-no-tools>
    <thead><tr><th>Aprobado</th><th>Lic.</th><th>Alumno</th><th>Título</th><th>Revisores</th><th>Constancias</th></tr></thead>
    <tbody>
    <?php foreach ($protocolos as $p): ?>
      <tr>
        <td><?= e((string) $p['fecha_aprobacion']) ?></td>
        <td><?= e(Catalogo::carreras()[$p['carrera']]['siglas'][0] ?? $p['carrera']) ?></td>
        <td><?= e($p['alumno']) ?></td>
        <td><?= e($p['titulo']) ?></td>
        <td><?= e(implode('; ', array_filter([$p['revisor1'], $p['revisor2'], $p['revisor3']]))) ?></td>
        <td style="white-space:nowrap">
          <?php if ($p['generado_en']): ?><span class="tit-chip ok">Generadas</span>
          <?php else: ?>
            <form method="post" action="<?= url('titulacion/constancias/captura/protocolo/' . (int) $p['id'] . '/generar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-primario" type="submit">Generar</button></form>
            <form method="post" action="<?= url('titulacion/constancias/captura/protocolo/' . (int) $p['id'] . '/eliminar') ?>" style="display:inline" onsubmit="return confirm('¿Eliminar este registro?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Eliminar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
