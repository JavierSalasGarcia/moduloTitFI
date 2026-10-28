<?php
use App\Core\Auth;
use App\Modules\Eventos\Asistencias;
use App\Modules\Eventos\Clasificacion;
$csrf = e(Auth::csrfToken());
$base = 'eventos/' . (int) $ev['id'];
$estados = ['validada' => ['Validada', 'badge-verde'], 'pendiente' => ['En revisión', 'badge-oro'], 'rechazada' => ['Rechazada', '']];
$metodos = ['qr' => 'QR', 'clave' => 'Palabra clave', 'constancia' => 'Constancia'];
?>
<style>
.card .badge{text-transform:none}.formulario input[type=url]{display:block;width:100%;box-sizing:border-box}
.ev-post{float:right;max-width:240px;margin:0 0 12px 16px}.ev-post img{width:100%;border-radius:8px;border:1px solid var(--gris-100)}
.ev-datos{display:grid;grid-template-columns:max-content 1fr;gap:4px 12px;font-size:13.5px;margin:10px 0}.ev-datos dt{font-weight:600}.ev-datos dd{margin:0}
.ev-caja{border-top:1px solid var(--gris-100);margin-top:16px;padding-top:12px}
@media(max-width:640px){.ev-post{float:none;max-width:100%;margin:0 0 12px}.ev-datos{grid-template-columns:1fr}}
</style>
<p><a class="enlace" href="<?= url($concluido ? 'eventos/biblioteca' : 'eventos') ?>"><?= icono('flecha-izq', 14) ?> Volver</a></p>

