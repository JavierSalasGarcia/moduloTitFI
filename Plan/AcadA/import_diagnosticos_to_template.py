import os
import sys
import re
import glob
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import qn, nsdecls

if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
        sys.stderr.reconfigure(encoding='utf-8')
    except Exception:
        pass

# Palette from Manual de Identidad Gráfica UAEMéx 2025-2029
HEX_VERDE = "526855"      # Verde UAEMéx (Principal)
HEX_ORO = "CA9E52"        # Oro UAEMéx (Acento)
HEX_OBSCURO = "384033"    # Verde obscuro UAEMéx (Texto)
HEX_TERRACOTA = "A15C17"  # Terracota
HEX_GRIS_FONDO = "F4F5F4" # Gris suave cebrado
HEX_BORDE = "D3D8D3"      # Borde tenue

RGB_VERDE = RGBColor(82, 104, 85)
RGB_ORO = RGBColor(202, 158, 82)
RGB_OBSCURO = RGBColor(56, 64, 51)
RGB_GRIS_FUENTE = RGBColor(100, 100, 100)

PROJECTS_CONFIG = [
    {
        "code": "P02",
        "file": "diagnostico_proy_02_estudios_profesionales.txt",
        "match_heading": "Estudiantado con oportunidades para su pleno desarrollo"
    },
    {
        "code": "P03",
        "file": "diagnostico_proy_03_estudios_avanzados.txt",
        "match_heading": "Estudios avanzados de excelencia"
    },
    {
        "code": "P04",
        "file": "diagnostico_proy_04_investigacion_calidad.txt",
        "match_heading": "Investigación abierta, inclusiva"
    },
    {
        "code": "P05",
        "file": "diagnostico_proy_05_innovacion_transferencia.txt",
        "match_heading": "Innovación, desarrollo tecnológico"
    },
    {
        "code": "P06",
        "file": "diagnostico_proy_06_difusion_ciencia_abierta.txt",
        "match_heading": "Difusión y divulgación de la ciencia"
    },
    {
        "code": "P09",
        "file": "diagnostico_proy_09_formacion_docente.txt",
        "match_heading": "Fortalecimiento de la formación integral del docente"
    },
    {
        "code": "P10",
        "file": "diagnostico_proy_10_trabajadores_universitarios.txt",
        "match_heading": "Dignificación de las y los trabajadores"
    },
    {
        "code": "P11",
        "file": "diagnostico_proy_11_gestion_administrativa.txt",
        "match_heading": "Gestión administrativa para responder"
    },
    {
        "code": "P12",
        "file": "diagnostico_proy_12_fortalecimiento_tecnologico.txt",
        "match_heading": "Fortalecimiento tecnológico"
    },
    {
        "code": "P13",
        "file": "diagnostico_proy_13_gobernanza.txt",
        "match_heading": "Gobernanza"
    },
    {
        "code": "P18",
        "file": "diagnostico_proy_18_diversidad_social.txt",
        "match_heading": "Reconocimiento de la diversidad"
    },
    {
        "code": "P19",
        "file": "diagnostico_proy_19_cultura_de_paz.txt",
        "match_heading": "Construcción de una cultura de paz"
    }
]

DIAGNOSTICOS_DIR = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"
GRAFICAS_DIR = r"D:\Proyectos\plataformaFI\Plan\AcadA\graficas"

def set_cell_background(cell, fill_hex):
    tcPr = cell._tc.get_or_add_tcPr()
    for child in list(tcPr):
        if child.tag.endswith('shd'):
            tcPr.remove(child)
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_hex}"/>')
    tcPr.append(shd)

def set_cell_margins(cell, top=140, bottom=140, left=160, right=160):
    tcPr = cell._tc.get_or_add_tcPr()
    tcMar = parse_xml(f'<w:tcMar {nsdecls("w")}><w:top w:w="{top}" w:type="dxa"/><w:bottom w:w="{bottom}" w:type="dxa"/><w:left w:w="{left}" w:type="dxa"/><w:right w:w="{right}" w:type="dxa"/></w:tcMar>')
    tcPr.append(tcMar)

