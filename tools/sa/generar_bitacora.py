#!/usr/bin/env python3
"""Genera 100 folios SA y los superpone como QR sobre una plantilla carta.

Uso normal (Windows):
    python generar_bitacora.py

Los valores por defecto escriben en D:\\SA\\arte\\bitacora y conservan un
registro acumulado en folios_usados.txt para no volver a emitir un folio.
"""

from __future__ import annotations

import argparse
import os
import secrets
import sys
from contextlib import contextmanager
from datetime import datetime
from io import BytesIO
from pathlib import Path
from urllib.parse import quote

import qrcode
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas


CANTIDAD_POR_LOTE = 100
ALFABETO = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ"
LONGITUD_ALEATORIA = 26  # ~129 bits; se omiten 0/O y 1/I para lectura manual.
QR_LADO_PT = 78.0
# El borde derecho (502 + 78 = 580 pt) coincide con el borde del rayado.
# Así no queda un fragmento de renglón inutilizable a la derecha del QR.
QR_X_PT = 502.0
QR_Y_SUPERIOR_PT = 414.0
QR_Y_INFERIOR_PT = 18.0


def nuevo_folio(prefijo: str = "SA") -> str:
    """Devuelve un folio opaco con el prefijo visible de una oficina."""
    cuerpo = "".join(secrets.choice(ALFABETO) for _ in range(LONGITUD_ALEATORIA))
    return prefijo + "-" + "-".join((cuerpo[0:5], cuerpo[5:10], cuerpo[10:15], cuerpo[15:20], cuerpo[20:26]))


def leer_folios(ruta: Path) -> set[str]:
    if not ruta.exists():
        return set()
    return {linea.strip().upper() for linea in ruta.read_text(encoding="utf-8").splitlines() if linea.strip()}


def generar_folios(usados: set[str], cantidad: int = CANTIDAD_POR_LOTE, prefijo: str = "SA") -> list[str]:
    lote: list[str] = []
    while len(lote) < cantidad:
        folio = nuevo_folio(prefijo)
        if folio not in usados and folio not in lote:
            lote.append(folio)
    return lote


