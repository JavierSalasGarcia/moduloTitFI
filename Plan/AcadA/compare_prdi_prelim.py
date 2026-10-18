import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

prdi_path = r"D:\Proyectos\plataformaFI\Plan\PRDI.json"
prelim_path = r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json"

with open(prdi_path, "r", encoding="utf-8") as f:
    prdi_raw = json.load(f)

with open(prelim_path, "r", encoding="utf-8") as f:
    prelim_raw = json.load(f)

# Extract projects from PRDI
prdi_projs = {}
for pilar in prdi_raw.get("pilares", []):
    pilar_num = pilar.get("numero")
    pilar_nom = pilar.get("nombre")
    for proj in pilar.get("proyectos", []):
        p_num = str(proj.get("numero") or proj.get("id"))
        proj["pilar_numero"] = pilar_num
        proj["pilar_nombre"] = pilar_nom
        prdi_projs[p_num] = proj

# Extract projects from preliminar_fi
prelim_projs = {}
for proj in prelim_raw.get("proyectos", []):
    p_num = str(proj.get("numero") or proj.get("id"))
    prelim_projs[p_num] = proj

print(f"PRDI projects extracted: {sorted([int(x) for x in prdi_projs.keys()])}")
print(f"Preliminar FI projects extracted: {sorted([int(x) for x in prelim_projs.keys()])}")

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

report = []

for tid in target_ids:
    s_tid = str(tid)
    p_prdi = prdi_projs.get(s_tid, {})
    p_prelim = prelim_projs.get(s_tid, {})
    
    report.append(f"\n{'='*100}")
    report.append(f"PROYECTO {tid}: {p_prelim.get('nombre') or p_prdi.get('nombre')}")
    report.append(f"Pilar: {p_prdi.get('pilar_nombre', 'N/A')}")
    report.append(f"{'='*100}")
    
    # 1. OBJETIVO GENERAL
    report.append("\n[1. OBJETIVO GENERAL]")
    report.append(f"  PRDI : {p_prdi.get('objetivo_general', 'N/A')}")
    report.append(f"  FI   : {p_prelim.get('objetivo_general', 'N/A')}")
    
    # 2. POLÍTICAS
    report.append("\n[2. POLÍTICAS]")
    pols_prdi = p_prdi.get('politicas', [])
    report.append(f"  Políticas PRDI ({len(pols_prdi)}):")
    for p in pols_prdi:
        ptxt = p if isinstance(p, str) else p.get('descripcion') or p.get('texto') or str(p)
        report.append(f"    - {ptxt}")
    pols_fi = p_prelim.get('politicas', [])
    report.append(f"  Políticas FI ({len(pols_fi)}):")
    for p in pols_fi:
        ptxt = p if isinstance(p, str) else p.get('descripcion') or p.get('texto') or str(p)
        report.append(f"    - {ptxt}")

    # 3. OBJETIVOS ESPECÍFICOS & ESTRATEGIAS & METAS PRDI
    report.append("\n[3. PRDI - OBJETIVOS ESPECÍFICOS, ESTRATEGIAS Y METAS INSTITUCIONALES]")
    for oi, o in enumerate(p_prdi.get('objetivos_especificos', [])):
        otxt = o.get('descripcion') or o.get('objetivo') or str(o)
        report.append(f"  Obj Específico {oi+1}: {otxt}")
        for ei, e in enumerate(o.get('estrategias', [])):
            etxt = e.get('descripcion') or e.get('estrategia') or str(e)
            report.append(f"    Estrategia {ei+1}: {etxt}")
            for mi, m in enumerate(e.get('metas', [])):
                mtxt = m.get('descripcion') or m.get('meta') or str(m)
                report.append(f"      * Meta Inst {mi+1}: {mtxt}")

    # 4. PRELIMINAR FI - OBJETIVOS ESPECÍFICOS, ESTRATEGIAS Y METAS FI
    report.append("\n[4. PRELIMINAR FI - OBJETIVOS ESPECÍFICOS, ESTRATEGIAS Y METAS DE LA FACULTAD]")
    for oi, o in enumerate(p_prelim.get('objetivos_especificos', [])):
        otxt = o.get('descripcion') or o.get('objetivo') or str(o)
        report.append(f"  Obj Específico FI {oi+1}: {otxt}")
        for ei, e in enumerate(o.get('estrategias', [])):
            etxt = e.get('descripcion') or e.get('estrategia') or str(e)
            report.append(f"    Estrategia FI {ei+1}: {etxt}")
            for mi, m in enumerate(e.get('metas', [])):
                mtxt = m.get('descripcion') or m.get('meta') or str(m)
                report.append(f"      * Meta FI {mi+1}: {mtxt}")

    # 5. METAS Y VARIABLES VINCULADAS AL EXCEL (referencia_base_2026)
    ref_base = p_prelim.get('referencia_base_2026', {})
    metas_base = ref_base.get('metas', []) if isinstance(ref_base, dict) else []
    report.append(f"\n[5. REFERENCIA BASE 2026 / MEMORIA DE CÁLCULO EXCEL FI] ({len(metas_base)} metas)")
    for mb in metas_base:
        mid = mb.get('id') or mb.get('clave') or mb.get('numero')
        mdesc = mb.get('descripcion') or mb.get('nombre') or mb.get('meta')
        vars_m = mb.get('variables', [])
        report.append(f"  Meta [{mid}]: {mdesc}")
        for v in vars_m:
            vid = v.get('id') or v.get('clave')
            vnom = v.get('nombre')
            vref = v.get('valor_ref_2026')
            vprog = v.get('programacion', {})
            report.append(f"    Var {vid} ({vnom}): Ref2026={vref} | Prog={vprog}")

out_path = r"D:\Proyectos\plataformaFI\Plan\AcadA\alineacion_detallada_prdi_fi.txt"
with open(out_path, "w", encoding="utf-8") as f:
    f.write("\n".join(report))

print(f"Report written successfully to {out_path} with {len(report)} lines.")
