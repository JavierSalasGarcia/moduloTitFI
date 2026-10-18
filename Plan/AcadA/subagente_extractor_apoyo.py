# -*- coding: utf-8 -*-
"""
Subagente Autónomo de Extracción y Alineación Documental (Versión 2 Robusta)
----------------------------------------------------------------------------
Localiza dinámicamente los archivos en "G:\Para EA\Documentos de apoyo"
independientemente de variantes de codificación Unicode (NFD/NFC) en tildes,
procesa todos los documentos relevantes y genera evidencia_documentos_apoyo.json.
"""
from __future__ import annotations

import os
import sys
import json
import re
import fitz  # PyMuPDF

sys.stdout.reconfigure(encoding='utf-8')

ROOT_APOYO = r"G:\Para EA\Documentos de apoyo"
OUT_JSON = r"D:\Proyectos\plataformaFI\Plan\AcadA\evidencia_documentos_apoyo.json"

TARGET_PROJECTS = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

KEYWORDS_BY_PROJECT = {
    2: {
        "nombre": "Estudios profesionales y pertinencia",
        "keywords": [
            "educación superior", "cobertura", "matrícula", "pertinencia", "acreditación",
            "cacei", "seaes", "abandono escolar", "eficiencia terminal", "titulación",
            "tutoría", "servicio social", "prácticas profesionales", "egel", "empleabilidad",
            "educación continua", "movilidad estudiantil", "becas"
        ]
    },
    3: {
        "nombre": "Estudios avanzados y posgrado",
        "keywords": [
            "posgrado", "maestría", "doctorado", "especialidad", "sistema nacional de posgrados",
            "snp", "conahcyt", "graduación", "investigación en posgrado", "tesis"
        ]
    },
    4: {
        "nombre": "Investigación abierta y de calidad",
        "keywords": [
            "investigación científica", "ciencia abierta", "cuerpos académicos", "snii",
            "sni", "líneas de generación", "producción científica", "artículos indizados",
            "financiamiento a la investigación"
        ]
    },
    5: {
        "nombre": "Innovación, desarrollo tecnológico y transferencia",
        "keywords": [
            "desarrollo tecnológico", "innovación", "transferencia tecnológica", "patentes",
            "propiedad intelectual", "vinculación con la industria", "triple hélice",
            "cuádruple hélice", "prototipos", "laboratorios especializados"
        ]
    },
    6: {
        "nombre": "Difusión de la ciencia abierta y bienes públicos",
        "keywords": [
            "difusión científica", "divulgación", "ciencia abierta", "acceso abierto",
            "bienes públicos digitales", "revistas científicas", "repositorio institucional",
            "libros abiertos"
        ]
    },
    9: {
        "nombre": "Formación integral del docente",
        "keywords": [
            "formación docente", "capacitación docente", "habilitación académica", "perfil deseable",
            "promep", "prodep", "proed", "carrera docente", "nueva escuela mexicana",
            "actualización pedagógica", "evaluación docente"
        ]
    },
    10: {
        "nombre": "Dignificación de las y los trabajadores universitarios",
        "keywords": [
            "personal administrativo", "trabajadores universitarios", "clima laboral",
            "bienestar laboral", "capacitación administrativa", "estímulos al personal",
            "condiciones laborales", "profesionalización"
        ]
    },
    11: {
        "nombre": "Gestión administrativa e infraestructura",
        "keywords": [
            "gestión administrativa", "infraestructura física", "mantenimiento", "espacios educativos",
            "aulas", "laboratorios", "sistema de gestión de calidad", "iso", "manuales de organización",
            "transporte universitario", "potrobús"
        ]
    },
    12: {
        "nombre": "Fortalecimiento tecnológico",
        "keywords": [
            "tecnologías de información", "tic", "conectividad", "red universitaria",
            "transformación digital", "ciberseguridad", "cultura digital", "equipamiento de cómputo",
            "sistemas digitales"
        ]
    },
    13: {
        "nombre": "Gobernanza y seguridad",
        "keywords": [
            "gobernanza", "órganos colegiados", "consejo de gobierno", "consejo académico",
            "protección universitaria", "protección civil", "seguridad institucional",
            "identidad universitaria", "atención estudiantil"
        ]
    },
    18: {
        "nombre": "Reconocimiento de la diversidad social e inclusión",
        "keywords": [
            "igualdad de género", "inclusión", "diversidad", "derechos humanos",
            "no discriminación", "grupos vulnerables", "discapacidad", "necesidades especiales",
            "perspectiva de género", "cuidados", "protocolo de género"
        ]
    },
    19: {
        "nombre": "Construcción de una cultura de paz",
        "keywords": [
            "cultura de paz", "prevención de violencia", "resolución pacífica de conflictos",
            "mediación", "círculos de paz", "comité de paz", "convivencia universitaria",
            "derechos universitarios"
        ]
    }
}

