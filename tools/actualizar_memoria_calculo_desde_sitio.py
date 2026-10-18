#!/usr/bin/env python3
"""Completa la memoria oficial con la propuesta metodológica publicada en Plan.

Uso normal:
    python tools/actualizar_memoria_calculo_desde_sitio.py

La fuente primaria son las API públicas del sitio. Con --local se usa y actualiza
Plan/preliminar_fi.json, útil antes de desplegar una nueva propuesta.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import sys
import tempfile
import urllib.error
import urllib.request
from copy import copy
from datetime import datetime
from pathlib import Path
from typing import Any

from openpyxl import load_workbook

from plan_memoria_metodologia import PROJECT_AREAS, YEARS, complete_projects, key, normalized


ROOT = Path(__file__).resolve().parents[1]
PLAN_DIR = ROOT / "Plan"
DEFAULT_WORKBOOK = PLAN_DIR / "Memoria de cálculo NS 2026-2030Ultima.xlsx"
LOCAL_PROPOSAL = PLAN_DIR / "preliminar_fi.json"
DEFAULT_BASE_URL = "https://fingenieria.mx/public/plan/api"
PROJECTS = tuple(range(2, 23))


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Alinea la propuesta del sitio con todos los renglones PRDI del libro oficial.")
    parser.add_argument("--workbook", type=Path, default=DEFAULT_WORKBOOK, help="Libro de salida.")
    parser.add_argument("--template", type=Path, help="Plantilla de origen; por defecto se usa el mismo libro de salida.")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="Base de la API pública.")
    parser.add_argument("--cookie-file", type=Path, help="Cookie opcional para incorporar revisiones privadas de ese navegador.")
    parser.add_argument("--proposal-file", type=Path,
                        help="JSON de propuesta ya integrado; conserva sus campos editados al completar la memoria.")
    parser.add_argument("--local", action="store_true", help="Usa Plan/preliminar_fi.json en vez del sitio publicado.")
    parser.add_argument("--sync-local-proposal", action="store_true", help="Guarda la memoria completada en preliminar_fi.json.")
    parser.add_argument("--no-backup", action="store_true", help="No crea respaldo antes de reemplazar el libro.")
    parser.add_argument("--dry-run", action="store_true", help="Valida y resume sin escribir archivos.")
    return parser.parse_args()


def read_cookie(path: Path | None) -> str | None:
    if path is None:
        return None
    value = path.read_text(encoding="utf-8").strip()
    if not value:
        raise ValueError("El archivo de cookie está vacío.")
    return value


def fetch_project(number: int, base_url: str, cookie: str | None) -> dict[str, Any]:
    request = urllib.request.Request(
        f"{base_url.rstrip('/')}/{number}",
        headers={"Accept": "application/json", "User-Agent": "Traza-FI-memoria-PRDI/2.0",
                 **({"Cookie": cookie} if cookie else {})},
    )
    with urllib.request.urlopen(request, timeout=45) as response:
        payload = json.loads(response.read().decode("utf-8"))
    proposal = payload.get("propuesta") or {}
    revisions = payload.get("revisiones") or []
    if revisions:
        proposal = {**proposal, **(revisions[-1].get("contenido") or {})}
    if int(proposal.get("numero", number)) != number:
        raise ValueError(f"La API devolvió un proyecto inesperado para {number}.")
    return proposal


def load_projects(local: bool, base_url: str, cookie: str | None,
                  proposal_file: Path | None = None) -> tuple[list[dict[str, Any]], str, dict[str, Any] | None]:
    if proposal_file is not None:
        payload = json.loads(proposal_file.resolve().read_text(encoding="utf-8-sig"))
        return payload["proyectos"], str(proposal_file.resolve()), payload
    if local:
        payload = json.loads(LOCAL_PROPOSAL.read_text(encoding="utf-8"))
        return payload["proyectos"], str(LOCAL_PROPOSAL), payload
    try:
        projects = [fetch_project(number, base_url, cookie) for number in PROJECTS]
        return projects, base_url, None
    except (OSError, ValueError, RuntimeError, urllib.error.URLError, json.JSONDecodeError) as error:
        print(f"Aviso: no se pudo consultar el sitio ({error}). Se usará la propuesta local.", file=sys.stderr)
        payload = json.loads(LOCAL_PROPOSAL.read_text(encoding="utf-8"))
        return payload["proyectos"], str(LOCAL_PROPOSAL), payload


def project_sheets(workbook) -> dict[int, Any]:
    result = {}
    for sheet in workbook.worksheets:
        prefix = sheet.title.split(" ", 1)[0]
        if prefix.isdigit():
            result[int(prefix)] = sheet
    return result


def rows_by_key(sheet) -> dict[str, int]:
    return {key(sheet.cell(row, 8).value): row for row in range(3, sheet.max_row + 1) if sheet.cell(row, 8).value}


def write_prdi_item(sheet, row: int, item: dict[str, Any]) -> None:
    sheet.cell(row, 9).value = str(item["meta_espacio_academico"]).strip()
    variables = item.get("variables", [])
    for offset, variable in enumerate(variables):
        variable_row = row + offset
        for column, year in zip(range(14, 18), YEARS):
            sheet.cell(variable_row, column).value = variable.get("programacion", {}).get(str(year))
    if normalized(str(item.get("tipo", ""))).startswith("actividad"):
        for output_column, input_column in zip(range(18, 23), range(13, 18)):
            letter = sheet.cell(row, input_column).column_letter
            sheet.cell(row, output_column).value = f'=IF({letter}{row}="","",{letter}{row})'
    else:
        item_key = key(item.get("clave"))
        for output_column, input_column in zip(range(18, 23), range(13, 18)):
            letter = sheet.cell(row, input_column).column_letter
            if item_key == "IND-PRDI-34":
                formula = f'=IF({letter}{row + 3},(1-(({letter}{row}-{letter}{row + 1}+{letter}{row + 2})/{letter}{row + 3}))*100,"")'
            elif item_key == "IND-PRDI-410":
                formula = f'=IF({letter}{row + 1},(({letter}{row}/{letter}{row + 1})-1)*100,"")'
            else:
                formula = f'=IF({letter}{row + 1},{letter}{row}/{letter}{row + 1}*100,"")'
            sheet.cell(row, output_column).value = formula
    if not sheet.cell(row, 23).value:
        sheet.cell(row, 23).value = str(item.get("medicion") or "Anual")
    sheet.cell(row, 24).value = str(item.get("area_responsable") or "")


def copy_style(source, target) -> None:
    target._style = copy(source._style)
    target.font = copy(source.font)
    target.fill = copy(source.fill)
    target.border = copy(source.border)
    target.alignment = copy(source.alignment)
    target.number_format = source.number_format
    target.protection = copy(source.protection)


def clear_additional(sheet) -> None:
    if sheet.max_row > 2:
        sheet.delete_rows(3, sheet.max_row - 2)


def additional_schedule(description: str) -> dict[int, int]:
    text = normalized(description)
    result = {year: 0 for year in YEARS}
    for first, last in re.findall(r"(202[7-9]|2030)\s*(?:a|al|hasta|-)\s*(202[7-9]|2030)", text):
        for year in range(int(first), int(last) + 1):
            result[year] = 1
    for year in YEARS:
        if str(year) in text:
            result[year] = 1
    if not any(result.values()) or any(term in text for term in ("cada ano", "anualmente", "hasta 2030")):
        result = {year: 1 for year in YEARS}
    return result


def write_additional(workbook, items: list[dict[str, Any]]) -> None:
    sheet = workbook["Metas adicionales"]
    clear_additional(sheet)
    activity_style = workbook["3 Est avanzados excelencia"]
    widths = {"A": 38, "B": 55, "C": 16, "D": 28, "E": 38, "F": 11, "G": 10, "H": 10, "I": 10,
              "J": 10, "K": 11, "L": 10, "M": 10, "N": 10, "O": 10, "P": 16, "Q": 34}
    for column, width in widths.items():
        sheet.column_dimensions[column].width = width
    sheet.freeze_panes = "A3"
    for index, item in enumerate(items, start=3):
        for target_column, source_column in enumerate((1, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24), start=1):
            copy_style(activity_style.cell(3, source_column), sheet.cell(index, target_column))
        schedule = additional_schedule(str(item.get("descripcion", "")))
        project_number = int(item["proyecto"])
        sheet.cell(index, 1).value = f"{project_number}. Proyecto sin renglón PRDI en la plantilla"
        sheet.cell(index, 2).value = str(item.get("descripcion", ""))
        sheet.cell(index, 3).value = "Actividad excepcional"
        sheet.cell(index, 4).value = "Sin fórmula"
        sheet.cell(index, 5).value = "Cumplimiento anual documentado (0=no, 1=sí)"
        for column, year in zip(range(7, 11), YEARS):
            sheet.cell(index, column).value = schedule[year]
        for output_column, input_column in zip(range(11, 16), range(6, 11)):
            letter = sheet.cell(index, input_column).column_letter
            sheet.cell(index, output_column).value = f'=IF({letter}{index}="","",{letter}{index})'
        sheet.cell(index, 16).value = "Anual"
        sheet.cell(index, 17).value = PROJECT_AREAS[project_number]
        sheet.row_dimensions[index].height = 90
    sheet.auto_filter.ref = f"A2:Q{max(2, sheet.max_row)}"


def validate(workbook, projects: list[dict[str, Any]], report: dict[str, Any]) -> None:
    sheets = project_sheets(workbook)
    expected = 0
    missing = []
    for project in projects:
        metas = project.get("referencia_base_2026", {}).get("metas", [])
        if not metas:
            continue
        sheet = sheets.get(int(project["numero"]))
        if sheet is None:
            missing.extend(str(item.get("clave")) for item in metas)
            continue
        available = rows_by_key(sheet)
        for item in metas:
            expected += 1
            item_key = key(item.get("clave"))
            if item_key not in available:
                missing.append(str(item.get("clave")))
                continue
            row = available[item_key]
            if not sheet.cell(row, 9).value or not sheet.cell(row, 24).value:
                missing.append(str(item.get("clave")))
            for offset, variable in enumerate(item.get("variables", [])):
                for column in range(14, 18):
                    if sheet.cell(row + offset, column).value is None:
                        missing.append(f"{item.get('clave')}:{sheet.cell(row + offset, column).coordinate}")
    if expected != report["filas_prdi"]:
        raise RuntimeError(f"Se esperaban {report['filas_prdi']} renglones PRDI y se validaron {expected}.")
    if missing:
        raise RuntimeError("Faltan claves o valores: " + ", ".join(missing[:20]))
    additional = workbook["Metas adicionales"]
    count = sum(1 for row in range(3, additional.max_row + 1) if additional.cell(row, 2).value)
    if count != len(report["metas_adicionales"]):
        raise RuntimeError(f"Se esperaban {len(report['metas_adicionales'])} metas excepcionales y se escribieron {count}.")


def atomic_json(path: Path, payload: dict[str, Any]) -> None:
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    os.replace(temporary, path)


def main() -> int:
    args = arguments()
    output_path = args.workbook.resolve()
    template_path = (args.template or args.workbook).resolve()
    if not template_path.is_file():
        raise FileNotFoundError(f"No existe la plantilla: {template_path}")
    projects, source, local_payload = load_projects(
        args.local, args.base_url, read_cookie(args.cookie_file), args.proposal_file
    )
    if sorted(int(project["numero"]) for project in projects) != list(PROJECTS):
        raise ValueError("La fuente no contiene exactamente los proyectos 2 a 22.")
    projects, report = complete_projects(
        projects, PLAN_DIR, preserve_edits=(not args.local or args.proposal_file is not None)
    )

    workbook = load_workbook(template_path, data_only=False)
    sheets = project_sheets(workbook)
    written = 0
    for project in projects:
        metas = project.get("referencia_base_2026", {}).get("metas", [])
        if not metas:
            continue
        sheet = sheets[int(project["numero"])]
        available = rows_by_key(sheet)
        for item in metas:
            item_key = key(item.get("clave"))
            if item_key not in available:
                raise RuntimeError(f"La clave {item.get('clave')} no existe en {sheet.title}.")
            write_prdi_item(sheet, available[item_key], item)
            written += 1
    write_additional(workbook, report["metas_adicionales"])
    validate(workbook, projects, report)
    workbook.calculation.fullCalcOnLoad = True
    workbook.calculation.forceFullCalc = True
    workbook.calculation.calcMode = "auto"

    print(f"Fuente: {source}")
    print(f"Proyectos: {len(projects)}")
    print(f"Renglones PRDI completados: {written}")
    print(f"Metas del sitio alineadas con PRDI: {report['metas_sitio_alineadas']}")
    print(f"Metas adicionales excepcionales: {len(report['metas_adicionales'])}")
    print("Líneas base históricas: " + ", ".join(f"{name}={value:g}" for name, value in report["contexto_historico"].items()))
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

    temporary = tempfile.NamedTemporaryFile(prefix=f".{output_path.stem}-", suffix=output_path.suffix,
                                            dir=output_path.parent, delete=False)
    temporary_path = Path(temporary.name)
    temporary.close()
    try:
        workbook.save(temporary_path)
        os.replace(temporary_path, output_path)
    finally:
        temporary_path.unlink(missing_ok=True)

    if args.sync_local_proposal:
        if local_payload is None:
            local_payload = json.loads(LOCAL_PROPOSAL.read_text(encoding="utf-8"))
        local_payload["proyectos"] = projects
        local_payload["metodologia_memoria"] = {
            "documentos": ["fuentes/criterios_cpi.pdf", "fuentes/Guía para la formulación de planes de desarrollo.pdf"],
            "criterio": "Alineación completa por clave PRDI, línea base histórica y programación conservadora 2027-2030.",
            "filas_prdi": report["filas_prdi"],
            "metas_adicionales": len(report["metas_adicionales"]),
        }
        atomic_json(LOCAL_PROPOSAL, local_payload)
        print(f"Propuesta local sincronizada: {LOCAL_PROPOSAL}")
    print(f"Libro actualizado: {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
