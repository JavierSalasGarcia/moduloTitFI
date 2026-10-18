<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('firmas') ?>"><?= icono('flecha-izq', 14) ?> Volver a firmas</a></p>

<section class="card" style="max-width:760px">
  <header class="tarjeta-cabecera"><h2>Nueva solicitud de firma</h2></header>

  <form method="post" action="<?= url('firmas') ?>" enctype="multipart/form-data" class="formulario" data-wizard data-draft="firma">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <fieldset class="ux-step" data-title="Documentos"><legend>Documentos que necesitas firmar</legend>

    <label>Título <span class="req">*</span>
      <input type="text" name="titulo" required maxlength="180" placeholder="Ej. Oficio de comisión académica — Bo.Vo. y firma">
    </label>

    <label>Mensaje para los firmantes
      <textarea name="mensaje" rows="2" placeholder="Contexto o instrucciones (opcional)…"></textarea>
    </label>

    <label>Documentos PDF <span class="req">*</span>
      <input type="file" name="documentos[]" accept="application/pdf" multiple required>
      <small class="ayuda-form">Puedes adjuntar varios PDF; durante la ruta se podrán añadir anexos.</small>
    </label>

    </fieldset><fieldset class="ux-step" data-title="Personas y orden"><legend>Personas y orden de firma</legend>
    <label>Modo de firma
      <select name="modo" id="modo-firma">
        <option value="secuencial">Secuencial — ruta con Bo.Vo. (ej. Coordinación → Dirección)</option>
        <option value="paralela">Paralela — todos firman en cualquier orden (ej. NAB, comité)</option>
      </select>
    </label>

    <fieldset id="campos-secuencial" class="formulario" style="border:0;padding:0;margin:0">
      <p class="texto-suave" style="margin:0">Ruta de firma en orden: normalmente un intermediario que da el Bo.Vo. y el destinatario final que firma el oficio.</p>
      <?php $etiquetas = ['Paso 1 (primer firmante)', 'Paso 2', 'Paso 3', 'Paso 4 (si aplica)']; ?>
      <?php for ($i = 1; $i <= 4; $i++): ?>
        <label <?= $i>1?'class="firmante-secuencial-extra" hidden':'' ?>><?= $etiquetas[$i - 1] ?>
          <input list="dl-usuarios" name="paso<?= $i ?>" autocomplete="off" placeholder="Nombre o email institucional…">
        </label>
      <?php endfor; ?>
      <button type="button" class="btn" id="mas-pasos">Añadir siguiente firmante</button>
    </fieldset>

    <fieldset id="campos-paralela" class="formulario" style="border:0;padding:0;margin:0;display:none">
      <?= \App\Core\RecipientGroups::selector('grupos_firma','Usar grupos de destinatarios') ?>
      <label>Grupo de firmantes
        <select name="grupo_id">
          <option value="">— Sin grupo: escribir firmantes abajo —</option>
          <?php foreach ($grupos as $g): ?>
            <option value="<?= (int) $g['id'] ?>"><?= e($g['nombre']) ?><?= $g['tipo'] !== 'personal' ? ' · ' . e(strtoupper($g['tipo'])) : '' ?></option>
          <?php endforeach; ?>
        </select>
        <small class="ayuda-form">NAB/CAP por posgrado, Comités Curriculares, Cuerpos Académicos y tus grupos guardados.</small>
      </label>
      <?php for ($i = 1; $i <= 10; $i++): ?>
        <label <?= $i > 1 ? 'class="firmante-extra" style="display:none"' : '' ?>>Firmante <?= $i ?>
          <input list="dl-usuarios" name="firmante<?= $i ?>" autocomplete="off" placeholder="Nombre o email institucional…">
        </label>
      <?php endfor; ?>
      <button type="button" class="btn" id="mas-firmantes">+ Más firmantes</button>
    </fieldset>

    <label>Guardar destinatarios como grupo (opcional)
      <input type="text" name="guardar_grupo_nombre" maxlength="160" placeholder='Ej. "Comité evaluación Jessica, DCTBC"'>
    </label>

    <datalist id="dl-usuarios">
      <?php foreach ($usuarios as $u): ?>
        <option value="<?= e($u['nombre']) ?> — <?= e($u['email']) ?>"></option>
      <?php endforeach; ?>
    </datalist>

    </fieldset>
    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario">Enviar a firma</button>
    </div>
  </form>
</section>

<script>
(function () {
  'use strict';
  var modo = document.getElementById('modo-firma');
  var sec = document.getElementById('campos-secuencial');
  var par = document.getElementById('campos-paralela');
  function alternar() {
    var esSec = modo.value === 'secuencial';
    sec.style.display = esSec ? '' : 'none';
    par.style.display = esSec ? 'none' : '';
    sec.disabled = !esSec;
    par.disabled = esSec;
    sec.querySelector('[name=paso1]').required = esSec;
    par.querySelector('[name=firmante1]').required = !esSec && !par.querySelector('[name=grupo_id]').value && !par.querySelector('[data-recipient-groups]')?.selectedOptions.length;
  }
  modo.addEventListener('change', alternar);
  par.querySelector('[name=grupo_id]').addEventListener('change', alternar);
  par.querySelector('[data-recipient-groups]')?.addEventListener('change', alternar);
  alternar();
  document.getElementById('mas-pasos').addEventListener('click', function(){const next=sec.querySelector('label[hidden]');if(next){next.hidden=false;next.querySelector('input').focus();}this.hidden=!sec.querySelector('label[hidden]');});
  modo.form.addEventListener('ux:draft-restored',()=>{sec.querySelectorAll('label[hidden]').forEach(label=>{if(label.querySelector('input').value)label.hidden=false;});par.querySelectorAll('.firmante-extra').forEach(label=>{if(label.querySelector('input').value)label.style.display='';});});
  document.getElementById('mas-firmantes').addEventListener('click', function () {
    var oculto = par.querySelector('.firmante-extra[style*="none"]');
    if (oculto) { oculto.style.display = ''; }
    if (!par.querySelector('.firmante-extra[style*="none"]')) { this.style.display = 'none'; }
  });
})();
</script>