CATALOGO_BUSQUEDA = [
    {"ambito": "Estatal", "subfolder": "Estatal", "pattern": "pdem", "titulo": "Plan de Desarrollo del Estado de México 2023-2029 (PDEM)"},
    {"ambito": "Internacional", "subfolder": "Internacional", "pattern": "agenda 2030", "titulo": "Agenda 2030 para el Desarrollo Sostenible (ODS)"},
    {"ambito": "Internacional", "subfolder": "Internacional", "pattern": "contribucio", "titulo": "Contribución de las IES a los ODS (ANUIES)"},
    {"ambito": "Nacional", "subfolder": "Nacional", "pattern": "3. ley general", "titulo": "Ley General de Educación Superior (LGES)"},
    {"ambito": "Nacional", "subfolder": "Nacional", "pattern": "2. ley general", "titulo": "Ley General de Educación"},
    {"ambito": "Nacional", "subfolder": "Nacional", "pattern": "pnd_", "titulo": "Plan Nacional de Desarrollo 2025-2030 (PND)"},
    {"ambito": "Nacional", "subfolder": "Nacional", "pattern": "vision_accion", "titulo": "Visión y Acción 2030 (ANUIES)"},
    {"ambito": "UAEMEX", "subfolder": "UAEMEX", "pattern": "pgd_", "titulo": "Plan General de Desarrollo de la UAEMex 2021-2033 (PGD)"},
]

def clean_text(text: str) -> str:
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

def find_file(subfolder: str, pattern: str) -> str | None:
    folder = os.path.join(ROOT_APOYO, subfolder)
    if not os.path.isdir(folder):
        return None
    pattern_lower = pattern.lower()
    for fname in os.listdir(folder):
        if pattern_lower in fname.lower() and fname.endswith(".pdf"):
            return os.path.join(folder, fname)
    return None

def extract_from_pdf(full_path: str, doc_info: dict) -> dict[int, list[dict]]:
    results_by_proj: dict[int, list[dict]] = {p: [] for p in TARGET_PROJECTS}
    print(f"[+] Procesando [{doc_info['ambito']}] {doc_info['titulo']}...")
    try:
        doc = fitz.open(full_path)
        total_pages = len(doc)
        print(f"    Archivo: {os.path.basename(full_path)} | Páginas: {total_pages}")
        
        for page_num in range(total_pages):
            page = doc[page_num]
            text = page.get_text()
            if not text:
                continue
            text_lower = text.lower()
            
            for p_num in TARGET_PROJECTS:
                kws = KEYWORDS_BY_PROJECT[p_num]["keywords"]
                matched_kws = [kw for kw in kws if kw in text_lower]
                
                if len(matched_kws) >= 2 or (len(matched_kws) == 1 and len(matched_kws[0]) > 12):
                    paragraphs = text.split('\n\n')
                    for para in paragraphs:
                        para_clean = clean_text(para)
                        para_lower = para_clean.lower()
                        if any(kw in para_lower for kw in matched_kws) and len(para_clean) > 80:
                            if not any(r["extracto"][:60] == para_clean[:60] for r in results_by_proj[p_num]):
                                results_by_proj[p_num].append({
                                    "documento": doc_info["titulo"],
                                    "archivo_origen": os.path.basename(full_path),
                                    "ambito": doc_info["ambito"],
                                    "pagina": page_num + 1,
                                    "palabras_clave": matched_kws[:4],
                                    "extracto": para_clean[:600]
                                })
            
            for p_num in TARGET_PROJECTS:
                if len(results_by_proj[p_num]) > 12:
                    results_by_proj[p_num] = results_by_proj[p_num][:12]
                    
    except Exception as e:
        print(f"[-] Error al procesar {doc_info['titulo']}: {e}")

    return results_by_proj

def main():
    print("=================================================================")
    print("Iniciando Subagente Extractor de Documentos de Apoyo (v2)...")
    print("=================================================================")
    
    consolidado_apoyo = {
        "metadata": {
            "proposito": "Evidencia documental externa e institucional de soporte para diagnósticos FI 2026-2030",
            "carpeta_origen": ROOT_APOYO,
            "proyectos_cubiertos": TARGET_PROJECTS,
            "documentos_procesados": []
        },
        "evidencia_por_proyecto": {str(p): {
            "numero": p,
            "nombre": KEYWORDS_BY_PROJECT[p]["nombre"],
            "extractos_documentales": []
        } for p in TARGET_PROJECTS}
    }

    for item in CATALOGO_BUSQUEDA:
        fpath = find_file(item["subfolder"], item["pattern"])
        if fpath:
            consolidado_apoyo["metadata"]["documentos_procesados"].append(item["titulo"])
            doc_results = extract_from_pdf(fpath, item)
            for p_num, extracts in doc_results.items():
                consolidado_apoyo["evidencia_por_proyecto"][str(p_num)]["extractos_documentales"].extend(extracts)
        else:
            print(f"[-] Archivo no localizado para patrón: {item['pattern']} en {item['subfolder']}")

    for p_num in TARGET_PROJECTS:
        exts = consolidado_apoyo["evidencia_por_proyecto"][str(p_num)]["extractos_documentales"]
        print(f"Proyecto {p_num:2d} ({KEYWORDS_BY_PROJECT[p_num]['nombre']}): {len(exts)} extractos relevantes consolidados.")

    with open(OUT_JSON, "w", encoding="utf-8") as f_out:
        json.dump(consolidado_apoyo, f_out, indent=2, ensure_ascii=False)

    print("=================================================================")
    print(f"Extracción finalizada exitosamente.")
    print(f"Archivo guardado en: {OUT_JSON}")
    print("=================================================================")

if __name__ == "__main__":
    main()
