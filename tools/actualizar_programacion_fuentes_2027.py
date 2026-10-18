#!/usr/bin/env python3
"""Actualiza únicamente las sugerencias de IA de programación 2027.

Prioridad de evidencia:
1. Tableros institucionales conservados en Plan/40*.json.
2. Programa Operativo Anual 2026 (PDF).
3. Estimación metodológica explícita.

Las revisiones de usuarios viven en storage/plan/revisiones y no se leen ni se
escriben aquí. El script sólo actualiza Plan/preliminar_fi.json.
"""

from __future__ import annotations

import argparse
import json
import math
import re
import shutil
import tempfile
import unicodedata
from collections import defaultdict
from copy import deepcopy
from datetime import datetime
from pathlib import Path
from typing import Any

import pdfplumber

from plan_memoria_metodologia import (
    YEARS,
    absolute_target,
    calculated_indicator,
    complete_projects,
    describe_activity,
    describe_indicator,
    historical_context,
    indicator_variable_program,
    key,
    normalized,
    percent_target,
)


ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / "Plan"
PROPOSAL = PLAN / "preliminar_fi.json"
DEFAULT_POA = Path(r"D:\POA_2026.pdf")

# Equivalencias temáticas revisadas entre las claves del PRDI vigente y el POA
# anterior. No se usa una coincidencia automática: compartir palabras como
# "personal" o "estudios" no demuestra que dos métricas midan lo mismo.
POA_EQUIVALENTS = {
    "IND-NS-210": "IND-PRDI-158",
    "IND-PRDI-351": "IND-NS-131",
    "ACT-PRDI-NS-2785": "ACT-PRDI-NS-2207",
    "ACT-PRDI-NS-2789": "ACT-PRDI-NS-2207",
    "IND-PRDI-365": "IND-PRDI-231",
    "IND-PRDI-368": "IND-PRDI-220",
    "IND-PRDI-370": "IND-PRDI-219",
    "IND-NS-212": "IND-PRDI-203",
    "IND-PRDI-373": "IND-PRDI-205",
    "ACT-PRDI-NS-2923": "ACT-PRDI-NS-2204",
    "ACT-PRDI-NS-2749": "ACT-PRDI-NS-1857",
    "ACT-PRDI-NS-2992": "ACT-PRDI-NS-1858",
    "ACT-PRDI-NS-3243": "ACT-PRDI-NS-1860",
    "ACT-PRDI-NS-3087": "ACT-PRDI-NS-2689",
    "ACT-PRDI-NS-3244": "ACT-PRDI-NS-2689",
    "ACT-PRDI-NS-3155": "ACT-PRDI-NS-2398",
    "ACT-PRDI-NS-3161": "ACT-PRDI-NS-2394",
    "ACT-PRDI-NS-3247": "ACT-PRDI-NS-2700",
}


def clean_key(value: Any) -> str:
    return re.sub(r"\s+", "", str(value or "")).upper()


def numeric(value: Any) -> float | None:
    try:
        result = float(str(value).replace(",", "").strip())
        return result if math.isfinite(result) else None
    except (TypeError, ValueError):
        return None


def plain(value: Any) -> str:
    text = str(value or "").replace("\n", " ")
    return re.sub(r"\s+", " ", text).strip()


def parse_poa(path: Path) -> dict[str, dict[str, Any]]:
    entries: dict[str, dict[str, Any]] = {}
    with pdfplumber.open(path) as document:
        for page_number, page in enumerate(document.pages, 1):
            for table in page.extract_tables():
                for row in table:
                    if len(row) < 7:
                        continue
                    item_key = clean_key(row[2])
                    if not re.match(r"^(?:ACT|IND)-(?:PRDI|POA|NS)-", item_key):
                        continue
                    description = plain(row[1])
                    formula = plain(row[4])
                    programmed = numeric(row[6])
                    monthly = [value for value in (numeric(cell) for cell in row[7:19]) if value is not None]
                    candidate = {
                        "clave": item_key,
                        "pagina": page_number,
                        "descripcion": description,
                        "formula": formula,
                        "meta_programada": programmed,
                        "valores_mensuales": monthly,
                    }
                    current = entries.get(item_key)
                    if current is None:
                        entries[item_key] = candidate
                        continue
                    # Las tablas partidas entre páginas repiten la clave. Se
                    # conserva la versión con más texto y el valor positivo.
                    for field in ("descripcion", "formula"):
                        if len(candidate[field]) > len(current[field]):
                            current[field] = candidate[field]
                    if (current.get("meta_programada") or 0) <= 0 < (programmed or 0):
                        current["meta_programada"] = programmed
                        current["pagina"] = page_number
                    if len(monthly) > len(current.get("valores_mensuales", [])):
                        current["valores_mensuales"] = monthly
    return entries


