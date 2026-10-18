<?php
$destino=rtrim((string)config()['sitio'],'/').'/'.$dep['clave'].'/turno';
$publicos=array_map(fn($t)=>['folio'=>folio_publico($dep,$t),
    'hora'=>$t['estimado']?u_hora($t['estimado']):null],$estado['espera']);
$atencionDirecta=es_atencion_directa($estado['actual']);
$paseDirecto=!$estado['actual'] && !$publicos && $estado['atencion']==='atendiendo';
$proximaBreve=preg_replace('/ ([ap])\. m\./u',"\u{A0}$1.\u{A0}m.",u_proxima_atencion_breve($estado['proxima_atencion'],$estado['ahora']));
$noDisponible=!$estado['actual'] && !empty($estado['pausa_manual']);
$estadoPredominante=!$estado['actual'] && $estado['atencion']!=='atendiendo';
$mostrarProxima=$estadoPredominante && !$noDisponible && $proximaBreve!=='';
$mostrarLista=(bool)$publicos && !$estadoPredominante;
$mostrarOpciones=(bool)$estado['actual'] && !$publicos;
$tituloPrincipal=$noDisponible?'No disponible.':($paseDirecto?'Disponible, pasa':($mostrarProxima?'Próximo horario de atención':($mostrarOpciones?'Puedes esperar a que termine la atención actual':'No hay atención programada')));
$detallePrincipal=$noDisponible?($proximaBreve!==''?'Próxima atención: '.lcfirst($proximaBreve):''):($paseDirecto?'No necesitas tomar turno.':($mostrarProxima?$proximaBreve:($mostrarOpciones?'o escanea el código para apartar tu lugar':'')));
$iconoPrincipal=$noDisponible?'⛔':($paseDirecto?'✅':($mostrarProxima?'🕒':($mostrarOpciones?'⏳':'📅')));
$iconoActual=$noDisponible?'📷':($estado['actual']?'🧑‍💼':'✅');
?>
<div class="hall-kiosk">
<header class="hall-header">
  <div><span class="hall-brand">TRAZA FI · FACULTAD DE INGENIERÍA</span><h1><?= u_escapar($dep['nombre']) ?></h1></div>
  <div class="hall-tools"><time id="hall-clock"><?= u_hora($estado['ahora']) ?></time>
    <button id="hall-fullscreen" type="button"><span aria-hidden="true">⛶</span> Pantalla completa</button></div>
