<style>
.esp-cabecera{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between;margin-bottom:16px}
.esp-semana{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
.esp-dia{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;padding:8px;min-height:120px}
.esp-dia h3{margin:0 0 4px;font-size:13px}
.esp-dia.hoy{border-color:var(--verde-700)}
.esp-dia .horario{font-size:11px;color:var(--gris-600);margin:0 0 6px}
.esp-uso{border-left:4px solid var(--verde-700);background:var(--verde-050);border-radius:4px;padding:5px 7px;margin:0 0 6px;font-size:12.5px}
.esp-uso.tit{border-left-color:var(--oro);background:var(--oro-050)}
.esp-uso b{display:block}
.esp-uso small{display:block;color:var(--gris-600)}
.esp-uso form{margin-top:4px}
.esp-campos{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.esp-campos .ancho{grid-column:1/-1}
.esp-horario{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
@media (max-width:900px){.esp-semana{grid-template-columns:1fr}.esp-dia{min-height:0}}
</style>
