# -*- coding: utf-8 -*-
"""
Genera el archivo de notas consolidadas en formato Markdown:
D:\Proyectos\plataformaFI\Plan\AcadA\notas_referencia_diagnosticos.md
"""
import json
import os

ACADA = r"D:\Proyectos\plataformaFI\Plan\AcadA"
json_path = os.path.join(ACADA, "puntos_proyectos_desarrollo.json")

with open(json_path, "r", encoding="utf-8") as f:
    data = json.load(f)

md_lines = []
md_lines.append("# Notas Consolidadas de Referencia para Diagnósticos del Plan de Desarrollo 2026–2030")
md_lines.append("## Facultad de Ingeniería — UAEMEX (Área Académica)\n")
md_lines.append("> **Documento de consulta y trazabilidad:** Este archivo consolida los requisitos oficiales de la *Guía para la formulación de planes de desarrollo* (SDFI, 2026), el marco metodológico de `contexto.md`, el inventario estadístico de las Agendas Universitarias (archivos JSON 4001 a 4037), la matriz FODA y los borradores preliminares para los proyectos **2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18 y 19**.\n")

md_lines.append("---")
md_lines.append("## 1. Criterios Metodológicos y Reglas Institucionales Obligatorias")
md_lines.append("Basados en la *Guía SDFI 2026* y el manual operativo `contexto.md`:\n")
md_lines.append("1. **Cadena de Razonamiento Unidireccional:**")
md_lines.append("   - `Datos históricos + Evidencia cualitativa + Contexto externo → Análisis FODA → Diagnóstico interpretativo → Objetivos → Políticas y Estrategias → Metas cuantificadas`.")
md_lines.append("2. **Función del Diagnóstico:**")
md_lines.append("   - Explica la situación actual, los retos y las tendencias de al menos **8 años previos**.")
md_lines.append("   - Debe redactarse en **prosa continua y rigurosa**, en **tercera persona** o estilo institucional impersonal.")
md_lines.append("   - Debe preparar al lector para comprender las necesidades de transformación sin anticipar ni listar las metas numéricas futuras.")
md_lines.append("3. **Estructura Interna de la Prosa por Diagnóstico:**")
md_lines.append("   - **a) Capacidad o punto de partida:** Dimensión central y magnitud actual.")
md_lines.append("   - **b) Trayectoria histórica:** Comparación de extremos (mínimo 8 años) y etapas intermedias.")
md_lines.append("   - **c) Interpretación sustantiva:** Significado de los datos para la Facultad.")
md_lines.append("   - **d) Tensión estratégica:** Contraste entre fortalezas y debilidades/brechas detectadas.")
md_lines.append("   - **e) Desagregación:** Diferenciación por programas educativos (las 6 licenciaturas o posgrados), género o áreas.")
md_lines.append("   - **f) Entorno e Inferencia:** Oportunidades y amenazas de los entornos estatal/nacional/laboral.")
md_lines.append("   - **g) Cierre direccional:** Hacia dónde debe orientarse el esfuerzo estratégico del espacio académico.")
md_lines.append("4. **Prohibiciones Editoriales Expresas:**")
md_lines.append("   - No usar frases de bitácora como: *'Se consultó el tablero'*, *'El archivo de Excel indica'*, *'La categoría 4001 muestra'*, *'Este diagnóstico corresponde al proyecto'*, *'Hace falta consultar'*, *'La meta será'*.")
md_lines.append("   - Las fuentes se acreditan en citas APA, notas al pie de tablas y gráficas; la prosa explica la realidad académica de la Facultad.")
md_lines.append("5. **Tablas y Gráficas de Respaldo:**")
md_lines.append("   - Cada tabla o gráfica debe llevar numeración, título descriptivo, parámetros claros y fuente en formato oficial.")
md_lines.append("   - Se cuenta con utilerías web en el módulo Plan (`plan-canvas`, cruces de indicadores y pie de figura sugerido).\n")

