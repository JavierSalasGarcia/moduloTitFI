<?php use App\Core\Auth; ?>
<p><a href="<?= url('documentos') ?>">← Archivo documental</a></p>
<form method="post" action="<?= url('documentos') ?>" enctype="multipart/form-data" class="card formulario" style="max-width:850px">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<h2>1. Guardar originales</h2><p>Primero se crea un borrador privado. Después puedes extraer datos, revisarlos e indizar el documento.</p>
<label>Título provisional<input name="titulo" maxlength="200" required placeholder="Ej. Oficio de solicitud de tutoría"></label>
<?php foreach(['departamento_id'=>['Departamento',$org['departamentos']],'coordinacion_id'=>['Coordinación',$org['coordinaciones']],'programa_id'=>['Programa de estudios',$org['programas']]] as $field=>[$label,$options]): ?><label><?= e($label) ?><select name="<?= e($field) ?>" <?= $field==='departamento_id'?'required':'' ?>><option value=""><?= $field==='departamento_id'?'Selecciona':'No aplica' ?></option><?php foreach($options as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['nombre']) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
<label>Confidencialidad<select name="nivel"><option value="restringida">Restringida · solo tú y quienes autorices</option><option value="confidencial">Confidencial · sin extracción externa</option></select></label>
<label>Archivos originales<input type="file" name="archivos[]" multiple accept="application/pdf,image/jpeg,image/png" required></label>
<p class="ayuda-form">De 1 a 3 archivos PDF, JPG o PNG. Máximo 4 MB por archivo y 8 MB por envío; límite PHP por archivo: <?= e(ini_get('upload_max_filesize')) ?>. Para extracción automática: hasta 3 páginas en total. El orden elegido será el orden de las páginas/imágenes.</p>
<button class="btn btn-primario">Guardar borrador y revisar</button>
</form>
