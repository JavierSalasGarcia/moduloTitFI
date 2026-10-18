from pathlib import Path
from PIL import Image
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4
from pypdf import PdfReader

root = Path(__file__).resolve().parent.parent
source = root / '.codex-remote-attachments/01a0b0bf-a452-7c60-9851-df4a0057fcbb/642945f6-3dc5-4033-a2a4-0c01cf471998/1-Photo-1.jpg'
output = root / 'output/pdf/calendario-escolar-2026-b.pdf'
output.parent.mkdir(parents=True, exist_ok=True)
with Image.open(source) as image:
    width, height = image.size
page_width, page_height = A4
margin = 12
scale = min((page_width - 2 * margin) / width, (page_height - 2 * margin) / height)
draw_width, draw_height = width * scale, height * scale
pdf = canvas.Canvas(str(output), pagesize=A4)
pdf.setTitle('Calendario Escolar 2026-B - Facultad de Ingenieria UAEMEX')
pdf.drawImage(str(source), (page_width - draw_width) / 2, (page_height - draw_height) / 2,
              width=draw_width, height=draw_height)
pdf.showPage()
pdf.save()
reader = PdfReader(output, strict=True)
assert len(reader.pages) == 1
assert len(reader.pages[0].images) == 1
assert output.stat().st_size > 10000
print(f'PDF valido: una pagina, imagen completa, {output.stat().st_size} bytes.')
