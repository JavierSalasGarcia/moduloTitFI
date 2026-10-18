<?php use App\Core\Auth; $captura=$captura??[]; $base=$base??null; ?>
<p><a class="btn" href="<?= e(url('reuniones')) ?>">← Volver a Reuniones</a></p>
<?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<section class="card"><h2><?= $base?e($base['asunto']):($reunion?e($reunion['asunto']):'Minuta de una reunión convocada fuera de Traza FI') ?></h2>
<p>No requiere oficina ni turno. Cada asistente recibirá por correo un enlace privado a sus minutas e historial, sin necesidad de registrarse.</p>
<form class="formulario" method="post" enctype="multipart/form-data" action="<?= e(url('reuniones/minutas')) ?>">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="reunion_id" value="<?= (int)($reunion['id']??0) ?>">
<input type="hidden" name="base_id" value="<?= (int)($base['id']??0) ?>">
<?php if (!$reunion && !$base): ?><label>Asunto<input name="asunto" maxlength="200" required value="<?= e($captura['asunto']??'') ?>"></label><?php endif; ?>
<?php if (!$base && (!$reunion || !$reunion['fecha_confirmada'])): ?><label>Fecha y hora de la reunión<input type="datetime-local" name="fecha" required value="<?= e($captura['fecha']??date('Y-m-d\TH:i')) ?>"></label><?php endif; ?>
<label>Participé como<select name="calidad"><?php foreach (['profesor'=>'Profesor','oficina'=>'Responsable de oficina / Subdirector Académico','asistente'=>'Asistente'] as $valor=>$texto): ?><option value="<?= e($valor) ?>" <?= ($captura['calidad']??'profesor')===$valor?'selected':'' ?>><?= e($texto) ?></option><?php endforeach; ?></select></label>
<label>Convocante (nombre o correo; opcional)<input name="convocante" maxlength="200" value="<?= e($captura['convocante']??$base['convocante']??'') ?>"></label>
<label>Correos de los presentes<textarea name="presentes" rows="3" placeholder="correo1@dominio.mx; correo2@dominio.mx"><?= e($captura['presentes']??'') ?></textarea></label>
<p>Sepáralos con comas, punto y coma o saltos de línea. Se admiten correos de cualquier dominio, registrados o no. <?= $reunion?'También se incluyen los participantes activos de esta reunión.':'' ?> Tu correo se incluye automáticamente.</p>
<label>Resumen / acuerdos<textarea name="resumen" rows="8" maxlength="20000"><?= e($captura['resumen']??'') ?></textarea></label>
<label>Archivo (PDF, TXT o Markdown; hasta 5 MB)<input type="file" name="minuta" accept=".pdf,.txt,.md"></label>
<button class="btn btn-primario" type="submit">Guardar y enviar minuta a los asistentes</button></form></section>
