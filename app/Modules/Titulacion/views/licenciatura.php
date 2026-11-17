<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
$sin = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'sin_trabajo');
$con = array_filter($modalidades, static fn(array $m): bool => $m['tipo'] === 'con_trabajo');
// El trámite abierto (si lo hay) manda: es lo primero que se ofrece.
$abierto = null;
foreach ($mis as $x) {
    if ($x['estado'] === 'abierto') { $abierto = $x; break; }
}
$varios = count($mis) > 1 || ($mis && $abierto === null);

/** Lista de opciones de una vía: nombre, resumen de una línea y el artículo en letra pequeña. */
$listaOpciones = static function (array $grupo): void { ?>
  <ul class="tit-opciones">
    <?php foreach ($grupo as $clave => $m): ?>
      <li><a class="tit-opc" href="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>">
        <strong><?= e($m['nombre']) ?></strong><span><?= e($m['resumen']) ?></span><small><?= e($m['art']) ?></small>
      </a></li>
    <?php endforeach; ?>
  </ul>
<?php };
?>
<?php if ($esPersonal): ?>
<div class="tit-cabecera"><div></div><a class="btn" href="<?= url('titulacion/gestion') ?>">Validar trámites</a></div>
<?php endif; ?>

<?php if ($abierto): $m = $modalidades[$abierto['modalidad']] ?? ['nombre' => $abierto['modalidad']];
  $pct = $abierto['total'] ? (int) round(100 * $abierto['avance'] / $abierto['total']) : 0; ?>
<section class="tit-continua" aria-labelledby="tit-continua-t">
  <h2 id="tit-continua-t">Continúa tu titulación</h2>
  <p class="tit-op"><?= e($m['nombre']) ?> · folio <?= e($abierto['folio']) ?></p>
  <div class="tit-barra-tit" role="progressbar" aria-valuenow="<?= (int) $abierto['avance'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) $abierto['total'] ?>" aria-label="Avance por etapas"><i style="width:<?= $pct ?>%"></i></div>
  <p class="tit-barra-leyenda"><?= (int) $abierto['avance'] ?> de <?= (int) $abierto['total'] ?> etapas completas</p>
  <?php if ($abierto['paso']): ?>
    <p class="tit-ahora">Ahora estás en</p>
    <p class="tit-paso-nombre"><?= e($abierto['paso']['titulo']) ?></p>
    <?php if (!empty($abierto['paso']['paso']['texto'])): ?><p class="tit-paso-texto"><?= e($abierto['paso']['paso']['texto']) ?></p><?php endif; ?>
  <?php endif; ?>
  <a class="tit-boton" href="<?= url('titulacion/licenciatura/tramite/' . (int) $abierto['id']) ?>">Continuar</a>
</section>
<?php endif; ?>

<?php if ($varios): ?>
<details class="tit-pliegue" <?= $abierto === null ? 'open' : '' ?>>
  <summary>Mis trámites (<?= count($mis) ?>)</summary>
  <div class="tit-cuerpo">
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
        <td><span class="estado estado-<?= e($t['estado']) ?>"><?= e(Catalogo::estadosTramite()[$t['estado']] ?? $t['estado']) ?></span></td>
        <td><a class="btn btn-mini" href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>"><?= $t['estado'] === 'abierto' ? 'Continuar' : 'Ver' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  </div>
</details>
<?php endif; ?>

<?php if (!$puedeIniciar): ?>
  <p class="tit-aviso">Solo las cuentas de pasantes pueden iniciar un trámite. Puedes explorar las opciones y sus requisitos.</p>
<?php endif; ?>

<?php if ($abierto === null): ?>
<header class="tit-hero">
  <h2>Titúlate en línea, a tu ritmo</h2>
  <p>Todo el trámite se hace desde aquí, paso a paso.</p>
  <ul class="tit-ideas">
    <li>Capturas tus datos una sola vez.</li>
    <li>Los formatos salen llenos.</li>
    <li>Te avisamos qué sigue.</li>
  </ul>
</header>
<?php endif; ?>

<?php if ($abierto !== null): ?>
<details class="tit-pliegue">
  <summary>Ver las opciones de titulación</summary>
  <div class="tit-cuerpo">
<?php else: ?>
<h2 class="tit-pregunta">¿Cómo quieres titularte?</h2>
<?php endif; ?>
<div class="tit-vias">
  <details class="tit-via" name="tit-via">
    <summary>
      <span class="tit-via-titulo">Sin trabajo escrito</span>
      <span class="tit-via-frase">Tomas protesta ante un jurado, sin redactar una tesis. Suele ser la vía más corta.</span>
      <span class="tit-via-n"><?= count($sin) ?> opciones</span>
    </summary>
    <?php $listaOpciones($sin); ?>
  </details>
  <details class="tit-via" name="tit-via">
    <summary>
      <span class="tit-via-titulo">Con trabajo escrito</span>
      <span class="tit-via-frase">Desarrollas un trabajo con un asesor y lo defiendes ante un jurado. Toma más tiempo.</span>
      <span class="tit-via-n"><?= count($con) ?> opciones</span>
    </summary>
    <?php $listaOpciones($con); ?>
  </details>
</div>
<?php if ($abierto !== null): ?>
  </div>
</details>
<?php endif; ?>

<details class="tit-pliegue tit-faq">
  <summary>Dudas frecuentes</summary>
  <div class="tit-cuerpo">
    <h3 style="margin-top:0">Antes de empezar</h3>
    <ul class="tit-lista-simple">
      <li>El plazo para presentar la evaluación profesional es de dos veces la duración del plan de estudios desde tu primera inscripción (Art. 5, REP).</li>
      <li>Los costos y requisitos pueden cambiar según lo determine la UAEM. El sistema muestra los vigentes (v082025).</li>
      <li>Atención presencial: Apoyo a Titulación, de 9:00 a 15:00 y de 16:00 a 17:30 h, de lunes a viernes. Correo: <a href="mailto:titulacion_fi@uaemex.mx">titulacion_fi@uaemex.mx</a>.</li>
      <li><a href="<?= url('titulacion/licenciatura/blanco/rep') ?>">Reglamento de Evaluación Profesional</a> · <a href="<?= url('titulacion/licenciatura/blanco/privacidad') ?>">Aviso de privacidad</a></li>
    </ul>
  </div>
</details>
