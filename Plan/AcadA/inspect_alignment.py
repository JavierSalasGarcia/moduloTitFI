import json
import os
import glob
import sys

sys.stdout.reconfigure(encoding='utf-8')

# 1. Load PRDI.json
prdi_path = r"D:\Proyectos\plataformaFI\Plan\PRDI.json"
with open(prdi_path, "r", encoding="utf-8") as f:
    prdi_data = json.load(f)

print("PRDI loaded. Keys:", list(prdi_data.keys()) if isinstance(prdi_data, dict) else f"List length: {len(prdi_data)}")

# 2. Load preliminar_fi.json
prelim_path = r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json"
with open(prelim_path, "r", encoding="utf-8") as f:
    prelim_data = json.load(f)

print("preliminar_fi loaded. Keys:", list(prelim_data.keys()) if isinstance(prelim_data, dict) else f"List length: {len(prelim_data)}")

# Check revision files
rev_pattern = r"D:\Proyectos\plataformaFI\storage\plan\revisiones\*\*.json"
rev_files = glob.glob(rev_pattern)
print(f"Total revision files found: {len(rev_files)}")
for rf in rev_files[:10]:
    print("  Revision file:", rf)

# Inspect projects in preliminar_fi
target_projs = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]
print("\n--- TARGET PROJECTS SUMMARY IN PRELIMINAR_FI ---")
proyectos_list = prelim_data.get("proyectos", []) if isinstance(prelim_data, dict) else prelim_data

for p in proyectos_list:
    p_num = p.get("numero") or p.get("id") or p.get("proyecto")
    if p_num in target_projs or str(p_num) in [str(x) for x in target_projs]:
        nombre = p.get("nombre") or p.get("titulo")
        objs = p.get("objetivos_especificos", [])
        ref_base = p.get("referencia_base_2026", {})
        metas_ref = ref_base.get("metas", []) if isinstance(ref_base, dict) else []
        print(f"\n[PROYECTO {p_num}] {nombre}")
        print(f"  Objetivos específicos count: {len(objs)}")
        for idx_o, o in enumerate(objs):
            o_desc = o.get("descripcion") or o.get("objetivo") or o.get("texto", "")
            estrat = o.get("estrategias", [])
            print(f"    Obj {idx_o+1}: {o_desc[:80]}... (Estrategias: {len(estrat)})")
            for idx_e, e in enumerate(estrat):
                e_desc = e.get("descripcion") or e.get("estrategia") or e.get("texto", "")
                metas = e.get("metas", [])
                print(f"      Estrat {idx_e+1}: {e_desc[:70]}... (Metas: {len(metas)})")
                for idx_m, m in enumerate(metas):
                    m_desc = m.get("descripcion") or m.get("meta") or m.get("texto", "")
                    print(f"        Meta {idx_m+1}: {m_desc[:70]}")
        print(f"  Referencia base 2026 metas count: {len(metas_ref)}")
