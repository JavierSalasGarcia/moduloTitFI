<?php
$categorias=['clases'=>['C','Clases'],'tramites'=>['T','Trámites y entregas'],'evaluacion'=>['E','Evaluaciones'],'suspension'=>['S','Suspensión de labores'],'vacaciones'=>['V','Vacaciones']];
$eventos=[];
$agregar=function(array $fechas,string $tipo,string $breve,string $detalle) use (&$eventos) {
    foreach ($fechas as $fecha) $eventos[$fecha][]=['tipo'=>$tipo,'breve'=>$breve,'detalle'=>$detalle];
};
$dias=fn(string $mes,array $numeros)=>array_map(fn($n)=>$mes.'-'.sprintf('%02d',$n),$numeros);
$agregar(['2026-08-04'],'clases','Inicio de clases','Inicio de clases.');
$agregar(['2026-08-21'],'tramites','Examen especial','Límite para solicitar examen especial.');
$agregar(['2026-08-28'],'tramites','Bajas posgrado','Límite para renunciar a inscripción de estudios avanzados (bajas).');
$agregar(['2026-09-01'],'tramites','Por competencias','Límite para solicitar examen por competencias.');
$agregar($dias('2026-09',[22,23,24,25,26,28,29,30]),'evaluacion','1.er parcial','Primer periodo de evaluaciones parciales.');
$agregar(['2026-09-28'],'tramites','Bajas licenciatura','Límite para renunciar a inscripción de estudios de licenciatura.');
$agregar(['2026-10-01'],'tramites','Calif. 1.er parcial','Límite para entrega de calificaciones de primera evaluación.');
$agregar($dias('2026-11',[19,20,21,23,24,25,26,27]),'evaluacion','Último parcial','Último periodo de evaluaciones parciales.');
$agregar(['2026-11-30'],'clases','Fin de clases','Fin de clases.');
$agregar(['2026-12-02'],'tramites','Calif. último parcial','Límite para entrega de calificaciones de última evaluación.');
$agregar($dias('2026-12',[3,4,5,7,8,9,10,11]),'evaluacion','Ordinarios','Evaluación ordinaria y exámenes especiales.');
$agregar(['2026-12-16'],'tramites','Calif. ordinarios','Límite para entrega de calificaciones de examen ordinario.');
$agregar([...$dias('2026-12',[17,18]),...$dias('2027-01',[6,7,8])],'evaluacion','Extraordinarios','Evaluación extraordinaria.');
$agregar(['2027-01-13'],'tramites','Calif. extraordinarios','Límite de entrega de calificaciones de extraordinario.');
$agregar($dias('2027-01',[14,15,16,18,19]),'evaluacion','Título suficiencia','Evaluación a título de suficiencia.');
$agregar(['2027-01-25'],'tramites','Calif. título / actas','Límite para entrega de calificaciones de título y actas de evaluación de licenciatura.');
$agregar([...$dias('2026-09',[14,15,16]),'2026-10-12',...$dias('2026-11',[1,2,16]),...$dias('2026-12',[12,25]),'2027-01-01'],'suspension','Sin labores','Suspensión de labores.');
$agregar([...$dias('2026-12',[21,22,23,24,28,29,30,31]),...$dias('2027-01',[4,5])],'vacaciones','Vacaciones','Vacaciones.');
$meses=['2026-08'=>'Agosto 2026','2026-09'=>'Septiembre 2026','2026-10'=>'Octubre 2026','2026-11'=>'Noviembre 2026','2026-12'=>'Diciembre 2026','2027-01'=>'Enero 2027'];
?>
<section class="card escolar" aria-labelledby="escolar-titulo">
<h2 id="escolar-titulo">Calendario escolar · 2026-B</h2>
<p>Agosto 2026 – enero 2027. Pulsa una fecha señalada para ver sus detalles.</p>
<div class="escolar-leyenda" aria-label="Simbología"><?php foreach ($categorias as $tipo=>[$simbolo,$texto]): ?><span class="escolar-marca escolar-<?= e($tipo) ?>"><?= e($simbolo.' · '.$texto) ?></span><?php endforeach; ?></div>
<div class="escolar-meses">
<?php foreach ($meses as $mes=>$nombre): $primero=new DateTimeImmutable($mes.'-01'); ?>
<section class="escolar-mes" aria-label="<?= e($nombre) ?>"><h3><?= e($nombre) ?></h3>
<div class="escolar-semana" aria-hidden="true"><?php foreach (['L','M','X','J','V','S','D'] as $dia): ?><span><?= $dia ?></span><?php endforeach; ?></div>
<div class="escolar-dias">
<?php for ($i=1;$i<(int)$primero->format('N');$i++): ?><span aria-hidden="true"></span><?php endfor; ?>
<?php for ($dia=1;$dia<=(int)$primero->format('t');$dia++): $fecha=$mes.'-'.sprintf('%02d',$dia); $lista=$eventos[$fecha]??[]; ?>
<?php if ($lista): ?><button type="button" class="escolar-dia con-evento" data-escolar-fecha="<?= e($fecha) ?>" aria-haspopup="dialog" aria-label="<?= e($dia.' de '.$nombre.': '.implode('; ',array_column($lista,'detalle'))) ?>"><strong><?= $dia ?></strong>
<?php foreach ($lista as $evento): ?><small class="escolar-marca escolar-<?= e($evento['tipo']) ?>"><?= e($categorias[$evento['tipo']][0].' · '.$evento['breve']) ?></small><?php endforeach; ?></button>
<?php else: ?><div class="escolar-dia"><span><?= $dia ?></span></div><?php endif; ?>
<?php endfor; ?></div></section><?php endforeach; ?>
</div>
<p class="texto-suave">Periodo de apreciación estudiantil: por definir.</p>
<dialog id="escolar-detalles" aria-labelledby="escolar-fecha"><h2 id="escolar-fecha"></h2><ul id="escolar-eventos"></ul><form method="dialog"><button class="btn btn-primario">Cerrar</button></form></dialog>
</section>
<style>
.escolar{margin-bottom:24px}.escolar-leyenda{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 24px}.escolar-marca{display:block;border-radius:4px;padding:3px 4px;font-size:.75rem;line-height:1.3;font-weight:600;overflow-wrap:anywhere}.escolar-clases{background:#dcece3;color:#19482e}.escolar-tramites{background:#fff0c9;color:#604400}.escolar-evaluacion{background:#e1eafb;color:#183e76}.escolar-suspension{background:#fbe3e2;color:#842923}.escolar-vacaciones{background:#ece3f7;color:#543279}.escolar-meses{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.escolar-mes{min-width:0}.escolar-mes h3{margin:0 0 12px}.escolar-semana,.escolar-dias{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px}.escolar-semana{text-align:center;font-size:.875rem;color:#536258;margin-bottom:6px}.escolar-dia{min-width:0;min-height:72px;padding:5px 2px;border:1px solid #e1e7e2;border-radius:6px;text-align:left;background:#fff;color:#203b2b;font:inherit;display:flex;flex-direction:column;gap:4px}.escolar-dia.con-evento{cursor:pointer;border-color:#98afa1}.escolar-dia.con-evento:hover{background:#f0f6f2}.escolar-dia.con-evento:focus-visible{outline:3px solid #28663f;outline-offset:2px}.escolar-dia>strong,.escolar-dia>span{padding:0 3px}.escolar #escolar-detalles{border:1px solid #c9d5cd;border-radius:14px;padding:24px;max-width:min(520px,calc(100vw - 32px));color:#203b2b}.escolar #escolar-detalles::backdrop{background:rgb(0 0 0 / .45)}.escolar #escolar-detalles li{margin:12px 0}.escolar #escolar-detalles h2{font-size:1.25rem}@media(max-width:1150px){.escolar-meses{grid-template-columns:1fr}}@media(min-width:1600px){.escolar-meses{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
<script>
(() => {
const eventos=<?= json_encode($eventos,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
const dialog=document.getElementById('escolar-detalles');
document.querySelectorAll('[data-escolar-fecha]').forEach(button=>button.addEventListener('click',()=>{
const fecha=button.dataset.escolarFecha;
document.getElementById('escolar-fecha').textContent=new Intl.DateTimeFormat('es-MX',{dateStyle:'full',timeZone:'UTC'}).format(new Date(fecha+'T12:00:00Z'));
const lista=document.getElementById('escolar-eventos');lista.replaceChildren();
(eventos[fecha]||[]).forEach(evento=>{const item=document.createElement('li');item.textContent=evento.detalle;lista.append(item);});
dialog.showModal();
}));
dialog.addEventListener('click',event=>{const rect=dialog.getBoundingClientRect();if(event.target===dialog&&(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom))dialog.close();});
})();
</script>