<section class="card" style="max-width:860px">
  <header class="tarjeta-cabecera columna-movil">
    <div>
      <h2><?= e($ev['titulo']) ?></h2>
      <small class="texto-suave">Publica: <?= e($ev['depto']) ?> · <?= e($ev['autor']) ?></small>
    </div>
    <div class="grupo-chips">
      <span class="badge badge-oro"><?= e($categorias[$ev['categoria']] ?? 'Otros') ?></span>
      <span class="badge"><?= e(Clasificacion::TEMAS[$ev['tema']] ?? 'Otros') ?></span>
      <?php if ($ev['modalidad']): ?><span class="badge"><?= e(Clasificacion::MODALIDADES[$ev['modalidad']]) ?></span><?php endif; ?>
      <?php if ($concluido): ?><span class="chip estado-cerrada">concluido</span><?php endif; ?>
    </div>
  </header>

  <?php if ($ev['post_imagen']): ?>
    <a class="ev-post" href="<?= e((string) ($ev['post_url'] ?: '#')) ?>" target="_blank" rel="noopener noreferrer"><img src="<?= url_archivo($base . '/imagen') ?>" alt="Imagen del aviso en Facebook"></a>
  <?php endif; ?>

  <dl class="ev-datos">
    <dt>Cuándo</dt><dd><?= e(fecha_corta($ev['inicio'])) ?><?= $ev['fin'] ? ' — ' . e(fecha_corta($ev['fin'])) : '' ?></dd>
    <?php if ($ev['fecha_limite']): ?><dt>Fecha límite</dt><dd><strong><?= e(fecha_corta($ev['fecha_limite'])) ?></strong></dd><?php endif; ?>
    <?php if ($ev['lugar']): ?><dt>Dónde</dt><dd><?= e($ev['lugar']) ?></dd><?php endif; ?>
    <?php if ($ev['organizador']): ?><dt>Organiza</dt><dd><?= e($ev['organizador']) ?></dd><?php endif; ?>
    <dt>Dirigido a</dt><dd><?= e(Clasificacion::destino($ev, $siglas)) ?></dd>
    <?php if ($ev['formacion_integral']): ?><dt>Formación integral</dt><dd><?= $ev['horas'] ? e((string) (float) $ev['horas']) . ' h · ' : '' ?>+<?= (int) $ev['puntos'] ?> puntos por asistir</dd><?php endif; ?>
  </dl>
  <div class="grupo-chips" style="margin-bottom:10px">
    <?php if ($ev['enlace_evento']): ?><a class="btn btn-primario btn-mini" href="<?= e($ev['enlace_evento']) ?>" target="_blank" rel="noopener noreferrer"><?= $ev['requiere_registro'] ? 'Registrarme' : ($ev['modalidad'] === 'presencial' ? 'Más información' : 'Conectarme') ?></a><?php endif; ?>
    <?php if ($ev['post_url']): ?><a class="btn btn-mini" href="<?= e($ev['post_url']) ?>" target="_blank" rel="noopener noreferrer">Ver post en Facebook</a><?php endif; ?>
    <?php if ($ev['grabacion_url']): ?><a class="btn btn-mini" href="<?= e($ev['grabacion_url']) ?>" target="_blank" rel="noopener noreferrer">Ver grabación</a><?php endif; ?>
  </div>

  <p class="descripcion-solicitud"><?= nl2br(e($ev['descripcion'])) ?></p>

  <?php
  $paras = array_filter($destinatarios, fn($d) => $d['tipo'] === 'para');
  $ccs = array_filter($destinatarios, fn($d) => $d['tipo'] === 'cc');
  foreach (['Para' => $paras, 'CC' => $ccs] as $et => $lista): if (!$lista) { continue; } ?>
    <p style="font-size:12px;margin:6px 0"><strong><?= $et ?>:</strong> <?php foreach ($lista as $d): ?><span class="badge"><?= $d['destinatario_type'] === 'departamento' ? '🏢' : '👤' ?> <?= e($d['destinatario_nombre']) ?></span> <?php endforeach; ?></p>
  <?php endforeach; ?>

  <?php if (!$concluido): ?>
    <div style="display:flex;gap:8px;align-items:center;margin-top:15px;flex-wrap:wrap;clear:both">
      <form method="post" action="<?= url($base . '/suscribir') ?>" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="volver" value="<?= e($base) ?>">
        <button type="submit" class="btn <?= $ev['suscrito'] ? '' : 'btn-primario' ?>"><?= $ev['suscrito'] ? '✓ Quitar de mi calendario' : '+ Agregar a mi calendario' ?></button>
      </form>
      <a href="<?= url_archivo($base . '/exportar-ics') ?>" class="btn">📅 Exportar a Teams / Outlook (.ics)</a>
    </div>
  <?php endif; ?>

  <?php if (Asistencias::aplica($ev)): ?>
  <div class="ev-caja" id="mi-asistencia" style="clear:both">
    <h3 style="margin:0 0 6px">Mi asistencia</h3>
    <?php if ($asistencia): [$et, $cl] = $estados[$asistencia['estado']]; ?>
      <p><span class="badge <?= $cl ?>"><?= $et ?></span> Folio <a href="<?= url('verificar/asistencia/' . $asistencia['folio']) ?>" target="_blank"><strong><?= e($asistencia['folio']) ?></strong></a>
        <?= $asistencia['estado'] === 'validada' ? '· +' . (int) $asistencia['puntos'] . ' puntos' : '' ?>
        <?= $asistencia['estado'] === 'rechazada' && $asistencia['motivo'] ? '· ' . e($asistencia['motivo']) : '' ?></p>
      <p class="ayuda-form">Comparte el enlace del folio con tu profesor como comprobante. Todo queda en <a href="<?= url('eventos/expediente') ?>">tu expediente</a>.</p>
    <?php else: ?>
      <p class="ayuda-form">Registra tu asistencia para sumar puntos y tener un comprobante verificable para tu profesor y tu CV.</p>
    <?php endif; ?>
    <?php if ((!$asistencia || $asistencia['estado'] !== 'validada') && Asistencias::ventanaAbierta($ev)): ?>
      <form method="post" action="<?= url($base . '/asistencia') ?>" class="ux-toolbar" style="margin:8px 0">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="modo" value="clave">
        <label>Palabra clave de la sesión <input type="text" name="clave" maxlength="30" required autocomplete="off" style="text-transform:uppercase"></label>
        <button class="btn btn-primario btn-mini" type="submit">Registrar asistencia</button>
      </form>
    <?php endif; ?>
    <?php if (Asistencias::constanciaPermitida($ev) && count($asistencia['archivos'] ?? []) < Asistencias::MAX_ARCHIVOS && ($asistencia['estado'] ?? '') !== 'validada'): ?>
      <details style="margin-top:6px"><summary>¿No tienes la palabra clave? Sube tu constancia o capturas de pantalla</summary>
        <form method="post" action="<?= url($base . '/asistencia') ?>" enctype="multipart/form-data" class="formulario" style="margin-top:8px">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="modo" value="constancia">
          <label>Constancia o capturas (PDF, JPG o PNG; hasta <?= Asistencias::MAX_ARCHIVOS ?>)<input type="file" name="archivos[]" multiple required accept=".pdf,.jpg,.jpeg,.png"></label>
          <button class="btn btn-mini" type="submit">Enviar a revisión</button>
        </form>
      </details>
    <?php endif; ?>
    <?php foreach ($asistencia['archivos'] ?? [] as $i => $f): ?><a class="btn btn-mini" href="<?= url_archivo('eventos/asistencia/' . (int) $asistencia['id'] . '/archivo/' . $i) ?>"><?= e($f['nombre_original']) ?></a> <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($gestiona): ?>
  <div class="ev-caja">
    <div class="grupo-chips">
      <a class="btn btn-mini" href="<?= url($base . '/editar') ?>">Editar aviso</a>
      <?php if (Asistencias::aplica($ev)): ?><a class="btn btn-mini btn-primario" href="<?= url($base . '/qr') ?>" target="_blank">Proyectar QR de asistencia</a>
      <span class="texto-suave">Palabra clave: <strong><?= e((string) $ev['clave_asistencia']) ?></strong></span><?php endif; ?>
      <?php if (!$ev['post_imagen'] || !$ev['post_url']): ?><span class="badge badge-oro">Falta el post de Facebook</span><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</section>

