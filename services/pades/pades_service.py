#!/usr/bin/env python3
"""Servicio local de certificados individuales y firmas PAdES para Traza FI.

Recibe una sola solicitud JSON por stdin y responde JSON por stdout. El proceso
debe ejecutarse con una cuenta del sistema operativo distinta de la del servidor
web en producción; el modo CLI existe para facilitar el piloto y las pruebas.
"""

from __future__ import annotations

import base64
import hashlib
import json
import os
import re
import secrets
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

from cryptography import x509
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives.serialization import pkcs12
from cryptography.x509.oid import NameOID
from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
from pyhanko.pdf_utils.reader import PdfFileReader
from pyhanko.pdf_utils.writer import find_inherited_value_in_tree
from pyhanko.sign import fields, signers
from pyhanko.sign.signers import PdfSignatureMetadata, PdfSigner
from pyhanko.stamp import TextStampStyle


def fail(message: str, code: str = "service_error") -> None:
    print(json.dumps({"ok": False, "error": code, "message": message}, ensure_ascii=False))
    raise SystemExit(1)


def safe_path(raw: str, *, must_exist: bool = True) -> Path:
    path = Path(raw).expanduser().resolve()
    if must_exist and not path.is_file():
        fail(f"No existe el archivo requerido: {path}", "missing_file")
    return path


def read_secret(path: Path) -> bytes:
    value = path.read_bytes().strip()
    if not value:
        fail(f"El archivo secreto está vacío: {path}", "invalid_secret")
    return value


def load_request() -> dict:
    try:
        data = json.load(sys.stdin)
    except Exception as exc:
        fail(f"Solicitud JSON inválida: {exc}", "invalid_request")
    if not isinstance(data, dict):
        fail("La solicitud debe ser un objeto JSON.", "invalid_request")
    return data


def pki_paths(data: dict) -> dict[str, Path]:
    return {
        "root_cert": safe_path(data["root_cert"]),
        "intermediate_cert": safe_path(data["intermediate_cert"]),
        "intermediate_key": safe_path(data["intermediate_key"]),
        "intermediate_pass": safe_path(data["intermediate_pass"]),
        "master_key": safe_path(data["master_key"]),
        "user_dir": Path(data["user_dir"]).expanduser().resolve(),
    }


def status(data: dict) -> dict:
    paths = pki_paths(data)
    root = x509.load_pem_x509_certificate(paths["root_cert"].read_bytes())
    intermediate = x509.load_pem_x509_certificate(paths["intermediate_cert"].read_bytes())
    if intermediate.issuer != root.subject:
        fail("La autoridad intermedia no fue emitida por la autoridad raíz configurada.", "invalid_chain")
    now = datetime.now(timezone.utc)
    if not (intermediate.not_valid_before_utc <= now <= intermediate.not_valid_after_utc):
        fail("El certificado de la autoridad intermedia no está vigente.", "expired_ca")
    return {
        "ok": True,
        "root_sha256": root.fingerprint(hashes.SHA256()).hex(),
        "intermediate_serial": format(intermediate.serial_number, "x"),
        "intermediate_expires": intermediate.not_valid_after_utc.isoformat(),
    }


def issue(data: dict) -> dict:
    paths = pki_paths(data)
    paths["user_dir"].mkdir(mode=0o700, parents=True, exist_ok=True)
    user_id = int(data["user_id"])
    if user_id <= 0:
        fail("Identificador de usuario inválido.", "invalid_user")
    name = str(data["name"]).strip()[:160]
    email = str(data["email"]).strip().lower()[:190]
    if not name or "@" not in email:
        fail("Nombre o correo institucional inválido.", "invalid_user")

    intermediate_cert = x509.load_pem_x509_certificate(paths["intermediate_cert"].read_bytes())
    intermediate_key = serialization.load_pem_private_key(
        paths["intermediate_key"].read_bytes(), password=read_secret(paths["intermediate_pass"])
    )
    key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
    now = datetime.now(timezone.utc)
    subject = x509.Name([
        x509.NameAttribute(NameOID.COUNTRY_NAME, "MX"),
        x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Facultad de Ingeniería UAEMéx"),
        x509.NameAttribute(NameOID.ORGANIZATIONAL_UNIT_NAME, "Traza FI"),
        x509.NameAttribute(NameOID.COMMON_NAME, name),
        x509.NameAttribute(NameOID.EMAIL_ADDRESS, email),
        x509.NameAttribute(NameOID.USER_ID, str(user_id)),
    ])
    cert = (
        x509.CertificateBuilder()
        .subject_name(subject)
        .issuer_name(intermediate_cert.subject)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now - timedelta(minutes=5))
        .not_valid_after(now + timedelta(days=int(data.get("valid_days", 365))))
        .add_extension(x509.BasicConstraints(ca=False, path_length=None), critical=True)
        .add_extension(
            x509.KeyUsage(
                digital_signature=True, content_commitment=True, key_encipherment=False,
                data_encipherment=False, key_agreement=False, key_cert_sign=False,
                crl_sign=False, encipher_only=None, decipher_only=None,
            ), critical=True,
        )
        .add_extension(x509.SubjectKeyIdentifier.from_public_key(key.public_key()), critical=False)
        .add_extension(x509.AuthorityKeyIdentifier.from_issuer_public_key(intermediate_key.public_key()), critical=False)
        .sign(intermediate_key, hashes.SHA256())
    )
    password = secrets.token_urlsafe(32).encode("ascii")
    pfx = pkcs12.serialize_key_and_certificates(
        name=f"traza-fi-{user_id}".encode("ascii"), key=key, cert=cert,
        cas=[intermediate_cert], encryption_algorithm=serialization.BestAvailableEncryption(password),
    )
    target = paths["user_dir"] / f"user-{user_id}.p12"
    temporary = target.with_suffix(".tmp")
    temporary.write_bytes(pfx)
    os.chmod(temporary, 0o600)
    temporary.replace(target)
    wrapped = Fernet(read_secret(paths["master_key"])).encrypt(password).decode("ascii")
    return {
        "ok": True,
        "pfx_path": str(target),
        "wrapped_secret": wrapped,
        "serial": format(cert.serial_number, "x"),
        "fingerprint_sha256": cert.fingerprint(hashes.SHA256()).hex(),
        "valid_from": cert.not_valid_before_utc.isoformat(),
        "valid_until": cert.not_valid_after_utc.isoformat(),
        "subject": cert.subject.rfc4514_string(),
    }


