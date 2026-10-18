#!/usr/bin/env python3
"""Descarga la propuesta del sitio y completa la Matriz de consistencia.

El libro resultante conserva la plantilla institucional y escribe, por proyecto,
el objetivo general, los objetivos específicos, las políticas, las estrategias y
las metas. Cada meta muestra la clave de la Memoria de cálculo que la sustenta.

Uso normal (API pública, con repliegue automático a la copia local):
    python tools/actualizar_matriz_consistencia_desde_sitio.py

Uso antes de publicar cambios del sitio:
    python tools/actualizar_matriz_consistencia_desde_sitio.py --local
"""

from __future__ import annotations

import argparse
import os
import re
import shutil
import tempfile
from copy import copy
from datetime import datetime
from pathlib import Path
from typing import Any

from openpyxl import load_workbook
from openpyxl.styles import Alignment

from actualizar_memoria_calculo_desde_sitio import load_projects, read_cookie
from plan_memoria_metodologia import complete_projects, normalized, tokens


ROOT = Path(__file__).resolve().parents[1]
PLAN_DIR = ROOT / "Plan"
DEFAULT_MATRIX = PLAN_DIR / "Matriz de consistenciaUltima.xlsx"
DEFAULT_MEMORY = PLAN_DIR / "Memoria de cálculo NS 2026-2030Ultima.xlsx"
DEFAULT_BASE_URL = "https://fingenieria.mx/public/plan/api"
PROJECTS = tuple(range(2, 23))
OBJECTIVE_ROWS = (8, 16, 24)


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Vierte la estructura metodológica del sitio en la Matriz de consistencia."
    )
    parser.add_argument("--workbook", type=Path, default=DEFAULT_MATRIX, help="Matriz de salida.")
    parser.add_argument("--template", type=Path, help="Plantilla de origen; por defecto, la matriz de salida.")
    parser.add_argument("--memory-workbook", type=Path, default=DEFAULT_MEMORY,
                        help="Memoria que se verificará para garantizar congruencia visible.")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="Base de la API pública.")
    parser.add_argument("--cookie-file", type=Path, help="Cookie opcional para incorporar la revisión de ese navegador.")
    parser.add_argument("--proposal-file", type=Path,
                        help="JSON de propuesta ya integrado; conserva sus campos editados al completar la matriz.")
    parser.add_argument("--local", action="store_true", help="Usa Plan/preliminar_fi.json en vez del sitio publicado.")
    parser.add_argument("--no-backup", action="store_true", help="No crea respaldo antes de reemplazar la matriz.")
    parser.add_argument("--dry-run", action="store_true", help="Valida y resume sin escribir el archivo.")
    return parser.parse_args()


def project_sheets(workbook) -> dict[int, Any]:
    result = {}
    for sheet in workbook.worksheets:
        prefix = sheet.title.split(" ", 1)[0]
        if prefix.isdigit():
            result[int(prefix)] = sheet
    return result


def safe_title(number: int, name: str) -> str:
    clean = re.sub(r"[\\/*?:\[\]]", " ", name).strip()
    return f"{number} {clean}"[:31]


def copy_cell_style(source, target) -> None:
    target._style = copy(source._style)
    target.font = copy(source.font)
    target.fill = copy(source.fill)
    target.border = copy(source.border)
    target.alignment = copy(source.alignment)
    target.number_format = source.number_format
    target.protection = copy(source.protection)


def ensure_sheets(workbook, projects: list[dict[str, Any]]) -> dict[int, Any]:
    sheets = project_sheets(workbook)
    if not sheets:
        raise RuntimeError("La plantilla no contiene hojas de proyecto.")
    model = sheets[min(sheets)]
    for project in projects:
        number = int(project["numero"])
        if number in sheets:
            continue
        sheet = workbook.copy_worksheet(model)
        sheet.title = safe_title(number, str(project["nombre"]))
        sheets[number] = sheet
    # Las hojas quedan en el orden metodológico de los proyectos.
    project_tabs = [sheets[number] for number in PROJECTS]
    other_tabs = [sheet for sheet in workbook.worksheets if sheet not in project_tabs]
    workbook._sheets = project_tabs + other_tabs
    return sheets


