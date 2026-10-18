<?php
use App\Core\Auth;
$days=[1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
$rows=$horarios;
$inhabiles=$inhabiles??[];
?>
<p><a class="btn" href="<?= e(url('fila?vista=atender')) ?>"><?= icono('flecha-izq',20) ?> Volver a Fila</a></p>
<p>Horario semanal de atención, en hora de Ciudad de México. Añade tantos intervalos como necesites para cada día, sin superponerlos. Marca “Inhabilitar” para conservar un día y sus horas en la misma posición, pero sin ofrecer atención. Esta casilla pausa todas las semanas ese día hasta que la desmarques; para un solo festivo usa una fecha concreta en “Días inhábiles”.</p>
<p>Guardar no abre la fila ni modifica citas existentes. Una fecha marcada como inhábil permanecerá cerrada aunque ya tuviera una jornada programada.</p>
<?php if (!$rows): ?><p>No hay intervalos guardados. Usa “Agregar día / intervalo” para configurar tu atención.</p><?php endif; ?>
<?php if ($error): ?><p class="flash flash-error"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('fila/horarios/'.$dep['clave'])) ?>" class="formulario">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<table class="tabla" data-no-tools><thead><tr><th>Día</th><th>Inicio</th><th>Fin</th><th>Inhabilitar</th><th>Acciones</th></tr></thead><tbody id="fila-horarios-rows">
<?php foreach ($rows as $i=>$row): ?>
<tr>
<td><input type="hidden" name="horarios[<?= $i ?>][activo]" value="1"><select aria-label="Día del intervalo <?= $i+1 ?>" name="horarios[<?= $i ?>][dia]">
<?php foreach ($days as $day=>$label): ?><option value="<?= $day ?>" <?= (int)$row['dia']===$day?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
</select></td>
<td><input aria-label="Inicio del intervalo <?= $i+1 ?>" type="time" name="horarios[<?= $i ?>][inicio]" value="<?= e(substr((string)($row['inicio']??''),0,5)) ?>"></td>
<td><input aria-label="Fin del intervalo <?= $i+1 ?>" type="time" name="horarios[<?= $i ?>][fin]" value="<?= e(substr((string)($row['fin']??''),0,5)) ?>"></td>
<td><input type="hidden" name="horarios[<?= $i ?>][inhabil]" value="0"><label><input type="checkbox" name="horarios[<?= $i ?>][inhabil]" value="1" <?= (!empty($row['inhabil'])||empty($row['activo']))?'checked':'' ?>> Sin atención</label></td>
<td><button class="btn" type="button" data-agregar-intervalo>Agregar intervalo</button> <button class="btn" type="button" data-quitar-intervalo>Quitar</button></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p><button class="btn" id="fila-agregar-dia" type="button">Agregar día / intervalo</button></p>
<h2>Días inhábiles</h2>
<p>Agrega fechas concretas en las que no habrá atención, por ejemplo un día festivo. Estas fechas prevalecen sobre el horario semanal y no se ofrecerán turnos ni citas.</p>
<table class="tabla" data-no-tools><thead><tr><th>Fecha</th><th>Motivo</th><th>Acciones</th></tr></thead><tbody id="fila-inhabiles-rows">
<?php foreach ($inhabiles as $i=>$row): ?>
<?php if (($row['tipo']??'cerrado')!=='cerrado' || !empty($row['inicio'])) continue; ?>
<tr><td><input type="hidden" name="inhabiles[<?= $i ?>][activo]" value="1"><input type="date" required name="inhabiles[<?= $i ?>][fecha]" value="<?= e((string)($row['fecha']??'')) ?>" min="<?= e(date('Y-m-d')) ?>"></td>
<td><input type="text" maxlength="160" name="inhabiles[<?= $i ?>][motivo]" value="<?= e((string)($row['motivo']??'')) ?>" placeholder="Ej. Día festivo"></td>
<td><button class="btn" type="button" data-quitar-inhabil>Quitar</button></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><button class="btn" id="fila-agregar-inhabil" type="button">Agregar día inhábil</button></p>
<p id="fila-horarios-status" role="status"></p>
<button class="btn btn-primario" type="submit">Guardar horarios</button>
</form>
<template id="fila-horario-template"><tr><td><input type="hidden" data-campo="activo" value="1"><select data-campo="dia" aria-label="Día"><?php foreach ($days as $day=>$label): ?><option value="<?= $day ?>"><?= $label ?></option><?php endforeach; ?></select></td><td><input type="time" data-campo="inicio" aria-label="Inicio"></td><td><input type="time" data-campo="fin" aria-label="Fin"></td><td><input type="hidden" data-campo="inhabil" value="0"><label><input type="checkbox" data-campo="inhabil" value="1"> Sin atención</label></td><td><button class="btn" type="button" data-agregar-intervalo>Agregar intervalo</button> <button class="btn" type="button" data-quitar-intervalo>Quitar</button></td></tr></template>
<template id="fila-inhabil-template"><tr><td><input type="hidden" data-campo="activo" value="1"><input type="date" data-campo="fecha" required min="<?= e(date('Y-m-d')) ?>" aria-label="Fecha inhábil"></td><td><input type="text" data-campo="motivo" maxlength="160" placeholder="Ej. Día festivo" aria-label="Motivo"></td><td><button class="btn" type="button" data-quitar-inhabil>Quitar</button></td></tr></template>
<script>
(() => {
  const rows=document.getElementById('fila-horarios-rows');
  const status=document.getElementById('fila-horarios-status');
  const inhabilRows=document.getElementById('fila-inhabiles-rows');
  let index=<?= count($rows) ?>;
  let inhabilIndex=<?= count($inhabiles) ?>;
  function add(day=1) {
    if (rows.children.length>=50) { status.textContent='Puedes guardar hasta 50 intervalos.'; return; }
    const row=document.getElementById('fila-horario-template').content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-campo]').forEach(input=>{input.name='horarios['+index+']['+input.dataset.campo+']';});
    index++;
    row.querySelector('select').value=day;
    const diaYaInhabil=[...rows.querySelectorAll('tr')].some(existing=>existing.querySelector('select')?.value==day && existing.querySelector('input[type="checkbox"]')?.checked);
    row.querySelector('input[type="checkbox"]').checked=diaYaInhabil;
    rows.appendChild(row);
    row.querySelector('input[type="time"]').focus();
    status.textContent='Intervalo añadido. Guarda los horarios para aplicar los cambios.';
  }
  document.getElementById('fila-agregar-dia').addEventListener('click',()=>add());
  document.getElementById('fila-agregar-inhabil').addEventListener('click',()=>{
    if (inhabilRows.children.length>=100) { status.textContent='Puedes guardar hasta 100 días inhábiles.'; return; }
    const row=document.getElementById('fila-inhabil-template').content.firstElementChild.cloneNode(true);
    row.querySelectorAll('[data-campo]').forEach(input=>{input.name='inhabiles['+inhabilIndex+']['+input.dataset.campo+']';});
    inhabilIndex++; inhabilRows.appendChild(row); row.querySelector('input[type="date"]').focus();
    status.textContent='Día inhábil añadido. Guarda los horarios para aplicar el cambio.';
  });
  rows.addEventListener('click',event=>{
    const button=event.target.closest('button');
    if (!button) return;
    const row=button.closest('tr');
    if (button.hasAttribute('data-agregar-intervalo')) add(row.querySelector('select').value);
    if (button.hasAttribute('data-quitar-intervalo')) { row.remove(); status.textContent='Intervalo quitado. Guarda para aplicar los cambios.'; }
  });
  rows.addEventListener('change',event=>{
    if (!event.target.matches('input[type="checkbox"][name*="[inhabil]"]')) return;
    const day=event.target.closest('tr').querySelector('select').value;
    rows.querySelectorAll('tr').forEach(row=>{
      if (row.querySelector('select')?.value==day) row.querySelector('input[type="checkbox"]').checked=event.target.checked;
    });
    status.textContent=event.target.checked?'Día inhabilitado. Guarda para aplicar el cambio.':'Día habilitado. Guarda para aplicar el cambio.';
  });
  inhabilRows.addEventListener('click',event=>{
    const button=event.target.closest('[data-quitar-inhabil]');
    if (button) { button.closest('tr').remove(); status.textContent='Día inhábil quitado. Guarda para aplicar el cambio.'; }
  });
})();
</script>
