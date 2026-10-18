<?php use App\Core\Auth; $base='fila/expediente/'.$dep['clave']; ?>
<p><a class="btn" href="<?= e(url('fila?vista=atender')) ?>"><?= icono('flecha-izq',20) ?> Volver a Fila</a> · <a href="<?= e(url($base)) ?>">Historial de la oficina</a></p>
<p>Las minutas se agrupan por correo dentro de esta oficina y no aparecen en el kiosco. La persona atendida puede consultar las minutas de su turno mediante su enlace privado.</p>
<?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($turno): ?>
<section class="card">
<h2><?= e(persona_referencia_interna($dep,$turno)) ?></h2>
<p><?= e($turno['email']) ?> · <?= e($turno['fecha']) ?> · <?= e($turno['estado']) ?></p>
<p><?= !empty($turno['nombre_real'])?'Nombre oficial del registro principal.':(!empty($turno['alias'])?'Nombre mostrado como alias; no hay un nombre oficial registrado.':'Sin nombre oficial registrado; identificado por su correo.') ?></p>
<p><?= e($turno['asunto']) ?></p>
<?php $atencion=$turno; require dirname(APP_RAIZ).'/fila/publico/vistas/modalidad_detalle.php'; ?>
<details><summary>Editar modalidad / enlace de Teams</summary>
<form class="formulario" method="post" action="<?= e(url($base.'/modalidad')) ?>">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="turno_id" value="<?= (int)$turno['id'] ?>">
<?php $datos=!empty($captura)?$captura:$turno; require dirname(APP_RAIZ).'/fila/publico/vistas/modalidad_form.php'; ?>
<button class="btn" type="submit">Guardar modalidad</button></form></details>
<form class="formulario" method="post" enctype="multipart/form-data" action="<?= e(url($base.'/minuta')) ?>">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<input type="hidden" name="turno_id" value="<?= (int)$turno['id'] ?>">
<label>Correos de otros asistentes (opcional)<textarea name="presentes" rows="3" placeholder="correo1@dominio.mx; correo2@dominio.mx"><?= e($captura['presentes']??'') ?></textarea></label>
<p>Separa los correos con comas, punto y coma o saltos de línea. Pueden estar registrados o no. El correo de quien tramitó el turno se incluye automáticamente; todos reciben su enlace privado de historial.</p>
<label>Resumen de la minuta / acuerdos y seguimiento
<textarea name="resumen" rows="8" maxlength="20000"><?= e($captura['resumen']??'') ?></textarea></label>
<label>Cargar minuta (PDF, TXT o Markdown; hasta 5 MB)<input type="file" name="minuta" accept=".pdf,.txt,.md"></label>
<p>Los archivos TXT y Markdown se incorporan como resumen si dejas el campo vacío. Los PDF se guardan como adjuntos, sin extracción automática.</p>
<?php if (in_array($turno['estado'],['espera','llamado'],true)): ?>
<label><input type="checkbox" name="finalizar" value="1" <?= !empty($captura['finalizar'])?'checked':'' ?>> Marcar como atendido al guardar la minuta</label>
<?php endif; ?>
<button class="btn">Guardar minuta</button>
</form>
<h3>Minutas de esta atención</h3>
<p>Al guardar se envía la minuta a <?= e($turno['email']) ?> y a los demás asistentes indicados. Las minutas también aparecen en Reuniones. Puedes reenviar las anteriores o reintentar un envío fallido.</p>
<?php if (!$registros): ?><p>Aún no tiene minutas.</p><?php endif; ?>
<?php foreach ($registros as $r): ?>
<article><p><?= e($r['creado']) ?></p><p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($r['resumen']) ?></p>
<?php if ($r['archivo']): ?><p><a href="<?= e(url($base.'/archivo/registro/'.$r['id'])) ?>">Descargar <?= e($r['nombre_archivo']) ?></a></p><?php endif; ?>
<form method="post" action="<?= e(url($base.'/minuta/reenviar')) ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="registro_id" value="<?= (int)$r['id'] ?>"><button class="btn" type="submit"><?= icono('enviar') ?> Reenviar por correo</button></form></article>
<?php endforeach; ?>
</section>
<?php else: ?>
<section class="card"><h2>Registrar atención sin haberse formado</h2>
<p>Crea un turno atendido para añadir su minuta. No requiere cuenta ni confirmación por correo y no entra en la fila de espera. Puedes indicar una fecha anterior para incorporar el historial.</p>
<form class="formulario" method="post" action="<?= e(url($base.'/directa')) ?>" data-identidad-url="<?= e(ruta($dep['clave'].'/identidad')) ?>" data-identidad-csrf="<?= e(csrf()) ?>">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<label>Correo de la persona<input type="email" name="email" required maxlength="160" value="<?= e($captura['email']??'') ?>"></label>
<label>Nombre / alias (opcional)<input name="nombre" maxlength="120" value="<?= e($captura['nombre']??'') ?>"></label>
<p data-identidad-estado role="status">El nombre oficial se obtiene del registro de usuarios por correo institucional. Si no existe, puedes escribir un alias.</p>
<label>Asunto<input name="asunto" required maxlength="200" value="<?= e($captura['asunto']??'') ?>"></label>
<?php $datos=$captura; require dirname(APP_RAIZ).'/fila/publico/vistas/modalidad_form.php'; ?>
<label>Fecha y hora de atención (opcional; vacío = ahora)<input type="datetime-local" name="fecha_atencion" value="<?= e($captura['fecha_atencion']??'') ?>" max="<?= e(date('Y-m-d\TH:i')) ?>"></label>
<button class="btn">Crear turno atendido y añadir minuta</button></form></section>
<form class="formulario" method="get" action="<?= e(url($base)) ?>"><label>Consultar historial por correo<input type="email" name="email" value="<?= e($email) ?>"></label><button class="btn">Consultar historial</button></form>
<?php endif; ?>
<section class="card"><h2>Historial de atenciones<?= $turno?' de esta persona':'' ?></h2>
<p>Hasta 200 registros recientes. Cada atención conserva sus minutas anteriores.</p>
<?php if (!$historial): ?><p>No hay atenciones registradas.</p><?php else: ?>
<div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Fecha / turno</th><th>Persona</th><th>Asunto / estado</th><th>Expediente</th></tr></thead><tbody>
<?php foreach ($historial as $t): ?><tr><td><?= e($t['fecha']) ?> · <?= e(folio_interno($dep,$t)) ?></td><td><?= e($t['nombre']) ?><br><?= e($t['email']) ?></td><td><?= e($t['asunto']) ?> · <?= e($t['estado']) ?></td><td><a href="<?= e(url($base.'?turno='.$t['id'])) ?>">Ver / añadir minuta (<?= (int)$t['minutas'] ?>)</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<section class="card"><h2>Minutas ya guardadas en el servidor</h2>
<?php if (!$guardadas): ?><p>No hay minutas previas<?= $turno||$email?' para este correo':'' ?>.</p><?php endif; ?>
<?php foreach ($guardadas as $r): ?><p><?= e($r['fecha']??$r['creado']) ?> · <?= e($r['persona']) ?> · <?= e($r['asunto']) ?> · <a href="<?= e(url($base.'/archivo/servidor/'.$r['id'])) ?>">Descargar <?= e($r['nombre']) ?></a></p><?php endforeach; ?></section>
<script src="<?= e(ruta('estatico/identidad.js')) ?>?v=<?= filemtime(dirname(APP_RAIZ).'/fila/publico/estatico/identidad.js') ?>"></script>
