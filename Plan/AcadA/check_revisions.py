import os
import glob
import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

rev_files = glob.glob(r"D:\Proyectos\plataformaFI\storage\plan\revisiones\*\*.json")
print(f"Revision files found: {len(rev_files)}")

for rf in rev_files:
    try:
        with open(rf, "r", encoding="utf-8") as f:
            data = json.load(f)
        user = data.get("usuario") or data.get("cuenta")
        revis = data.get("revisiones", [])
        print(f"File: {rf} | Revisions: {len(revis)}")
        if revis:
            last_rev = revis[-1]
            fecha = last_rev.get("fecha")
            autor = last_rev.get("autor")
            contenido = last_rev.get("contenido", {})
            objs = contenido.get("objetivos_especificos", [])
            print(f"   Last rev: {fecha} by {autor} | Objetivos específicos: {len(objs)}")
    except Exception as e:
        print(f"Error reading {rf}: {e}")