def records(filename: str) -> list[dict[str, Any]]:
    data = json.loads((PLAN / filename).read_text(encoding="utf-8"))
    return [
        row
        for indicator in data.get("facultad", {}).get("indicadores", [])
        for row in indicator.get("registros", [])
    ]


def period_of(row: dict[str, Any]) -> str:
    for field in ("Año", "Ciclo escolar", "Ciclo Escolar", "Periodo", "periodo"):
        if row.get(field) is not None:
            return str(row[field])
    return "último periodo publicado"


def source_value(filename: str, metric: str, value: float, mode: str = "last") -> dict[str, Any]:
    rows = [row for row in records(filename) if numeric(row.get(metric)) is not None]
    if not rows:
        return {"archivo": filename, "metrica": metric, "periodo": "no identificado", "valor_base": value}
    if mode == "sum_latest":
        latest = max(period_of(row) for row in rows)
        selected = [row for row in rows if period_of(row) == latest]
    else:
        matching = [row for row in rows if abs((numeric(row.get(metric)) or 0) - value) < 1e-7]
        selected = matching[-1:] or rows[-1:]
        latest = period_of(selected[-1])
    return {"archivo": filename, "metrica": metric, "periodo": latest, "valor_base": value}


def latest_grouped_sum(filename: str, metric: str) -> tuple[float, dict[str, Any]]:
    grouped: dict[str, list[float]] = defaultdict(list)
    for row in records(filename):
        value = numeric(row.get(metric))
        if value is not None:
            grouped[period_of(row)].append(value)
    if not grouped:
        raise ValueError(f"No se encontró {metric} en {filename}.")
    latest = max(grouped)
    # Algunas visualizaciones repiten el total además del desglose. Si hay un
    # solo valor mayor que la suma de los demás, se toma como total; en otro
    # caso se suma el desglose del último periodo.
    values = grouped[latest]
    total = max(values) if len(values) > 2 and max(values) >= sum(values) - max(values) else sum(values)
    return total, {"archivo": filename, "metrica": metric, "periodo": latest, "valor_base": total}


def source_catalog(context: dict[str, float]) -> dict[str, dict[str, Any]]:
    catalog = {
        "matricula": source_value("4005_matricula_total.json", "Matricula Ciclo Actual1", context["matricula"]),
        "nuevo_ingreso": source_value("4003_nuevo_ingreso_uaemex.json", "Ingreso Ciclo Actual", context["nuevo_ingreso"]),
        "egresados": source_value("4007_egresados.json", "Egresados Ciclo Actual1", context["egresados"]),
        "tutoria": source_value("4023_tutores.json", "Alumnos que reciben Tutoría", context["tutoria"]),
        "ptc": source_value("4027_ptc_registrados_en_sep.json", "Total_PTC_SEP1", context["ptc"], "sum_latest"),
        "academicos": source_value("4031_personal_academico.json", "Total", context["academicos"]),
        "administrativos": source_value("4033_personal_administrativo.json", "Total", context["administrativos"]),
        "servicio_social": source_value("4018_servicio_social.json", "Total SS Año Actual", context["servicio_social"]),
    }
    try:
        value, info = latest_grouped_sum("4020_numero_de_becas_uaemex.json", "Suma de Total_Becas")
        catalog["becas"] = info | {"valor_base": value}
    except ValueError:
        pass
    try:
        value, info = latest_grouped_sum("4025_snii.json", "Suma de Total_SNII")
        catalog["snii"] = info | {"valor_base": value}
    except ValueError:
        pass
    catalog["personal"] = {
        "archivos": [catalog["academicos"]["archivo"], catalog["administrativos"]["archivo"]],
        "metrica": "Total de personal académico + total de personal administrativo",
        "periodo": f"{catalog['academicos']['periodo']} / {catalog['administrativos']['periodo']}",
        "valor_base": context["personal"],
    }
    catalog["comunidad"] = {
        "archivos": [catalog["matricula"]["archivo"], catalog["academicos"]["archivo"], catalog["administrativos"]["archivo"]],
        "metrica": "Matrícula + personal académico + personal administrativo",
        "periodo": "últimos periodos disponibles",
        "valor_base": context["comunidad"],
    }
    return catalog