def write_project(sheet, project: dict[str, Any]) -> None:
    templates = {column: copy(sheet.cell(8, column)._style) for column in range(2, 7)}
    for merged in list(sheet.merged_cells.ranges):
        if merged.min_row >= 8:
            sheet.unmerge_cells(str(merged))
    if sheet.max_row >= 8:
        sheet.delete_rows(8, sheet.max_row - 7)

    objectives = project.get("objetivos_especificos", [])
    spans = []
    total_rows = 0
    for objective in objectives:
        strategy_spans = [max(1, len(strategy.get("metas", []))) for strategy in objective.get("estrategias", [])]
        objective_span = max(1, sum(strategy_spans))
        spans.append((objective_span, strategy_spans))
        total_rows += objective_span
    total_rows = max(1, total_rows)
    sheet.insert_rows(8, total_rows)
    for row in range(8, 8 + total_rows):
        for column in range(2, 7):
            sheet.cell(row, column)._style = copy(templates[column])

    sheet["B3"] = f"Pilar {project.get('pilar', '')}"
    sheet["B4"] = f"Proyecto {project['numero']}. {project['nombre']}"
    sheet["B8"] = str(project.get("objetivo_general", "")).strip()
    policies = project.get("politicas", [])
    end_row = 7 + total_rows
    if total_rows > 1:
        sheet.merge_cells(start_row=8, start_column=2, end_row=end_row, end_column=2)

    cursor = 8
    for objective, (objective_span, strategy_spans) in zip(objectives, spans):
        base = cursor
        objective_id = str(objective.get("id", "")).strip()
        sheet.cell(base, 3).value = str(objective.get("texto", "")).strip()
        related = [str(policy.get("texto", "")).strip() for policy in policies
                   if objective_id in policy.get("objetivos", [])]
        sheet.cell(base, 4).value = "\n".join(f"{index}. {text}" for index, text in enumerate(related, 1))
        if objective_span > 1:
            sheet.merge_cells(start_row=base, start_column=3, end_row=base + objective_span - 1, end_column=3)
            sheet.merge_cells(start_row=base, start_column=4, end_row=base + objective_span - 1, end_column=4)
        strategies = objective.get("estrategias", [])
        strategy_row = base
        for strategy, strategy_span in zip(strategies, strategy_spans):
            sheet.cell(strategy_row, 5).value = str(strategy.get("texto", "")).strip()
            goals = strategy.get("metas", [])
            if strategy_span > 1:
                sheet.merge_cells(start_row=strategy_row, start_column=5,
                                  end_row=strategy_row + strategy_span - 1, end_column=5)
            for goal_index, goal in enumerate(goals):
                description = str(goal.get("descripcion", "")).strip()
                sheet.cell(strategy_row + goal_index, 6).value = description
            strategy_row += strategy_span
        cursor += objective_span

    for row in range(8, end_row + 1):
        longest = max(len(str(sheet.cell(row, column).value or "")) for column in range(2, 7))
        sheet.row_dimensions[row].height = min(120, max(42, 18 + longest / 3.8))
        for column in range(2, 7):
            cell = sheet.cell(row, column)
            # Las celdas internas de un rango combinado son MergedCell y no
            # admiten formato; basta aplicar el ajuste a cada ancla real.
            if cell.__class__.__name__ == "Cell":
                cell.alignment = Alignment(horizontal="left", vertical="top", wrap_text=True)
    for column, width in {"B": 50, "C": 58, "D": 58, "E": 68, "F": 58}.items():
        sheet.column_dimensions[column].width = width
    sheet.sheet_view.showGridLines = False
    sheet.freeze_panes = "B8"
    sheet.page_setup.orientation = "landscape"
    sheet.page_setup.fitToWidth = 1
    sheet.page_setup.fitToHeight = 0
    sheet.sheet_properties.pageSetUpPr.fitToPage = True
    sheet.print_area = f"B2:F{end_row}"