md_lines.append("---")
md_lines.append("## 2. Inventario Consolidado por Proyecto Objetivo\n")

for p_num_str, p_data in data["proyectos"].items():
    p_num = p_data["numero"]
    p_nombre = p_data["nombre"]
    p_pilar = p_data["pilar"]
    p_clave = p_data["clave"]
    
    md_lines.append(f"### Proyecto {p_num}. {p_nombre}")
    md_lines.append(f"- **Clave Programática:** `{p_clave}` | **Pilar:** {p_pilar}")
    md_lines.append(f"- **Hojas asociadas en Excel:** `Matriz de consistenciaUltima.xlsx` / `Memoria de cálculo NS 2026-2030Ultima.xlsx`")
    
    # Ejes y puntos de la guía
    md_lines.append("\n#### A. Puntos que se deben desarrollar según la Guía (Anexo 1)")
    for eje in p_data["puntos_guia_anexo1"]:
        md_lines.append(f"- **{eje['eje']}:**")
        for pt in eje["puntos"]:
            md_lines.append(f"  * {pt}")
            
    # Fuentes estadísticas
    md_lines.append("\n#### B. Fuentes Estadísticas Disponibles en JSON")
    stats = p_data.get("archivos_estadisticos_json", [])
    if stats:
        for s in stats:
            cid = s["id_categoria"]
            tit = s["titulo"]
            arch = s["archivo"]
            mets = ", ".join(s["metricas_clave"][:4]) if s["metricas_clave"] else "Registros agregados"
            md_lines.append(f"- `[{cid}]` **{tit}** (`{arch}`)")
            if mets:
                md_lines.append(f"  * *Métricas principales:* {mets}")
    else:
        md_lines.append("- *No cuenta con series numéricas directas en los tableros 4001–4037. Su diagnóstico se sustenta en fuentes cualitativas, normativas, inventario físico institucional y cuestionarios FI 360.*")
        
    # Diagnóstico preliminar e inferencia FODA
    md_lines.append("\n#### C. Diagnóstico Preliminar y Tensión FODA Identificada")
    diag_prev = p_data.get("diagnostico_preliminar_base", "")
    if diag_prev:
        md_lines.append(f"**Borrador de partida:** {diag_prev}\n")
    
    foda = p_data.get("foda_estructurado", {})
    if any(foda.values()):
        md_lines.append("**Elementos clave del FODA:**")
        if foda.get("fortalezas"):
            md_lines.append(f"- *Fortalezas:* {'; '.join(foda['fortalezas'][:3])}")
        if foda.get("debilidades"):
            md_lines.append(f"- *Debilidades:* {'; '.join(foda['debilidades'][:3])}")
        if foda.get("oportunidades"):
            md_lines.append(f"- *Oportunidades:* {'; '.join(foda['oportunidades'][:2])}")
        if foda.get("amenazas"):
            md_lines.append(f"- *Amenazas:* {'; '.join(foda['amenazas'][:2])}")

    # Cruces y referencias
    cruces = p_data.get("cruces_indicadores", [])
    if cruces:
        md_lines.append("\n#### D. Cruces de Indicadores y Gráficas Disponibles en la Plataforma")
        for cr in cruces:
            arch_cr = cr.get("archivo", "")
            tipo_cr = cr.get("tipo", "")
            fnts_cr = cr.get("fuentes", [])
            md_lines.append(f"- Gráfica/Cruce: `{arch_cr}` ({tipo_cr}, fuentes: {fnts_cr})")

    refs = p_data.get("referencias_apa", [])
    if refs:
        md_lines.append("\n#### E. Referencias APA")
        for r in refs[:3]:
            md_lines.append(f"- {r}")

    md_lines.append("\n" + "-"*40 + "\n")

out_md_path = os.path.join(ACADA, "notas_referencia_diagnosticos.md")
with open(out_md_path, "w", encoding="utf-8") as f_out:
    f_out.write("\n".join(md_lines))

print(f"Archivo de notas consolidadas creado con éxito en: {out_md_path}")
