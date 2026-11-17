<style>
/* Módulo Posgrado: estilos propios con prefijo pos-. Móvil primero; reutiliza las variables del sitio. */
.pos-cabecera{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.pos-cabecera p{margin:2px 0}
.pos-volver{font-size:13px}
.pos-pila{display:flex;flex-direction:column;gap:16px}

/* «Tu siguiente paso»: una sola acción principal */
.pos-paso{border:2px solid var(--verde-700);border-radius:var(--radio);background:#fff;padding:18px 18px 16px;box-shadow:0 2px 10px rgba(44,82,52,.10)}
.pos-paso.personal{border-color:var(--oro);background:var(--oro-050)}
.pos-paso.nadie{border-color:var(--gris-100)}
.pos-paso-etiqueta{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--verde-700);margin:0 0 4px}
.pos-paso.personal .pos-paso-etiqueta{color:var(--oro-texto)}
.pos-paso h2{margin:0 0 6px;font-size:20px;line-height:1.25;color:var(--verde-900)}
.pos-paso p{margin:0 0 12px;font-size:15px;line-height:1.5}
.pos-paso .btn{min-height:44px;padding:10px 20px;font-size:15px}

/* Indicador de los cuatro momentos */
.pos-momentos{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:4px;counter-reset:mom}
.pos-momentos li{position:relative;text-align:center;font-size:12px;line-height:1.3;color:var(--gris-600);padding-top:36px}
.pos-momentos li::before{content:'';position:absolute;top:14px;left:-50%;width:100%;height:3px;background:var(--gris-100);z-index:0}
.pos-momentos li:first-child::before{display:none}
.pos-momentos .pos-num{position:absolute;top:0;left:50%;margin-left:-15px;width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;background:#fff;border:3px solid var(--gris-100);color:var(--gris-600);z-index:1}
.pos-momentos li.hecho{color:var(--texto)}
.pos-momentos li.hecho::before{background:var(--ok)}
.pos-momentos li.hecho .pos-num{background:var(--ok);border-color:var(--ok);color:#fff}
.pos-momentos li.actual{color:var(--verde-900);font-weight:700}
.pos-momentos li.actual::before{background:var(--ok)}
.pos-momentos li.actual .pos-num{border-color:var(--oro);background:var(--oro-050);color:var(--oro-texto)}
.pos-momentos a{color:inherit;text-decoration:none;display:block}
.pos-momentos a:hover{text-decoration:underline}

/* Momentos plegables */
.pos-momento{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff}
.pos-momento>summary{list-style:none;cursor:pointer;display:flex;gap:10px;align-items:center;padding:14px 16px;font-weight:700;color:var(--verde-900);font-size:16px}
.pos-momento>summary::-webkit-details-marker{display:none}
.pos-momento>summary::after{content:'';margin-left:auto;width:9px;height:9px;border-right:2px solid var(--gris-600);border-bottom:2px solid var(--gris-600);transform:rotate(45deg);transition:transform .15s}
.pos-momento[open]>summary::after{transform:rotate(-135deg)}
.pos-momento>summary small{font-weight:400;color:var(--gris-600);font-size:13px}
.pos-momento-cuerpo{padding:0 16px 16px}
.pos-momento.actual{border-color:var(--verde-700)}
.pos-sub{font-size:15px;color:var(--verde-900);margin:18px 0 8px}

/* Avance de requisitos */
.pos-avance{display:flex;flex-wrap:wrap;align-items:center;gap:6px 12px;margin:4px 0 12px}
.pos-avance strong{font-size:15px}
.pos-barra{flex:1 1 180px;height:10px;border-radius:6px;background:var(--gris-100);overflow:hidden}
.pos-barra>i{display:block;height:100%;background:var(--verde-700);border-radius:6px}

/* Grupos de requisitos */
.pos-grupo{border-top:1px solid var(--gris-100)}
.pos-grupo>summary{cursor:pointer;padding:12px 2px;display:flex;gap:8px;align-items:center;font-weight:600;list-style:none}
.pos-grupo>summary::-webkit-details-marker{display:none}
.pos-grupo>summary::before{content:'';width:7px;height:7px;border-right:2px solid var(--gris-600);border-bottom:2px solid var(--gris-600);transform:rotate(-45deg);transition:transform .15s;margin-right:4px}
.pos-grupo[open]>summary::before{transform:rotate(45deg)}
.pos-grupo>summary .pos-cuenta{margin-left:auto;font-weight:400;font-size:13px;color:var(--gris-600)}
.pos-lista{list-style:none;margin:0 0 8px;padding:0}

/* Renglón de documento */
.pos-doc{display:grid;grid-template-columns:28px minmax(0,1fr);gap:4px 10px;padding:12px 0;border-top:1px dashed var(--gris-100)}
.pos-doc:first-child{border-top:0}
.pos-doc:target{background:var(--oro-050);outline:2px solid var(--oro);outline-offset:2px;border-radius:6px}
.pos-icono{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:14px;font-weight:700}
.pos-icono .icono{width:18px;height:18px}
.pos-doc h4{margin:2px 0;font-size:15px;line-height:1.3}
.pos-doc .pos-ayuda{margin:2px 0 0;font-size:13px;color:var(--gris-600);line-height:1.45}
.pos-doc .pos-legal{font-size:11.5px;color:var(--gris-600);margin:2px 0 0}
.pos-doc-acciones{grid-column:2;display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:6px}
.pos-doc-acciones form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0;min-width:0;max-width:100%}
.pos-doc-acciones input[type=file]{flex:1 1 200px;width:auto;min-width:0;max-width:100%;font-size:13px;margin:0;padding:5px 6px}
.pos-reemplazar>summary{cursor:pointer;font-size:13px;font-weight:600;color:var(--verde-700);padding:4px 0}
.pos-reemplazar[open]>summary{margin-bottom:6px}
.pos-doc-acciones textarea{min-width:220px;flex:1 1 220px;margin:0}
.pos-estado{display:inline-flex;align-items:center;gap:4px;padding:1px 9px;border-radius:12px;font-size:12px;font-weight:700;vertical-align:middle;white-space:nowrap}
.pos-e-pend{background:var(--gris-100);color:var(--gris-600)}
.pos-e-rev{background:#E3ECFA;color:#1D4E89}
.pos-e-ok{background:#E1EFE4;color:#22502D}
.pos-e-mal{background:#FEF2F2;color:var(--error)}
.pos-e-na{background:#fff;color:var(--gris-600);border:1px dashed var(--gris-100)}
.pos-icono.pos-e-pend{border:2px solid var(--gris-100);background:#fff}
.pos-obs{grid-column:2;background:#FEF2F2;border-left:4px solid var(--error);padding:8px 10px;border-radius:4px;font-size:14px;margin:6px 0 0;white-space:pre-wrap}
.pos-nota{background:var(--oro-050);border-left:4px solid var(--oro);padding:10px 12px;border-radius:4px;font-size:14px;margin:8px 0 12px;line-height:1.5}
.pos-ok{background:#F0FDF4;border-left:4px solid var(--ok);padding:10px 12px;border-radius:4px;font-size:14px;margin:8px 0 12px}

/* Formularios */
.pos-campos input[type=time],.pos-campos input[type=tel],.formulario input[type=time]{display:block;width:100%;padding:8px 10px;border:1px solid var(--borde-campo);border-radius:6px;background:var(--blanco);font-family:var(--fuente);font-size:14px;color:var(--texto);margin-top:4px}
.pos-campos{display:grid;grid-template-columns:1fr;gap:4px 14px}
.pos-campos .ancho{grid-column:1/-1}
.pos-campos label{margin-bottom:8px}
.pos-campos small{display:block;font-weight:400;color:var(--gris-600);font-size:12px;margin-top:2px}
.pos-radios{display:flex;flex-wrap:wrap;gap:6px 16px;margin:6px 0 10px;font-weight:400}
.pos-radios label{display:inline-flex;align-items:center;gap:6px;font-weight:400;margin:0}
.pos-radios input{width:auto;margin:0}
.pos-fieldset{border:1px solid var(--gris-100);border-radius:var(--radio);padding:10px 12px 4px;margin:0 0 12px}
.pos-fieldset legend{font-weight:700;color:var(--verde-900);padding:0 6px;font-size:14px}
.pos-miembro{display:grid;grid-template-columns:1fr;gap:0 10px;padding:10px 0;border-top:1px dashed var(--gris-100)}
.pos-miembro:first-of-type{border-top:0}
.pos-miembro .pos-n{font-weight:700;color:var(--verde-900);font-size:13px}
.pos-check{display:inline-flex !important;align-items:center;gap:6px;font-weight:400 !important}
.pos-check input{width:auto;margin:0}

/* Datos en solo lectura */
.pos-dl{display:grid;grid-template-columns:1fr;gap:2px 14px;margin:0}
.pos-dl dt{font-size:12px;color:var(--gris-600);margin-top:8px}
.pos-dl dd{margin:0;font-weight:600;word-break:break-word}

/* Bandeja */
.pos-contadores{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:0 0 16px}
.pos-contador{display:block;background:#fff;border:1px solid var(--gris-100);border-radius:var(--radio);padding:12px;text-decoration:none;color:inherit}
.pos-contador:hover,.pos-contador.activo{border-color:var(--verde-700);text-decoration:none}
.pos-contador b{display:block;font-size:24px;color:var(--verde-900)}
.pos-contador span{font-size:12.5px;color:var(--gris-600)}
.pos-filtros{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:12px}
.pos-filtros label{margin:0;min-width:150px;flex:1 1 150px}
.pos-evento{padding:8px 0;border-bottom:1px solid var(--gris-100);font-size:13px}
.pos-evento time{display:block;color:var(--gris-600);font-size:12px}
.pos-tarjetas{display:grid;grid-template-columns:1fr;gap:12px}
.pos-momento-info{display:flex;gap:12px;align-items:flex-start;padding:12px;border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff}
.pos-momento-info .pos-num{flex:0 0 32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--verde-050);color:var(--verde-900)}
.pos-momento-info h3{margin:0 0 4px;font-size:15px;color:var(--verde-900)}
.pos-momento-info p{margin:0;font-size:14px;line-height:1.45}

.pos-paso a:focus-visible,.pos-momento>summary:focus-visible,.pos-grupo>summary:focus-visible,.pos-contador:focus-visible,.pos-momentos a:focus-visible{outline:3px solid var(--oro);outline-offset:2px;border-radius:6px}

@media (min-width:700px){
  .pos-campos{grid-template-columns:1fr 1fr}
  .pos-miembro{grid-template-columns:24px minmax(0,2fr) minmax(0,1.3fr) minmax(0,1.4fr) auto;align-items:end}
  .pos-miembro label{margin-bottom:0}
  .pos-dl{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pos-contadores{grid-template-columns:repeat(6,1fr)}
  .pos-tarjetas{grid-template-columns:1fr 1fr}
  .pos-momentos li{font-size:13px}
}
@media (max-width:420px){.pos-momentos li{font-size:11px}}
</style>
