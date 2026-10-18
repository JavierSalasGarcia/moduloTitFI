import fitz
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')

doc = fitz.open("guia_plan_desarrollo.pdf")
out_dir = os.path.join(os.path.dirname(__file__), "guia_imagenes")
os.makedirs(out_dir, exist_ok=True)

# Render pages 19 through 30 (0-indexed: 18 to 29) at 2x resolution (144 dpi)
zoom = 2.0
mat = fitz.Matrix(zoom, zoom)

for page_num in range(18, 30):
    page = doc[page_num]
    pix = page.get_pixmap(matrix=mat)
    out_file = os.path.join(out_dir, f"pagina_{page_num+1:02d}.png")
    pix.save(out_file)
    print(f"Rendered: {out_file} (Page {page_num+1})")

print("Done rendering key pages.")
