import json
import os
import glob
import re
import sys

sys.stdout.reconfigure(encoding='utf-8')

# 1. Inspect preliminar_fi.json
with open(r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json", "r", encoding="utf-8") as f:
    prelim = json.load(f)

target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

print("="*80)
print("AUDITORÍA DE POLÍTICAS EN preliminar_fi.json (OBLIGATORIEDAD vs VERBOS DÉBILES)")
print("="*80)

weak_verbs = ["promover", "promoverá", "promoverán", "fomentar", "fomentará", "fomentarán", 
              "impulsar", "impulsará", "impulsarán", "propiciar", "propiciará", "propiciarán", 
              "buscar", "buscará", "buscarán", "favorecer", "favorecerá", "favorecerán"]

obligatory_verbs = ["garantizar", "garantizará", "garantizarán", "asegurar", "asegurará", "asegurarán",
                    "deberá", "deberán", "establecerá", "establecerán", "normará", "normarán",
                    "exigirá", "exigirán", "priorizará", "priorizarán", "obligará", "obligarán"]

policy_issues = []

for p in prelim.get("proyectos", []):
    pid = p.get("numero")
    if pid not in target_ids:
        continue
    pname = p.get("nombre")
    pols = p.get("politicas", [])
    print(f"\n[PROYECTO {pid:02d}] {pname} ({len(pols)} políticas)")
    for pi, pol in enumerate(pols):
        ptxt = pol.get("texto", "") if isinstance(pol, dict) else str(pol)
        # analyze words
        words = [w.lower().strip(",.;:()") for w in ptxt.split()]
        has_weak = [w for w in words if any(w.startswith(wv) for wv in ["promov", "foment", "impuls", "propici", "busc", "favorec"])]
        has_obligatory = [w for w in words if any(w.startswith(ov) for ov in ["garantiz", "asegur", "deber", "establec", "norm", "exig", "prioriz"])]
        
        status = "CORRECTA"
        if has_weak and not has_obligatory:
            status = "DÉBIL (No denota obligatoriedad)"
        elif has_weak and has_obligatory:
            status = "MIXTA (Contiene verbos débiles junto a obligatorios)"
            
        print(f"  P{pi+1}: {ptxt}")
        print(f"      Estado: {status} | Verbos obligación: {has_obligatory} | Verbos débiles: {has_weak}")
        if "DÉBIL" in status or "MIXTA" in status:
            policy_issues.append((pid, pi+1, ptxt, status, has_weak))

print("\n" + "="*80)
print("AUDITORÍA DE LENGUAJE INCLUYENTE Y NO SEXISTA (preliminar_fi.json)")
print("="*80)

non_inclusive_patterns = [
    (r"\b(los\s+)?alumnos\b", "alumnos -> estudiantado / comunidad estudiantil"),
    (r"\b(las\s+)?alumnas\b", "alumnas -> estudiantado / comunidad estudiantil"),
    (r"\b(el\s+)?alumno\b", "alumno -> persona estudiante / estudiante"),
    (r"\b(la\s+)?alumna\b", "alumna -> persona estudiante / estudiante"),
    (r"\b(los\s+)?profesores\b", "profesores -> profesorado / personal docente / personal académico"),
    (r"\b(las\s+)?profesoras\b", "profesoras -> profesorado / personal docente"),
    (r"\b(los\s+)?maestros\b", "maestros -> profesorado / personal docente"),
    (r"\b(los\s+)?trabajadores\b", "trabajadores -> personal administrativo / personal universitario"),
    (r"\b(las\s+)?trabajadoras\b", "trabajadoras -> personal administrativo"),
    (r"\b(los\s+)?docentes\b", "los docentes -> personal docente / profesorado"),
    (r"\b(los\s+)?egresados\b", "los egresados -> personas egresadas / comunidad de egreso")
]

lang_issues_prelim = []

for p in prelim.get("proyectos", []):
    pid = p.get("numero")
    if pid not in target_ids:
        continue
    pname = p.get("nombre")
    
    # Check all fields
    fields_to_check = []
    if p.get("objetivo_general"):
        fields_to_check.append(("Objetivo General", p["objetivo_general"]))
    for pi, pol in enumerate(p.get("politicas", [])):
        ptxt = pol.get("texto", "") if isinstance(pol, dict) else str(pol)
        fields_to_check.append((f"Política {pi+1}", ptxt))
    for oi, oe in enumerate(p.get("objetivos_especificos", [])):
        fields_to_check.append((f"OE {oi+1}", oe.get("texto", "")))
        for ei, est in enumerate(oe.get("estrategias", [])):
            fields_to_check.append((f"OE{oi+1}.E{ei+1}", est.get("texto", "")))
            for mi, m in enumerate(est.get("metas", [])):
                fields_to_check.append((f"OE{oi+1}.E{ei+1}.M{mi+1}", m.get("descripcion", "")))
                
    for label, text in fields_to_check:
        for pat, suggestion in non_inclusive_patterns:
            matches = re.findall(pat, text, flags=re.IGNORECASE)
            if matches:
                lang_issues_prelim.append((pid, label, text, suggestion, matches))

print(f"Total incidencias de lenguaje excluyente en preliminar_fi.json (12 proyectos): {len(lang_issues_prelim)}")
for item in lang_issues_prelim[:25]:
    print(f"  [P{item[0]:02d} - {item[1]}] Sugerencia: {item[3]}")
    print(f"      Texto: {item[2][:90]}...")
