<?php use App\Core\Auth; ?>
<p><a href="<?= url('ajustes') ?>">← Ajustes</a></p>
<form method="post" action="<?= url('ajustes/ia') ?>" class="formulario" id="ia-form" style="max-width:850px">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<section class="card"><h2>Árbitro de consultas: Jev</h2>
<p>Jev (TypeSafe AI) no genera texto: decide si la pregunta de la página de inicio corresponde al sitio, si es un intento de manipulación y a qué secciones dirigir a la persona. Sin Jev, o al agotarse la cuota, decide el árbitro local por palabras.</p>
<label><input type="checkbox" name="jev_activo" <?= ajuste('ia_jev_activo','0')==='1'?'checked':'' ?>> Usar Jev como árbitro principal</label>
<label>API key de Jev<input type="password" name="jev_api_key" autocomplete="new-password" maxlength="512"><small><?= $jevOrigen==='config'?'Clave definida en el servidor (variable de entorno o config.local.php); tiene prioridad y se edita ahí.':($jevOrigen==='ajustes'?'Clave configurada y cifrada. Deja vacío para conservarla.':'Aún no hay una clave configurada.') ?></small></label>
<label><input type="checkbox" name="jev_eliminar_clave"> Eliminar la clave de Jev guardada</label>
<label>Modelo de Jev<input name="jev_modelo" maxlength="36" pattern="jev-[a-z0-9.\-]+" value="<?= e(ajuste('ia_jev_modelo','jev-latest')) ?>"><small>Usa <code>jev-latest</code> o fija una versión (por ejemplo <code>jev-1.14.0</code>) si ajustas el umbral.</small></label>
<label>Umbral de pertinencia<input name="jev_umbral" type="number" min="0.05" max="0.95" step="0.05" required value="<?= e(ajuste('ia_jev_umbral','0.5')) ?>"><small>Probabilidad mínima para aceptar que la pregunta corresponde al sitio. Más alto = más estricto.</small></label>
<div class="ux-toolbar"><button type="button" class="btn" id="jev-test">Probar Jev</button><span role="status" id="jev-test-status"></span></div>
<p class="ayuda-form">La clave se crea en <a href="https://console.typesafe.ai/keys" target="_blank" rel="noopener">console.typesafe.ai ↗</a>. La prueba consume una llamada mínima y no envía datos personales.</p>
</section>
<section class="card"><h2>Conexión con Gemini</h2>
<label>Proveedor<select name="proveedor"><option value="local" <?= ajuste('ia_proveedor','local')==='local'?'selected':'' ?>>Local · sin consumo externo</option><option value="gemini" <?= ajuste('ia_proveedor','local')==='gemini'?'selected':'' ?>>Gemini</option></select></label>
<label>API key de Gemini<input type="password" name="api_key" autocomplete="new-password" maxlength="512"><small><?= $geminiOrigen==='config'?'Clave definida en el servidor (variable de entorno o config.local.php); tiene prioridad y se edita ahí.':($keySet?'Clave configurada y cifrada. Deja vacío para conservarla.':'Aún no hay una clave configurada.') ?></small></label>
<label>Modelo de Gemini<input name="gemini_modelo" maxlength="46" pattern="gemini-[a-z0-9.\-]+" value="<?= e(ajuste('ia_gemini_modelo','gemini-2.5-flash-lite')) ?>"></label>
<label><input type="checkbox" name="eliminar_clave"> Eliminar la clave guardada (requiere modo local)</label>
<label><input type="checkbox" name="pago" <?= ajuste('ia_gemini_pago','0')==='1'?'checked':'' ?>> Confirmo que este proyecto utiliza servicios de pago de Gemini y he revisado sus condiciones de datos.</label>
<p>La clave permanece en el servidor. En el portal, Gemini orienta trámites sin recibir resultados de solicitudes o reuniones. En Archivo documental, el propietario puede autorizar expresamente el envío de originales restringidos para extraer metadatos; los documentos confidenciales no se envían.</p>
<div class="ux-toolbar"><button type="button" class="btn" id="ia-test">Probar conexión</button><span role="status" id="ia-test-status"></span></div>
<p class="ayuda-form">La prueba consume una llamada mínima y no envía datos personales. <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Obtener una API key ↗</a></p>
</section>
<section class="card"><h2>Límites de uso</h2><label>Llamadas externas diarias de toda la plataforma<input name="limite_diario" type="number" min="1" max="10000" required value="<?= e(ajuste('ia_limite_diario','100')) ?>"></label>
<label>Consultas asistidas diarias por usuario (Jev y Gemini cuentan como una sola por consulta)<input name="limite_usuario" type="number" min="1" max="1000" required value="<?= e(ajuste('ia_limite_usuario','20')) ?>"></label>
<p>Reservas de llamadas de hoy: <?= $usage ?>. Los intentos fallidos también cuentan. Día de cuota: UTC. Máximo 5 intentos por minuto por usuario, 256 tokens de salida y una llamada por consulta; sin reintentos automáticos.</p>
<p>La extracción documental comparte la cuota general y tiene un tope adicional de 20 extracciones diarias globales y 5 por usuario: máximo 3 páginas y 2,000 tokens de respuesta por extracción. El tope de 256 tokens corresponde solo a la orientación de trámites.</p>
<p>Al alcanzar el límite sigue funcionando la consulta local y la indización manual. Esta cuota cubre este asistente y la extracción documental, no otros usos de tu clave. Configura además un límite mensual en Google AI Studio → Spend → Monthly spend cap.</p>
</section><button class="btn btn-primario" type="submit">Guardar configuración</button></form>
<section class="card" style="max-width:850px;margin-top:24px"><h2>Capas de acceso del asistente</h2>
<ul><li><strong>Pública:</strong> catálogo de funciones oficiales, usado para orientar trámites.</li><li><strong>Interna:</strong> catálogo de herramientas; requiere sesión.</li><li><strong>Restringida:</strong> estado de solicitudes propias y reuniones propias o con invitación vigente. Titulares y operativos también pueden pedir las solicitudes de su departamento.</li><li><strong>Confidencial:</strong> excluida de las consultas del asistente para todos, incluso administradores.</li></ul>
<p>El administrador técnico no hereda acceso a todos los registros. Documentos, calificaciones, información laboral y otros módulos no están habilitados para esta IA. La clasificación siguiente solo limita al asistente: no sustituye los permisos actuales del resto del sitio.</p>
<form class="formulario" method="post" action="<?= url('ajustes/ia/clasificar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<label>Tipo de registro<select name="tipo"><option value="tickets">Solicitud</option><option value="reuniones">Reunión</option></select></label>
<label>Número del registro<input name="registro_id" type="number" min="1" required placeholder="Número que aparece en la URL del registro"></label>
<label>Clasificación para el asistente<select name="nivel"><option value="confidencial">Confidencial · excluir del asistente</option><option value="restringida">Restringida · respetar propietario y participación</option></select></label>
<button class="btn" type="submit">Guardar clasificación</button></form>
<details><summary>Clasificaciones registradas (hasta 200)</summary><ul><?php foreach($policies as $p): ?><li><?= e(str_replace('ia_nivel_','',$p['clave'])) ?>: <?= e($p['valor']) ?></li><?php endforeach; ?></ul></details>
<p><a href="<?= url('ajustes/auditoria?q=ia_') ?>">Consultar registro de acciones del asistente</a></p></section>
<script>
document.getElementById('jev-test').addEventListener('click',async function(){const form=document.getElementById('ia-form'),status=document.getElementById('jev-test-status'),data=new FormData();data.set('_csrf',form.elements._csrf.value);data.set('jev_api_key',form.elements.jev_api_key.value);this.disabled=true;status.textContent='Comprobando…';try{const r=await fetch(<?= json_encode(url('ajustes/ia/probar-jev')) ?>,{method:'POST',body:data});const body=await r.json();status.textContent=body.mensaje||'No se pudo comprobar.';}catch{status.textContent='No se pudo comprobar la conexión.';}finally{this.disabled=false;}});
document.getElementById('ia-test').addEventListener('click',async function(){const form=document.getElementById('ia-form'),status=document.getElementById('ia-test-status'),data=new FormData();data.set('_csrf',form.elements._csrf.value);data.set('api_key',form.elements.api_key.value);this.disabled=true;status.textContent='Comprobando…';try{const r=await fetch(<?= json_encode(url('ajustes/ia/probar')) ?>,{method:'POST',body:data});const body=await r.json();status.textContent=body.mensaje||'No se pudo comprobar.';}catch{status.textContent='No se pudo comprobar la conexión.';}finally{this.disabled=false;}});
</script>
