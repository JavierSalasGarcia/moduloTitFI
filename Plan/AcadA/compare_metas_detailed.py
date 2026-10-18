import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\PRDI.json", "r", encoding="utf-8") as f:
    prdi = json.load(f)

with open(r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json", "r", encoding="utf-8") as f:
    prelim = json.load(f)

prdi_map = {}
for pilar in prdi.get("pilares", []):
    for p in pilar.get("proyectos", []):
        prdi_map[p["numero"]] = p

prelim_map = {p["numero"]: p for p in prelim.get("proyectos", [])}

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

print("=== COMPARACIÓN DETALLADA DE METAS INSTITUCIONALES VS PRELIMINAR FI ===")

for tid in target_ids:
    p_prdi = prdi_map.get(tid, {})
    p_fi = prelim_map.get(tid, {})
    
    print(f"\n=======================================================")
    print(f"PROYECTO {tid}: {p_fi.get('nombre')}")
    print(f"=======================================================")
    
    # Extract PRDI metas
    prdi_metas = []
    for obj in p_prdi.get("objetivos_especificos", []):
        for m in obj.get("metas_institucionales", []):
            prdi_metas.append(m)
            
    # Extract FI metas from objetivos_especificos
    fi_metas = []
    for obj in p_fi.get("objetivos_especificos", []):
        for e in obj.get("estrategias", []):
            for m in e.get("metas", []):
                fi_metas.append(m)
                
    # Extract FI metas from referencia_base_2026
    ref_metas = p_fi.get("referencia_base_2026", {}).get("metas", [])
    
    print(f"PRDI metas count: {len(prdi_metas)} | FI metas count: {len(fi_metas)} | RefBase count: {len(ref_metas)}")
    
    for idx in range(max(len(prdi_metas), len(fi_metas), len(ref_metas))):
        m_p = prdi_metas[idx] if idx < len(prdi_metas) else {}
        m_f = fi_metas[idx] if idx < len(fi_metas) else {}
        m_r = ref_metas[idx] if idx < len(ref_metas) else {}
        
        desc_p = m_p.get("texto_prdi") or m_p.get("descripcion") or "N/A"
        desc_f = m_f.get("descripcion") or m_f.get("meta") or "N/A"
        desc_r = m_r.get("descripcion") or m_r.get("meta") or "N/A"
        
        vars_r = m_r.get("variables", [])
        var_info = []
        for v in vars_r:
            var_info.append(f"{v.get('id')}:{v.get('nombre')} (Ref26={v.get('valor_ref_2026')}, 2030={v.get('programacion', {}).get('2030')})")
            
        print(f"\n  [Meta {idx+1}]")
        print(f"    PRDI Inst : {desc_p[:90]}...")
        print(f"    FI Prop   : {desc_f[:90]}...")
        print(f"    Excel Ref : {desc_r[:90]}...")
        if var_info:
            print(f"    Variables : {' | '.join(var_info)}")