<?php if ($gestiona): ?>
<section class="card" style="max-width:860px;margin-top:16px" id="evidencias">
  <h3 style="margin-top:0">Bitácora de evidencias</h3>
  <p class="ayuda-form">Fotografías, listas de asistencia, grabación y demás. Indica la carrera cuando la evidencia es de una sola, para el reporte de acreditación.</p>
  <form method="post" action="<?= url($base . '/evidencias') ?>" enctype="multipart/form-data" class="formulario">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="form-rejilla">
      <label>Tipo<select name="tipo"><?php foreach (Clasificacion::TIPOS_EVIDENCIA as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Carrera<select name="programa_id"><option value="">Todas</option><?php foreach ($programas as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['siglas']) ?></option><?php endforeach; ?></select></label>
      <label>Descripción<input type="text" name="descripcion" maxlength="300"></label>
    </div>
    <div class="form-rejilla">
      <label>Archivos (PDF, imágenes, Word, Excel, PowerPoint o ZIP)<input type="file" name="archivos[]" multiple></label>
      <label>o enlace (Drive, YouTube…)<input type="url" name="url" maxlength="500" placeholder="https://"></label>
    </div>
    <button class="btn btn-primario btn-mini" type="submit">Agregar a la bitácora</button>
  </form>
  <?php if ($evidencias): ?>
  <div class="tabla-contenedor" style="margin-top:10px"><table class="tabla">
    <thead><tr><th>Tipo</th><th>Carrera</th><th>Evidencia</th><th>Descripción</th><th>Subió</th><th></th></tr></thead>
    <tbody><?php foreach ($evidencias as $x): ?>
      <tr><td><?= e(Clasificacion::TIPOS_EVIDENCIA[$x['tipo']] ?? $x['tipo']) ?></td><td><?= e((string) ($x['siglas'] ?? 'Todas')) ?></td>
        <td><?php if ($x['archivo']): ?><a href="<?= url_archivo('eventos/evidencia/' . (int) $x['id']) ?>"><?= e((string) $x['nombre']) ?></a><?php else: ?><a href="<?= e((string) $x['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir enlace</a><?php endif; ?></td>
        <td><?= e((string) $x['descripcion']) ?></td><td><small><?= e($x['autor']) ?><br><?= e(fecha_corta($x['creado_en'])) ?></small></td>
        <td><form method="post" action="<?= url($base . '/evidencias/' . (int) $x['id'] . '/eliminar') ?>" data-confirm="¿Eliminar esta evidencia?"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Eliminar</button></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if (Asistencias::aplica($ev)): $pend = count(array_filter($asistentes, static fn($a) => $a['estado'] === 'pendiente')); ?>
<section class="card" style="max-width:860px;margin-top:16px" id="asistencias">
  <h3 style="margin-top:0">Asistencias (<?= count($asistentes) ?>)<?= $pend ? ' · <span class="badge badge-oro">' . $pend . ' por revisar</span>' : '' ?></h3>
  <?php if (!$asistentes): ?><p class="texto-suave">Aún no hay asistencias registradas.</p><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Persona</th><th>Carrera</th><th>Sem.</th><th>Registro</th><th>Estado</th><th>Evidencia</th><th></th></tr></thead>
    <tbody><?php foreach ($asistentes as $a): [$et, $cl] = $estados[$a['estado']]; ?>
      <tr><td><?= e($a['nombre']) ?><br><small class="texto-suave"><?= e($a['email']) ?></small></td><td><?= e((string) $a['siglas']) ?></td><td><?= e((string) $a['semestre']) ?></td>
        <td><?= e($metodos[$a['metodo']] ?? $a['metodo']) ?><br><small><?= e(fecha_corta($a['creado_en'])) ?></small></td>
        <td><span class="badge <?= $cl ?>"><?= $et ?></span><br><small><?= e($a['folio']) ?></small></td>
        <td><?php foreach ($a['archivos'] as $i => $f): ?><a href="<?= url_archivo('eventos/asistencia/' . (int) $a['id'] . '/archivo/' . $i) ?>"><?= e($f['nombre_original']) ?></a><br><?php endforeach; ?></td>
        <td style="white-space:nowrap"><?php if ($a['estado'] !== 'validada' || $a['metodo'] === 'constancia'): ?>
          <form method="post" action="<?= url($base . '/asistencias/' . (int) $a['id']) ?>" class="ux-toolbar">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <?php if ($a['estado'] !== 'validada'): ?><button class="btn btn-mini btn-primario" name="decision" value="validar">Validar</button><?php endif; ?>
            <?php if ($a['estado'] !== 'rechazada'): ?><input type="text" name="motivo" maxlength="300" placeholder="Motivo (si rechazas)" style="max-width:150px"><button class="btn btn-mini" name="decision" value="rechazar">Rechazar</button><?php endif; ?>
          </form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
