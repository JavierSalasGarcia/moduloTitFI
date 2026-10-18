"""Prueba integral local: emite un certificado y firma el mismo PDF dos veces."""

from __future__ import annotations

import json
import subprocess
import sys
import tempfile
from datetime import datetime, timedelta, timezone
from pathlib import Path

from asn1crypto import pem, x509 as asn1_x509
from cryptography import x509
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID
from pyhanko.pdf_utils.reader import PdfFileReader
from pyhanko.sign.validation import validate_pdf_signature
from pyhanko_certvalidator import ValidationContext
from reportlab.pdfgen.canvas import Canvas


SERVICE = Path(__file__).resolve().parents[1] / "pades_service.py"


def ca_name(common_name: str) -> x509.Name:
    return x509.Name([x509.NameAttribute(NameOID.COUNTRY_NAME, "MX"),
                      x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Traza FI Test"),
                      x509.NameAttribute(NameOID.COMMON_NAME, common_name)])


def build_ca(subject: x509.Name, issuer: x509.Name, public_key, issuer_key, path_length: int) -> x509.Certificate:
    now = datetime.now(timezone.utc)
    return (x509.CertificateBuilder().subject_name(subject).issuer_name(issuer).public_key(public_key)
            .serial_number(x509.random_serial_number()).not_valid_before(now - timedelta(minutes=1))
            .not_valid_after(now + timedelta(days=30))
            .add_extension(x509.BasicConstraints(ca=True, path_length=path_length), critical=True)
            .add_extension(x509.KeyUsage(False, False, False, False, False, True, True, None, None), critical=True)
            .sign(issuer_key, hashes.SHA256()))


def call(payload: dict) -> dict:
    result = subprocess.run([sys.executable, str(SERVICE)], input=json.dumps(payload), text=True,
                            capture_output=True, check=False)
    if result.returncode != 0:
        raise AssertionError(result.stdout + result.stderr)
    response = json.loads(result.stdout)
    assert response["ok"], response
    return response


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="traza-fi-pades-") as temp:
        base = Path(temp)
        public, private, secrets, users = [base / name for name in ("public", "private", "secrets", "users")]
        for directory in (public, private, secrets, users): directory.mkdir()
        root_key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
        root_name = ca_name("Test Root")
        root_cert = build_ca(root_name, root_name, root_key.public_key(), root_key, 1)
        intermediate_key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
        intermediate_cert = build_ca(ca_name("Test Intermediate"), root_name,
                                     intermediate_key.public_key(), root_key, 0)
        password = b"test-intermediate-password"
        root_path = public / "root.pem"
        intermediate_path = public / "intermediate.pem"
        key_path = private / "intermediate-key.pem"
        pass_path = secrets / "intermediate.pass"
        master_path = secrets / "master.key"
        root_path.write_bytes(root_cert.public_bytes(serialization.Encoding.PEM))
        intermediate_path.write_bytes(intermediate_cert.public_bytes(serialization.Encoding.PEM))
        key_path.write_bytes(intermediate_key.private_bytes(serialization.Encoding.PEM,
            serialization.PrivateFormat.PKCS8, serialization.BestAvailableEncryption(password)))
        pass_path.write_bytes(password)
        master_path.write_bytes(Fernet.generate_key())
        common = {"root_cert": str(root_path), "intermediate_cert": str(intermediate_path),
                  "intermediate_key": str(key_path), "intermediate_pass": str(pass_path),
                  "master_key": str(master_path), "user_dir": str(users)}
        call(common | {"command": "status"})
        issued = call(common | {"command": "issue", "user_id": 7,
                                "name": "Persona de Prueba", "email": "prueba@example.mx"})
        original = base / "original.pdf"
        canvas = Canvas(str(original)); canvas.drawString(72, 720, "Documento de prueba Traza FI"); canvas.save()
        first, second = base / "firma-1.pdf", base / "firma-2.pdf"
        signed = first
        for index, (source, target) in enumerate(((original, first), (first, second)), start=1):
            call(common | {"command": "sign", "input_pdf": str(source), "output_pdf": str(target),
                            "pfx_path": issued["pfx_path"], "wrapped_secret": issued["wrapped_secret"],
                            "field_name": f"TrazaFI_Test_{index}", "signer_name": "Persona de Prueba",
                            "folio": "TEST-0001", "page": "ultima", "position": "abajo_derecha",
                            "level": index - 1})
            signed = target
        root_der = root_cert.public_bytes(serialization.Encoding.DER)
        context = ValidationContext(trust_roots=[asn1_x509.Certificate.load(root_der)], allow_fetching=False)
        with signed.open("rb") as stream:
            signatures = list(PdfFileReader(stream).embedded_signatures)
            assert len(signatures) == 2
            for embedded in signatures:
                result = validate_pdf_signature(embedded, signer_validation_context=context)
                assert result.bottom_line, result.pretty_print_details()
        print("OK: dos firmas PAdES incrementales válidas con certificados individuales.")


if __name__ == "__main__":
    main()