def variable_source_key(name: str) -> str | None:
    text = normalized(name)
    if "nuevo ingreso" in text:
        return "nuevo_ingreso"
    if "egresad" in text and "titulado" not in text:
        return "egresados"
    if "tutoria" in text or "beneficiados con tutoria" in text:
        return "tutoria"
    if "becas" in text or "apoyos o estimulos" in text:
        return "becas"
    if "snii" in text or "sni" in text:
        return "snii"
    if ("profesores de tiempo completo" in text or re.search(r"\bptc\b", text)) and "total" in text:
        return "ptc"
    if ("personal administrativo" in text or "administrativos" in text) and "total" in text:
        return "administrativos"
    if ("personal academico" in text or "academicos de nivel superior" in text or "total de academicos" in text) and "total" in text:
        return "academicos"
    if "personal universitario" in text and "total" in text:
        return "personal"
    if ("comunidad universitaria" in text or "integrantes de la comunidad" in text) and ("total" in text or "integrantes" in text):
        return "comunidad"
    if "servicio social" in text:
        return "servicio_social"
    if any(term in text for term in ("matricula", "total de estudiantes", "total del estudiantado")):
        return "matricula"
    return None


def json_note(info: dict[str, Any], formula: str, derived: bool = False) -> dict[str, Any]:
    result = {
        "tipo": "calculo_con_tablero" if derived else "tablero_uaemex",
        "archivo": info.get("archivo"),
        "archivos": info.get("archivos"),
        "metrica": info.get("metrica"),
        "periodo": info.get("periodo"),
        "valor_base": info.get("valor_base"),
        "formula": formula,
        "nota": "Los JSON 40*.json reproducen los Tableros de variables e indicadores de la UAEMéx.",
    }
    return {field: value for field, value in result.items() if value not in (None, [], "")}


def poa_value(entry: dict[str, Any]) -> float:
    programmed = numeric(entry.get("meta_programada")) or 0
    if programmed > 0:
        return programmed
    description = entry.get("descripcion", "")
    return absolute_target(description) if description else 1.0


def poa_match(item: dict[str, Any], entries: dict[str, dict[str, Any]]) -> dict[str, Any] | None:
    item_key = clean_key(item.get("clave"))
    exact = entries.get(item_key)
    if exact:
        return exact
    equivalent_key = POA_EQUIVALENTS.get(item_key)
    if not equivalent_key or equivalent_key not in entries:
        return None
    return {**entries[equivalent_key], "equivalencia_revisada": item_key}


def poa_note(entry: dict[str, Any], value: float) -> dict[str, Any]:
    result = {
        "tipo": "poa_2026",
        "archivo": "POA_2026.pdf",
        "pagina": entry["pagina"],
        "clave": entry["clave"],
        "valor_base": value,
        "formula": f"2027 = {value:g}; se conserva como referencia anual la cantidad programada en el POA 2026.",
        "nota": "Se usa el POA anterior porque no se encontró una serie directamente comparable en los JSON 40*.json.",
    }
    if entry.get("equivalencia_revisada"):
        result["criterio_coincidencia"] = (
            f"Equivalencia temática revisada entre {entry['equivalencia_revisada']} y la meta "
            f"{entry['clave']} del POA 2026; se conserva la unidad antes de trasladar el valor."
        )
    return result


def estimate_note(item: dict[str, Any], value: float) -> dict[str, Any]:
    text = normalized(str(item.get("meta_prdi", "")))
    target = absolute_target(str(item.get("meta_prdi", "")))
    quota = 1 if "%" in text or "espacios academicos" in text else math.ceil(target / 55)
    if value == 0:
        formula = (
            f"2027 = 0; la cuota local de {quota:g} se difiere a un año posterior según el plazo de la meta, "
            "por lo que cero significa programación y no ausencia de trabajo."
        )
    elif "%" in text or "espacios academicos" in text:
        formula = f"2027 = {value:g}; cuota local mínima definida para un espacio académico."
    elif "semestral" in text:
        formula = f"2027 = 2 × ceil({target:g} / 55) = {value:g}."
    else:
        formula = f"2027 = ceil({target:g} / 55) = {value:g}; reparto conservador entre 55 espacios académicos."
    return {
        "tipo": "estimacion",
        "formula": formula,
        "nota": "No se encontró una serie comparable en los JSON 40*.json ni una meta equivalente en el POA 2026.",
    }


