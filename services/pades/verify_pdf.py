#!/usr/bin/env python3
"""Muestra las firmas incorporadas a un PDF y su resultado criptográfico."""

import argparse
from pathlib import Path

from asn1crypto import pem, x509
from pyhanko.pdf_utils.reader import PdfFileReader
from pyhanko.sign.validation import validate_pdf_signature
from pyhanko_certvalidator import ValidationContext


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("--root", type=Path, required=True)
    args = parser.parse_args()
    root_bytes = args.root.read_bytes()
    if pem.detect(root_bytes):
        _, _, root_bytes = pem.unarmor(root_bytes)
    root = x509.Certificate.load(root_bytes)
    context = ValidationContext(trust_roots=[root], allow_fetching=False)
    with args.pdf.open("rb") as stream:
        reader = PdfFileReader(stream)
        signatures = list(reader.embedded_signatures)
        print(f"Firmas encontradas: {len(signatures)}")
        for signature in signatures:
            status = validate_pdf_signature(signature, signer_validation_context=context)
            print(status.pretty_print_details())


if __name__ == "__main__":
    main()
