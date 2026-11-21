<style>
/* Módulo Consejos: estilos propios con prefijo cns-. Reutiliza las variables del sitio. */
.cns-pila{display:flex;flex-direction:column;gap:16px}
.cns-cab{display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;justify-content:space-between}
.cns-cab h1,.cns-cab h2{margin:0;font-size:20px;color:var(--verde-900)}
.cns-sub{margin:4px 0 0;color:var(--gris-600);font-size:14px;line-height:1.5}
.cns-acciones{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.cns-pasos{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
.cns-paso{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;padding:14px 16px;display:flex;flex-direction:column;gap:8px}
.cns-paso h3{margin:0;font-size:15px;color:var(--verde-900)}
.cns-paso p{margin:0;font-size:13px;color:var(--gris-600);line-height:1.45}
.cns-paso.hecho{border-color:var(--verde-700);background:var(--verde-050)}
.cns-etq{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:2px 10px;background:var(--gris-100);color:var(--gris-600);white-space:nowrap}
.cns-etq.ok{background:var(--verde-050);color:var(--verde-900)}
.cns-etq.av{background:var(--oro-050);color:var(--oro-texto)}
.cns-etq.no{background:#fdecec;color:#9b1c1c}
.cns-tabla{width:100%;border-collapse:collapse;font-size:14px}
.cns-tabla th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--gris-600);padding:8px 6px;border-bottom:1px solid var(--gris-100)}
.cns-tabla td{padding:9px 6px;border-bottom:1px dashed var(--gris-100);vertical-align:top}
.cns-clave{font-weight:800;color:var(--verde-900);white-space:nowrap}
.cns-form label{display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:14px;margin:0 0 12px}
.cns-form .cns-fila{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0 14px}
.cns-form input[type=text],.cns-form input[type=date],.cns-form input[type=time],.cns-form select,.cns-form textarea{width:100%;font:inherit}
.cns-form textarea{min-height:90px}
.cns-ayuda{font-size:13px;color:var(--gris-600);line-height:1.5;margin:2px 0 0;font-weight:400}
/* Lienzo donde se pega desde Word */
.cns-lienzo{border:2px dashed var(--gris-200,#cbd5e1);border-radius:var(--radio);background:#fff;padding:12px;min-height:120px;outline:none;font-size:15px;line-height:1.5;overflow-x:auto}
.cns-lienzo:focus{border-color:var(--verde-700);border-style:solid}
.cns-lienzo:empty::before{content:attr(data-vacio);color:#94a3b8}
.cns-lienzo p{margin:0 0 8px}
.cns-lienzo ul,.cns-lienzo ol{margin:0 0 8px 22px;padding:0}
.cns-lienzo table,.cns-doc table{border-collapse:collapse;margin:6px 0 10px;max-width:100%}
.cns-lienzo td,.cns-lienzo th,.cns-doc td,.cns-doc th{border:1px solid #64748b;padding:4px 8px;vertical-align:top;font-size:14px}
.cns-lienzo th,.cns-doc th{background:#f1f5f9}
.cns-herr{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 6px}
.cns-herr button{min-height:34px}
.cns-doc p{margin:0 0 8px}
.cns-doc ul,.cns-doc ol{margin:0 0 8px 22px;padding:0}
.cns-doc{overflow-x:auto}
.cns-personas{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0}
.cns-chip{display:inline-flex;gap:6px;align-items:center;background:var(--verde-050);color:var(--verde-900);border-radius:999px;padding:4px 10px;font-size:13px;font-weight:600}
.cns-chip button{border:0;background:none;cursor:pointer;font-size:16px;line-height:1;color:inherit}
.cns-sug{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;max-height:220px;overflow:auto}
.cns-sug button{display:block;width:100%;text-align:left;border:0;background:none;padding:8px 12px;cursor:pointer;font:inherit}
.cns-sug button:hover{background:var(--verde-050)}
.cns-mark mark,mark{background:#fef08a;padding:0 1px}
.cns-res{border:1px solid var(--gris-100);border-radius:var(--radio);background:#fff;padding:12px 16px}
.cns-res p{margin:6px 0 0;font-size:14px;line-height:1.5}
.cns-res small{color:var(--gris-600)}
.cns-amarillo{background:#fef9c3}
</style>
