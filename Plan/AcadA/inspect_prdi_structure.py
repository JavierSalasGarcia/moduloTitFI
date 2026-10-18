import json
import sys

sys.stdout.reconfigure(encoding='utf-8')

prdi_path = r"D:\Proyectos\plataformaFI\Plan\PRDI.json"
with open(prdi_path, "r", encoding="utf-8") as f:
    prdi = json.load(f)

print("Type of prdi:", type(prdi))
if isinstance(prdi, dict):
    print("Keys of prdi:", list(prdi.keys()))
    for k in list(prdi.keys())[:5]:
        val = prdi[k]
        print(f"Key '{k}': type={type(val)}, len={len(val) if hasattr(val, '__len__') else 'N/A'}")
elif isinstance(prdi, list):
    print(f"List length: {len(prdi)}")
    if prdi:
        print("First item keys:", list(prdi[0].keys()) if isinstance(prdi[0], dict) else type(prdi[0]))
