#!/usr/bin/env python3
"""Genera un índice verificable de un respaldo de contenido del Plan.

El script es deliberadamente de solo lectura respecto de los archivos respaldados:
únicamente crea o reemplaza INDICE_REDACCIONES_USUARIOS.json en la raíz indicada.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
from datetime import datetime
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


def file_record(path: Path, role: str, base: Path | None = None) -> dict[str, Any]:
    relative = str(path.relative_to(base)) if base and path.is_relative_to(base) else None
    return {
        "nombre": path.name,
        "ruta_local": str(path.resolve()),
        "ruta_relativa_respaldo": relative,
        "funcion": role,
        "bytes": path.stat().st_size,
        "sha256": sha256(path),
    }


def content_sections(content: Any) -> list[str]:
    return sorted(content.keys()) if isinstance(content, dict) else []


def authors(revisions: list[dict[str, Any]]) -> list[dict[str, Any]]:
    unique: dict[str, dict[str, Any]] = {}
    for revision in revisions:
        author = revision.get("autor")
        if not isinstance(author, dict):
            continue
        public = {
            key: author.get(key)
            for key in ("nombre", "correo", "usuario_id", "identidad")
            if author.get(key) not in (None, "")
        }
        if public:
            unique[json.dumps(public, ensure_ascii=False, sort_keys=True)] = public
    return list(unique.values())


def revision_record(path: Path, backup_root: Path) -> dict[str, Any]:
    data = read_json(path)
    revisions = data.get("revisiones", []) if isinstance(data, dict) else []
    if not isinstance(revisions, list):
        revisions = []
    project_match = re.search(r"proyecto-(\d+)\.json", path.name)
    dates = sorted(
        str(item.get("fecha"))
        for item in revisions
        if isinstance(item, dict) and item.get("fecha")
    )
    sections = sorted(
        {
            section
            for item in revisions
            if isinstance(item, dict)
            for section in content_sections(item.get("contenido"))
        }
    )
    record = file_record(
        path,
        "Historial de redacciones y decisiones guardadas por usuarios",
        backup_root,
    )
    record.update(
        {
            "proyecto": int(project_match.group(1)) if project_match else None,
            "version_archivo": data.get("version") if isinstance(data, dict) else None,
            "cantidad_revisiones": len(revisions),
            "fecha_primera_revision": dates[0] if dates else None,
            "fecha_ultima_revision": dates[-1] if dates else None,
            "autores": authors(revisions),
            "secciones_con_contenido": sections,
        }
    )
    return record


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("backup_root", type=Path)
    args = parser.parse_args()
    backup_root = args.backup_root.resolve()
    revisions_root = backup_root / "storage" / "plan" / "revisiones"
    if not revisions_root.is_dir():
        raise SystemExit(f"No existe el directorio de revisiones: {revisions_root}")

    revision_paths = sorted(
        path
        for path in revisions_root.rglob("*")
        if path.is_file() and (path.name.endswith(".json") or ".json." in path.name)
    )

    base_sources: list[tuple[Path, str]] = [
        (ROOT / "Plan" / "preliminar_fi.json", "Propuesta base: diagnósticos, objetivos, políticas, estrategias, metas y programación anual"),
        (ROOT / "Plan" / "PRDI.json", "Fuente institucional del Plan Rector de Desarrollo Institucional"),
        (ROOT / "Plan" / "memoria_calculo_2026.json", "Estructura y referencias de la memoria de cálculo 2026"),
        (ROOT / "Plan" / "fuentes_externas.json", "Fuentes externas y factores de contexto"),
        (ROOT / "Plan" / "instrumentos.json", "Definición de cuestionarios e instrumentos"),
        (ROOT / "Plan" / "estadisticas_encuestas.json", "Corte consolidado de estadísticas de cuestionarios"),
        (ROOT / "Plan" / "foda_interpretaciones.json", "Interpretaciones FODA asociadas al plan"),
        (ROOT / "Plan" / "Memoria de cálculo NS 2026-2030Ultima.xlsx", "Libro de memoria de cálculo y programación 2026-2030"),
        (ROOT / "Plan" / "Matriz de consistenciaUltima.xlsx", "Matriz de consistencia del plan"),
        (Path("D:/POA_2026.pdf"), "Plan Operativo Anual 2026 usado como referencia secundaria"),
    ]
    source_records = [file_record(path, role) for path, role in base_sources if path.is_file()]
    source_records.extend(
        file_record(path, "Tablero UAEMéx de variables e indicadores (fuente prioritaria)")
        for path in sorted((ROOT / "Plan").glob("40*.json"))
        if path.is_file()
    )

    downloaded = [
        file_record(path, "Archivo descargado de producción", backup_root)
        for path in sorted(backup_root.rglob("*"))
        if path.is_file() and path.name != "INDICE_REDACCIONES_USUARIOS.json"
    ]
    revision_records = [revision_record(path, backup_root) for path in revision_paths]

    index = {
        "nombre": "Índice de redacciones de usuarios y fuentes del Plan",
        "generado_en": datetime.now().astimezone().isoformat(timespec="seconds"),
        "raiz_respaldo": str(backup_root),
        "principio_integridad": (
            "Los archivos descargados se indexaron en modo de solo lectura. "
            "Cada registro incluye SHA-256 para comprobar que no fue alterado."
        ),
        "resumen": {
            "archivos_descargados": len(downloaded),
            "archivos_de_redacciones": len(revision_records),
            "revisiones_historicas": sum(item["cantidad_revisiones"] for item in revision_records),
            "archivos_fuente_referenciados": len(source_records),
            "tableros_uaemex_40_json": sum(
                1 for item in source_records if item["nombre"].startswith("40") and item["nombre"].endswith(".json")
            ),
        },
        "redacciones_usuarios": revision_records,
        "fuentes_y_archivos_base": source_records,
        "inventario_completo_del_respaldo": downloaded,
    }
    output = backup_root / "INDICE_REDACCIONES_USUARIOS.json"
    output.write_text(json.dumps(index, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(output)
    print(json.dumps(index["resumen"], ensure_ascii=False))


if __name__ == "__main__":
    main()
