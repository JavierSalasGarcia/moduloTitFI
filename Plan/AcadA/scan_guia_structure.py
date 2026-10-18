import fitz
import sys
import os

sys.stdout.reconfigure(encoding='utf-8')

doc = fitz.open("guia_plan_desarrollo.pdf")
print("Total pages:", len(doc))

out_dir = "guia_paginas"
os.makedirs(out_dir, exist_ok=True)

# Check each page for text, images, drawings, and keywords like estructura, relacion, diagnostico, objetivos, metas, etc.
for i, page in enumerate(doc):
    text = page.get_text()
    images = page.get_images()
    drawings = page.get_drawings()
    
    first_few_lines = [line.strip() for line in text.split("\n") if line.strip()][:3]
    has_diagram_keywords = any(kw in text.lower() for kw in [
        "relación", "estructura", "alineación", "componente", "metodolog", "esquema", "figura", "etapa", "objetivo", "meta", "política", "diagnóstico"
    ])
    
    print(f"Page {i+1}: Images={len(images)}, Drawings={len(drawings)}, Keywords={has_diagram_keywords}, Header={first_few_lines}")
