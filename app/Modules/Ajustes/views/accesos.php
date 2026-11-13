<?php use App\Core\Auth; ?>
<p>Busca cualquier cuenta para ayudar a su usuario. Actuarás con sus permisos y las acciones indicarán quién las realizó realmente. No se permite cambiar sus credenciales.</p>
<?php if(!$listo): ?><form method="post" action="<?= url('ajustes/accesos/activar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-primario">Activar autorregistro y auditoría</button></form><?php endif; ?>
<?php $pct=static fn(int $a,int $b): string => $b>0 ? number_format($a*100/$b,0).' %' : '—'; ?>
<section class="card" style="padding:18px;margin:0 0 16px">
<h3 style="margin-top:0">Radiografía de las cuentas</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px">
<?php foreach ([['Cuentas',$est['total']],['Activas',$est['activas']],['Correo verificado',$est['verificadas'].' ('.$pct($est['verificadas'],$est['total']).')'],['Activas sin verificar',$est['sin_verificar']],['Nuevas en 30 días',$est['nuevas_30']],['Con segundo factor',$est['con_2fa']],['Profesores enlazados',$est['profesores_enlazados'].' de '.$est['profesores_directorio']],['Dicen ser profesor (por confirmar)',$est['declaraciones']],['Sin programa asignado',$est['sin_programa']]] as [$t,$v]): ?>
<div><small class="texto-suave"><?= e($t) ?></small><div style="font-size:22px;font-weight:700"><?= e((string)$v) ?></div></div>
<?php endforeach; ?></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
<div class="tabla-contenedor"><table class="tabla" data-no-tools><caption style="text-align:left;font-weight:700;padding-bottom:4px">Por licenciatura y posgrado</caption><thead><tr><th>Programa</th><th>Cuentas</th><th>Activas</th><th>Verificadas</th></tr></thead><tbody>
<?php foreach ($est['por_programa'] as $r): ?><tr><td title="<?= e($r['nombre']) ?>"><?= e($r['siglas']) ?><?= $r['nivel']==='posgrado' ? ' <small class="texto-suave">posgrado</small>' : '' ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['activas'] ?></td><td><?= (int)$r['verificadas'] ?> <small class="texto-suave">(<?= e($pct((int)$r['verificadas'],(int)$r['total'])) ?>)</small></td></tr><?php endforeach; ?>
<tr><td><em>Sin programa</em></td><td><?= (int)$est['sin_programa'] ?></td><td colspan="2"></td></tr>
</tbody></table></div>
<div class="tabla-contenedor"><table class="tabla" data-no-tools><caption style="text-align:left;font-weight:700;padding-bottom:4px">Por rol</caption><thead><tr><th>Rol</th><th>Cuentas</th><th>Activas</th><th>Verificadas</th></tr></thead><tbody>
<?php foreach ($est['por_rol'] as $r): ?><tr><td><?= e($r['rol']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['activas'] ?></td><td><?= (int)$r['verificadas'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="tabla" data-no-tools style="margin-top:12px"><caption style="text-align:left;font-weight:700;padding-bottom:4px">Por dominio de correo</caption><thead><tr><th>Dominio</th><th>Cuentas</th></tr></thead><tbody>
<?php foreach ($est['por_dominio'] as $r): ?><tr><td><?= e($r['dominio']) ?></td><td><?= (int)$r['total'] ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div></section>
<form method="get" class="buscador"><input type="hidden" name="orden" value="<?= e($orden) ?>"><input type="hidden" name="dir" value="<?= e($dir) ?>"><label>Nombre o correo<input type="search" name="q" value="<?= e($q) ?>"></label><button class="btn">Buscar</button></form>
<p><?= (int)$total ?> cuentas · Página <?= (int)$pagina ?></p>
<div class="card tabla-contenedor"><table class="tabla" data-no-tools><?php $ord=static function(string $k,string $t) use($q,$orden,$dir): string { $sig=($orden===$k&&$dir==='asc')?'desc':'asc'; $f=$orden===$k?($dir==='asc'?' ▲':' ▼'):''; return '<th aria-sort="'.($orden===$k?($dir==='asc'?'ascending':'descending'):'none').'"><a href="'.url('ajustes/accesos?q='.urlencode($q).'&orden='.$k.'&dir='.$sig).'" title="Ordenar por '.$t.'">'.$t.$f.'</a></th>'; }; ?>
<thead><tr><?= $ord('nombre','Cuenta') ?><?= $ord('email','Correo') ?><?= $ord('rol','Rol') ?><?= $ord('estado','Estado') ?><th>Ayudar al usuario</th></tr></thead><tbody>
<?php foreach($cuentas as $u): ?><tr><td><?= e($u['nombre']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['rol']) ?></td><td><?= $u['activo']?'Activa':'Inactiva' ?></td><td>
<?php if($listo && $u['activo'] && $u['rol']!=='admin'): ?><details><summary>Impersonar</summary><form method="post" action="<?= url('ajustes/accesos/impersonar') ?>" class="formulario"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>"><label>Tu contraseña de administrador<input type="password" name="clave_admin" autocomplete="current-password" required></label><button type="submit" class="btn">Entrar como esta persona</button></form></details><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><nav class="ux-toolbar" aria-label="Páginas"><?php if($pagina>1): ?><a class="btn" href="<?= url('ajustes/accesos?q='.urlencode($q).'&orden='.$orden.'&dir='.$dir.'&pagina='.($pagina-1)) ?>">Anterior</a><?php endif; ?><?php if($pagina*30<$total): ?><a class="btn" href="<?= url('ajustes/accesos?q='.urlencode($q).'&orden='.$orden.'&dir='.$dir.'&pagina='.($pagina+1)) ?>">Siguiente</a><?php endif; ?></nav>
