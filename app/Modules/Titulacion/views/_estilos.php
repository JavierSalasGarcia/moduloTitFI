<style>
.tit-cabecera{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:18px}
.tit-cabecera h1{margin:0 0 4px}
.tit-cuadricula{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
.tit-opcion{display:flex;flex-direction:column;gap:6px;padding:16px;border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;text-decoration:none;color:inherit}
.tit-opcion:hover,.tit-opcion:focus-visible{border-color:var(--verde-700);box-shadow:0 2px 8px rgba(44,82,52,.12)}
.tit-opcion strong{color:var(--verde-900);font-size:16px}
.tit-opcion small{color:var(--gris-600)}
.tit-dos{display:grid;grid-template-columns:minmax(0,2.1fr) minmax(250px,1fr);gap:18px;align-items:start}
.tit-etapas{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px}
.tit-etapas li a,.tit-etapas li span{display:flex;gap:10px;align-items:center;padding:9px 10px;border-radius:8px;text-decoration:none;color:var(--texto);font-size:14px}
.tit-etapas li a:hover{background:var(--verde-050)}
.tit-etapas .num{flex:0 0 26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;background:var(--gris-100);color:var(--gris-600)}
.tit-etapas .completa .num{background:var(--ok);color:#fff}
.tit-etapas .actual .num{background:var(--oro);color:#fff}
.tit-etapas .actual a,.tit-etapas .seleccionada a,.tit-etapas .seleccionada span{background:var(--verde-050);font-weight:700}
.tit-etapas .bloqueada span{opacity:.55}
.tit-barra{height:8px;border-radius:6px;background:var(--gris-100);overflow:hidden}
.tit-barra>i{display:block;height:100%;background:var(--verde-700)}
.tit-doc{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px 14px;padding:12px 0;border-bottom:1px solid var(--gris-100)}
.tit-doc:last-child{border-bottom:0}
.tit-doc h4{margin:0 0 2px;font-size:15px}
.tit-doc .nota{font-size:13px;color:var(--gris-600);margin:0}
.tit-doc form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:8px 0 0}
.tit-doc input[type=file]{max-width:260px;font-size:13px}
.tit-obs{background:#FEF2F2;border-left:4px solid var(--error);padding:8px 10px;border-radius:4px;font-size:14px;margin:8px 0 0}
.tit-ok{background:#F0FDF4;border-left:4px solid var(--ok);padding:8px 10px;border-radius:4px;font-size:14px;margin:8px 0 0}
.tit-aviso{background:var(--oro-050);border-left:4px solid var(--oro);padding:10px 12px;border-radius:4px;font-size:14px;margin:0 0 14px}
.tit-lista-simple{margin:6px 0 0;padding-left:20px}
.tit-lista-simple li{margin:3px 0}
.tit-evento{padding:8px 0;border-bottom:1px solid var(--gris-100);font-size:13px}
.tit-evento:last-child{border-bottom:0}
.tit-evento time{display:block;color:var(--gris-600);font-size:12px}
.tit-campos{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.tit-campos .ancho{grid-column:1/-1}
.tit-crono{width:100%;border-collapse:collapse;font-size:13px}
.tit-crono th,.tit-crono td{border:1px solid var(--gris-100);padding:4px;text-align:center}
.tit-crono td:first-child{min-width:190px;text-align:left}
.tit-crono input[type=text]{width:100%}
.tit-chip{display:inline-block;padding:2px 9px;border-radius:12px;font-size:12px;font-weight:700}
.tit-chip.pend{background:#FFFBEB;color:var(--alerta)}
.tit-chip.rev{background:#EFF6FF;color:#1D4ED8}
.tit-chip.ok{background:#F0FDF4;color:var(--ok)}
.tit-chip.mal{background:#FEF2F2;color:var(--error)}
.tit-chip.na{background:var(--gris-100);color:var(--gris-600)}

.sem{display:inline-block;width:13px;height:13px;border-radius:50%;vertical-align:middle;margin-right:5px;border:1px solid rgba(0,0,0,.15)}
.sem.verde{background:#16A34A}.sem.ambar{background:#F59E0B}.sem.rojo{background:#DC2626}.sem.listo{background:#2563EB}.sem.cancelada{background:#9CA3AF}
.tit-linea{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:0}
.tit-linea li{flex:1 1 96px;min-width:88px;position:relative;padding:34px 4px 0;text-align:center;font-size:12.5px;color:var(--gris-600)}
.tit-linea li::before{content:'';position:absolute;top:10px;left:0;right:0;height:3px;background:var(--gris-100)}
.tit-linea li::after{content:'';position:absolute;top:3px;left:50%;margin-left:-8px;width:16px;height:16px;border-radius:50%;background:#fff;border:3px solid var(--gris-100)}
.tit-linea li.hecho{color:var(--texto)}.tit-linea li.hecho::before{background:var(--ok)}.tit-linea li.hecho::after{background:var(--ok);border-color:var(--ok)}
.tit-linea li.ahora{color:var(--oro-texto);font-weight:700}.tit-linea li.ahora::after{border-color:var(--oro);background:var(--oro-050)}
.tit-linea li.mal::after{border-color:var(--error);background:#FEF2F2}.tit-linea li.mal{color:var(--error);font-weight:700}
.tit-banner{background:#FFF7E6;border:1px solid #F59E0B;border-left-width:6px;padding:12px 14px;border-radius:6px;margin:0 0 16px;font-size:14px}
.tit-banner strong{display:block;margin-bottom:2px}
/* Dejar un trámite (borrar o poner en pausa) y retomarlo */
.estado-abandonado,.estado-cancelado{background:#F3F4F6;color:var(--gris-600);border:1px solid var(--gris-100)}
.tit-dejar{margin-top:4px;padding:0 2px;font-size:14px;color:var(--gris-600)}
.tit-dejar>summary{cursor:pointer;padding:8px 0;color:var(--gris-600)}
.tit-dejar>summary:focus-visible{outline:3px solid var(--oro);outline-offset:2px}
.tit-dejar p{margin:4px 0 10px}
.tit-confirma{max-width:760px;margin:0 auto}
.tit-confirma h2{margin:0 0 6px;font-size:22px;color:var(--verde-900);line-height:1.25}
.tit-confirma .tit-lead{font-size:16px;margin:0 0 14px}
.tit-confirma h3{font-size:15px;margin:16px 0 6px;color:var(--verde-900)}
.tit-confirma ul{margin:0;padding-left:20px}
.tit-confirma li{margin:4px 0;line-height:1.45}
.tit-entiendo{display:flex;gap:10px;align-items:flex-start;margin:18px 0 14px;font-size:15px;line-height:1.4}
.tit-entiendo input{width:20px;height:20px;margin:1px 0 0;flex:0 0 auto}
.tit-acciones{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.tit-abandonadas .tit-aband{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--gris-100)}
.tit-abandonadas .tit-aband:first-child{border-top:0}
.tit-abandonadas .tit-aband p{margin:2px 0 0;font-size:13.5px;color:var(--gris-600)}
.tit-abandonadas form{margin:0}
.tit-dos-abiertos .tit-aband{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid var(--oro-arena)}
@media(max-width:860px){.tit-dos{grid-template-columns:1fr}.tit-doc{grid-template-columns:1fr}}
</style>

<style>
/* Presentación guiada del trámite (prefijo tit-): solo se añaden clases; las anteriores no cambian. */
.tit-boton{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:10px 22px;border-radius:var(--radio);background:var(--verde-900);color:#fff;border:1px solid var(--verde-900);font-size:16px;font-weight:700;text-decoration:none;cursor:pointer}
.tit-boton:hover{background:#234228;color:#fff}
.tit-boton:focus-visible,.tit-via>summary:focus-visible,.tit-pliegue>summary:focus-visible,.tit-opc:focus-visible,.tit-etapa>summary:focus-visible,.tit-grupo>summary:focus-visible{outline:3px solid var(--oro);outline-offset:2px}
.tit-enlace-sec{font-size:14px}
.tit-hero{margin:0 0 22px;max-width:760px}
.tit-hero h2{margin:0 0 6px;font-size:26px;color:var(--verde-900);line-height:1.2}
.tit-hero p{margin:0;color:var(--gris-600);font-size:16px}
.tit-ideas{list-style:none;margin:14px 0 0;padding:0;display:grid;gap:8px}
.tit-ideas li{display:flex;gap:10px;align-items:baseline;font-size:15px}
.tit-ideas li::before{content:"✓";color:var(--ok);font-weight:700}
.tit-pregunta{margin:26px 0 12px;font-size:20px;color:var(--verde-900)}
.tit-vias{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;align-items:start}
.tit-via{border:2px solid var(--gris-100);border-radius:var(--radio);background:#fff}
.tit-via[open]{border-color:var(--verde-700)}
.tit-via>summary{list-style:none;cursor:pointer;padding:20px;display:block;border-radius:var(--radio)}
.tit-via>summary::-webkit-details-marker{display:none}
.tit-via>summary:hover{background:var(--verde-050)}
.tit-via .tit-via-titulo{display:flex;justify-content:space-between;align-items:center;gap:12px;font-size:19px;font-weight:700;color:var(--verde-900)}
.tit-via .tit-via-titulo::after{content:"+";font-size:26px;line-height:1;color:var(--verde-700)}
.tit-via[open] .tit-via-titulo::after{content:"–"}
.tit-via .tit-via-frase{display:block;margin-top:6px;color:var(--gris-600);font-size:15px}
.tit-via .tit-via-n{display:inline-block;margin-top:10px;padding:2px 10px;border-radius:12px;background:var(--verde-050);color:var(--verde-900);font-size:13px;font-weight:700}
.tit-opciones{list-style:none;margin:0;padding:0 12px 12px;display:grid;gap:8px}
.tit-opc{display:block;padding:12px 14px;border:1px solid var(--gris-100);border-radius:var(--radio);text-decoration:none;color:inherit;background:#fff}
.tit-opc:hover{border-color:var(--verde-700);background:var(--verde-050)}
.tit-opc strong{display:block;color:var(--verde-900);font-size:16px}
.tit-opc span{display:block;font-size:14px;color:var(--texto);margin-top:2px}
.tit-opc small{display:block;font-size:12px;color:var(--gris-600);margin-top:4px}
.tit-continua{border:2px solid var(--verde-700);background:linear-gradient(180deg,var(--verde-050),#fff);border-radius:var(--radio);padding:22px;margin:0 0 22px}
.tit-continua h2{margin:0 0 2px;font-size:22px;color:var(--verde-900)}
.tit-continua .tit-op{margin:0 0 12px;color:var(--gris-600);font-size:15px}
.tit-continua .tit-ahora{margin:14px 0 4px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--gris-600)}
.tit-continua .tit-paso-nombre{margin:0 0 4px;font-size:18px;font-weight:700}
.tit-continua .tit-paso-texto{margin:0 0 16px;font-size:15px}
.tit-barra-tit{height:10px;border-radius:6px;background:var(--gris-100);overflow:hidden}
.tit-barra-tit>i{display:block;height:100%;background:var(--verde-700)}
.tit-barra-tit+.tit-barra-leyenda{margin:6px 0 0;font-size:13px;color:var(--gris-600)}
.tit-pliegue{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;margin:0 0 14px}
.tit-pliegue>summary{cursor:pointer;padding:14px 18px;font-weight:700;color:var(--verde-900);list-style:none;display:flex;justify-content:space-between;gap:12px}
.tit-pliegue>summary::-webkit-details-marker{display:none}
.tit-pliegue>summary::after{content:"+";font-size:20px;line-height:1.1;color:var(--verde-700)}
.tit-pliegue[open]>summary::after{content:"–"}
.tit-pliegue>.tit-cuerpo{padding:0 18px 16px}
.tit-necesitas{border-left:5px solid var(--oro);background:var(--oro-050);border-radius:var(--radio);padding:16px 18px;margin:0 0 18px}
.tit-necesitas h2{margin:0 0 6px;font-size:19px;color:var(--verde-900)}
.tit-necesitas ul{margin:6px 0 0;padding-left:20px}
.tit-necesitas .tit-boton{margin-top:14px}
.tit-momentos{list-style:none;margin:8px 0 0;padding:0;counter-reset:mom;display:grid;gap:12px}
.tit-momentos li{position:relative;padding:0 0 0 46px;counter-increment:mom}
.tit-momentos li::before{content:counter(mom);position:absolute;left:0;top:0;width:32px;height:32px;border-radius:50%;background:var(--verde-900);color:#fff;display:grid;place-items:center;font-weight:700;font-size:15px}
.tit-momentos strong{display:block;color:var(--verde-900);font-size:16px}
.tit-momentos span{font-size:14px;color:var(--gris-600)}
.tit-siguiente{border:2px solid var(--verde-700);border-radius:var(--radio);background:linear-gradient(180deg,var(--verde-050),#fff);padding:20px 22px;margin:0 0 18px}
.tit-siguiente .tit-sobre{margin:0 0 4px;font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:var(--gris-600)}
.tit-siguiente h2{margin:0 0 6px;font-size:22px;color:var(--verde-900)}
.tit-siguiente p{margin:0 0 14px;font-size:15px}
.tit-siguiente.espera{border-color:var(--gris-100);background:#fff}
.tit-siguiente.listo{border-color:var(--ok)}
.tit-devuelto{background:#FEF2F2;border:1px solid #FCA5A5;border-left:6px solid var(--error);border-radius:var(--radio);padding:12px 16px;margin:0 0 18px}
.tit-devuelto strong{display:block;margin-bottom:4px;color:var(--error)}
.tit-devuelto ul{margin:4px 0 0;padding-left:20px}
.tit-progreso{list-style:none;margin:0 0 6px;padding:0;display:flex;gap:6px}
.tit-progreso li{flex:1 1 0;min-width:0}
.tit-progreso a,.tit-progreso span.tit-seg{display:block;text-decoration:none;color:inherit}
.tit-progreso .tit-barrita{display:block;height:8px;border-radius:5px;background:var(--gris-100)}
.tit-progreso .hecho .tit-barrita{background:var(--ok)}
.tit-progreso .actual .tit-barrita{background:var(--oro)}
.tit-progreso .tit-nom{display:block;margin-top:6px;font-size:12.5px;line-height:1.3;color:var(--gris-600);overflow-wrap:anywhere}
.tit-progreso .actual .tit-nom{color:var(--texto);font-weight:700}
.tit-progreso .hecho .tit-nom{color:var(--texto)}
.tit-progreso-leyenda{margin:0 0 18px;font-size:14px;color:var(--gris-600)}
.tit-etapas-lista{display:grid;gap:12px}
.tit-etapa{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff}
.tit-etapa>summary{cursor:pointer;list-style:none;display:flex;gap:12px;align-items:center;padding:14px 18px}
.tit-etapa>summary::-webkit-details-marker{display:none}
.tit-etapa .tit-marca{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:14px;font-weight:700;background:var(--gris-100);color:var(--gris-600)}
.tit-etapa.hecha .tit-marca{background:var(--ok);color:#fff}
.tit-etapa.actual .tit-marca{background:var(--oro);color:#fff}
.tit-etapa .tit-et-t{flex:1 1 auto;font-weight:700;font-size:16px}
.tit-etapa .tit-et-s{display:block;font-weight:400;font-size:13px;color:var(--gris-600)}
.tit-etapa.futura{background:#FAFAF8}
.tit-etapa.futura .tit-et-t{color:var(--gris-600)}
.tit-etapa>.tit-cuerpo{padding:0 18px 18px}
.tit-etapa.actual{border:2px solid var(--oro)}
.tit-etapa.abierta-sola>.tit-cuerpo{padding-top:4px}
.tit-entregado{list-style:none;margin:6px 0 0;padding:0}
.tit-entregado li{padding:6px 0;border-bottom:1px solid var(--gris-100);font-size:14px}
.tit-entregado li:last-child{border-bottom:0}
.tit-grupo{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;margin:0 0 10px}
.tit-grupo>summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 14px;font-weight:700;font-size:14px}
.tit-grupo>summary::-webkit-details-marker{display:none}
.tit-grupo .tit-cuenta{font-weight:400;font-size:12.5px;color:var(--gris-600);white-space:nowrap}
.tit-grupo.actual{border:2px solid var(--oro)}
.tit-grupo.futura>summary{color:var(--gris-600)}
.tit-grupo>.ruta{padding:2px 14px 8px}
.ruta .tit-punto{width:8px;height:8px;border-radius:50%;background:var(--gris-600);display:block}
.ruta .pendiente .ic.tit-ic-punto{background:transparent}
.tit-etapa .tit-et-cab{display:flex;gap:12px;align-items:center;padding:14px 18px}
.tit-etapa .nota{font-size:13px;color:var(--gris-600)}
@media(max-width:640px){
  .tit-progreso{gap:4px}
  .tit-progreso li:not(.actual) .tit-nom{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .tit-progreso li.actual{flex:3 1 0}
}
.tit-faq{margin-top:26px}
.tit-aviso-fase{font-size:14px;color:var(--gris-600)}
@media(max-width:640px){
  .tit-hero h2{font-size:22px}
  .tit-progreso .tit-nom{font-size:11px}
  .tit-continua,.tit-siguiente{padding:16px}
  .tit-boton{width:100%}
}
@media(prefers-reduced-motion:no-preference){
  .tit-opc,.tit-via>summary,.tit-boton{transition:background-color .15s ease,border-color .15s ease}
}
.tit-progreso li:not(.actual):not(.hecho) .tit-nom{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.tit-recorrido>summary{cursor:pointer;font-weight:700;color:var(--verde-900);font-size:15px;list-style:none;display:flex;justify-content:space-between;align-items:center}
.tit-recorrido>summary::-webkit-details-marker{display:none}
.tit-recorrido>summary::after{content:"+";font-size:20px;line-height:1;color:var(--verde-700)}
.tit-recorrido[open]>summary::after{content:"–"}
.tit-recorrido>summary:focus-visible{outline:2px solid var(--verde-700);outline-offset:4px;border-radius:4px}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
</style>
