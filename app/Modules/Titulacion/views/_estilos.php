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
@media(max-width:860px){.tit-dos{grid-template-columns:1fr}.tit-doc{grid-template-columns:1fr}}
</style>