def source_files(note: dict[str, Any]) -> list[str]:
    files = list(note.get("archivos", []))
    if note.get("archivo"):
        files.append(note["archivo"])
    return sorted(set(files))


def enrich_item(item: dict[str, Any], poa: dict[str, dict[str, Any]], context: dict[str, float],
                catalog: dict[str, dict[str, Any]]) -> str:
    item_key = clean_key(item.get("clave"))
    variables = item.get("variables", [])
    is_activity = normalized(str(item.get("tipo", ""))).startswith("actividad")
    poa_entry = poa_match(item, poa)
    provenance_type = "estimacion"

    if is_activity:
        first = variables[0] if variables else None
        source_key = variable_source_key(str(first.get("nombre", ""))) if first else None
        if first and source_key and source_key in catalog:
            info = catalog[source_key]
            value = float(info["valor_base"])
            for year in YEARS:
                first.setdefault("programacion", {})[str(year)] = round(value)
            first["fuente_programacion_2027"] = json_note(
                info, f"2027 = {value:g}; se conserva el último dato institucional disponible como base prudente."
            )
            provenance_type = "tablero_uaemex"
        elif first and poa_entry:
            value = poa_value(poa_entry)
            first.setdefault("programacion", {})["2027"] = value
            if any(term in normalized(str(item.get("meta_prdi", ""))) for term in ("anualmente", "cada ano", "al ano", "anual")):
                for year in YEARS:
                    first["programacion"][str(year)] = value
            first["fuente_programacion_2027"] = poa_note(poa_entry, value)
            provenance_type = "poa_2026"
        elif first:
            value = float(first.get("programacion", {}).get("2027") or 0)
            first["fuente_programacion_2027"] = estimate_note(item, value)

        schedule = {
            year: float(variables[0].get("programacion", {}).get(str(year)) or 0)
            for year in YEARS
        } if variables else {year: 0 for year in YEARS}
        item["proyeccion_libro"] = {str(year): value for year, value in schedule.items()}
        item["proyeccion_indicador"] = dict(item["proyeccion_libro"])
        item["meta_espacio_academico"] = describe_activity(item, schedule)
    else:
        # Si el tablero publica directamente este indicador, la trayectoria ya
        # fue construida por complete_projects a partir de esa serie.
        direct_indicator_files = {
            "IND-PRDI-40": "4017_estudios_profesionales_indice_de_titulacion.json",
            "IND-NS-127": "4013_estudios_profesionales_eficiencia_terminal_cohorte.json",
            "IND-PRDI-34": "4015_estudios_profesionales_abandono_escolar.json",
            "IND-PRDI-353": "4023_tutores.json",
        }
        percentages = {year: float(item.get("proyeccion_indicador", {}).get(str(year)) or 0) for year in YEARS}
        indicator_source = direct_indicator_files.get(item_key)
        if not indicator_source and poa_entry:
            baseline = poa_value(poa_entry)
            target = percent_target(str(item.get("meta_prdi", ""))) or baseline
            decreasing = any(word in normalized(str(item.get("meta_prdi", ""))) for word in ("disminuir", "reducir"))
            end = min(baseline, target) if decreasing else max(baseline, target)
            percentages = {
                year: round(baseline + (end - baseline) * (year - 2026) / 4, 2)
                for year in YEARS
            }
            item["variables"] = indicator_variable_program(item, percentages, context)
            variables = item["variables"]
            item["proyeccion_indicador"] = {
                str(year): value for year, value in calculated_indicator(item, variables).items()
            }
            provenance_type = "poa_2026"

        desired_percentages = {
            year: float(item.get("proyeccion_indicador", {}).get(str(year)) or 0)
            for year in YEARS
        }
        denominator_source_key = variable_source_key(str(variables[-1].get("nombre", ""))) if variables else None
        for index, variable in enumerate(variables):
            name = str(variable.get("nombre", ""))
            source_key = variable_source_key(name)
            value = float(variable.get("programacion", {}).get("2027") or 0)
            if source_key and source_key in catalog:
                info = catalog[source_key]
                direct_value = float(info["valor_base"])
                direct_observation = (
                    index == len(variables) - 1
                    or (index == 0 and source_key != denominator_source_key)
                )
                if item_key != "IND-PRDI-34" and direct_observation:
                    for year in YEARS:
                        variable.setdefault("programacion", {})[str(year)] = round(direct_value)
                    value = round(direct_value)
                percentage = float(item.get("proyeccion_indicador", {}).get("2027") or 0)
                if index == 0 and len(variables) > 1 and abs(value - direct_value) > 1e-7:
                    denominator = float(variables[-1].get("programacion", {}).get("2027") or direct_value)
                    formula = f"2027 = redondeo({denominator:g} × {percentage:g} / 100) = {value:g}."
                elif abs(value - direct_value) > 1e-7:
                    formula = (
                        f"2027 = {value:g}; valor derivado desde la base {direct_value:g}. "
                        f"Fórmula de la memoria: {item.get('metodo_calculo') or 'relación definida por el indicador'}."
                    )
                else:
                    formula = f"2027 = {value:g}; se conserva el último dato institucional disponible."
                variable["fuente_programacion_2027"] = json_note(info, formula, abs(value - direct_value) > 1e-7)
                provenance_type = "tablero_uaemex"
            elif indicator_source:
                formula = (
                    f"2027 = {value:g}; se aplica la fórmula de la memoria "
                    f"«{item.get('metodo_calculo') or 'relación definida por el indicador'}» "
                    "a la trayectoria publicada."
                )
                variable["fuente_programacion_2027"] = {
                    "tipo": "calculo_con_tablero", "archivo": indicator_source,
                    "formula": formula,
                    "nota": "La serie directa del indicador se usa para fijar el porcentaje; las variables se reconstruyen para conservar la fórmula de la memoria.",
                }
                provenance_type = "tablero_uaemex"
            elif poa_entry:
                variable["fuente_programacion_2027"] = {
                    **poa_note(poa_entry, poa_value(poa_entry)),
                    "formula": (
                        f"2027 = {value:g}; variable reconstruida con la fórmula de la memoria "
                        f"«{item.get('metodo_calculo') or 'relación definida por el indicador'}» y la meta del POA 2026."
                    ),
                }
                provenance_type = "poa_2026"
            else:
                denominator = float(variables[-1].get("programacion", {}).get("2027") or 0) if variables else 0
                pct = float(item.get("proyeccion_indicador", {}).get("2027") or 0)
                if index == 0 and len(variables) > 1:
                    formula = f"2027 = redondeo({denominator:g} × {pct:g} / 100) = {value:g}."
                else:
                    formula = f"2027 = {value:g}; supuesto conservador para mantener operable la fórmula del indicador."
                variable["fuente_programacion_2027"] = {
                    "tipo": "estimacion", "formula": formula,
                    "nota": "No se encontró una variable directamente comparable en los JSON 40*.json ni en el POA 2026.",
                }

        # Cuando el tablero aporta el universo (denominador), se recalcula el
        # numerador para que el cambio de base no distorsione la meta sugerida.
        # Se exceptúan fórmulas con estructura propia y numeradores observados.
        numerator_source_key = variable_source_key(str(variables[0].get("nombre", ""))) if variables else None
        if (len(variables) >= 2 and item_key not in {"IND-PRDI-34", "IND-PRDI-410"}
                and denominator_source_key in catalog
                and (numerator_source_key is None or numerator_source_key == denominator_source_key)):
            denominator_info = catalog[denominator_source_key]
            for year in YEARS:
                denominator = float(variables[-1].get("programacion", {}).get(str(year)) or 0)
                variables[0].setdefault("programacion", {})[str(year)] = round(
                    denominator * desired_percentages[year] / 100
                )
            numerator_2027 = float(variables[0]["programacion"]["2027"])
            denominator_2027 = float(variables[-1]["programacion"]["2027"])
            variables[0]["fuente_programacion_2027"] = json_note(
                denominator_info,
                f"2027 = redondeo({denominator_2027:g} × {desired_percentages[2027]:g} / 100) = {numerator_2027:g}.",
                True,
            )

        item["proyeccion_indicador"] = {
            str(year): value for year, value in calculated_indicator(item, variables).items()
        }
        source_description = "serie histórica directamente comparable del sitio" if provenance_type == "tablero_uaemex" else "POA 2026" if provenance_type == "poa_2026" else "estimación explícita"
        item["meta_espacio_academico"] = describe_indicator(
            item,
            {year: float(item["proyeccion_indicador"][str(year)]) for year in YEARS},
            source_description,
        )

    notes = [variable.get("fuente_programacion_2027", {}) for variable in item.get("variables", [])]
    item["fuentes_programacion_2027"] = source_files({
        "archivos": [filename for note in notes for filename in source_files(note)]
    })
    item["criterio_programacion"] = (
        "La sugerencia 2027 prioriza los Tableros de variables e indicadores de la UAEMéx (JSON 40*.json); "
        "si no existe una serie comparable, usa el POA 2026 y, en último término, una estimación con fórmula visible."
    )
    return provenance_type


