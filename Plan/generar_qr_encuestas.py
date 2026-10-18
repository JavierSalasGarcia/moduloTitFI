#!/usr/bin/env python3
"""Genera los códigos QR públicos de todos los cuestionarios FI 360."""

from __future__ import annotations

import argparse
import json
import re
import textwrap
import unicodedata
from pathlib import Path

import qrcode
from PIL import Image, ImageDraw, ImageFont
from qrcode.constants import ERROR_CORRECT_H


ROOT = Path(__file__).resolve().parent
DEFAULT_OUTPUT = ROOT / "codigos_qr_encuestas"
DEFAULT_BASE_URL = "https://fingenieria.mx/public/consulta-fi"
DEFAULT_COUNTS = ROOT / "conteos_encuestas.json"
GREEN = "#14532d"
GRAY = "#475569"


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        Path("C:/Windows/Fonts") / ("arialbd.ttf" if bold else "arial.ttf"),
        Path("C:/Windows/Fonts") / ("segoeuib.ttf" if bold else "segoeui.ttf"),
    ]
    for candidate in candidates:
        if candidate.is_file():
            return ImageFont.truetype(str(candidate), size=size)
    return ImageFont.load_default()


def safe_name(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode()
    value = re.sub(r"[^a-zA-Z0-9]+", "_", value).strip("_").lower()
    return value[:90]


def centered_text(draw: ImageDraw.ImageDraw, canvas_width: int, y: int, text: str,
                  selected_font: ImageFont.ImageFont, fill: str) -> int:
    bbox = draw.textbbox((0, 0), text, font=selected_font)
    width = bbox[2] - bbox[0]
    draw.text(((canvas_width - width) // 2, y), text, font=selected_font, fill=fill)
    return bbox[3] - bbox[1]


def make_card(identifier: str, title: str, url: str, destination: Path) -> None:
    qr = qrcode.QRCode(version=None, error_correction=ERROR_CORRECT_H, box_size=16, border=4)
    qr.add_data(url)
    qr.make(fit=True)
    qr_image = qr.make_image(fill_color="#111827", back_color="white").convert("RGB")
    qr_image.thumbnail((940, 940), Image.Resampling.NEAREST)

    width, height = 1200, 1450
    card = Image.new("RGB", (width, height), "white")
    draw = ImageDraw.Draw(card)
    draw.rounded_rectangle((24, 24, width - 24, height - 24), radius=28, outline=GREEN, width=8)

    centered_text(draw, width, 72, "FACULTAD DE INGENIERÍA · UAEMÉX", font(36, True), GREEN)
    centered_text(draw, width, 130, f"Cuestionario {identifier}", font(48, True), "#111827")

    title_lines = textwrap.wrap(title, width=47) or [title]
    y = 202
    for line in title_lines[:3]:
        y += centered_text(draw, width, y, line, font(34, True), "#1f2937") + 12

    qr_x = (width - qr_image.width) // 2
    qr_y = max(320, y + 16)
    card.paste(qr_image, (qr_x, qr_y))

    instruction_y = qr_y + qr_image.height + 25
    centered_text(draw, width, instruction_y, "Escanea para responder", font(34, True), GREEN)
    url_lines = textwrap.wrap(url, width=76)
    y = instruction_y + 56
    for line in url_lines:
        y += centered_text(draw, width, y, line, font(20), GRAY) + 8

    card.save(destination, format="PNG", optimize=True, dpi=(300, 300))


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--salida", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL)
    parser.add_argument("--conteos", type=Path, default=DEFAULT_COUNTS)
    args = parser.parse_args()

    instruments = json.loads((ROOT / "instrumentos.json").read_text(encoding="utf-8"))
    links = json.loads((ROOT / "enlaces_encuesta.json").read_text(encoding="utf-8"))
    counts = json.loads(args.conteos.read_text(encoding="utf-8")) if args.conteos.is_file() else {}
    surveys = instruments.get("encuestas", [])
    survey_ids = {item["id"] for item in surveys}
    if survey_ids != set(links):
        missing = sorted(survey_ids - set(links))
        extra = sorted(set(links) - survey_ids)
        raise RuntimeError(f"Catálogo y enlaces no coinciden. Faltan={missing}; sobran={extra}")

    output = args.salida.resolve()
    output.mkdir(parents=True, exist_ok=True)
    rows: list[dict[str, str | int]] = []
    for survey in surveys:
        identifier = survey["id"]
        title = survey["titulo"]
        token = links[identifier]
        if not re.fullmatch(r"[a-f0-9]{48}", token):
            raise RuntimeError(f"Token inválido para {identifier}")
        url = f"{args.base_url.rstrip('/')}/{token}"
        filename = f"{identifier}_{safe_name(title)}.png"
        make_card(identifier, title, url, output / filename)
        rows.append({
            "cuestionario": identifier,
            "destinatarios": survey["destinatarios"],
            "respuestas": int(counts.get(identifier, 0)),
            "archivo": filename,
        })

    index_lines: list[str] = []
    for position, row in enumerate(rows):
        index_lines.extend([
            f"Número de cuestionario: {row['cuestionario']}",
            f"Dirigida a: {row['destinatarios']}",
            f"Han contestado: {row['respuestas']}",
            f"Imagen del código QR: {row['archivo']}",
        ])
        if position < len(rows) - 1:
            index_lines.append("-----------")
    (output / "indice.txt").write_text("\n".join(index_lines) + "\n", encoding="utf-8")
    old_csv = output / "indice.csv"
    if old_csv.is_file():
        old_csv.unlink()

    readme = [
        "# Códigos QR de los cuestionarios FI 360",
        "",
        f"Total: {len(rows)} cuestionarios.",
        "",
        "Cada imagen contiene el identificador, el título y el enlace público codificado.",
        "El archivo `indice.txt` indica el número de cuestionario, sus destinatarios,",
        "las respuestas registradas y el nombre de la imagen correspondiente.",
        "",
        "Para regenerarlos desde la carpeta `Plan`:",
        "",
        "```powershell",
        "python .\\generar_qr_encuestas.py",
        "```",
        "",
    ]
    (output / "README.md").write_text("\n".join(readme), encoding="utf-8")
    print(f"Generados {len(rows)} códigos QR en {output}")


if __name__ == "__main__":
    main()
