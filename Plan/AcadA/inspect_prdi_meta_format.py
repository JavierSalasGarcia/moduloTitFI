import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\PRDI.json", "r", encoding="utf-8") as f:
    prdi = json.load(f)

for pilar in prdi.get("pilares", []):
    for p in pilar.get("proyectos", []):
        if p["numero"] == 2:
            for oi, o in enumerate(p.get("objetivos_especificos", [])):
                metas = o.get("metas_institucionales", [])
                print(f"Obj {oi+1} metas_institucionales ({len(metas)}):")
                if metas:
                    print("  Sample meta:", metas[0])
            break
