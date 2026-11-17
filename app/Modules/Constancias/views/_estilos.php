<style>
/* Módulo Constancias: estilos propios con prefijo const-. Móvil primero; reutiliza las variables del sitio. */
.const-pila{display:flex;flex-direction:column;gap:16px}
.const-frase{margin:0 0 4px;color:var(--gris-600);font-size:15px;line-height:1.5}
.const-cabecera{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between}
.const-cabecera h2{margin:0;font-size:18px;color:var(--verde-900)}
.const-filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end}
.const-filtros label{display:flex;flex-direction:column;gap:4px;font-size:13px;font-weight:600;margin:0}
.const-filtros input,.const-filtros select{min-height:40px}
.const-filtros .const-q{flex:1 1 220px}
.const-filtros .const-q input{width:100%}
.const-alcance{font-size:13px;color:var(--gris-600);margin:8px 0 0}
.const-depto{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff}
.const-depto>summary{list-style:none;cursor:pointer;display:flex;gap:10px;align-items:center;padding:14px 16px;font-weight:700;color:var(--verde-900);font-size:16px}
.const-depto>summary::-webkit-details-marker{display:none}
.const-depto>summary::after{content:'';margin-left:auto;flex:none;width:9px;height:9px;border-right:2px solid var(--gris-600);border-bottom:2px solid var(--gris-600);transform:rotate(45deg);transition:transform .15s}
.const-depto[open]>summary::after{transform:rotate(-135deg)}
.const-cuenta{font-weight:400;font-size:13px;color:var(--gris-600);white-space:nowrap}
.const-depto-cuerpo{padding:0 16px 12px}
.const-lista{width:100%;border-collapse:collapse;font-size:14px}
.const-lista th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--gris-600);padding:8px 6px;border-bottom:1px solid var(--gris-100)}
.const-lista td{padding:10px 6px;border-bottom:1px dashed var(--gris-100);vertical-align:top}
.const-lista tr:last-child td{border-bottom:0}
.const-lista small{color:var(--gris-600)}
.const-acc .btn{white-space:nowrap}
.const-estado{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:2px 10px;white-space:nowrap}
.const-estado.firmada{background:var(--verde-050);color:var(--verde-900)}
.const-estado.en_firma,.const-estado.borrador{background:var(--oro-050);color:var(--oro-texto)}
.const-estado.devuelta{background:#fdecec;color:#9b1c1c}
.const-estado.cancelada{background:var(--gris-100);color:var(--gris-600)}
.const-vacio{text-align:center;padding:28px 16px}
.const-vacio h2{margin:0 0 6px;font-size:18px;color:var(--verde-900)}
.const-vacio p{margin:0 auto;max-width:520px;color:var(--gris-600);line-height:1.5}
.const-form .const-modo{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 10px;border:0;padding:0}
.const-form .const-modo label{display:flex;gap:8px;align-items:center;border:1px solid var(--gris-100);border-radius:var(--radio);padding:10px 14px;cursor:pointer;font-weight:600;margin:0}
.const-form .const-modo label:has(input:checked){border-color:var(--verde-700);background:var(--verde-050)}
.const-form textarea.const-texto{min-height:240px;font-family:inherit;line-height:1.5}
.const-ayuda{font-size:13px;color:var(--gris-600);line-height:1.5;margin:4px 0 0}
p.const-ayuda{margin:6px 0 14px}
.const-ayuda code{background:var(--gris-bg);padding:0 4px;border-radius:4px}
.const-doble{display:grid;grid-template-columns:1fr;gap:0 16px}
.const-error{border-left:4px solid var(--error);background:#fdecec;padding:10px 14px;border-radius:var(--radio);margin-bottom:12px}
.const-nota{border-left:4px solid var(--oro);background:var(--oro-050);padding:10px 14px;border-radius:var(--radio);font-size:14px;line-height:1.5}
.const-nota ul{margin:6px 0 0;padding-left:18px}
.const-datos{display:grid;grid-template-columns:1fr;gap:8px 18px;margin:0}
.const-datos dt{font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--gris-600);margin:0}
.const-datos dd{margin:0 0 6px;font-weight:600;overflow-wrap:anywhere}
.const-acciones{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:14px}
.const-acciones form{margin:0}
.const-acciones .btn-primario{min-height:44px;padding:10px 20px;font-size:15px}
/* Vista previa del cuerpo en HTML */
.const-hoja{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;padding:18px;font-size:15px;line-height:1.55;overflow-wrap:anywhere}
.const-hoja .const-dest{margin:0 0 10px}
.const-hoja .const-dest strong{text-transform:uppercase}
.const-hoja p{margin:0 0 10px;white-space:pre-line}
.const-hoja p.C{text-align:center}.const-hoja p.R{text-align:right}.const-hoja p.J{text-align:justify}
.const-tabla-envoltura{overflow-x:auto;margin:0 0 12px}
.const-hoja table{border-collapse:collapse;width:100%;font-size:13px}
.const-hoja th,.const-hoja td{border:1px solid #9aa59c;padding:5px 7px;vertical-align:top;white-space:pre-line;text-align:left;overflow-wrap:normal;word-break:normal;min-width:4em}
.const-hoja th{background:#e6ece7}
.const-historial{list-style:none;margin:0;padding:0;font-size:14px}
.const-historial li{padding:8px 0;border-top:1px dashed var(--gris-100)}
.const-historial q{display:block;margin-top:4px;color:var(--texto);font-style:italic}
.const-verif{max-width:560px;margin:0 auto}
.const-verif .const-sello{font-size:20px;font-weight:700;margin:0 0 10px}
.const-verif .const-sello.si{color:var(--verde-900)}
.const-verif .const-sello.no{color:#9b1c1c}
.const-huella{font-family:monospace;font-size:12px;word-break:break-all}
@media (min-width:720px){
  .const-doble{grid-template-columns:1fr 1fr}
  .const-datos{grid-template-columns:repeat(2,minmax(0,1fr))}
}
/* En pantallas angostas cada constancia se muestra como tarjeta */
@media (max-width:719px){
  .const-hoja p.J{text-align:left}
  .const-lista thead{display:none}
  .const-lista,.const-lista tbody,.const-lista tr,.const-lista td{display:block;width:100%}
  .const-lista tr{padding:10px 0;border-bottom:1px dashed var(--gris-100)}
  .const-lista tr:last-child{border-bottom:0}
  .const-lista td{border:0;padding:2px 0}
  .const-lista td[data-et]::before{content:attr(data-et) ': ';color:var(--gris-600);font-size:12px}
  .const-lista td.const-acc{padding-top:8px}
}
</style>
