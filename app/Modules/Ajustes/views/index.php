<?php
use App\Core\Auth;
$valores = array_column($ajustes, 'valor', 'clave');
$campo = static function(string $clave, string $etiqueta, string $tipo='text') use ($valores): void { ?>
  <label><?= e($etiqueta) ?><input type="<?= e($tipo) ?>" name="valor[<?= e($clave) ?>]" value="<?= $tipo==='password'?'':e($valores[$clave]??'') ?>" autocomplete="<?= $tipo==='password'?'new-password':'off' ?>" <?= $tipo==='number'?'min="1" step="1"':'' ?>>
  <?php if ($tipo==='password'): ?><small class="ayuda-form"><?= !empty($valores[$clave])?'Configurada. Déjala vacía para conservarla.':'Sin configurar.' ?></small><?php endif; ?></label>
<?php }; ?>
<form method="post" action="<?= url('ajustes') ?>" class="formulario" id="ajustes-form" style="max-width:850px">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
  <section class="card"><h2>Inteligencia artificial</h2>
    <p><a class="btn btn-primario" href="<?= url('ajustes/ia') ?>">Configurar Gemini, límites y privacidad</a></p>
    <p>El portal utiliza consultas locales seguras y, opcionalmente, Gemini. Los parámetros NVIDIA de abajo se conservan como configuración anterior y no se utilizan en el portal.</p>
    <details><summary>Configuración anterior y búsqueda semántica</summary>
    <p>El asistente orienta las consultas hacia el área correspondiente. Sin una clave utiliza la búsqueda local.</p>
    <?php $campo('ia_nvidia_api_key','Clave de acceso de NVIDIA','password'); $campo('ia_nvidia_modelo','Modelo de NVIDIA'); ?>
    <div class="ux-toolbar"><button class="btn" type="button" id="probar-nvidia">Probar conexión</button><span id="resultado-nvidia" role="status"></span></div>
    <details class="ux-disclosure"><summary>Configuración avanzada</summary>
      <?php $campo('ia_nvidia_url','Dirección del servicio NVIDIA','url'); $campo('ia_api_key','Clave del servicio de búsqueda semántica','password'); $campo('ia_modelo_embeddings','Modelo de búsqueda semántica'); $campo('ia_url_embeddings','Dirección del servicio de búsqueda','url'); ?>
    </details>
    <p class="ayuda-form">Obtén tu clave en <a href="https://build.nvidia.com/" target="_blank" rel="noopener">NVIDIA ↗</a>. La disponibilidad y las condiciones de uso dependen de tu cuenta.</p>
    </details>
  </section>
  <section class="card"><h2>Leyendas oficiales</h2>
    <p class="ayuda-form">El Director y el Subdirector Académico se toman de <a href="<?= url('ajustes/organizacion') ?>">Organización</a> (titular de Dirección y de Subdirección Académica); ahí mismo se registran los Coordinadores de Docencia de cada licenciatura.</p>
    <?php $campo('institucion_lema_anual','Lema oficial del año (aparece bajo «Patria, Ciencia y Trabajo» en los oficios; vacío para omitirlo)'); ?>
  </section>
  <section class="card"><h2>Departamentos</h2><label>Área responsable de los grupos de investigación<select name="valor[firmas_depto_investigacion]"><?php foreach ($departamentos as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$d['id']===($valores['firmas_depto_investigacion']??'')?'selected':'' ?>><?= e($d['nombre']) ?></option><?php endforeach; ?></select></label></section>
  <section class="card"><h2>Comunidad</h2><?php $campo('umbral_likes_trazabilidad','Apoyos necesarios para dar seguimiento a una propuesta','number'); ?></section>
  <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar ajustes</button></div>
</form>
<script>
document.getElementById('probar-nvidia').addEventListener('click', async function(){
 const form=document.getElementById('ajustes-form'),result=document.getElementById('resultado-nvidia'),data=new FormData();
 data.set('_csrf',form.elements['_csrf'].value);data.set('api_key',form.elements['valor[ia_nvidia_api_key]'].value);data.set('modelo',form.elements['valor[ia_nvidia_modelo]'].value);
 this.disabled=true;result.textContent='Comprobando conexión…';
 try {const response=await fetch(<?= json_encode(url('ajustes/probar-nvidia')) ?>,{method:'POST',body:data});const body=await response.json();result.textContent=body.mensaje||'No fue posible comprobar la conexión.';}
 catch {result.textContent='No se pudo conectar. Intenta de nuevo.';}finally{this.disabled=false;}
});
</script>
