import json
import os
import glob
import sys

sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\preliminar_fi.json", "r", encoding="utf-8") as f:
    prelim = json.load(f)

diag_dir = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"
target_ids = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

print("=== REVISIÓN DE CONCORDANCIA DIAGNÓSTICO VS OBJETIVOS/METAS ===")

diag_alignment_notes = {}

for p in prelim.get("proyectos", []):
    pid = p.get("numero")
    if pid not in target_ids:
        continue
    pname = p.get("nombre")
    oes = p.get("objetivos_especificos", [])
    
    # Load diagnosis
    dfiles = glob.glob(os.path.join(diag_dir, f"*proy_{pid:02d}*.txt"))
    diag_text = ""
    if dfiles:
        with open(dfiles[0], "r", encoding="utf-8") as df:
            diag_text = df.read().lower()
            
    print(f"\n[PROYECTO {pid:02d}] {pname}")
    notes = []
    for oi, oe in enumerate(oes):
        oe_txt = oe.get("texto", "")
        # Extract keywords from OE
        keywords = [w for w in oe_txt.lower().split() if len(w) > 4 and w not in ["para", "mediante", "través", "sobre", "entre", "hacer", "tener"]]
        found_kw = [kw for kw in keywords if kw in diag_text]
        ratio = len(found_kw) / max(len(keywords), 1)
        print(f"  OE{oi+1}: {oe_txt[:70]}...")
        print(f"    Coincidencia temática en diagnóstico: {len(found_kw)}/{len(keywords)} ({ratio:.1%}) - Palabras: {found_kw[:5]}")
        
        # Check metas
        for est in oe.get("estrategias", []):
            for m in est.get("metas", []):
                m_desc = m.get("descripcion", "")
                m_ind = m.get("indicador", "")
                m_kw = [w for w in m_desc.lower().split() if len(w) > 4 and w not in ["para", "cada", "entre", "sobre", "facultad", "ingeniería", "manera", "acumulada"]]
                m_found = [kw for kw in m_kw if kw in diag_text]
                m_ratio = len(m_found) / max(len(m_kw), 1)
                if m_ratio < 0.3:
                    notes.append(f"Meta [{m_ind}] baja presencia en diagnóstico: {m_desc[:60]}... ({m_ratio:.1%})")

    diag_alignment_notes[pid] = notes
    if notes:
        print(f"  Alertas de cobertura en diagnóstico ({len(notes)}):")
        for n in notes[:3]:
            print(f"    ! {n}")
    else:
        print("  Cobertura temática: Excelente y congruente.")
