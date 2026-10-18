import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\PRDI.json", "r", encoding="utf-8") as f:
    prdi = json.load(f)

for pilar in prdi.get("pilares", []):
    for proj in pilar.get("proyectos", []):
        p_num = proj.get("numero") or proj.get("id")
        if p_num in [2, 4, 12]:
            print(f"\nProj {p_num} keys in PRDI:", list(proj.keys()))
            for k in proj.keys():
                if "meta" in k.lower():
                    print(f"  Key '{k}':", len(proj[k]) if isinstance(proj[k], list) else proj[k])
            # Check inside objetivos_especificos
            objs = proj.get("objetivos_especificos", [])
            print(f"  Objs específicos count: {len(objs)}")
            if objs:
                print("  First obj keys:", list(objs[0].keys()))
                estrats = objs[0].get("estrategias", [])
                if estrats:
                    print("  First estrat keys:", list(estrats[0].keys()))
                    if "metas" in estrats[0]:
                        print("  Metas in estrat:", len(estrats[0]["metas"]))
            # Check if there is 'apertura_programatica' or 'metas' at project level
            if "metas" in proj:
                print(f"  Metas at proj level: {len(proj['metas'])}")
