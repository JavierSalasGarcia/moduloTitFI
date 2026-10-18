import json
import os
import glob
import sys

sys.stdout.reconfigure(encoding='utf-8')

# Load PRDI and preliminar_fi
prdi_path = r"D:\Proyectos\plataformaFI\Plan\PRDI.json"
prelim_path = r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json"

with open(prdi_path, "r", encoding="utf-8") as f:
    prdi_raw = json.load(f)

with open(prelim_path, "r", encoding="utf-8") as f:
    prelim_raw = json.load(f)

prdi_projs = {}
for pilar in prdi_raw.get("pilares", []):
    for proj in pilar.get("proyectos", []):
        prdi_projs[str(proj.get("numero") or proj.get("id"))] = proj

prelim_projs = {str(p.get("numero") or p.get("id")): p for p in prelim_raw.get("proyectos", [])}

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

results = []

diag_dir = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"

for pid in target_ids:
    s_pid = str(pid)
    p_prdi = prdi_projs.get(s_pid, {})
    p_fi = prelim_projs.get(s_pid, {})
    
    # Find diagnosis file
    diag_files = glob.glob(os.path.join(diag_dir, f"*proy_{pid:02d}*.txt"))
    diag_content = ""
    diag_file_name = "NO ENCONTRADO"
    if diag_files:
        diag_file_name = os.path.basename(diag_files[0])
        with open(diag_files[0], "r", encoding="utf-8") as df:
            diag_content = df.read()
            
    res = {
        "proyecto": pid,
        "nombre": p_fi.get("nombre") or p_prdi.get("nombre"),
        "diag_file": diag_file_name,
        "diag_length": len(diag_content),
        "obj_gral_prdi": p_prdi.get("objetivo_general"),
        "obj_gral_fi": p_fi.get("objetivo_general"),
        "politicas_prdi_count": len(p_prdi.get("politicas", [])),
        "politicas_fi_count": len(p_fi.get("politicas", [])),
        "politicas_fi": [p if isinstance(p, str) else p.get("descripcion") for p in p_fi.get("politicas", [])],
        "objs_especificos_fi_count": len(p_fi.get("objetivos_especificos", [])),
        "objs_especificos_prdi_count": len(p_prdi.get("objetivos_especificos", [])),
        "estrategias_fi_count": sum(len(o.get("estrategias", [])) for o in p_fi.get("objetivos_especificos", [])),
        "metas_fi_count": sum(sum(len(e.get("metas", [])) for e in o.get("estrategias", [])) for o in p_fi.get("objetivos_especificos", [])),
        "metas_prdi_count": sum(sum(len(e.get("metas", [])) for e in o.get("estrategias", [])) for o in p_prdi.get("objetivos_especificos", [])),
        "metas_ref_base_count": len(p_fi.get("referencia_base_2026", {}).get("metas", [])),
        "detalles_alineacion": []
    }
    
    # Check alignment of each objective
    for oi, o in enumerate(p_fi.get("objetivos_especificos", [])):
        o_txt = o.get("descripcion") or o.get("objetivo")
        for ei, e in enumerate(o.get("estrategias", [])):
            e_txt = e.get("descripcion") or e.get("estrategia")
            metas_e = e.get("metas", [])
            for mi, m in enumerate(metas_e):
                m_txt = m.get("descripcion") or m.get("meta")
                res["detalles_alineacion"].append({
                    "obj_idx": oi + 1,
                    "obj_txt": o_txt,
                    "strat_idx": ei + 1,
                    "strat_txt": e_txt,
                    "meta_idx": mi + 1,
                    "meta_txt": m_txt
                })
                
    results.append(res)

print("Alignment inspection complete for all 12 projects.")
for r in results:
    print(f"P{r['proyecto']:02d}: {r['nombre'][:35]} | Diag={r['diag_file']} | Objs FI={r['objs_especificos_fi_count']} (PRDI={r['objs_especificos_prdi_count']}) | Estrat FI={r['estrategias_fi_count']} | Metas FI={r['metas_fi_count']} (PRDI={r['metas_prdi_count']}) | Metas RefBase={r['metas_ref_base_count']}")
