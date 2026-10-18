<?php use App\Core\Auth; ?>
<style>
.org-tabs{display:flex;gap:8px;margin-bottom:18px}.org-grid{display:grid;grid-template-columns:minmax(230px,32%) 1fr;gap:18px}.org-lista{max-height:68vh;overflow:auto}.org-item{display:block;padding:12px;border-bottom:1px solid var(--gris-100);color:inherit;text-decoration:none}.org-item:hover,.org-item.activo{background:#f0f7f2}.org-contadores{display:flex;gap:6px;margin-top:6px}.org-pill{font-size:11px;padding:2px 7px;border-radius:12px;background:#eef2f6;color:#475569}.org-persona{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--gris-100)}.org-persona:last-child{border-bottom:0}.org-form{display:grid;grid-template-columns:minmax(180px,2fr) minmax(125px,1fr) 145px auto;gap:9px;align-items:end}.org-ac{position:relative}.org-sugerencias{display:none;position:absolute;z-index:20;top:100%;left:0;right:0;background:white;border:1px solid var(--gris-200);box-shadow:0 8px 22px rgba(15,23,42,.14);max-height:260px;overflow:auto}.org-sugerencia{display:block;width:100%;border:0;border-bottom:1px solid var(--gris-100);background:white;text-align:left;padding:9px 11px;cursor:pointer}.org-sugerencia:hover,.org-sugerencia:focus{background:#f0f7f2}.org-sugerencia small{display:block;color:var(--gris-600)}.org-resumen{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:15px 0}.org-resumen>div{padding:12px;background:#f8fafc;border-radius:8px}.org-vacio{padding:18px;border:1px dashed var(--gris-300);color:var(--gris-600);text-align:center;border-radius:8px}@media(max-width:850px){.org-grid{grid-template-columns:1fr}.org-form{grid-template-columns:1fr}.org-lista{max-height:none}}
</style>

<p class="texto-suave">Gestiona profesores por carrera o posgrado, Cuerpos Académicos (CA), NAB, CAP y coordinaciones. Los nombres siempre se toman de las cuentas registradas.</p>
<nav class="org-tabs" aria-label="Forma de consulta">
  <a class="btn <?= $vistaActual==='programas'?'btn-primario':'' ?>" href="<?= url('ajustes/organizacion?vista=programas') ?>">Por programa o carrera</a>
  <a class="btn <?= $vistaActual==='profesores'?'btn-primario':'' ?>" href="<?= url('ajustes/organizacion?vista=profesores') ?>">Por profesor</a>
  <a class="btn <?= $vistaActual==='ca'?'btn-primario':'' ?>" href="<?= url('ajustes/organizacion?vista=ca') ?>">Cuerpos Académicos (CA)</a>
</nav>

<?php if ($vistaActual === 'profesores'): ?>
  <section class="card" style="padding:18px;margin-bottom:18px">
    <h2 style="margin-top:0">Buscar profesor</h2>
    <div class="org-ac" data-autocomplete data-modo="navegar">
      <input type="search" data-ac-input placeholder="Escribe parte del nombre o correo…" autocomplete="off" aria-label="Buscar profesor registrado">
      <input type="hidden" data-ac-id>
      <div class="org-sugerencias" data-ac-list role="listbox"></div>
    </div>
  </section>
  <?php if (!$profesor): ?>
    <div class="org-vacio">Empieza a escribir el nombre del profesor y selecciónalo de las propuestas.</div>
  <?php else: ?>
    <section class="card" style="padding:20px">
      <h2 style="margin:0"><?= e($profesor['nombre']) ?></h2>
      <p class="texto-suave"><?= e($profesor['email']) ?> · <?= e($profesor['rol']) ?><?= $profesor['departamento']?' · '.e($profesor['departamento']):'' ?></p>
      <div class="org-resumen"><div><strong><?= count($profesor['adscripciones']) ?></strong><br><small>Programas/carreras</small></div><div><strong><?= count($profesor['organos']) ?></strong><br><small>NAB/CAP</small></div><div><strong><?= count($profesor['cuerpos']) ?></strong><br><small>Cuerpos Académicos</small></div></div>
      <h3>Profesor por programa o carrera</h3>
      <?php if (!$profesor['adscripciones']): ?><p class="texto-suave">Sin adscripciones docentes registradas.</p><?php endif; ?>
      <?php foreach ($profesor['adscripciones'] as $r): ?><div class="org-persona"><span><strong><?= e($r['siglas']) ?> · <?= e($r['programa']) ?></strong><br><small><?= e(str_replace('_',' ',$r['tipo_vinculo'])) ?> · <?= e($r['nivel']) ?> · desde <?= e($r['fecha_inicio']) ?><?= !$r['activo']?' · histórico':'' ?></small></span><a class="btn btn-mini" href="<?= url('ajustes/organizacion?programa_id='.(int)$r['programa_id']) ?>">Abrir programa</a></div><?php endforeach; ?>
      <h3>Programas educativos</h3>
      <?php if (!$profesor['programas']): ?><p class="texto-suave">Sin responsabilidades registradas.</p><?php endif; ?>
      <?php foreach ($profesor['programas'] as $r): ?><div class="org-persona"><span><strong><?= e($r['siglas']) ?> · <?= e($r['programa']) ?></strong><br><small><?= e(str_replace('_',' ',$r['cargo'])) ?> · desde <?= e($r['fecha_inicio']) ?><?= !$r['activo']?' · histórico':'' ?></small></span><a class="btn btn-mini" href="<?= url('ajustes/organizacion?programa_id='.(int)$r['programa_id']) ?>">Abrir posgrado</a></div><?php endforeach; ?>
      <h3>NAB y CAP</h3>
      <?php if (!$profesor['organos']): ?><p class="texto-suave">Sin membresías registradas.</p><?php endif; ?>
      <?php foreach ($profesor['organos'] as $r): ?><div class="org-persona"><span><strong><?= e($r['tipo']) ?> · <?= e($r['siglas']) ?></strong><br><small><?= e($r['cargo']) ?> · desde <?= e($r['fecha_inicio']) ?><?= !$r['activo']?' · histórico':'' ?></small></span><a class="btn btn-mini" href="<?= url('ajustes/organizacion?programa_id='.(int)$r['programa_id']) ?>">Abrir posgrado</a></div><?php endforeach; ?>
      <h3>Cuerpos Académicos (CA)</h3>
      <?php if (!$profesor['cuerpos']): ?><p class="texto-suave">Sin Cuerpos Académicos registrados.</p><?php endif; ?>
      <?php foreach ($profesor['cuerpos'] as $r): ?><div class="org-persona"><strong><?= e($r['nombre']) ?></strong><a class="btn btn-mini" href="<?= url('ajustes/organizacion?vista=ca&ca_id='.(int)$r['id']) ?>">Abrir CA</a></div><?php endforeach; ?>
      <?php if ($profesor['dependencias']): ?><h3>Dependencias</h3><?php foreach ($profesor['dependencias'] as $r): ?><div class="org-persona"><span><strong><?= e($r['dependencia']) ?></strong><br><small><?= e($r['cargo']) ?> · desde <?= e($r['fecha_inicio']) ?><?= !$r['activo']?' · histórico':'' ?></small></span></div><?php endforeach; endif; ?>
    </section>
  <?php endif; ?>
<?php elseif ($vistaActual === 'ca'): ?>
  <div class="org-grid">
    <aside class="card org-lista" aria-label="Cuerpos Académicos">
      <div style="padding:12px"><strong>Cuerpos Académicos</strong></div>
      <?php foreach($cuerpos as $ca):?><a class="org-item <?= $cuerpo&&(int)$cuerpo['id']===(int)$ca['id']?'activo':'' ?>" href="<?= url('ajustes/organizacion?vista=ca&ca_id='.(int)$ca['id']) ?>"><strong><?= e($ca['nombre']) ?></strong><br><small><?= (int)$ca['total'] ?> integrante(s)</small></a><?php endforeach;?>
      <?php if(!$cuerpos):?><div class="org-vacio" style="margin:10px">No hay CA registrados.</div><?php endif;?>
    </aside>
    <main>
      <section class="card" style="padding:20px;margin-bottom:16px"><h2 style="margin-top:0">Crear Cuerpo Académico</h2><form method="post" action="<?= url('ajustes/organizacion/ca/crear') ?>" style="display:flex;gap:9px"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input name="nombre" maxlength="160" placeholder="Nombre oficial del Cuerpo Académico" required><button class="btn btn-primario">Crear CA</button></form></section>
      <?php if($cuerpo):?><section class="card" style="padding:20px"><h2 style="margin-top:0"><?= e($cuerpo['nombre']) ?></h2><?php if(!$cuerpo['miembros']):?><p class="texto-suave">Todavía no hay integrantes.</p><?php endif;?><?php foreach($cuerpo['miembros'] as $m):?><div class="org-persona"><span><strong><?= e($m['nombre']) ?></strong><br><small><?= e($m['email']) ?> · <?= e($m['rol']) ?></small></span><div style="display:flex;gap:6px"><a class="btn btn-mini" href="<?= url('ajustes/organizacion?vista=profesores&profesor_id='.(int)$m['id']) ?>">Ver profesor</a><form method="post" action="<?= url('ajustes/organizacion/ca/'.(int)$cuerpo['id'].'/miembro/retirar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="usuario_id" value="<?= (int)$m['id'] ?>"><button class="btn btn-mini">Retirar</button></form></div></div><?php endforeach;?><form class="org-form" method="post" action="<?= url('ajustes/organizacion/ca/'.(int)$cuerpo['id'].'/miembro') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><div class="org-ac" data-autocomplete><label>Profesor<input type="search" data-ac-input placeholder="Escribe parte del nombre…" autocomplete="off" required></label><input type="hidden" name="usuario_id" data-ac-id required><div class="org-sugerencias" data-ac-list role="listbox"></div></div><span></span><span></span><button class="btn btn-primario">Agregar</button></form></section><?php endif;?>
    </main>
  </div>
<?php else: ?>
  <div class="org-grid">
    <aside class="card org-lista" aria-label="Programas y carreras">
      <div style="padding:12px"><input type="search" id="filtrar-posgrados" placeholder="Filtrar programas…" autocomplete="off"></div>
      <?php foreach ($programas as $p): ?><a class="org-item <?= $programa && (int)$programa['id']===(int)$p['id']?'activo':'' ?>" data-programa-texto="<?= e(strtolower($p['siglas'].' '.$p['nombre'].' '.$p['nivel'])) ?>" href="<?= url('ajustes/organizacion?programa_id='.(int)$p['id']) ?>"><strong><?= e($p['siglas']) ?></strong> <small>· <?= e($p['nivel']) ?></small><br><small><?= e($p['nombre']) ?></small><div class="org-contadores"><span class="org-pill">Profesores <?= (int)$p['total_profesores'] ?></span><?php if($p['nivel']==='posgrado'):?><span class="org-pill">NAB <?= (int)$p['total_nab'] ?></span><span class="org-pill">CAP <?= (int)$p['total_cap'] ?></span><?php endif;?></div></a><?php endforeach; ?>
    </aside>
    <main>
    <?php if (!$programa): ?><div class="org-vacio">No hay un programa seleccionado.</div><?php else: ?>
      <section class="card" style="padding:20px;margin-bottom:16px">
        <h2 style="margin:0"><?= e($programa['siglas']) ?> · <?= e($programa['nombre']) ?></h2><p class="texto-suave"><?= e(ucfirst($programa['nivel'])) ?></p>
        <h3>Profesores del programa</h3>
        <?php if(!$programa['profesores']):?><p class="texto-suave">Todavía no hay profesores adscritos.</p><?php endif;?>
        <?php foreach($programa['profesores'] as $m):?><div class="org-persona"><span><strong><?= e($m['nombre']) ?></strong><br><small><?= e(str_replace('_',' ',$m['tipo_vinculo'])) ?> · <?= e($m['email']) ?> · desde <?= e($m['fecha_inicio']) ?></small></span><div style="display:flex;gap:6px"><a class="btn btn-mini" href="<?= url('ajustes/organizacion?vista=profesores&profesor_id='.(int)$m['usuario_id']) ?>">Ver profesor</a><form method="post" action="<?= url('ajustes/organizacion/profesor/'.(int)$m['id'].'/retirar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini">Retirar</button></form></div></div><?php endforeach;?>
        <form class="org-form" method="post" action="<?= url('ajustes/organizacion/programa/'.(int)$programa['id'].'/profesor') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><div class="org-ac" data-autocomplete><label>Profesor<input type="search" data-ac-input placeholder="Escribe parte del nombre…" autocomplete="off" required></label><input type="hidden" name="usuario_id" data-ac-id required><div class="org-sugerencias" data-ac-list role="listbox"></div></div><label>Vínculo<select name="tipo_vinculo"><option value="docente">Docente</option><option value="responsable_academico">Responsable académico</option><option value="colaborador">Colaborador</option></select></label><label>Desde<input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required></label><button class="btn btn-primario">Agregar</button></form>
        <?php if($programa['cuerpos']):?><h3>Cuerpos Académicos de sus profesores</h3><p><?php foreach($programa['cuerpos'] as $ca):?><a class="org-pill" href="<?= url('ajustes/organizacion?vista=ca&ca_id='.(int)$ca['id']) ?>"><?= e($ca['nombre']) ?></a> <?php endforeach;?></p><?php endif;?>
        <h3>Responsables del programa</h3>
        <?php if (!$programa['responsables']): ?><p class="texto-suave">Todavía no hay responsables vigentes.</p><?php endif; ?>
        <?php foreach ($programa['responsables'] as $r): ?><div class="org-persona"><span><strong><?= e($r['nombre']) ?></strong><br><small><?= e(str_replace('_',' ',$r['cargo'])) ?> · <?= e($r['email']) ?> · desde <?= e($r['fecha_inicio']) ?></small></span><form method="post" action="<?= url('ajustes/organizacion/responsable/'.(int)$r['id'].'/retirar') ?>" onsubmit="return confirm('¿Cerrar esta responsabilidad? Se conservará el historial.')"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Retirar</button></form></div><?php endforeach; ?>
        <form class="org-form" method="post" action="<?= url('ajustes/organizacion/programa/'.(int)$programa['id'].'/responsable') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <div class="org-ac" data-autocomplete><label>Persona<input type="search" data-ac-input placeholder="Escribe nombre o correo…" autocomplete="off" required></label><input type="hidden" name="usuario_id" data-ac-id required><div class="org-sugerencias" data-ac-list role="listbox"></div></div>
          <label>Cargo<select name="cargo"><option value="coordinador">Coordinador</option><option value="responsable">Responsable</option><option value="secretario_academico">Secretario académico</option></select></label>
          <label>Desde<input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required></label>
          <button class="btn btn-primario" type="submit">Asignar</button>
        </form>
      </section>
      <?php if($programa['nivel']==='posgrado'):?>
      <?php foreach (['NAB'=>'Núcleo Académico Básico','CAP'=>'Comité Académico de Posgrado'] as $tipo=>$tituloOrg): ?>
      <section class="card" style="padding:20px;margin-bottom:16px">
        <h3 style="margin-top:0"><?= e($tipo) ?> · <?= e($tituloOrg) ?></h3>
        <?php $miembros=array_values(array_filter($programa['miembros'],fn($m)=>$m['tipo']===$tipo)); ?>
        <?php if (!$miembros): ?><p class="texto-suave">Todavía no hay integrantes vigentes.</p><?php endif; ?>
        <?php foreach ($miembros as $m): ?><div class="org-persona"><span><strong><?= e($m['nombre']) ?></strong><br><small><?= e($m['cargo']) ?> · <?= e($m['email']) ?> · desde <?= e($m['fecha_inicio']) ?></small></span><div style="display:flex;gap:6px"><a class="btn btn-mini" href="<?= url('ajustes/organizacion?vista=profesores&profesor_id='.(int)$m['usuario_id']) ?>">Ver profesor</a><form method="post" action="<?= url('ajustes/organizacion/miembro/'.(int)$m['id'].'/retirar') ?>" onsubmit="return confirm('¿Retirar a esta persona del <?= e($tipo) ?>?')"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">Retirar</button></form></div></div><?php endforeach; ?>
        <form class="org-form" method="post" action="<?= url('ajustes/organizacion/programa/'.(int)$programa['id'].'/miembro') ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="tipo" value="<?= e($tipo) ?>">
          <div class="org-ac" data-autocomplete><label>Profesor<input type="search" data-ac-input placeholder="Escribe parte del nombre…" autocomplete="off" required></label><input type="hidden" name="usuario_id" data-ac-id required><div class="org-sugerencias" data-ac-list role="listbox"></div></div>
          <label>Participación<select name="cargo"><option value="miembro">Miembro</option><option value="presidente">Presidente</option><option value="coordinador">Coordinador</option><option value="secretario">Secretario</option><option value="vocal">Vocal</option></select></label>
          <label>Desde<input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required></label>
          <button class="btn btn-primario" type="submit">Agregar</button>
        </form>
      </section>
      <?php endforeach; ?>
      <?php endif;?>
    <?php endif; ?>
    </main>
  </div>
<?php endif; ?>

<script>
(() => {
  const endpoint = <?= json_encode(url('ajustes/organizacion/buscar-profesores')) ?>;
  document.querySelectorAll('[data-autocomplete]').forEach(widget => {
    const input=widget.querySelector('[data-ac-input]'), hidden=widget.querySelector('[data-ac-id]'), list=widget.querySelector('[data-ac-list]'); let timer;
    const close=()=>{list.style.display='none';list.replaceChildren()};
    input.addEventListener('input',()=>{hidden.value='';clearTimeout(timer);const q=input.value.trim();if(q.length<2){close();return}timer=setTimeout(async()=>{try{const r=await fetch(endpoint+'?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}});const data=await r.json();list.replaceChildren();data.resultados.forEach(persona=>{const b=document.createElement('button');b.type='button';b.className='org-sugerencia';const strong=document.createElement('strong');strong.textContent=persona.nombre;const small=document.createElement('small');small.textContent=persona.email+' · '+persona.rol+(persona.departamento?' · '+persona.departamento:'');b.append(strong,small);b.addEventListener('click',()=>{input.value=persona.nombre+' — '+persona.email;hidden.value=persona.id;close();if(widget.dataset.modo==='navegar')window.location.assign(<?= json_encode(url('ajustes/organizacion?vista=profesores&profesor_id=')) ?>+persona.id)});list.append(b)});list.style.display=data.resultados.length?'block':'none'}catch(e){close()}},220)});
    input.closest('form')?.addEventListener('submit',e=>{if(!hidden.value){e.preventDefault();input.setCustomValidity('Selecciona una persona de las propuestas.');input.reportValidity()}else input.setCustomValidity('')});
    document.addEventListener('click',e=>{if(!widget.contains(e.target))close()});
  });
  const filtro=document.getElementById('filtrar-posgrados');if(filtro)filtro.addEventListener('input',()=>{const q=filtro.value.toLowerCase().trim();document.querySelectorAll('[data-programa-texto]').forEach(a=>a.hidden=!a.dataset.programaTexto.includes(q))});
})();
</script>