def goals_by_key(project: dict[str, Any]) -> dict[str, dict[str, Any]]:
    result = {}
    for objective in project.get("objetivos_especificos", []):
        for strategy in objective.get("estrategias", []):
            for goal in strategy.get("metas", []):
                item_key = clean_key(goal.get("indicador"))
                if item_key:
                    result[item_key] = goal
    return result


def atomic_write(path: Path, payload: dict[str, Any]) -> None:
    with tempfile.NamedTemporaryFile("w", encoding="utf-8", suffix=".json", dir=path.parent, delete=False) as temporary:
        json.dump(payload, temporary, ensure_ascii=False, indent=2)
        temporary.write("\n")
        temp_path = Path(temporary.name)
    temp_path.replace(path)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--poa", type=Path, default=DEFAULT_POA)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()
    if not args.poa.is_file():
        raise FileNotFoundError(args.poa)

    payload = json.loads(PROPOSAL.read_text(encoding="utf-8"))
    recomputed, _ = complete_projects(deepcopy(payload["proyectos"]), PLAN, preserve_edits=False)
    poa = parse_poa(args.poa)
    context = historical_context(PLAN)
    catalog = source_catalog(context)
    counts = defaultdict(int)

    current_projects = {int(project["numero"]): project for project in payload["proyectos"]}
    for project in recomputed:
        current = current_projects[int(project["numero"])]
        memory = project["referencia_base_2026"]
        for item in memory.get("metas", []):
            counts[enrich_item(item, poa, context, catalog)] += 1
        current["referencia_base_2026"] = memory
        suggestions = goals_by_key(current)
        for item in memory.get("metas", []):
            goal = suggestions.get(clean_key(item.get("clave")))
            if not goal:
                continue
            goal["descripcion"] = item.get("meta_espacio_academico", goal.get("descripcion", ""))
            goal["metodo"] = item.get("criterio_programacion", goal.get("metodo", ""))
            goal["programacion_variables"] = [
                {
                    "nombre": variable.get("nombre", ""),
                    "programacion": {str(year): variable.get("programacion", {}).get(str(year)) for year in YEARS},
                }
                for variable in item.get("variables", [])
            ]

    total_variables = sum(
        len(item.get("variables", []))
        for project in payload["proyectos"]
        for item in project.get("referencia_base_2026", {}).get("metas", [])
    )
    missing_notes = sum(
        1
        for project in payload["proyectos"]
        for item in project.get("referencia_base_2026", {}).get("metas", [])
        for variable in item.get("variables", [])
        if not variable.get("fuente_programacion_2027")
    )
    print(f"Filas POA identificadas: {len(poa)}")
    print(f"Variables con trazabilidad: {total_variables - missing_notes}/{total_variables}")
    print("Origen principal por meta: " + ", ".join(f"{name}={value}" for name, value in sorted(counts.items())))
    if missing_notes:
        raise RuntimeError(f"Faltan notas de fuente en {missing_notes} variables.")
    if args.dry_run:
        print("Simulación terminada; no se modificaron archivos.")
        return 0

    backup_dir = PLAN / "respaldos"
    backup_dir.mkdir(parents=True, exist_ok=True)
    backup = backup_dir / f"preliminar_fi-antes-fuentes-2027-{datetime.now():%Y%m%d-%H%M%S}.json"
    shutil.copy2(PROPOSAL, backup)
    atomic_write(PROPOSAL, payload)
    print(f"Respaldo: {backup}")
    print(f"Actualizado: {PROPOSAL}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
