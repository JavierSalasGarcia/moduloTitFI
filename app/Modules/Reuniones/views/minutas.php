<?php use App\Core\Auth; $token=$token??''; ?>
<section class="card" style="width:100%;max-width:1000px;margin:24px auto">
<h2><?= $token?'Tu historial privado de reuniones':'Minutas de reuniones y atenciones' ?></h2>
<?php if ($token && ($aviso=flash_leer())): ?><p class="flash" role="status"><?= e($aviso['mensaje']) ?></p><?php endif; ?>
<?php if ($token): ?><p>Solo se muestran las minutas compartidas contigo. Este enlace es privado; no lo compartas.</p><?php endif; ?>
<?php if (!$minutas): ?><p>Aún no hay minutas compartidas.</p><?php endif; ?>
<?php foreach ($minutas as $m): ?>
<article style="border-top:1px solid var(--gris-200);padding:16px 0">
<h3><?= e($m['asunto']) ?></h3>
<p><?= e($m['fecha']) ?> · <?= e(['profesor'=>'Profesor','oficina'=>'Responsable de oficina','asistente'=>'Asistente'][$m['calidad']]??$m['calidad']) ?><br>Compartida por: <?= e($m['subido_por_email']?:$m['autor']) ?><?= $m['convocante']?' · Convocante / oficina: '.e($m['convocante']):'' ?></p>
<details><summary>Ver minuta y acuerdos</summary>
<p style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($m['resumen']) ?></p>
<?php if ($m['archivo']): ?><p><a class="btn" href="<?= e($token?url('reuniones/historial/'.$token.'/archivo/'.$m['id']):url_archivo('reuniones/minutas/'.$m['id'].'/archivo')) ?>">Descargar <?= e($m['nombre_archivo']) ?></a></p><?php endif; ?>
</details>
<?php if (!$token): ?><a class="btn" href="<?= e(url('reuniones/minutas/nueva?base='.$m['id'])) ?>">Añadir otra minuta de esta reunión</a><?php endif; ?>
<?php if ($token): $borrador=$_SESSION['minuta_captura'][(int)$m['id']]??null; unset($_SESSION['minuta_captura'][(int)$m['id']]); ?><details <?= $borrador!==null?'open':'' ?>><summary>Compartir tu minuta de esta reunión</summary><form method="post" enctype="multipart/form-data" class="formulario" action="<?= e(url('reuniones/historial/'.$token.'/minuta/'.$m['id'])) ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><label>Resumen / acuerdos<textarea name="resumen" rows="5" maxlength="20000"><?= e($borrador??'') ?></textarea></label><label>Archivo (PDF, TXT o MD; hasta 5 MB)<input type="file" name="minuta" accept=".pdf,.txt,.md"></label><p>Se enviará a los mismos asistentes de esta minuta.</p><button class="btn btn-primario">Guardar y enviar a los asistentes</button></form></details><?php endif; ?>
<?php if (!$token && (int)$m['autor_id']===Auth::id()): ?><form method="post" action="<?= e(url('reuniones/minutas/'.$m['id'].'/reenviar')) ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn">Reenviar a los asistentes</button></form><?php endif; ?>
</article><?php endforeach; ?></section>
