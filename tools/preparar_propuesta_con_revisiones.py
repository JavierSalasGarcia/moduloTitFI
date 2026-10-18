#!/usr/bin/env python3
"""Integra la revisión más reciente por proyecto sobre la propuesta base.

Los archivos de revisión se abren únicamente en modo lectura. El resultado se
escribe en un JSON separado que sirve como entrada reproducible para los libros.
"""

from __future__ import annotations

import argparse
import json
import re
from copy import deepcopy
from datetime import datetime
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_BASE = ROOT / "Plan" / "preliminar_fi.json"


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


def revision_candidates(revisions_root: Path) -> list[dict[str, Any]]:
    candidates: list[dict[str, Any]] = []
    for path in sorted(revisions_root.rglob("*.json")):
        match = re.fullmatch(r"proyecto-(\d+)\.json", path.name)
        if not match:
            continue
        payload = read_json(path)
        for revision in payload.get("revisiones", []):
            if not isinstance(revision, dict) or not isinstance(revision.get("contenido"), dict):
                continue
            date = str(revision.get("fecha") or "")
            if not date:
                continue
            candidates.append(
                {
                    "proyecto": int(match.group(1)),
                    "fecha": date,
                    "version": revision.get("version"),
                    "autor": revision.get("autor"),
                    "contenido": revision["contenido"],
                    "archivo": str(path.resolve()),
                }
            )
    return candidates


def normalize_legacy_content(content: dict[str, Any], proposal: dict[str, Any]) -> dict[str, Any]:
    """Replica la normalización de matrixContent() usada por la interfaz web."""
    objectives = content.get("objetivos_especificos")
    structured = (
        isinstance(content.get("politicas"), list)
        and isinstance(objectives, list)
        and all(isinstance(item, dict) and isinstance(item.get("estrategias"), list) for item in objectives)
    )
    if structured:
        return deepcopy(content)

    normalized = deepcopy(content)
    policies = deepcopy(proposal.get("politicas", []))
    generated_objectives = deepcopy(proposal.get("objetivos_especificos", []))
    first = objectives[0] if isinstance(objectives, list) and objectives and isinstance(objectives[0], dict) else None
    if first and first.get("politica") and policies:
        policies[0]["texto"] = first["politica"]
    if first and generated_objectives:
        generated_objectives[0]["texto"] = first.get("texto") or generated_objectives[0].get("texto")
        strategies = generated_objectives[0].get("estrategias", [])
        if first.get("estrategia") and strategies:
            strategy_text = str(first["estrategia"]).rstrip()
            if not re.search(r"\[(?:C|P|I)(?:,\s*(?:C|P|I))*\]$", strategy_text):
                criteria = ", ".join(str(value) for value in strategies[0].get("criterios", []))
                strategy_text += f" [{criteria}]"
            strategies[0]["texto"] = strategy_text
        if first.get("meta") and strategies and strategies[0].get("metas"):
            strategies[0]["metas"][0] = deepcopy(first["meta"])
    normalized["politicas"] = policies
    normalized["objetivos_especificos"] = generated_objectives
    return normalized


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--revisions-dir", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--report", type=Path)
    parser.add_argument("--base", type=Path, default=DEFAULT_BASE)
    args = parser.parse_args()

    payload = read_json(args.base.resolve())
    projects = {int(project["numero"]): deepcopy(project) for project in payload["proyectos"]}
    selected: dict[int, dict[str, Any]] = {}
    for candidate in revision_candidates(args.revisions_dir.resolve()):
        number = candidate["proyecto"]
        current = selected.get(number)
        order = (candidate["fecha"], int(candidate.get("version") or 0), candidate["archivo"])
        current_order = (
            (current["fecha"], int(current.get("version") or 0), current["archivo"])
            if current else None
        )
        if current_order is None or order > current_order:
            selected[number] = candidate

    applied = []
    for number, project in projects.items():
        revision = selected.get(number)
        if revision:
            # La API del sitio aplica la revisión con esta misma combinación
            # superficial: sólo sustituye los campos editables presentes.
            integrated_content = normalize_legacy_content(revision["contenido"], project)
            project.update(integrated_content)
            applied.append(
                {
                    "proyecto": number,
                    "fecha": revision["fecha"],
                    "version": revision["version"],
                    "autor": revision["autor"],
                    "archivo": revision["archivo"],
                    "campos_aplicados": sorted(integrated_content.keys()),
                }
            )

    merged = deepcopy(payload)
    merged["proyectos"] = [projects[number] for number in sorted(projects)]
    merged["integracion_revisiones"] = {
        "generado_en": datetime.now().astimezone().isoformat(timespec="seconds"),
        "regla": "Última revisión con fecha por proyecto; propuesta IA cuando no existe revisión.",
        "origen_revisiones": str(args.revisions_dir.resolve()),
        "proyectos_con_revision": [item["proyecto"] for item in applied],
        "proyectos_sin_revision": [number for number in sorted(projects) if number not in selected],
    }

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(merged, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    report_path = args.report or args.output.with_suffix(".reporte.json")
    report_path.write_text(
        json.dumps(
            {
                "regla": merged["integracion_revisiones"]["regla"],
                "base": str(args.base.resolve()),
                "salida": str(args.output.resolve()),
                "revisiones_aplicadas": applied,
                "proyectos_ia": merged["integracion_revisiones"]["proyectos_sin_revision"],
            },
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )
    print(f"Propuesta integrada: {args.output.resolve()}")
    print(f"Reporte: {report_path.resolve()}")
    print("Revisiones aplicadas: " + ", ".join(str(item["proyecto"]) for item in applied))
    print(f"Proyectos con IA: {len(projects) - len(applied)}")
    return 0



if __name__ == "__main__":
    raise SystemExit(main())
