<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url('firmas') ?>"><?= icono('flecha-izq', 14) ?> Volver a firmas</a></p>

<section class="card" style="max-width:620px">
  <header class="tarjeta-cabecera"><h2>Mi imagen de firma</h2></header>
  <div class="aviso aviso-advertencia"><strong>No uses la firma de tu INE.</strong> El trazo es solo visual; la validez proviene de tu identidad y certificado digital.</div>
  <div class="firma-canvas-wrap"><canvas id="firma-canvas" width="560" height="180" style="width:100%;border:1px dashed #aaa;background:#fff;touch-action:none"></canvas><button type="button" class="btn" id="limpiar-firma">Borrar trazo</button><?php if ($firma): ?><button type="button" class="btn" id="editar-firma">Editar firma</button><?php endif; ?></div>
  <p class="texto-suave">Esta imagen se estampa dentro de los PDF cuando firmas (no se muestra en la plataforma). Usa una imagen png o jpg de tu firma autógrafa sobre fondo blanco o transparente, en horizontal.</p>

  <?php if ($firma): ?>
    <div class="sello-vista">
      <p><?= icono('ok', 15) ?> Tienes una imagen de firma guardada
        <small class="texto-suave">(actualizada el <?= e(fecha_corta($firma['actualizado_en'])) ?>)</small></p>
    </div>
  <?php else: ?>
    <div class="sello-vista">
      <p class="texto-suave"><?= icono('alerta', 15) ?> Aún no subes tu imagen de firma. Puedes firmar sin ella: se estampa un sello tipográfico con tu nombre y la fecha.</p>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('firmas/mi-firma') ?>" enctype="multipart/form-data" class="formulario" id="form-firma">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <input type="hidden" name="firma_canvas" id="firma_canvas">
    <label><?= $firma ? 'Reemplazar imagen' : 'Subir imagen' ?>
      <input type="file" name="imagen" accept="image/png,image/jpeg" <?= $firma ? '' : 'required' ?> >
    </label>
    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario">Guardar</button>
    </div>
  </form>
</section>
<script>
(function(){const c=document.getElementById('firma-canvas'),x=c.getContext('2d');let d=false,ok=false,edit=<?= $firma ? 'false' : 'true' ?>;x.lineWidth=3;x.lineCap='round';function p(e){const r=c.getBoundingClientRect();return{x:(e.clientX-r.left)*c.width/r.width,y:(e.clientY-r.top)*c.height/r.height}}function cargar(){const i=new Image();i.onload=()=>{x.clearRect(0,0,c.width,c.height);const s=Math.min(c.width/i.width,c.height/i.height);x.drawImage(i,(c.width-i.width*s)/2,(c.height-i.height*s)/2,i.width*s,i.height*s)};i.src='<?= url('firmas/mi-firma-imagen') ?>?v=<?= $firma ? urlencode((string)$firma['actualizado_en']) : '' ?>'}<?php if ($firma): ?>cargar();c.style.pointerEvents='none';document.getElementById('editar-firma').onclick=()=>{edit=true;c.style.pointerEvents='auto';x.clearRect(0,0,c.width,c.height);document.getElementById('editar-firma').textContent='Editando'};<?php endif; ?>c.onpointerdown=e=>{if(!edit)return;d=true;const q=p(e);x.beginPath();x.moveTo(q.x,q.y)};c.onpointermove=e=>{if(d){const q=p(e);x.lineTo(q.x,q.y);x.stroke();ok=true}};c.onpointerup=()=>d=false;c.onpointerleave=()=>d=false;document.getElementById('limpiar-firma').onclick=()=>{x.clearRect(0,0,c.width,c.height);ok=false;edit=true;c.style.pointerEvents='auto'};document.getElementById('form-firma').onsubmit=()=>{if(ok)document.getElementById('firma_canvas').value=c.toDataURL('image/png')};})();
</script>