def set_table_borders(table):
    tblPr = table._tbl.tblPr
    borders = parse_xml(
        f'<w:tblBorders {nsdecls("w")}>'
        f'<w:top w:val="single" w:sz="8" w:space="0" w:color="{HEX_VERDE}"/>'
        f'<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
        f'<w:bottom w:val="single" w:sz="8" w:space="0" w:color="{HEX_VERDE}"/>'
        f'<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
        f'<w:insideH w:val="single" w:sz="4" w:space="0" w:color="{HEX_BORDE}"/>'
        f'<w:insideV w:val="none" w:sz="0" w:space="0" w:color="auto"/>'
        f'</w:tblBorders>'
    )
    tblPr.append(borders)

def parse_diagnostic_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    diag_match = re.search(r'DIAGNÓSTICO\s*(.*?)(?=\n[A-ZÁÉÍÓÚ\s]{4,}OBJETIVO GENERAL|\nOBJETIVO GENERAL|\Z)', content, re.DOTALL | re.IGNORECASE)
    if diag_match:
        diag_raw = diag_match.group(1).strip()
    else:
        diag_raw = content.strip()

    lines = diag_raw.split('\n')
    blocks = []
    
    i = 0
    while i < len(lines):
        line = lines[i].strip()
        if not line:
            i += 1
            continue

        # Check for Subtheme (e.g. "1. Cobertura de estudios profesionales", "2. Plan de estudios pertinente", etc.)
        subtheme_match = re.match(r'^(\d+\.\s+[A-ZÁÉÍÓÚ][^\n]+)$', line)
        if subtheme_match and len(line) < 120 and not line.endswith(':'):
            blocks.append({
                'type': 'subtheme',
                'text': line
            })
            i += 1
            continue

        # Check for APA References Section Header
        if line.startswith("Fuentes y Referencias") or line.startswith("Referencias"):
            blocks.append({
                'type': 'references_header',
                'text': line
            })
            i += 1
            # Parse subsequent references
            while i < len(lines):
                ref_line = lines[i].strip()
                if not ref_line:
                    i += 1
                    continue
                if ref_line.startswith('- ') or ref_line.startswith('* '):
                    ref_text = ref_line[2:].strip()
                    i += 1
                    while i < len(lines):
                        next_r = lines[i].strip()
                        if next_r and not next_r.startswith('- ') and not next_r.startswith('* ') and not next_r.startswith('='):
                            ref_text += " " + next_r
                            i += 1
                        else:
                            break
                    blocks.append({
                        'type': 'reference_item',
                        'text': ref_text
                    })
                else:
                    break
            continue

        # Check for Chart reference [[GRAFICA: filename | Title]]
        grafica_match = re.match(r'\[\[GRAFICA:\s*([^\|\]]+)(?:\|\s*([^\]]+))?\]\]', line)
        if grafica_match:
            g_file = grafica_match.group(1).strip()
            g_title = grafica_match.group(2).strip() if grafica_match.group(2) else ""
            blocks.append({
                'type': 'figure',
                'file': g_file,
                'title': g_title
            })
            i += 1
            continue

        # Check for Table start (e.g. "Tabla X. ...")
        table_title_match = re.match(r'^(Tabla\s+\d+[\.\:]\s+[^\n]+)', line, re.IGNORECASE)
        if table_title_match:
            table_title = table_title_match.group(1).strip()
            table_lines = []
            table_source = ""
            i += 1
            while i < len(lines):
                t_line = lines[i].strip()
                if not t_line:
                    i += 1
                    continue
                if t_line.startswith("Fuente:") or t_line.startswith("Fuente :"):
                    table_source = t_line
                    i += 1
                    break
                if '|' in t_line:
                    table_lines.append(t_line)
                    i += 1
                else:
                    break
            
            if table_lines:
                parsed_rows = []
                for tl in table_lines:
                    if re.match(r'^\s*\|?\s*[-:]+[-| :]*\s*\|?\s*$', tl):
                        continue
                    cells = [c.strip() for c in tl.split('|')]
                    if cells and cells[0] == '':
                        cells = cells[1:]
                    if cells and cells[-1] == '':
                        cells = cells[:-1]
                    if cells:
                        parsed_rows.append(cells)
                
                if parsed_rows:
                    blocks.append({
                        'type': 'table',
                        'title': table_title,
                        'headers': parsed_rows[0],
                        'rows': parsed_rows[1:],
                        'source': table_source if table_source else "Fuente: Facultad de Ingeniería, UAEMéx."
                    })
            continue

        # Check for Table starting with |
        if line.startswith('|') and '|' in line[1:]:
            table_lines = [line]
            table_source = ""
            i += 1
            while i < len(lines):
                t_line = lines[i].strip()
                if not t_line:
                    i += 1
                    continue
                if t_line.startswith("Fuente:") or t_line.startswith("Fuente :"):
                    table_source = t_line
                    i += 1
                    break
                if '|' in t_line:
                    table_lines.append(t_line)
                    i += 1
                else:
                    break
            
            parsed_rows = []
            for tl in table_lines:
                if re.match(r'^\s*\|?\s*[-:]+[-| :]*\s*\|?\s*$', tl):
                    continue
                cells = [c.strip() for c in tl.split('|')]
                if cells and cells[0] == '':
                    cells = cells[1:]
                if cells and cells[-1] == '':
                    cells = cells[:-1]
                if cells:
                    parsed_rows.append(cells)
            
            if parsed_rows:
                blocks.append({
                    'type': 'table',
                    'title': "Tabla de Datos Diagnósticos",
                    'headers': parsed_rows[0],
                    'rows': parsed_rows[1:],
                    'source': table_source if table_source else "Fuente: Facultad de Ingeniería, UAEMéx."
                })
            continue

        # Check for bullet points
        if line.startswith('- ') or line.startswith('* ') or line.startswith('• '):
            bullet_text = line[2:].strip()
            i += 1
            while i < len(lines):
                next_l = lines[i].strip()
                if next_l and not next_l.startswith('- ') and not next_l.startswith('* ') and not next_l.startswith('• ') and not next_l.startswith('|') and not re.match(r'^\d+\.\s+[A-ZÁÉÍÓÚ]', next_l) and not next_l.startswith('Tabla') and not next_l.startswith('[[GRAFICA'):
                    bullet_text += " " + next_l
                    i += 1
                else:
                    break
            blocks.append({
                'type': 'bullet',
                'text': bullet_text
            })
            continue

        # Regular prose paragraph
        prose_text = line
        i += 1
        while i < len(lines):
            next_l = lines[i].strip()
            if next_l and not next_l.startswith('- ') and not next_l.startswith('* ') and not next_l.startswith('• ') and not next_l.startswith('|') and not re.match(r'^\d+\.\s+[A-ZÁÉÍÓÚ]', next_l) and not next_l.startswith('Tabla') and not next_l.startswith('[[GRAFICA') and not next_l.startswith('Hallazgos'):
                prose_text += " " + next_l
                i += 1
            else:
                break
        blocks.append({
            'type': 'paragraph',
            'text': prose_text
        })

    return blocks