def validate_diagnostic_congruence(project: dict[str, Any]) -> None:
    diagnosis = str(project.get("diagnostico", "")).strip()
    if not diagnosis:
        raise RuntimeError(f"El proyecto {project['numero']} no tiene diagnóstico.")
    proposal = " ".join(
        [str(project.get("objetivo_general", ""))]
        + [str(item.get("texto", "")) for item in project.get("politicas", [])]
        + [str(objective.get("texto", "")) for objective in project.get("objetivos_especificos", [])]
        + [str(strategy.get("texto", ""))
           for objective in project.get("objetivos_especificos", [])
           for strategy in objective.get("estrategias", [])]
    )
    overlap = tokens(diagnosis) & tokens(proposal)
    if len(overlap) < 4:
        raise RuntimeError(
            f"El proyecto {project['numero']} no muestra congruencia léxica suficiente entre diagnóstico y propuesta."
        )


def validate_matrix(workbook, projects: list[dict[str, Any]], report: dict[str, Any]) -> None:
    sheets = project_sheets(workbook)
    if sorted(sheets) != list(PROJECTS):
        raise RuntimeError("La matriz no contiene exactamente los proyectos 2 a 22.")
    exceptions = {(int(item["proyecto"]), str(item.get("descripcion", "")).strip())
                  for item in report["metas_adicionales"]}
    for project in projects:
        validate_diagnostic_congruence(project)
        number = int(project["numero"])
        sheet = sheets[number]
        if str(project.get("objetivo_general", "")).strip() != str(sheet["B8"].value or "").strip():
            raise RuntimeError(f"El objetivo general del proyecto {number} no coincide con el sitio.")
        expected_objectives = [str(objective.get("texto", "")).strip()
                               for objective in project.get("objetivos_especificos", [])]
        expected_strategies = [str(strategy.get("texto", "")).strip()
                               for objective in project.get("objetivos_especificos", [])
                               for strategy in objective.get("estrategias", [])]
        expected_goals = [str(goal.get("descripcion", "")).strip()
                          for objective in project.get("objetivos_especificos", [])
                          for strategy in objective.get("estrategias", [])
                          for goal in strategy.get("metas", [])]
        actual_objectives = [str(sheet.cell(row, 3).value).strip() for row in range(8, sheet.max_row + 1)
                             if sheet.cell(row, 3).value]
        actual_strategies = [str(sheet.cell(row, 5).value).strip() for row in range(8, sheet.max_row + 1)
                             if sheet.cell(row, 5).value]
        actual_goals = [str(sheet.cell(row, 6).value).strip() for row in range(8, sheet.max_row + 1)
                        if sheet.cell(row, 6).value]
        if actual_objectives != expected_objectives:
            raise RuntimeError(f"Los objetivos específicos del proyecto {number} no coinciden.")
        if actual_strategies != expected_strategies:
            raise RuntimeError(f"Las estrategias del proyecto {number} no coinciden.")
        if actual_goals != expected_goals:
            raise RuntimeError(f"Las metas del proyecto {number} no coinciden literalmente con el sitio.")
        for description in expected_goals:
            if not any(item.get("meta_espacio_academico") == description
                       for item in project.get("referencia_base_2026", {}).get("metas", [])) \
                    and (number, description) not in exceptions:
                raise RuntimeError(f"La meta del proyecto {number} no existe en la Memoria ni es una excepción.")


