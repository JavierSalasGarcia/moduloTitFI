# -*- coding: utf-8 -*-
"""
Constructor Exhaustivo de Diagnósticos del Plan de Desarrollo 2026–2030
Facultad de Ingeniería — UAEMEX (Subdirección / Área Académica)
"""
import os
import sys
import json
import glob

sys.stdout.reconfigure(encoding='utf-8')

ROOT = r"D:\Proyectos\plataformaFI"
PLAN = os.path.join(ROOT, "Plan")
ACADA = os.path.join(PLAN, "AcadA")
DIAG_DIR = os.path.join(ACADA, "Diagnosticos")

os.makedirs(DIAG_DIR, exist_ok=True)

# Cargar fuentes estructuradas
with open(os.path.join(ACADA, "puntos_proyectos_desarrollo.json"), "r", encoding="utf-8") as f:
    puntos_guia = json.load(f)["proyectos"]

with open(os.path.join(ACADA, "evidencia_documentos_apoyo.json"), "r", encoding="utf-8") as f:
    evidencia_apoyo = json.load(f)["evidencia_por_proyecto"]

with open(os.path.join(PLAN, "instrumentos.json"), "r", encoding="utf-8") as f:
    instrumentos = json.load(f)

with open(os.path.join(PLAN, "preliminar_fi.json"), "r", encoding="utf-8") as f:
    preliminar = json.load(f)
prelim_by_num = {p["numero"]: p for p in preliminar.get("proyectos", [])}

def get_stats_category(cid):
    matches = glob.glob(os.path.join(PLAN, f"{cid}_*.json"))
    if matches:
        with open(matches[0], "r", encoding="utf-8") as f:
            return json.load(f)
    return {}

print("[+] Recursos maestros cargados. Compilando diagnósticos individuales...")
