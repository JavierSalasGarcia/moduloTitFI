import json
import os
import glob
import sys

sys.stdout.reconfigure(encoding='utf-8')

prdi_path = r"D:\Proyectos\plataformaFI\Plan\PRDI.json"
prelim_path = r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json"
diag_dir = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"

with open(prdi_path, "r", encoding="utf-8") as f:
    prdi = json.load(f)

with open(prelim_path, "r", encoding="utf-8") as f:
    prelim = json.load(f)

prdi_map = {}
for pilar in prdi.get("pilares", []):
    for p in pilar.get("proyectos", []):
        prdi_map[p["numero"]] = p

prelim_map = {p["numero"]: p for p in prelim.get("proyectos", [])}

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

audit_results = {}

for pid in target_ids:
    p_prdi = prdi_map.get(pid, {})
    p_fi = prelim_map.get(pid, {})
    
    # Load diagnosis text
    diag_files = glob.glob(os.path.join(diag_dir, f"*proy_{pid:02d}*.txt"))
    diag_text = ""
    diag_name = ""
    if diag_files:
        diag_name = os.path.basename(diag_files[0])
        with open(diag_files[0], "r", encoding="utf-8") as df:
            diag_text = df.read()
            
    # PRDI items
    prdi_objs = p_prdi.get("objetivos_especificos", [])
    prdi_metas = []
    for o in prdi_objs:
        for m in o.get("metas_institucionales", []):
            prdi_metas.append(m)
            
    # FI items
    fi_objs = p_fi.get("objetivos_especificos", [])
    fi_pols = p_fi.get("politicas", [])
    fi_metas = []
    fi_estrats = []
    for o in fi_objs:
        for e in o.get("estrategias", []):
            fi_estrats.append(e)
            for m in e.get("metas", []):
                fi_metas.append(m)
                
    ref_metas = p_fi.get("referencia_base_2026", {}).get("metas", [])
    
    # Check syntax of FI Objs
    objs_syntax_issues = []
    for oi, o in enumerate(fi_objs):
        desc = o.get("descripcion", "")
        # Rule: starts with infinitive of achievement (Incrementar, Mejorar, Lograr, etc.)
        first_word = desc.split()[0].lower() if desc.split() else ""
        if not (first_word.endswith("ar") or first_word.endswith("er") or first_word.endswith("ir")):
            objs_syntax_issues.append(f"Obj {oi+1} no inicia con verbo en infinitivo: '{first_word}'")
            
    # Check syntax of FI Policies
    pols_syntax_issues = []
    for pi, pol in enumerate(fi_pols):
        ptxt = pol if isinstance(pol, str) else pol.get("descripcion", "")
        # Rule: Elemento/Poblacion + Verbo en futuro (garantizará, promoverá, etc.) + Condicion soporte
        words = ptxt.lower().split()
        has_future_verb = any(w.endswith("rá") or w.endswith("rán") or w.endswith("remos") for w in words[:6])
        if not has_future_verb:
            pols_syntax_issues.append(f"Política {pi+1} podría no denotar obligatoriedad en futuro: '{ptxt[:50]}...'")

    # Check syntax of FI Strategies
    estrats_syntax_issues = []
    for ei, e in enumerate(fi_estrats):
        etxt = e.get("descripcion", "")
        first_word = etxt.split()[0].lower() if etxt.split() else ""
        if not (first_word.endswith("ar") or first_word.endswith("er") or first_word.endswith("ir")):
            estrats_syntax_issues.append(f"Estrategia {ei+1} no inicia con infinitivo: '{first_word}'")
            
    # Check syntax of FI Metas
    metas_syntax_issues = []
    for mi, m in enumerate(fi_metas):
        mtxt = m.get("descripcion", "")
        first_word = mtxt.split()[0].lower() if mtxt.split() else ""
        if not (first_word.endswith("ar") or first_word.endswith("er") or first_word.endswith("ir")):
            metas_syntax_issues.append(f"Meta {mi+1} no inicia con verbo en infinitivo: '{first_word}'")
            
    audit_results[pid] = {
        "nombre": p_fi.get("nombre") or p_prdi.get("nombre"),
        "diag_name": diag_name,
        "diag_size_bytes": len(diag_text.encode('utf-8')),
        "count_prdi_objs": len(prdi_objs),
        "count_fi_objs": len(fi_objs),
        "count_fi_pols": len(fi_pols),
        "count_fi_estrats": len(fi_estrats),
        "count_prdi_metas": len(prdi_metas),
        "count_fi_metas": len(fi_metas),
        "count_ref_metas": len(ref_metas),
        "metas_count_match": (len(prdi_metas) == len(fi_metas) == len(ref_metas)),
        "objs_syntax_issues": objs_syntax_issues,
        "pols_syntax_issues": pols_syntax_issues,
        "estrats_syntax_issues": estrats_syntax_issues,
        "metas_syntax_issues": metas_syntax_issues,
        "sample_fi_metas": [m.get("descripcion", "")[:75] for m in fi_metas[:3]],
        "sample_prdi_metas": [m.get("descripcion_prdi", "")[:75] for m in prdi_metas[:3]]
    }

print("=== AUDIT SUMMARY RESULTS ===")
for pid, d in audit_results.items():
    print(f"\n[PROYECTO {pid:02d}] {d['nombre']}")
    print(f"  Diagnóstico: {d['diag_name']} ({d['diag_size_bytes']} bytes)")
    print(f"  Metas: PRDI={d['count_prdi_metas']} | FI={d['count_fi_metas']} | RefBase Excel={d['count_ref_metas']} -> Match: {d['metas_count_match']}")
    print(f"  Estructura FI: Objs={d['count_fi_objs']} | Pols={d['count_fi_pols']} | Estrats={d['count_fi_estrats']}")
    if d['objs_syntax_issues']:
        print(f"  Issues Objs: {d['objs_syntax_issues']}")
    if d['pols_syntax_issues']:
        print(f"  Issues Pols: {d['pols_syntax_issues']}")
    if d['estrats_syntax_issues']:
        print(f"  Issues Estrats: {d['estrats_syntax_issues']}")
    if d['metas_syntax_issues']:
        print(f"  Issues Metas: {d['metas_syntax_issues']}")