def signature_box(position: str, width: float, height: float, level: int) -> tuple[int, int, int, int]:
    box_width, box_height, margin, gap = 175, 64, 28, 6
    x = {"abajo_izquierda": margin, "abajo_centro": (width - box_width) / 2,
         "abajo_derecha": width - box_width - margin}.get(position, width - box_width - margin)
    y = margin + max(0, level) * (box_height + gap)
    y = min(y, max(margin, height - box_height - margin))
    return tuple(round(v) for v in (x, y, x + box_width, y + box_height))


def sign(data: dict) -> dict:
    paths = pki_paths(data)
    source = safe_path(data["input_pdf"])
    output = safe_path(data["output_pdf"], must_exist=False)
    output.parent.mkdir(mode=0o750, parents=True, exist_ok=True)
    pfx_path = safe_path(data["pfx_path"])
    password = Fernet(read_secret(paths["master_key"])).decrypt(
        str(data["wrapped_secret"]).encode("ascii")
    )
    signer = signers.SimpleSigner.load_pkcs12(
        pfx_file=str(pfx_path), ca_chain_files=(str(paths["root_cert"]),), passphrase=password
    )
    if signer is None:
        fail("No fue posible abrir el certificado individual.", "invalid_certificate")

    field_name = re.sub(r"[^A-Za-z0-9_.-]", "_", str(data["field_name"]))[:100]
    page_number = 0 if data.get("page") == "primera" else -1
    with source.open("rb") as inf:
        writer = IncrementalPdfFileWriter(inf)
        page_ref, _ = writer.find_page_for_modification(page_number)
        media_box = find_inherited_value_in_tree(page_ref.get_object(), "/MediaBox", "/Parent")
        width = float(media_box[2]) - float(media_box[0])
        height = float(media_box[3]) - float(media_box[1])
        box = signature_box(str(data.get("position", "abajo_derecha")), width, height, int(data.get("level", 0)))
        spec = fields.SigFieldSpec(sig_field_name=field_name, on_page=page_number, box=box)
        metadata = PdfSignatureMetadata(
            field_name=field_name, md_algorithm="sha256", name=str(data["signer_name"])[:160],
            reason=str(data.get("reason", "Aprobación en Traza FI"))[:200],
            location=str(data.get("location", "Facultad de Ingeniería UAEMéx"))[:120],
        )
        style = TextStampStyle(
            stamp_text="Firmado digitalmente por %(signer)s\n%(ts)s\nFolio: " + str(data["folio"])[:80]
        )
        pdf_signer = PdfSigner(signature_meta=metadata, signer=signer, stamp_style=style, new_field_spec=spec)
        temporary = output.with_suffix(output.suffix + ".tmp")
        with temporary.open("wb") as outf:
            pdf_signer.sign_pdf(writer, output=outf)
        os.chmod(temporary, 0o640)
        temporary.replace(output)

    with output.open("rb") as signed_file:
        embedded = list(PdfFileReader(signed_file).embedded_signatures)
    return {
        "ok": True,
        "output_pdf": str(output),
        "sha256_before": hashlib.sha256(source.read_bytes()).hexdigest(),
        "sha256_after": hashlib.sha256(output.read_bytes()).hexdigest(),
        "field_name": field_name,
        "embedded_signatures": len(embedded),
    }


def main() -> None:
    data = load_request()
    command = data.get("command")
    try:
        result = {"status": status, "issue": issue, "sign": sign}.get(command, lambda _: fail("Comando desconocido."))(data)
        print(json.dumps(result, ensure_ascii=False))
    except SystemExit:
        raise
    except Exception as exc:
        fail(f"Falló la operación criptográfica: {exc}")


if __name__ == "__main__":
    main()