@contextmanager
def bloqueo_directorio(directorio: Path):
    """Impide que dos ejecuciones reserven folios al mismo tiempo."""
    ruta = directorio / ".generando_bitacora.lock"
    try:
        descriptor = os.open(ruta, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
    except FileExistsError as exc:
        raise RuntimeError(f"Ya hay otra generación en curso ({ruta}).") from exc
    try:
        os.write(descriptor, f"pid={os.getpid()}\n".encode("ascii"))
        os.close(descriptor)
        yield
    finally:
        try:
            ruta.unlink()
        except FileNotFoundError:
            pass


def dibujar_qr(pdf: canvas.Canvas, contenido: str, x: float, y: float, lado: float) -> None:
    qr = qrcode.QRCode(
        version=None,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=1,
        border=4,
    )
    qr.add_data(contenido)
    qr.make(fit=True)
    matriz = qr.get_matrix()
    modulo = lado / len(matriz)

    # Fondo blanco, incluida la zona silenciosa, para aislarlo del rayado.
    pdf.setFillColorRGB(1, 1, 1)
    pdf.rect(x, y, lado, lado, fill=1, stroke=0)
    pdf.setFillColorRGB(0, 0, 0)
    for fila, valores in enumerate(matriz):
        for columna, oscuro in enumerate(valores):
            if oscuro:
                pdf.rect(
                    x + columna * modulo,
                    y + (len(matriz) - fila - 1) * modulo,
                    modulo + 0.02,
                    modulo + 0.02,
                    fill=1,
                    stroke=0,
                )


def pagina_superpuesta(ancho: float, alto: float, urls: tuple[str, str]) -> PdfReader:
    memoria = BytesIO()
    pdf = canvas.Canvas(memoria, pagesize=(ancho, alto), pageCompression=1)
    dibujar_qr(pdf, urls[0], QR_X_PT, QR_Y_SUPERIOR_PT, QR_LADO_PT)
    dibujar_qr(pdf, urls[1], QR_X_PT, QR_Y_INFERIOR_PT, QR_LADO_PT)
    pdf.showPage()
    pdf.save()
    memoria.seek(0)
    return PdfReader(memoria)


def crear_pdf(plantilla: Path, destino: Path, folios: list[str], url_base: str) -> None:
    lector = PdfReader(str(plantilla))
    if len(lector.pages) != 1:
        raise ValueError("La plantilla debe contener exactamente una página.")
    base = lector.pages[0]
    ancho, alto = float(base.mediabox.width), float(base.mediabox.height)
    if abs(ancho - 612) > 2 or abs(alto - 792) > 2:
        raise ValueError(f"La plantilla no es tamaño carta (mide {ancho:.1f} x {alto:.1f} pt).")

    escritor = PdfWriter()
    for indice in range(0, len(folios), 2):
        par = folios[indice : indice + 2]
        if len(par) != 2:
            raise ValueError("La cantidad de folios debe ser par.")
        urls = tuple(url_base + quote(folio, safe="-") for folio in par)
        capa = pagina_superpuesta(ancho, alto, urls).pages[0]
        # Cada página necesita un grafo PDF independiente. Reutilizar/deepcopy
        # conserva referencias indirectas y algunos escritores deduplican la
        # primera capa QR en todas las hojas.
        pagina = PdfReader(str(plantilla)).pages[0]
        pagina.merge_page(capa, over=True)
        escritor.add_page(pagina)

    temporal = destino.with_suffix(destino.suffix + ".tmp")
    with temporal.open("wb") as salida:
        escritor.write(salida)
    os.replace(temporal, destino)


def escribir_atomico(ruta: Path, contenido: str) -> None:
    temporal = ruta.with_suffix(ruta.suffix + ".tmp")
    temporal.write_text(contenido, encoding="utf-8", newline="\n")
    os.replace(temporal, ruta)


def argumentos() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Genera una bitácora carta con 100 códigos QR SA.")
    parser.add_argument("--plantilla", type=Path, default=Path(r"D:\SA\arte\bitacora.pdf"))
    parser.add_argument("--salida", type=Path, default=Path(r"D:\SA\arte\bitacora"))
    parser.add_argument(
        "--prefijo",
        default="SA",
        help="Prefijo de oficina de 2 a 6 caracteres (por defecto: SA).",
    )
    parser.add_argument(
        "--url-base",
        default="https://fingenieria.mx/public/fila/sa/reg.php?",
        help="URL completa hasta el signo ?; el folio se agrega al final.",
    )
    return parser.parse_args()


def main() -> int:
    args = argumentos()
    plantilla = args.plantilla.resolve()
    salida = args.salida.resolve()
    if not plantilla.is_file():
        print(f"No existe la plantilla: {plantilla}", file=sys.stderr)
        return 2
    if not args.url_base.startswith("https://") or "?" not in args.url_base:
        print("--url-base debe ser HTTPS y terminar en la consulta del folio.", file=sys.stderr)
        return 2
    prefijo = args.prefijo.strip().upper()
    if not (2 <= len(prefijo) <= 6 and prefijo.isalnum() and prefijo[0].isalpha()):
        print("--prefijo debe tener de 2 a 6 caracteres alfanuméricos y comenzar con una letra.", file=sys.stderr)
        return 2

    salida.mkdir(parents=True, exist_ok=True)
    historial = salida / "folios_usados.txt"
    marca = datetime.now().strftime("%Y%m%d_%H%M%S_%f")
    pdf_destino = salida / f"bitacora_{marca}.pdf"
    lote_destino = salida / f"folios_{marca}.txt"

    try:
        with bloqueo_directorio(salida):
            usados = leer_folios(historial)
            folios = generar_folios(usados, prefijo=prefijo)
            crear_pdf(plantilla, pdf_destino, folios, args.url_base)
            lote = "\n".join(folios) + "\n"
            escribir_atomico(lote_destino, lote)
            escribir_atomico(historial, "\n".join(sorted(usados | set(folios))) + "\n")
    except Exception as exc:
        try:
            pdf_destino.unlink()
        except FileNotFoundError:
            pass
        try:
            lote_destino.unlink()
        except FileNotFoundError:
            pass
        print(f"No se pudo generar la bitácora: {exc}", file=sys.stderr)
        return 1

    print(f"PDF: {pdf_destino}")
    print(f"Folios del lote: {lote_destino}")
    print(f"Historial acumulado: {historial}")
    print(f"Páginas: {CANTIDAD_POR_LOTE // 2}; folios: {CANTIDAD_POR_LOTE}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