</header>
<div class="hall-grid">
  <section class="hall-scan">
    <div class="hall-scan-heading">
      <span class="hall-scan-icon" aria-hidden="true">⏱️</span>
      <h2>Evita esperar<br>y dar vueltas</h2>
      <p class="hall-scan-action">Escanea para saber cuándo pasar</p>
    </div>
    <a class="hall-qr" id="hall-qr" href="<?= u_escapar($destino) ?>" aria-label="Abrir el formulario para tomar turno"></a>
    <div class="hall-scan-details">
      <p class="hall-address"><?= u_escapar(preg_replace('#^https?://#','',$destino)) ?></p>
      <p id="hall-status" class="hall-status"><?= u_escapar(preg_replace('/ ([ap])\. m\./u',"\u{A0}$1.\u{A0}m.",leyenda_publica_atencion($estado))) ?></p>
    </div>
  </section>
  <section class="hall-queue" aria-label="Avance de la fila">
    <div class="hall-now">
      <div><p class="hall-eyebrow"><span id="hall-now-icon" class="hall-inline-icon" aria-hidden="true"><?= $iconoActual ?></span><span id="hall-now-eyebrow"><?= $noDisponible?'Para atenderte mejor':'En este momento' ?></span></p><p class="hall-now-name" id="hall-current-label"><?= $noDisponible?'Escanea el código y aparta tu lugar':($estado['actual']?'Hay una persona siendo atendida':'No hay ninguna persona siendo atendida') ?></p></div>
      <strong id="hall-current-folio" <?= $noDisponible?'hidden':'' ?>><?= $estado['actual'] && !$atencionDirecta?u_escapar(folio_publico($dep,$estado['actual'])):'—' ?></strong>
    </div>
    <div class="hall-waiting">
      <div id="hall-waiting-title" class="hall-waiting-title" <?= $mostrarLista?'':'hidden' ?>><h2><span aria-hidden="true">👥</span> Próximos turnos</h2><span id="hall-total"><?= count($publicos) ?> en espera</span></div>
      <div id="hall-columns" class="hall-columns" <?= $mostrarLista?'':'hidden' ?>><span><span aria-hidden="true">🔢</span> Turno</span><span><span aria-hidden="true">🕒</span> Hora aprox.</span></div>
      <ol id="hall-waiting-list" class="hall-list" <?= $mostrarLista?'':'hidden' ?>>
      <?php foreach (array_slice($publicos,0,6) as $turno): ?>
        <li><strong><span class="hall-row-icon" aria-hidden="true">🚶</span><?= $turno['folio'] ?></strong><time><?= u_escapar($turno['hora']??'Por confirmar') ?></time></li>
      <?php endforeach; ?>
      </ol>
      <p id="hall-empty" class="hall-empty<?= $noDisponible?' hall-empty-unavailable':($paseDirecto?' hall-empty-available':($mostrarProxima?' hall-empty-next':($mostrarOpciones?' hall-empty-options':''))) ?>" <?= $mostrarLista?'hidden':'' ?>><span id="hall-empty-icon" class="hall-state-icon" aria-hidden="true"><?= $iconoPrincipal ?></span><strong id="hall-empty-title"><?= u_escapar($tituloPrincipal) ?></strong><span id="hall-empty-help"><?= u_escapar($detallePrincipal) ?></span></p>
      <p id="hall-pages" class="hall-pages"></p>
    </div>
  </section>
