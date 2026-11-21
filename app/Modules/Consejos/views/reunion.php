<?php
/** @var array $r @var array $asuntos @var array $actaFirmada @var int $oficios */
use App\Core\Auth;
use App\Modules\Consejos\Services\Html;
use App\Modules\Consejos\Services\Texto;
include __DIR__ . '/_estilos.php';
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$fe = static fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : 'sin fecha';
$grupos = ['profesor' => 'Casos de profesores', 'alumno' => 'Casos de alumnos', 'general' => 'Asuntos generales'];
$porGrupo = array_fill_keys(array_keys($grupos), []);
foreach ($asuntos as $a) { $porGrupo[$a['grupo']][] = $a; }
$aval = ['pendiente' => ['Pendiente', ''], 'avala' => ['Avala', 'ok'], 'no_avala' => ['No avala', 'no']];
$aprob = ['pendiente' => ['Pendiente', ''], 'aprueba' => ['Aprueba', 'ok'], 'no_aprueba' => ['No aprueba', 'no']];
$sesion = static function (string $k, string $nombre, array $r, ?array $firmada) use ($csrf, $fe): void {
    $inicioListo = $k === 'academico' || !empty($r['academico_fin']);
    $fin = $r[$k . '_fin'] ?? null;
    $enCurso = !$fin && (($k === 'academico' && $r['estado'] !== 'preparacion') || ($k === 'gobierno' && in_array($r['estado'], ['gobierno', 'cerrada'], true)));
    ?>
    <div class="cns-paso<?= $fin ? ' hecho' : '' ?>">
      <h3><?= $k === 'academico' ? '2' : '3' ?>. Consejo <?= e($nombre) ?></h3>
      <p><?= e($fe($r[$k . '_fecha'])) ?><?= $r[$k . '_hora'] ? ' · ' . e(substr((string) $r[$k . '_hora'], 0, 5)) . ' h' : '' ?>
        <?php if ($fin): ?><br><strong>Terminó el <?= e(date('d/m/Y', strtotime($fin))) ?> a las <?= e(date('H:i', strtotime($fin))) ?> h</strong><?php endif; ?></p>
      <div class="cns-acciones">
        <?php if (!$inicioListo): ?>
          <span class="cns-ayuda">Disponible cuando termine el Consejo Académico.</span>
        <?php elseif (!$enCurso && !$fin): ?>
          <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/sesion/' . $k . '/iniciar')) ?>"><?= $csrf ?><button class="btn btn-primario btn-mini" type="submit">Iniciar la sesión</button></form>
        <?php else: ?>
          <a class="btn btn-primario btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/sesion/' . $k)) ?>"><?= $fin ? 'Ver la sesión' : 'Entrar a la sesión' ?></a>
        <?php endif; ?>
        <?php if ($fin): ?>
          <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/sesion/' . $k . '/reabrir')) ?>" data-confirm="¿Reabrir la sesión para corregir los acuerdos?"><?= $csrf ?><button class="btn btn-mini" type="submit">Reabrir</button></form>
        <?php endif; ?>
      </div>
      <div class="cns-acciones">
        <a class="btn btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/acta/' . $k)) ?>">Acta en Word</a>
      </div>
      <form method="post" enctype="multipart/form-data" action="<?= e(url('consejos/reunion/' . $r['id'] . '/acta/' . $k . '/firmada')) ?>" class="cns-acciones">
        <?= $csrf ?>
        <label class="cns-ayuda" style="margin:0">Acta firmada y sellada (PDF)<br><input type="file" name="archivo" accept="application/pdf" required></label>
        <button class="btn btn-mini" type="submit"><?= $firmada ? 'Reemplazar' : 'Subir' ?></button>
      </form>
      <?php if ($firmada): ?>
        <p><a href="<?= e(url('consejos/archivo/' . $firmada['id'])) ?>" target="_blank" rel="noopener">📄 <?= e($firmada['nombre']) ?></a>
          <form method="post" action="<?= e(url('consejos/archivo/' . $firmada['id'] . '/quitar')) ?>" style="display:inline" data-confirm="¿Retirar el archivo firmado?"><?= $csrf ?><button class="btn btn-mini" type="submit">Quitar</button></form></p>
      <?php endif; ?>
      <?php if ($enCurso && !$fin): ?>
        <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/sesion/' . $k . '/terminar')) ?>" class="cns-acciones" style="border-top:1px solid var(--gris-100);padding-top:8px" data-confirm="¿Terminar la sesión del Consejo <?= e($nombre) ?>? Después de esto los acuerdos ya no se editan sin reabrirla.">
          <?= $csrf ?>
          <label class="cns-ayuda" style="margin:0">Terminó el<br><input type="date" name="fecha" value="<?= e((string) ($r[$k . '_fecha'] ?: date('Y-m-d'))) ?>"></label>
          <label class="cns-ayuda" style="margin:0">a las<br><input type="time" name="hora" value="<?= e(date('H:i')) ?>" required></label>
          <button class="btn btn-mini" type="submit">Terminar sesión</button>
        </form>
      <?php endif; ?>
    </div>
    <?php
};
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos')) ?>">← Todas las reuniones</a></p>
        <h1><?= e($r['etiqueta']) ?> <span class="cns-etq"><?= e(ucfirst($r['tipo_sesion'])) ?></span></h1>
      </div>
      <div class="cns-acciones">
        <a class="btn btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/editar')) ?>">Datos de la reunión</a>
        <?php if ($r['estado'] === 'preparacion'): ?>
          <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar la reunión y todos sus casos? No se puede deshacer."><?= $csrf ?><button class="btn btn-mini btn-peligro" type="submit">Eliminar</button></form>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <div class="cns-pasos">
    <div class="cns-paso<?= $asuntos ? ' hecho' : '' ?>">
      <h3>1. Casos</h3>
      <p><?= count($porGrupo['profesor']) ?> de profesores · <?= count($porGrupo['alumno']) ?> de alumnos · <?= count($porGrupo['general']) ?> generales</p>
      <div class="cns-acciones">
        <a class="btn btn-primario btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=profesor')) ?>">+ Profesor</a>
        <a class="btn btn-primario btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=alumno')) ?>">+ Alumno</a>
        <a class="btn btn-primario btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=general')) ?>">+ General</a>
      </div>
    </div>
    <?php $sesion('academico', 'Académico', $r, $actaFirmada['academico']); ?>
    <?php $sesion('gobierno', 'de Gobierno', $r, $actaFirmada['gobierno']); ?>
    <div class="cns-paso<?= $oficios ? ' hecho' : '' ?>">
      <h3>4. Dictámenes</h3>
      <p><?= $oficios ? $oficios . ' oficios generados.' : 'Se generan con los acuerdos del Consejo de Gobierno.' ?></p>
      <div class="cns-acciones"><a class="btn btn-primario btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/dictamenes')) ?>">Abrir dictámenes</a></div>
    </div>
  </div>

  <details class="tarjeta">
    <summary style="cursor:pointer;font-weight:700;color:var(--verde-900)">Importar casos desde un acta de Word o un Excel</summary>
    <p class="cns-sub" style="margin:8px 0">Un caso que ya existe (misma clave) no se duplica ni se pisa: solo se completa lo que le falta. Así puedes importar el acta del Consejo Académico y luego la del de Gobierno. Del Excel se leen clave, solicitud, antecedentes, acuerdos y observación; <a href="<?= e(url('consejos/plantilla-casos')) ?>">descarga la plantilla</a>.</p>
    <form class="cns-form cns-acciones" method="post" enctype="multipart/form-data" action="<?= e(url('consejos/reunion/' . $r['id'] . '/importar')) ?>" style="align-items:flex-end">
      <?= $csrf ?>
      <label style="margin:0">Archivo (.docx o .xlsx)<input type="file" name="archivo" accept=".docx,.xlsx" required></label>
      <button class="btn btn-primario" type="submit">Importar</button>
    </form>
  </details>

  <?php if (!empty($r['gobierno_fin'])): ?>
  <section class="tarjeta cns-acciones" style="justify-content:space-between">
    <span>La sesión del Consejo de Gobierno terminó: los profesores ya ven sus comisiones.</span>
    <form method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/avisar')) ?>" data-confirm="¿Avisar por correo a los profesores mencionados en los casos de esta reunión?"><?= $csrf ?><button class="btn" type="submit">✉️ Avisar a los profesores mencionados</button></form>
  </section>
  <?php endif; ?>

  <?php foreach ($grupos as $g => $titulo): ?>
    <section class="tarjeta">
      <div class="cns-cab" style="margin-bottom:8px">
        <h2><?= e($titulo) ?></h2>
        <a class="btn btn-mini" href="<?= e(url('consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=' . $g)) ?>">+ Agregar</a>
      </div>
      <?php if (!$porGrupo[$g]): ?>
        <p class="cns-sub">Sin casos todavía.</p>
      <?php else: ?>
        <table class="cns-tabla">
          <thead><tr><th>Clave</th><th>Solicitud</th><th>Profesores</th><th>Consejo Académico</th><th>Consejo de Gobierno</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($porGrupo[$g] as $a): ?>
            <tr>
              <td class="cns-clave"><?= e(Texto::clave($a['grupo'], (int) $a['numero'])) ?></td>
              <td><?= e(mb_strimwidth(Texto::limpio(Html::texto((string) $a['solicitud_html'])), 0, 170, '…')) ?></td>
              <td><?= (int) $a['profesores'] ?: '—' ?></td>
              <td><span class="cns-etq <?= e($aval[$a['aval_academico']][1]) ?>"><?= e($aval[$a['aval_academico']][0]) ?></span></td>
              <td><span class="cns-etq <?= e($aprob[$a['aprob_gobierno']][1]) ?>"><?= e($aprob[$a['aprob_gobierno']][0]) ?></span></td>
              <td class="cns-acciones">
                <a class="btn btn-mini" href="<?= e(url('consejos/asunto/' . $a['id'] . '/editar')) ?>">Editar</a>
                <form method="post" action="<?= e(url('consejos/asunto/' . $a['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar el caso <?= e(Texto::clave($a['grupo'], (int) $a['numero'])) ?>?"><?= $csrf ?><button class="btn btn-mini btn-peligro" type="submit">Quitar</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