def validate_memory(memory_path: Path, projects: list[dict[str, Any]], report: dict[str, Any]) -> None:
    if not memory_path.is_file():
        raise FileNotFoundError(f"No existe la Memoria para validar congruencia: {memory_path}")
    workbook = load_workbook(memory_path, data_only=False, read_only=True)
    sheets = project_sheets(workbook)
    checked = 0
    for project in projects:
        metas = project.get("referencia_base_2026", {}).get("metas", [])
        if not metas:
            continue
        sheet = sheets.get(int(project["numero"]))
        if sheet is None:
            raise RuntimeError(f"Falta el proyecto {project['numero']} en la Memoria.")
        rows = {str(sheet.cell(row, 8).value or "").strip().upper().replace(" ", ""): row
                for row in range(3, sheet.max_row + 1) if sheet.cell(row, 8).value}
        for item in metas:
            item_key = str(item.get("clave", "")).strip().upper().replace(" ", "")
            row = rows.get(item_key)
            if row is None:
                raise RuntimeError(f"La clave {item.get('clave')} no existe en la Memoria.")
            memory_text = str(sheet.cell(row, 9).value or "").strip()
            if memory_text != str(item.get("meta_espacio_academico", "")).strip():
                raise RuntimeError(
                    f"La meta {item.get('clave')} de la Memoria no es idéntica a la meta editable del sitio. "
                    "Ejecuta primero actualizar_memoria_calculo_desde_sitio.py."
                )
            checked += 1
    additional = workbook["Metas adicionales"]
    written = {str(additional.cell(row, 2).value or "").strip()
               for row in range(3, additional.max_row + 1) if additional.cell(row, 2).value}
    expected = {str(item.get("descripcion", "")).strip() for item in report["metas_adicionales"]}
    if written != expected:
        raise RuntimeError(
            "Las metas adicionales de la Memoria y la Matriz no coinciden. "
            "Ejecuta primero actualizar_memoria_calculo_desde_sitio.py."
        )
    if checked != report["filas_prdi"]:
        raise RuntimeError(f"Se validaron {checked} renglones de Memoria y se esperaban {report['filas_prdi']}.")


def atomic_save(workbook, output_path: Path) -> None:
    temporary = tempfile.NamedTemporaryFile(
        prefix=f".{output_path.stem}-", suffix=output_path.suffix, dir=output_path.parent, delete=False
    )
    temporary_path = Path(temporary.name)
    temporary.close()
    try:
        workbook.save(temporary_path)
        os.replace(temporary_path, output_path)
    finally:
        temporary_path.unlink(missing_ok=True)


def main() -> int:
    args = arguments()
    output_path = args.workbook.resolve()
    template_path = (args.template or args.workbook).resolve()
    if not template_path.is_file():
        raise FileNotFoundError(f"No existe la plantilla: {template_path}")
    projects, source, _ = load_projects(
        args.local, args.base_url, read_cookie(args.cookie_file), args.proposal_file
    )
    if sorted(int(project["numero"]) for project in projects) != list(PROJECTS):
        raise ValueError("La fuente no contiene exactamente los proyectos 2 a 22.")
    projects, report = complete_projects(
        projects, PLAN_DIR, preserve_edits=(not args.local or args.proposal_file is not None)
    )
    workbook = load_workbook(template_path, data_only=False)
    sheets = ensure_sheets(workbook, projects)
    for project in projects:
        write_project(sheets[int(project["numero"])], project)
    validate_matrix(workbook, projects, report)
    validate_memory(args.memory_workbook.resolve(), projects, report)

    print(f"Fuente: {source}")
    print(f"Proyectos escritos: {len(projects)}")
    print(f"Objetivos específicos: {sum(len(p.get('objetivos_especificos', [])) for p in projects)}")
    print(f"Políticas: {sum(len(p.get('politicas', [])) for p in projects)}")
    print(f"Estrategias: {sum(len(o.get('estrategias', [])) for p in projects for o in p.get('objetivos_especificos', []))}")
    print(f"Metas de Matriz: {report['metas_sitio_alineadas'] + len(report['metas_adicionales'])}")
    print(f"Renglones de Memoria comprobados: {report['filas_prdi']}")
    print(f"Excepciones sin renglón PRDI: {len(report['metas_adicionales'])}")
    if args.dry_run:
        print("Simulación terminada; no se guardaron cambios.")
        return 0

    if output_path.exists() and not args.no_backup:
        stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
        backup_dir = output_path.parent / "respaldos"
        backup_dir.mkdir(parents=True, exist_ok=True)
        backup_path = backup_dir / f"{output_path.stem}-{stamp}{output_path.suffix}"
        shutil.copy2(output_path, backup_path)
        print(f"Respaldo: {backup_path}")
    atomic_save(workbook, output_path)
    print(f"Matriz actualizada: {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
