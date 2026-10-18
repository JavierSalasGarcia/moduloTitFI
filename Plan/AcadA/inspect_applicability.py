import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json", "r", encoding="utf-8") as f:
    prelim = json.load(f)

with open(r"D:\Proyectos\plataformaFI\Plan\PRDI.json", "r", encoding="utf-8") as f:
    prdi = json.load(f)

# Check applicability of PRDI metas
prdi_map = {}
for pilar in prdi.get("pilares", []):
    for p in pilar.get("proyectos", []):
        prdi_map[p["numero"]] = p

print("--- REVISIÓN DE APLICABILIDAD DE METAS PRDI A FACULTAD DE INGENIERÍA ---")
for pid in [5, 6, 9, 10, 11, 13]:
    p = prdi_map.get(pid, {})
    all_metas = []
    for o in p.get("objetivos_especificos", []):
        for m in o.get("metas_institucionales", []):
            all_metas.append(m)
    app_metas = [m for m in all_metas if m.get("aplica_a_facultad_ingenieria")]
    print(f"Proyecto {pid:02d} ({p.get('nombre')[:30]}): Total PRDI={len(all_metas)} | Aplica a FI={len(app_metas)}")
    for m in all_metas:
        print(f"   [{m.get('clave_meta')}] Aplica={m.get('aplica_a_facultad_ingenieria')} | {m.get('descripcion_prdi')[:75]}...")

print("\n--- REVISIÓN DE KEYS EN preliminar_fi.json (Proyecto 2) ---")
p2 = next(p for p in prelim.get("proyectos", []) if p["numero"] == 2)
print("Keys of project 2:", list(p2.keys()))
o1 = p2["objetivos_especificos"][0]
print("Keys of obj 1:", list(o1.keys()))
print("Obj 1 content:", o1)
pol1 = p2["politicas"][0]
print("Politica 1 type & content:", type(pol1), pol1)
