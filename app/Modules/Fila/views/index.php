<?php
$pestana=$pestana??'otros';
$filaIcono=function(string $icono,string $texto): string {
    $descripciones=[
        'Administrar Fila'=>'Configura oficinas y opciones generales del módulo.',
        'Configurar mis horarios'=>'Define los días e intervalos de atención de tu oficina.',
        'Copiar enlace del kiosco'=>'Copia la URL para usarla en otra computadora; no abre la pantalla aquí.',
        'Minutas, historial y atención directa'=>'Consulta expedientes y registra a una persona que atendiste sin haberse formado.',
        'Copiar enlace de cita'=>'Copia la URL para enviarla a quien quiera reservar una reunión.',
        'Pasar al siguiente'=>'Finaliza el turno actual y llama al primero en espera; actualiza el kiosco.',
        'No disponible'=>'Finaliza la atención del intervalo actual y muestra cuándo será la próxima.',
        'Disponible'=>'Quita la pausa y restablece el estado normal del kiosco.',
        'Ver historial / añadir minuta'=>'Abre el expediente de la persona que estás atendiendo ahora.',
        'Generar y copiar token'=>'Crea un token nuevo y lo copia (para el monitor de Internet o la laptop); el anterior deja de servir.',
        'Copiar enlace público para el QR'=>'Copia la URL para tomar turno sin cuenta; puedes compartirla o crear un QR.',
        'Tomar turno / solicitar cita'=>'Abre el formulario de esta oficina para incorporarte a su fila o solicitar una reunión.',
        'Ver la fila pública'=>'Consulta los turnos y las horas aproximadas, sin datos privados.',
    ];
    $trazos=[
        'monitor'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
        'cita'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M9 16h6"/>',
        'minuta'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'qr'=>'<rect x="3" y="3" width="6" height="6"/><rect x="15" y="3" width="6" height="6"/><rect x="3" y="15" width="6" height="6"/><path d="M15 15h3v3h3M15 21h3M21 15v-3M12 3v6M3 12h6"/>',
        'no-disponible'=>'<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'llave'=>'<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
    ];
    $svg=isset($trazos[$icono]) ? '<svg class="icono" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$trazos[$icono].'</svg>' : icono($icono,24);
    return $svg.'<span class="fila-tooltip" aria-hidden="true">'.e($texto.' — '.($descripciones[$texto]??'')).'</span>';
};
?>
<style>
.fila-icon-btn.btn { position:relative; display:inline-flex; justify-content:center; align-items:center; width:48px; height:48px; padding:0; margin:4px 4px 4px 0; vertical-align:middle; }
.fila-icon-btn:focus-visible { outline:3px solid #9b7a17; outline-offset:3px; }
.fila-icon-btn:active:not(:disabled) { transform:scale(.97); }
.fila-icon-btn .fila-tooltip { display:none; }
.fila-floating-tooltip { position:fixed; z-index:10050; width:max-content; max-width:min(280px,calc(100vw - 24px)); padding:8px 12px; border-radius:8px; background:#142b1b; color:#fff; font-size:14px; font-weight:500; line-height:1.4; white-space:normal; text-align:left; box-shadow:0 4px 12px #0002; }
.fila-floating-tooltip[hidden] { display:none; }
.fila-icon-btn[aria-busy="true"] { opacity:.65; }
.fila-icon-btn.fila-pausa-activa { background:#8a5a12; border-color:#8a5a12; color:#fff; }
.fila-actions { display:flex; flex-wrap:nowrap; align-items:center; gap:8px; overflow-x:auto; padding:4px; margin:8px 0; }
.fila-actions > .fila-icon-btn,.fila-actions > form { flex:0 0 auto; }
.fila-actions > form { margin:0; }
.fila-actions .fila-icon-btn.btn { margin:0; }
.fila-main-actions { display:flex; flex-wrap:wrap; align-items:stretch; gap:10px; margin:12px 0 8px; }
.fila-main-actions form { display:flex; flex:1 1 220px; margin:0; }
.fila-text-btn.btn { display:flex; align-items:center; justify-content:center; width:100%; min-height:54px; padding:12px 18px; font-size:16px; font-weight:700; text-align:center; }
.fila-text-btn:active:not(:disabled) { transform:scale(.98); }
.fila-text-btn.fila-pausa-activa { background:#8a5a12; border-color:#8a5a12; color:#fff; }
@media (max-width:680px) { .fila-main-actions { display:grid; grid-template-columns:1fr; } }
</style>
<nav class="pestanas" aria-label="Vistas de Fila">
  <a class="pestana<?= $pestana==='atender'?' activa':'' ?>" <?= $pestana==='atender'?'aria-current="page"':'' ?> href="<?= e(url('fila?vista=atender')) ?>">Para que yo atienda</a>
  <a class="pestana<?= $pestana==='otros'?' activa':'' ?>" <?= $pestana==='otros'?'aria-current="page"':'' ?> href="<?= e(url('fila?vista=otros')) ?>">Otros departamentos</a>
</nav>
<?php if (\App\Core\Auth::tieneRol('admin')): ?>
<p><a class="btn fila-icon-btn" aria-label="Administrar Fila" href="<?= e(ruta('admin')) ?>"><?= $filaIcono('engrane','Administrar Fila') ?></a></p>
<?php endif; ?>
<?php if ($pestana==='atender'): ?>
<p>Personas formadas hoy en las oficinas que tienes asignadas para atender. Esta información es exclusiva de sus responsables.</p>
<?php if (!($oficinasGestion??[])): ?>
<p>No tienes oficinas asignadas para atender. Puedes consultar las filas en “Otros departamentos”.</p>
<?php else: ?>
<?php foreach ($oficinasGestion as $oficina): ?>
<?php
$enlaceCita=rtrim((string)config()['sitio'],'/').'/'.rawurlencode($oficina['clave']).'/turno?tipo=cita';
$enlaceKiosco=rtrim((string)config()['sitio'],'/').'/'.rawurlencode($oficina['clave']);
$enlaceTurno=$enlaceKiosco.'/turno';
$mensajeKiosco='Kiosco de '.$oficina['nombre'].': abre este enlace en la computadora o pantalla de la entrada. Muestra el QR, el folio en atención y el avance de la fila.'."\n".$enlaceKiosco;
$mensajeCita='Para solicitar una cita en '.$oficina['nombre'].', elige el día y la hora disponibles y confirma tu solicitud con el código que recibirás en tu correo institucional. No necesitas una cuenta en Traza FI.'."\n".$enlaceCita;
$mensajeTurno='Para tomar turno en '.$oficina['nombre'].', abre este enlace e indica tu correo institucional. El folio aparece de inmediato y también llega por email. Este enlace puede convertirse en un código QR.'."\n".$enlaceTurno;
$estado=null;
foreach ($oficinasAtencion??[] as $panelAcciones) if ($panelAcciones['oficina']['id']===$oficina['id']) $estado=$panelAcciones['estado'];
?>
<div class="fila-office-controls">
<p><?= e($oficina['cargo']??'Administración') ?> · <?= e($oficina['nombre']) ?>:</p>
<div class="fila-actions" role="group" aria-label="Acciones de <?= e($oficina['nombre']) ?>">
<a class="btn fila-icon-btn" aria-label="Configurar mis horarios" href="<?= e(url('fila/horarios/'.$oficina['clave'])) ?>"><?= $filaIcono('reloj','Configurar mis horarios') ?></a>
<a class="btn fila-icon-btn" aria-label="Minutas, historial y atención directa" href="<?= e(url('fila/expediente/'.$oficina['clave'])) ?>"><?= $filaIcono('minuta','Minutas, historial y atención directa') ?></a>
<button class="btn fila-icon-btn" aria-label="Copiar enlace del kiosco" type="button" data-copiar-enlace="<?= e($mensajeKiosco) ?>"><?= $filaIcono('monitor','Copiar enlace del kiosco') ?></button>
<button class="btn fila-icon-btn" aria-label="Copiar enlace de cita" type="button" data-copiar-enlace="<?= e($mensajeCita) ?>"><?= $filaIcono('cita','Copiar enlace de cita') ?></button>
<button class="btn fila-icon-btn" aria-label="Copiar enlace público para el QR" type="button" data-copiar-enlace="<?= e($mensajeTurno) ?>"><?= $filaIcono('qr','Copiar enlace público para el QR') ?></button>
<button class="btn fila-icon-btn" aria-label="Generar y copiar token" type="button" data-copiar-token="<?= e(url('fila/atender/'.$oficina['clave'].'/token')) ?>"><?= $filaIcono('llave','Generar y copiar token') ?></button>
</div>
<?php if ($estado): ?>
<?php
$pausaActiva=!empty($estado['pausa_manual']);
$textoPausa=$pausaActiva?'Volver a disponible':'No disponible';
$actual=$estado['actual']??null;
$siguiente=$estado['espera'][0]??null;
$referenciaSiguiente=$siguiente?persona_referencia_interna($oficina,$siguiente):'';
?>
<div class="fila-main-actions" role="group" aria-label="Acciones principales de <?= e($oficina['nombre']) ?>">
<?php if (!$actual && !$siguiente): ?>
<form method="post" action="<?= e(url('fila/atender/'.$oficina['clave'].'/directa')) ?>" data-operacion-atencion>
<input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<button class="btn btn-primario fila-text-btn" type="submit" <?= !$estado['abierta'] || $pausaActiva?'disabled':'' ?>>Ya entró una persona sin turno</button>
</form>
<?php else: ?>
<form method="post" action="<?= e(url('fila/atender/'.$oficina['clave'].'/siguiente')) ?>" data-operacion-atencion>
<input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<input type="hidden" name="actual_id" value="<?= (int)($actual['id']??0) ?>">
<input type="hidden" name="siguiente_id" value="<?= (int)($siguiente['id']??0) ?>">
<?php $textoAvanzar=$actual ? ($siguiente?'Terminé · Atender '.$referenciaSiguiente:'Terminé esta atención') : 'Atender '.$referenciaSiguiente; ?>
<button class="btn btn-primario fila-text-btn" type="submit"><?= e($textoAvanzar) ?></button>
</form>
<?php endif; ?>
<form method="post" action="<?= e(url('fila/atender/'.$oficina['clave'].'/disponibilidad')) ?>">
<input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<input type="hidden" name="disponible" value="<?= $pausaActiva?'1':'0' ?>">
<button class="btn fila-text-btn<?= $pausaActiva?' fila-pausa-activa':'' ?>" aria-pressed="<?= $pausaActiva?'true':'false' ?>" type="submit"><?= e($textoPausa) ?></button>
</form>
</div>
<?php if (!$actual && !$siguiente): ?><p><small>Pulsa “Ya entró una persona sin turno” únicamente cuando alguien ya esté dentro y hayas comenzado a atenderle.</small></p><?php endif; ?>
<?php if ($actual && !es_atencion_directa($actual)): ?>
<p><a href="<?= e(url('fila/expediente/'.$oficina['clave'].'?turno='.$actual['id'])) ?>">Ver historial / añadir minuta del turno actual</a></p>
<?php endif; endif; ?>
<span class="copia-cita-estado" role="status" aria-live="polite"></span>
<textarea class="copia-cita-manual" rows="4" readonly hidden aria-label="Explicación y enlace para copiar manualmente"></textarea></div>
<?php endforeach; ?>
<?php if ($pendiente): ?>
<p><a href="<?= e(url('cuenta/verificar')) ?>">Verifica tu correo</a> para consultar las personas que esperan atención.</p>
<?php else: foreach ($oficinasAtencion??[] as $panel): $oficina=$panel['oficina']; $estado=$panel['estado']; ?>
<section class="card">
<h2><?= e($oficina['nombre']) ?></h2>
<p>El kiosco se actualiza automáticamente en unos 5 segundos.<?= $estado['actual'] && !$estado['espera']?' No hay más personas esperando: el botón “Terminé esta atención” dejará libre la oficina.':'' ?></p>
<p><?= e(leyenda_atencion($estado)) ?> · Actualizado a las <?= e(u_hora($estado['ahora'])) ?></p>
<?php if ($estado['actual']): $actual=$estado['actual']; ?>
<?php if (es_atencion_directa($actual)): ?>
<h3>Atención directa en curso</h3>
<p>La primera persona pasó sin necesidad de tomar turno.</p>
<?php else: ?>
<h3>En atención · <?= e(persona_referencia_interna($oficina,$actual)) ?></h3>
<p><?= e($actual['asunto']) ?></p>
<?php $atencion=$actual; require dirname(APP_RAIZ).'/fila/publico/vistas/modalidad_detalle.php'; ?>
<?php endif; ?>
<?php endif; ?>
<h3>Personas en espera (<?= count($estado['espera']) ?>)</h3>
<?php if (!$estado['espera']): ?><p>No hay personas esperando para que las atiendas.</p>
<?php else: ?>
<div class="tabla-contenedor">
<table class="tabla"><thead><tr><th>Persona (referencia)</th><th>Asunto</th><th>Atención estimada</th><th>Expediente</th></tr></thead><tbody>
<?php foreach ($estado['espera'] as $turno): ?>
<tr><td><?= e(persona_referencia_interna($oficina,$turno)) ?></td><td><?= e($turno['asunto']) ?><br><small><?= !empty($turno['videoconferencia'])?'Teams'.(empty($turno['enlace_teams'])?' · el titular llama':' · con enlace'):'Presencial' ?></small></td>
<td><?= $turno['estimado'] ? e(u_hora($turno['estimado'])) : 'Sin hora estimada' ?></td><td><a href="<?= e(url('fila/expediente/'.$oficina['clave'].'?turno='.$turno['id'])) ?>">Historial / minuta</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
<?php endforeach; endif; ?>
<?php endif; ?>
<?php else: ?>
<p><a class="btn" href="<?= e(url('fila')) ?>"><?= icono('flecha-izq',20) ?> Volver a Fila</a></p>
<p>Consulta las filas de otros departamentos o toma un turno. El acceso por QR no requiere cuenta ni iniciar sesión en Traza FI.</p>
<h2>Filas de otros departamentos</h2>
<?php if (!($otrasOficinas??[])): ?><p>Todavía no hay otros departamentos con fila habilitada.</p>
<?php else: foreach ($otrasOficinas as $panel): $oficina=$panel['oficina']; ?>
<section class="card">
<h3><?= e($oficina['nombre']) ?></h3>
<p><?= (int)$panel['espera'] ?> persona(s) en espera · <?= (int)$panel['atendiendo'] ?> en atención.</p>
<p><a class="btn fila-icon-btn" aria-label="Tomar turno / solicitar cita" href="<?= e(ruta($oficina['clave'].'/turno')) ?>"><?= $filaIcono('cita','Tomar turno / solicitar cita') ?></a>
<a class="btn fila-icon-btn" aria-label="Ver la fila pública" href="<?= e(ruta($oficina['clave'].'/fila')) ?>"><?= $filaIcono('lista','Ver la fila pública') ?></a></p>
</section>
<?php endforeach; endif; ?>
<h2>Mis turnos como visitante</h2>
<p>Turnos confirmados con el mismo correo de tu cuenta, incluso si los tomaste sin iniciar sesión.</p>
<?php $filtros=$filtros??[]; ?>
<form class="formulario" id="fila-filtros" method="get" action="<?= e(url('fila')) ?>">
<input type="hidden" name="vista" value="otros">
<label>Departamento<select name="departamento"><option value="">Todos mis departamentos</option>
<?php foreach ($departamentosFiltro??[] as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)$d['id']===($filtros['departamento']??'')?'selected':'' ?>><?= e($d['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Ordenar por<select name="orden"><?php foreach (['recientes'=>'Fecha: más recientes primero','antiguos'=>'Fecha: más antiguos primero','departamento'=>'Departamento: A–Z, más recientes primero'] as $valor=>$texto): ?><option value="<?= e($valor) ?>" <?= ($filtros['orden']??'recientes')===$valor?'selected':'' ?>><?= e($texto) ?></option><?php endforeach; ?></select></label>
<p><button class="btn btn-primario" type="submit"><?= icono('buscar') ?> Filtrar / ordenar</button> <a class="btn" href="<?= e(url('fila?vista=otros')) ?>">Limpiar filtros</a></p>
</form>
<p>Se muestran hasta 200 turnos de tu historial, incluidos los de fechas anteriores.</p>
<?php if (!empty($errorFiltros)): ?><p class="flash flash-error" role="alert"><?= e($errorFiltros) ?></p><?php endif; ?>
<?php if ($pendiente): ?>
<p><a href="<?= e(url('cuenta/verificar')) ?>">Verifica tu correo</a> para consultar tus turnos.</p>
<?php elseif (!$turnos): ?><p>No hay turnos que coincidan con estos filtros.</p>
<?php else: foreach ($turnos as $turno): ?>
<section class="card">
<h3><?= e($turno['oficina']) ?> · Turno <?= e(folio_publico(['clave'=>$turno['oficina_clave']??'xx'],$turno)) ?></h3>
<?php if (isset($turno['fecha'])): ?><p>Fecha: <?= e($turno['fecha']) ?></p><?php endif; ?>
<p>Estado: <?= e($turno['estado']) ?>.
<?php if ($turno['estado']==='espera' && ($turno['fecha']??date('Y-m-d'))===date('Y-m-d')): ?>Hay <?= (int)$turno['adelante'] ?> persona(s) en espera antes de ti.
<?php if ($turno['estimado']): ?>Atención estimada: <?= e(u_hora($turno['estimado'])) ?>.<?php endif; ?>
<?php elseif ($turno['estado']==='llamado'): ?><?= !empty($turno['videoconferencia'])?'Es tu turno: prepárate para Teams.':'Es tu turno: pasa a la oficina.' ?><?php endif; ?></p>
<?php $atencion=$turno; require dirname(APP_RAIZ).'/fila/publico/vistas/modalidad_detalle.php'; ?>
<?php $cantidadMinutas=(int)($turno['cantidad_minutas']??0); ?>
<p><?= $cantidadMinutas ? icono('minuta').' '.($cantidadMinutas===1?'1 minuta disponible':$cantidadMinutas.' minutas disponibles') : 'Sin minutas todavía' ?>
<?php if ($cantidadMinutas): ?> · <a href="<?= e(ruta('t/'.$turno['token']).'#minutas') ?>">Ver minutas</a><?php endif; ?></p>
<a href="<?= e(ruta('t/'.$turno['token'])) ?>">Ver y gestionar mi turno</a>
</section>
<?php endforeach; endif; ?>
<?php endif; ?>
<script>
(() => {
  // Fuera de las fichas: sus transformaciones crean capas que ocultaban la ayuda.
  const tooltip = document.createElement('div');
  tooltip.className = 'fila-floating-tooltip';
  tooltip.id = 'fila-floating-tooltip';
  tooltip.setAttribute('role','tooltip');
  tooltip.hidden = true;
  document.body.appendChild(tooltip);
  let active = null;
  let hideTimer;
  function hide() {
    clearTimeout(hideTimer);
    if (active) active.removeAttribute('aria-describedby');
    active = null;
    tooltip.hidden = true;
  }
  function position() {
    if (!active) return;
    const rect = active.getBoundingClientRect();
    const box = tooltip.getBoundingClientRect();
    const left = Math.max(12,Math.min(rect.left,innerWidth-box.width-12));
    const top = rect.top >= box.height+20 ? rect.top-box.height-8 : rect.bottom+8;
    tooltip.style.left = left+'px';
    tooltip.style.top = Math.max(12,Math.min(top,innerHeight-box.height-12))+'px';
  }
  function show(button) {
    hide();
    active = button;
    tooltip.textContent = button.querySelector('.fila-tooltip').textContent;
    button.setAttribute('aria-describedby',tooltip.id);
    tooltip.hidden = false;
    position();
  }
  function scheduleHide() { hideTimer = setTimeout(hide,120); }
  document.querySelectorAll('.fila-icon-btn').forEach(button => {
    button.addEventListener('pointerenter',event => { if (event.pointerType==='mouse') show(button); });
    button.addEventListener('pointerleave',scheduleHide);
    button.addEventListener('focus',() => show(button));
    button.addEventListener('blur',hide);
    button.addEventListener('click',hide);
  });
  tooltip.addEventListener('pointerenter',() => clearTimeout(hideTimer));
  tooltip.addEventListener('pointerleave',scheduleHide);
  document.addEventListener('keydown',event => { if (event.key==='Escape') hide(); });
  window.addEventListener('scroll',hide,true);
  window.addEventListener('resize',hide);
})();
document.querySelectorAll('[data-operacion-atencion]').forEach(form => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button');
    button.disabled = true;
    button.setAttribute('aria-busy','true');
    button.textContent = 'Actualizando…';
  });
});
document.querySelectorAll('[data-copiar-enlace]').forEach(button => {
  button.addEventListener('click', async () => {
    const controls = button.closest('.fila-office-controls');
    const status = controls.querySelector('.copia-cita-estado');
    const manual = controls.querySelector('.copia-cita-manual');
    manual.value = button.dataset.copiarEnlace;
    try {
      await navigator.clipboard.writeText(button.dataset.copiarEnlace);
      manual.hidden = true;
      status.textContent = 'Explicación y enlace copiados.';
    } catch (error) {
      manual.hidden = false;
      manual.focus();
      manual.select();
      status.textContent = 'No se pudo copiar automáticamente. Copia la explicación y el enlace seleccionados.';
    }
  });
});
document.querySelectorAll('[data-copiar-token]').forEach(button => {
  button.addEventListener('click', async () => {
    if (!confirm('Se generará un token nuevo y el actual dejará de funcionar (laptop y monitor de Internet). ¿Continuar?')) return;
    const controls = button.closest('.fila-office-controls');
    const status = controls.querySelector('.copia-cita-estado');
    const manual = controls.querySelector('.copia-cita-manual');
    try {
      const datos = new FormData();
      datos.append('_csrf', <?= json_encode(\App\Core\Auth::csrfToken()) ?>);
      const respuesta = await fetch(button.dataset.copiarToken, {method:'POST', body:datos, credentials:'same-origin'});
      const json = await respuesta.json();
      if (!json.ok) throw new Error(json.error || 'No se pudo generar el token.');
      manual.value = json.token;
      try {
        await navigator.clipboard.writeText(json.token);
        manual.hidden = true;
        status.textContent = 'Token nuevo copiado. Pégalo donde lo necesites; no se vuelve a mostrar.';
      } catch (error) {
        manual.hidden = false;
        manual.select();
        status.textContent = 'Token nuevo generado. Cópialo del cuadro; no se vuelve a mostrar.';
      }
    } catch (error) {
      status.textContent = error.message;
    }
  });
});
setTimeout(() => { if (!document.hidden && !document.querySelector('.copia-cita-manual:not([hidden])') && !document.querySelector('#fila-filtros:focus-within')) location.reload(); }, 30000);
</script>
