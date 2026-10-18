<?php use App\Core\{Auth,RecipientGroups}; ?>
<p>Guarda listas de personas para reutilizarlas en solicitudes, reuniones y firmas. Los grupos nuevos son privados. Solo el creador y el administrador pueden modificarlos.</p>
<?php if(!$listo): ?>
  <?php if(Auth::tieneRol('admin')): ?><form method="post" action="<?= url('grupos-destinatarios/activar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-primario">Activar grupos de destinatarios</button></form><?php else: ?><p>La administración está preparando esta función.</p><?php endif; ?>
<?php else: ?>
<div class="barra-acciones"><form class="buscador" method="get"><label>Buscar grupo<input type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre del grupo"></label><button class="btn">Buscar</button></form><a class="btn btn-primario" href="<?= url('grupos-destinatarios/nuevo') ?>">Crear grupo</a></div>
<?php if(Auth::tieneRol('admin')): ?><p class="ayuda-form">Ves todos los grupos, incluidos los privados. Abre uno para definir quién puede usarlo.</p><?php endif; ?>
<?php if(!$grupos): ?><section class="card"><p><?= $q!==''?'No hay grupos que coincidan con la búsqueda.':'Todavía no tienes grupos propios ni compartidos contigo.' ?></p></section><?php else: ?>
<div class="card tabla-contenedor"><table class="tabla"><thead><tr><th>Grupo</th><th>Creador</th><th>Destinatarios activos</th><th>Visibilidad</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($grupos as $g): ?><tr><td><?= e($g['nombre']) ?></td><td><?= e($g['creador']) ?></td><td><?= (int)$g['total'] ?></td><td><?= $g['visibilidad']==='privado'?'Privado':'Público · audiencia seleccionada' ?></td><td><a class="btn" href="<?= url('grupos-destinatarios/'.$g['id']) ?>"><?= RecipientGroups::editable($g)?'Editar grupo':'Ver grupo' ?></a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; endif; ?>