</div>
<footer class="hall-footer"><span><span aria-hidden="true">ℹ️</span> Las horas son aproximadas y pueden cambiar.</span><span id="hall-connection"><span id="hall-connection-icon" aria-hidden="true">🔄</span> <span id="hall-connection-text">Actualización automática cada 5 segundos</span></span></footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
(() => {
  const destination=<?= json_encode($destino,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  const qr=document.getElementById('hall-qr');
  if (window.QRCode) new QRCode(qr,{text:destination,width:512,height:512,colorDark:'#142b1b',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
  else qr.textContent='Toca aquí para tomar turno';
  let waiting=<?= json_encode($publicos,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  let walkInAvailable=<?= $paseDirecto?'true':'false' ?>;
  let hasCurrent=<?= $estado['actual']?'true':'false' ?>;
  let attentionState=<?= json_encode($estado['atencion']) ?>;
  let manualUnavailable=<?= $noDisponible?'true':'false' ?>;
  let nextAttention=<?= json_encode($proximaBreve,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  const nb=s=>(s||'').replace(/ ([ap])\. m\./g,' $1. m.');
  let page=0;
  function paint() {
    const pages=Math.max(1,Math.ceil(waiting.length/6)); page%=pages;
    const list=document.getElementById('hall-waiting-list'); list.replaceChildren();
    const dominantStatus=!hasCurrent&&attentionState!=='atendiendo';
    const showQueue=waiting.length>0&&!dominantStatus;
    list.hidden=!showQueue;
    waiting.slice(page*6,page*6+6).forEach(turn => {
      const row=document.createElement('li');
      const folio=document.createElement('strong');
      const icon=document.createElement('span');icon.className='hall-row-icon';icon.setAttribute('aria-hidden','true');icon.textContent='🚶';
      folio.append(icon,document.createTextNode(turn.folio));
      const time=document.createElement('time');time.textContent=turn.hora||'Por confirmar';
      row.append(folio,time);
      list.appendChild(row);
    });
    const empty=document.getElementById('hall-empty');
    empty.hidden=showQueue;
    empty.classList.toggle('hall-empty-unavailable',manualUnavailable);
    empty.classList.toggle('hall-empty-available',walkInAvailable);
    empty.classList.toggle('hall-empty-next',!manualUnavailable&&!walkInAvailable&&!hasCurrent&&!!nextAttention);
    empty.classList.toggle('hall-empty-options',hasCurrent&&waiting.length===0);
    document.getElementById('hall-waiting-title').hidden=!showQueue;
    document.getElementById('hall-columns').hidden=!showQueue;
    const title=manualUnavailable?'No disponible.':(walkInAvailable?'Disponible, pasa':(!hasCurrent&&nextAttention?'Próximo horario de atención':(hasCurrent?'Puedes esperar a que termine la atención actual':'No hay atención programada')));
    const help=manualUnavailable?(nextAttention?'Próxima atención: '+nextAttention.charAt(0).toLowerCase()+nextAttention.slice(1):''):(walkInAvailable?'No necesitas tomar turno.':(hasCurrent?'o escanea el código para apartar tu lugar':nextAttention));
    document.getElementById('hall-empty-title').textContent=title;
    document.getElementById('hall-empty-help').textContent=help;
    document.getElementById('hall-empty-icon').textContent=manualUnavailable?'⛔':(walkInAvailable?'✅':(!hasCurrent&&nextAttention?'🕒':(hasCurrent?'⏳':'📅')));
    document.getElementById('hall-total').textContent=waiting.length+' en espera';
    document.getElementById('hall-pages').textContent=pages>1?'Turnos '+(page*6+1)+'–'+Math.min(page*6+6,waiting.length)+' de '+waiting.length+' · La lista rota automáticamente':'';
  }
  const fullscreen=document.getElementById('hall-fullscreen');
  fullscreen.addEventListener('click',async()=>{
    try { await document.documentElement.requestFullscreen(); }
    catch(e) { fullscreen.textContent='Usa F11 para pantalla completa'; }
  });
  document.addEventListener('fullscreenchange',()=>{fullscreen.hidden=!!document.fullscreenElement;});
  async function refresh() {
    const controller=new AbortController(); const timeout=setTimeout(()=>controller.abort(),10000);
    try {
      const response=await fetch(<?= json_encode(ruta($dep['clave'].'/estado.json')) ?>,{cache:'no-store',signal:controller.signal});
      if (!response.ok) throw new Error('Sin conexión'); const data=await response.json();
      const direct=!!(data.actual&&data.actual.directa);
      const unavailable=!data.actual&&!!data.pausa_manual;
      document.getElementById('hall-current-folio').textContent=data.actual&&!direct?data.actual.folio:'—';
      document.getElementById('hall-current-folio').hidden=unavailable;
      document.getElementById('hall-now-eyebrow').textContent=unavailable?'Para atenderte mejor':'En este momento';
      document.getElementById('hall-now-icon').textContent=unavailable?'📷':(data.actual?'🧑‍💼':'✅');
      document.getElementById('hall-current-label').textContent=unavailable?'Escanea el código y aparta tu lugar':(data.actual?'Hay una persona siendo atendida':'No hay ninguna persona siendo atendida');
      document.getElementById('hall-clock').textContent=data.ahora;
      document.getElementById('hall-status').textContent=nb(data.leyenda_publica||data.leyenda);
      waiting=data.siguientes;
      hasCurrent=!!data.actual;
      attentionState=data.atencion;
      manualUnavailable=unavailable;
      nextAttention=nb(data.proxima_atencion_texto);
      walkInAvailable=!data.actual&&waiting.length===0&&data.atencion==='atendiendo';
      paint();
      document.getElementById('hall-connection-icon').textContent='🟢';
      document.getElementById('hall-connection-text').textContent='Actualizado '+data.ahora+' · En línea';
    } catch(e) {
      document.getElementById('hall-connection-icon').textContent='⚠️';
      document.getElementById('hall-connection-text').textContent='Sin conexión · Los datos pueden estar desactualizados';
    }
    finally {clearTimeout(timeout); setTimeout(refresh,5000);}
  }
  paint(); setInterval(()=>{page++;paint();},10000); setTimeout(refresh,5000);
})();
</script>
