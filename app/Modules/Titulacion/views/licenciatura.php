<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$sin = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'sin_trabajo');
$con = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'con_trabajo');
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">Elige tu opción de evaluación profesional. El sistema te guía paso a paso, llena tus formatos y valida tus documentos en línea.</p>
  </div>
  <?php if ($esPersonal): ?><a class="btn" href="<?= url('titulacion/gestion') ?>">Validar trámites</a><?php endif; ?>
</div>

<?php if ($mis): ?>
<section class="tarjeta" style="margin-bottom:18px">
  <h2>Mis trámites</h2>
  <div class="tabla-contenedor">
  <table class="tabla">
    <thead><tr><th>Folio</th><th>Opción</th><th>Avance</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($mis as $t): $m = $modalidades[$t['modalidad']] ?? ['nombre' => $t['modalidad']]; ?>
      <tr>
        <td><?= e($t['folio']) ?></td>
        <td><?= e($m['nombre']) ?><?php if ($t['titulo']): ?><br><span class="texto-secundario"><?= e(mb_strimwidth($t['titulo'], 0, 90, '…')) ?></span><?php endif; ?></td>
        <td style="min-width:150px"><div class="tit-barra"><i style="width:<?= $t['total'] ? (int) round(100 * $t['avance'] / $t['total']) : 0 ?>%"></i></div>
          <small><?= (int) $t['avance'] ?> de <?= (int) $t['total'] ?> etapas<?= $t['paso'] ? ' · ahora: ' . e($t['paso']['titulo']) : '' ?></small></td>
        <td><span class="estado estado-<?= e($t['estado']) ?>"><?= e(\App\Modules\Titulacion\Catalogo::estadosTramite()[$t['estado']] ?? $t['estado']) ?></span></td>
        <td><a class="btn btn-mini" href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>"><?= $t['estado'] === 'abierto' ? 'Continuar' : 'Ver' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>

<?php if (!$puedeIniciar): ?>
  <p class="tit-aviso">Solo las cuentas de pasantes pueden iniciar un trámite. Puedes explorar las opciones y sus requisitos.</p>
<?php endif; ?>

<section class="tarjeta" style="margin-bottom:18px">
  <h2>Sin trabajo escrito</h2>
  <p class="texto-secundario">Consiste en tomar protesta ante un jurado, sin desarrollar un trabajo escrito.</p>
  <div class="tit-cuadricula">
    <?php foreach ($sin as $clave => $m): ?>
      <a class="tit-opcion" href="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>">
        <strong><?= e($m['nombre']) ?></strong><span><?= e($m['resumen']) ?></span><small><?= e($m['art']) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="tarjeta" style="margin-bottom:18px">
  <h2>Con trabajo escrito</h2>
  <p class="texto-secundario">Desarrollas un trabajo escrito con un asesor y lo sustentas ante un jurado. Incluye registro del protocolo, revisión previa y sustentación.</p>
  <div class="tit-cuadricula">
    <?php foreach ($con as $clave => $m): ?>
      <a class="tit-opcion" href="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>">
        <strong><?= e($m['nombre']) ?></strong><span><?= e($m['resumen']) ?></span><small><?= e($m['art']) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="tarjeta">
  <h2>Antes de empezar</h2>
  <ul class="tit-lista-simple">
    <li>El plazo para presentar la evaluación profesional es de dos veces la duración del plan de estudios desde tu primera inscripción (Art. 5, REP).</li>
    <li>Los costos y requisitos pueden cambiar según lo determine la UAEM. El sistema muestra los vigentes (v082025).</li>
    <li>Atención presencial: Apoyo a Titulación, de 9:00 a 15:00 y de 16:00 a 17:30 h, de lunes a viernes. Correo: <a href="mailto:titulacion_fi@uaemex.mx">titulacion_fi@uaemex.mx</a>.</li>
    <li><a href="<?= url('titulacion/licenciatura/blanco/rep') ?>">Reglamento de Evaluación Profesional</a> · <a href="<?= url('titulacion/licenciatura/blanco/privacidad') ?>">Aviso de privacidad</a></li>
  </ul>
</section>