def format_bold_keywords(paragraph, text):
    """
    Parses **bold** markdown tags and adds runs with appropriate formatting.
    """
    parts = re.split(r'(\*\*[^*]+\*\*)', text)
    for p in parts:
        if p.startswith('**') and p.endswith('**'):
            run = paragraph.add_run(p[2:-2])
            run.bold = True
        else:
            if p:
                paragraph.add_run(p)

def build_consolidated_document():
    # Find template
    template_files = glob.glob("G:/Para EA/*Plantilla*.docx")
    if not template_files:
        print("Template not found in G:/Para EA/")
        return
    template_path = template_files[0]
    print(f"Loading template: {template_path}")
    doc = docx.Document(template_path)

    # Locate each project in the document
    paragraphs = doc.paragraphs
    for p_cfg in PROJECTS_CONFIG:
        p_code = p_cfg["code"]
        match_title = p_cfg["match_heading"]
        file_name = p_cfg["file"]
        file_path = os.path.join(DIAGNOSTICOS_DIR, file_name)

        if not os.path.exists(file_path):
            print(f"[{p_code}] File not found: {file_path}")
            continue

        print(f"\nProcessing {p_code}: {match_title}")

        # Find Heading 2 for project
        h2_idx = None
        for idx, p in enumerate(paragraphs):
            if p.style.name.startswith("Heading 2") and match_title.lower() in p.text.lower():
                h2_idx = idx
                break
        
        if h2_idx is None:
            # Fallback search anywhere in paragraph text
            for idx, p in enumerate(paragraphs):
                if match_title.lower() in p.text.lower():
                    h2_idx = idx
                    break

        if h2_idx is None:
            print(f"[{p_code}] WARNING: Project heading '{match_title}' not found in template!")
            continue

        # Find "Diagnóstico" and "Objetivo General" after h2_idx
        diag_idx = None
        obj_gen_idx = None
        for idx in range(h2_idx, min(h2_idx + 40, len(paragraphs))):
            p_text = paragraphs[idx].text.strip()
            if "Diagnóstico" in p_text or "Diagnostico" in p_text:
                diag_idx = idx
            elif "Objetivo General" in p_text:
                obj_gen_idx = idx
                break

        if diag_idx is None or obj_gen_idx is None:
            print(f"[{p_code}] WARNING: Could not find Diagnóstico ({diag_idx}) or Objetivo General ({obj_gen_idx}) bounds.")
            continue

        print(f"[{p_code}] Found bounds: Diagnóstico at P[{diag_idx}], Objetivo General at P[{obj_gen_idx}]")

        # Target element before which we insert everything
        p_obj_gen = paragraphs[obj_gen_idx]

        # Parse blocks from diagnostic file
        blocks = parse_diagnostic_file(file_path)
        print(f"[{p_code}] Parsed {len(blocks)} blocks from {file_name}")

        # Insert blocks before p_obj_gen
        for block in blocks:
            b_type = block['type']

            if b_type == 'subtheme':
                p_sub = p_obj_gen.insert_paragraph_before()
                p_sub.paragraph_format.space_before = Pt(14)
                p_sub.paragraph_format.space_after = Pt(4)
                p_sub.paragraph_format.keep_with_next = True
                run = p_sub.add_run(block['text'])
                run.bold = True
                run.font.size = Pt(12)
                run.font.color.rgb = RGB_OBSCURO
                run.font.name = 'DM Sans'

            elif b_type == 'references_header':
                p_ref = p_obj_gen.insert_paragraph_before()
                p_ref.paragraph_format.space_before = Pt(14)
                p_ref.paragraph_format.space_after = Pt(4)
                p_ref.paragraph_format.keep_with_next = True
                run_ref = p_ref.add_run(block['text'])
                run_ref.bold = True
                run_ref.font.size = Pt(11)
                run_ref.font.color.rgb = RGB_VERDE
                run_ref.font.name = 'DM Sans'

            elif b_type == 'reference_item':
                p_item = p_obj_gen.insert_paragraph_before()
                # APA 7th hanging indent: left_indent = 0.5 in, first_line_indent = -0.5 in
                p_item.paragraph_format.left_indent = Inches(0.4)
                p_item.paragraph_format.first_line_indent = Inches(-0.4)
                p_item.paragraph_format.space_before = Pt(0)
                p_item.paragraph_format.space_after = Pt(3)
                p_item.paragraph_format.line_spacing = 1.15
                p_item.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
                
                # Parse markdown *italics* and **bold**
                text = block['text']
                # Split by italic *...* and bold **...**
                tokens = re.split(r'(\*[^*]+\*)', text)
                for t in tokens:
                    if t.startswith('*') and t.endswith('*') and len(t) > 2:
                        r = p_item.add_run(t[1:-1])
                        r.italic = True
                    else:
                        r = p_item.add_run(t)
                    r.font.size = Pt(9.5)
                    r.font.name = 'DM Sans'
                    r.font.color.rgb = RGB_OBSCURO

            elif b_type == 'paragraph':
                p_body = p_obj_gen.insert_paragraph_before()
                p_body.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
                p_body.paragraph_format.space_before = Pt(0)
                p_body.paragraph_format.space_after = Pt(5)
                p_body.paragraph_format.line_spacing = 1.15
                format_bold_keywords(p_body, block['text'])
                for r in p_body.runs:
                    r.font.size = Pt(10.5)
                    r.font.name = 'DM Sans'
                    r.font.color.rgb = RGB_OBSCURO

            elif b_type == 'bullet':
                p_bullet = p_obj_gen.insert_paragraph_before()
                p_bullet.paragraph_format.left_indent = Inches(0.25)
                p_bullet.paragraph_format.space_before = Pt(0)
                p_bullet.paragraph_format.space_after = Pt(3)
                p_bullet.paragraph_format.line_spacing = 1.15
                p_bullet.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
                
                # Bullet bullet symbol with Verde UAEMéx color
                b_run = p_bullet.add_run("▪  ")
                b_run.bold = True
                b_run.font.color.rgb = RGB_VERDE
                b_run.font.size = Pt(9)
                
                format_bold_keywords(p_bullet, block['text'])
                for r in p_bullet.runs[1:]:
                    r.font.size = Pt(10.5)
                    r.font.name = 'DM Sans'
                    r.font.color.rgb = RGB_OBSCURO

            elif b_type == 'table':
                # Table Title above
                p_ttitle = p_obj_gen.insert_paragraph_before()
                p_ttitle.paragraph_format.space_before = Pt(10)
                p_ttitle.paragraph_format.space_after = Pt(3)
                p_ttitle.paragraph_format.keep_with_next = True
                run_tt = p_ttitle.add_run(block['title'])
                run_tt.bold = True
                run_tt.font.size = Pt(10)
                run_tt.font.color.rgb = RGB_VERDE
                run_tt.font.name = 'DM Sans'

                # Create Table
                num_rows = len(block['rows']) + 1
                num_cols = len(block['headers'])
                tbl = doc.add_table(rows=num_rows, cols=num_cols)
                tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
                set_table_borders(tbl)

                # Style Header Row
                hdr_cells = tbl.rows[0].cells
                for c_idx, h_text in enumerate(block['headers']):
                    cell = hdr_cells[c_idx]
                    set_cell_background(cell, HEX_VERDE)
                    set_cell_margins(cell, top=140, bottom=140, left=160, right=160)
                    cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
                    p_cell = cell.paragraphs[0]
                    p_cell.paragraph_format.space_before = Pt(0)
                    p_cell.paragraph_format.space_after = Pt(0)
                    p_cell.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
                    c_run = p_cell.add_run(h_text)
                    c_run.bold = True
                    c_run.font.size = Pt(9.5)
                    c_run.font.name = 'DM Sans'
                    c_run.font.color.rgb = RGBColor(255, 255, 255)

                # Make header repeat on next page
                tbl.rows[0]._tr.get_or_add_trPr().append(parse_xml(f'<w:tblHeader {nsdecls("w")}/>'))

                # Style Data Rows
                for r_idx, row_data in enumerate(block['rows']):
                    row_cells = tbl.rows[r_idx + 1].cells
                    fill_color = HEX_GRIS_FONDO if (r_idx % 2 == 1) else "FFFFFF"
                    for c_idx, cell_value in enumerate(row_data):
                        if c_idx < len(row_cells):
                            cell = row_cells[c_idx]
                            set_cell_background(cell, fill_color)
                            set_cell_margins(cell, top=100, bottom=100, left=140, right=140)
                            cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
                            p_cell = cell.paragraphs[0]
                            p_cell.paragraph_format.space_before = Pt(0)
                            p_cell.paragraph_format.space_after = Pt(0)
                            # Align numbers center/right, text left
                            if re.match(r'^\s*[\d\.,%+-]+\s*$', cell_value) and len(cell_value) < 15:
                                p_cell.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
                            else:
                                p_cell.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
                            format_bold_keywords(p_cell, cell_value)
                            for cr in p_cell.runs:
                                cr.font.size = Pt(9)
                                cr.font.name = 'DM Sans'
                                cr.font.color.rgb = RGB_OBSCURO

                # Move table before p_obj_gen
                p_obj_gen._p.addprevious(tbl._tbl)

                # Source Note below
                p_tsrc = p_obj_gen.insert_paragraph_before()
                p_tsrc.paragraph_format.space_before = Pt(2)
                p_tsrc.paragraph_format.space_after = Pt(8)
                run_ts = p_tsrc.add_run(block['source'])
                run_ts.italic = True
                run_ts.font.size = Pt(8.5)
                run_ts.font.color.rgb = RGB_GRIS_FUENTE
                run_ts.font.name = 'DM Sans'

            elif b_type == 'figure':
                fig_filename = block['file']
                fig_title = block['title']
                fig_path = os.path.join(GRAFICAS_DIR, fig_filename)
                
                if os.path.exists(fig_path):
                    # Figure Title
                    if fig_title:
                        p_ftitle = p_obj_gen.insert_paragraph_before()
                        p_ftitle.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
                        p_ftitle.paragraph_format.space_before = Pt(10)
                        p_ftitle.paragraph_format.space_after = Pt(4)
                        p_ftitle.paragraph_format.keep_with_next = True
                        run_ft = p_ftitle.add_run(fig_title)
                        run_ft.bold = True
                        run_ft.font.size = Pt(10)
                        run_ft.font.color.rgb = RGB_VERDE
                        run_ft.font.name = 'DM Sans'

                    # Image
                    p_img = p_obj_gen.insert_paragraph_before()
                    p_img.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
                    p_img.paragraph_format.space_before = Pt(2)
                    p_img.paragraph_format.space_after = Pt(2)
                    r_img = p_img.add_run()
                    r_img.add_picture(fig_path, width=Inches(5.6))

                    # Figure Source
                    p_fsrc = p_obj_gen.insert_paragraph_before()
                    p_fsrc.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
                    p_fsrc.paragraph_format.space_before = Pt(2)
                    p_fsrc.paragraph_format.space_after = Pt(8)
                    run_fs = p_fsrc.add_run("Fuente: Agenda Estadística UAEMéx / Dirección de la Facultad de Ingeniería.")
                    run_fs.italic = True
                    run_fs.font.size = Pt(8.5)
                    run_fs.font.color.rgb = RGB_GRIS_FUENTE
                    run_fs.font.name = 'DM Sans'
                else:
                    print(f"[{p_code}] Chart image not found: {fig_path}")

        # Clean empty paragraphs between Diagnóstico header and the newly inserted content
        # Note: In docx, we just leave Diagnóstico header intact and our content leads right down to Objetivo General!

    # Save outputs
    output_g = r"G:\Para EA\Plantilla_Diseño_PD_ES_FI_2026_2030.docx"
    output_local = r"D:\Proyectos\plataformaFI\Plan\AcadA\Plantilla_Diseño_PD_ES_FI_2026_2030.docx"

    print(f"\nSaving generated document to:\n1. {output_g}\n2. {output_local}")
    doc.save(output_local)
    try:
        doc.save(output_g)
        print("Saved successfully to G: drive!")
    except Exception as e:
        print(f"Could not save to G: drive: {e}")

    print("DOCUMENT IMPORT AND CONSOLIDATION COMPLETED SUCCESSFULLY!")

if __name__ == "__main__":
    build_consolidated_document()
