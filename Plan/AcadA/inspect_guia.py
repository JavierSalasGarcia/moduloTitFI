import sys
import os
import shutil

sys.stdout.reconfigure(encoding='utf-8')

pdf_path = r"G:\Para EA\Guía para la formulación de planes de desarrollo.pdf"
dest_path = os.path.join(os.path.dirname(__file__), "guia_plan_desarrollo.pdf")

print("Checking if source PDF exists:", os.path.exists(pdf_path))
if os.path.exists(pdf_path):
    print("Source size:", os.path.getsize(pdf_path), "bytes")
    if not os.path.exists(dest_path):
        shutil.copy2(pdf_path, dest_path)
        print("Copied to workspace as:", dest_path)
    else:
        print("Already copied in workspace:", dest_path)

try:
    import fitz
    doc = fitz.open(dest_path)
    print(f"PyMuPDF is installed! Total pages: {len(doc)}")
    # Let's inspect TOC or headings
    toc = doc.get_toc()
    print("TOC:", toc[:20])
except Exception as e:
    print("PyMuPDF error/not found:", e)

try:
    import pypdf
    reader = pypdf.PdfReader(dest_path)
    print(f"pypdf is installed! Total pages: {len(reader.pages)}")
except Exception as e:
    print("pypdf error/not found:", e)
