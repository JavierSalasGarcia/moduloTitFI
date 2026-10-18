import json
import re
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json", "r", encoding="utf-8") as f:
    prelim = json.load(f)

with open(r"D:\Proyectos\plataformaFI\Plan\PRDI.json", "r", encoding="utf-8") as f:
    prdi = json.load(f)

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

def is_infinitive(word):
    w = word.lower().strip(",.:;")
    return w.endswith("ar") or w.endswith("er") or w.endswith("ir")

audit = []

for p in prelim.get("proyectos", []):
    pid = p.get("numero")
    if pid not in target_ids:
        continue
    pname = p.get("nombre")
    og = p.get("objetivo_general", "")
    pols = p.get("politicas", [])
    oes = p.get("objetivos_especificos", [])
    ref_metas = p.get("referencia_base_2026", {}).get("metas", [])
    
    audit.append(f"\n================================================================================")
    audit.append(f"PROYECTO {pid:02d}: {pname}")
    audit.append(f"================================================================================")
    
    # 1. Objetivo General
    og_first = og.split()[0] if og.split() else ""
    og_inf = is_infinitive(og_first)
    audit.append(f"[OBJETIVO GENERAL] (Infinitivo: {og_inf})")
    audit.append(f"  Texto: {og}")
    
    # 2. Políticas
    audit.append(f"\n[POLÍTICAS] ({len(pols)})")
    for pi, pol in enumerate(pols):
        ptxt = pol.get("texto", "") if isinstance(pol, dict) else str(pol)
        # Check obligatoriedad: deberan, debera, garantizará, etc.
        has_ob = any(k in ptxt.lower() for k in ["deberá", "deberán", "garantizará", "garantizarán", "asegurará", "asegurarán", "promoverá", "promoverán", "priorizará"])
        audit.append(f"  P{pi+1}: {ptxt}")
        audit.append(f"      Denota obligatoriedad explícita: {has_ob}")
        
    # 3. Objetivos Específicos, Estrategias y Metas
    total_metas_p = 0
    audit.append(f"\n[OBJETIVOS ESPECÍFICOS, ESTRATEGIAS Y METAS] ({len(oes)} OE)")
    for oi, oe in enumerate(oes):
        oe_id = oe.get("id", f"OE{oi+1}")
        oe_txt = oe.get("texto", "")
        oe_first = oe_txt.split()[0] if oe_txt.split() else ""
        oe_inf = is_infinitive(oe_first)
        estrats = oe.get("estrategias", [])
        audit.append(f"  {oe_id}: {oe_txt} (Infinitivo: {oe_inf})")
        
        for ei, est in enumerate(estrats):
            e_id = est.get("id", f"E{oi+1}.{ei+1}")
            e_txt = est.get("texto", "")
            e_first = e_txt.split()[0] if e_txt.split() else ""
            e_inf = is_infinitive(e_first)
            # Check '¿cómo?' connector: mediante, a través de, con, etc.
            has_como = any(c in e_txt.lower() for c in ["mediante", "a través de", "con base en", "a partir de", "con apoyo de"])
            metas = est.get("metas", [])
            total_metas_p += len(metas)
            audit.append(f"    {e_id}: {e_txt}")
            audit.append(f"        Sintaxis (Infinitivo={e_inf}, Conector 'cómo'={has_como}) | Metas asociadas: {len(metas)}")
            
            for mi, m in enumerate(metas):
                m_id = m.get("id", f"M{mi+1}")
                m_desc = m.get("descripcion", "")
                m_first = m_desc.split()[0] if m_desc.split() else ""
                m_inf = is_infinitive(m_first)
                m_ind = m.get("indicador", "")
                m_tipo = m.get("tipo", "")
                # Check cuanto and cuando
                has_year = any(y in m_desc for y in ["2026", "2027", "2028", "2029", "2030", "administración", "anual"])
                audit.append(f"        {m_id} [{m_ind} - {m_tipo}]: {m_desc}")
                audit.append(f"            Sintaxis (Infinitivo={m_inf}, Temporalidad={has_year})")

    audit.append(f"\n[RECUENTO DE METAS]")
    audit.append(f"  Total Metas en Estrategias: {total_metas_p}")
    audit.append(f"  Total Metas en Referencia Base 2026: {len(ref_metas)}")
    audit.append(f"  Consistencia numérica interna: {total_metas_p == len(ref_metas)}")

out_path = r"D:\Proyectos\plataformaFI\Plan\AcadA\auditoria_sintactica_metodologica.txt"
with open(out_path, "w", encoding="utf-8") as f:
    f.write("\n".join(audit))

print(f"Syntactic and methodological audit written to {out_path}. Lines: {len(audit)}")
