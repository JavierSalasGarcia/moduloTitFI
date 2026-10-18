#!/usr/bin/env python3
"""Herramientas explícitas para la ceremonia de la PKI interna de Traza FI."""

from __future__ import annotations

import argparse
import getpass
import os
import secrets
from datetime import datetime, timedelta, timezone
from pathlib import Path

from cryptography import x509
from cryptography.fernet import Fernet
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.x509.oid import NameOID


def protected_write(path: Path, data: bytes, mode: int = 0o600) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.exists():
        raise SystemExit(f"Se rechazó sobrescribir el archivo existente: {path}")
    path.write_bytes(data)
    os.chmod(path, mode)


def password_twice(label: str) -> bytes:
    first = getpass.getpass(f"Contraseña para {label}: ").encode()
    second = getpass.getpass("Repite la contraseña: ").encode()
    if first != second or len(first) < 16:
        raise SystemExit("Las contraseñas no coinciden o tienen menos de 16 caracteres.")
    return first


def subject(common_name: str, unit: str) -> x509.Name:
    return x509.Name([
        x509.NameAttribute(NameOID.COUNTRY_NAME, "MX"),
        x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Facultad de Ingeniería UAEMéx"),
        x509.NameAttribute(NameOID.ORGANIZATIONAL_UNIT_NAME, unit),
        x509.NameAttribute(NameOID.COMMON_NAME, common_name),
    ])


def create_root(output: Path) -> None:
    password = password_twice("la autoridad raíz")
    key = rsa.generate_private_key(public_exponent=65537, key_size=4096)
    name = subject("Traza FI Root CA", "Autoridad Certificadora Raíz")
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder().subject_name(name).issuer_name(name).public_key(key.public_key())
        .serial_number(x509.random_serial_number()).not_valid_before(now - timedelta(minutes=5))
        .not_valid_after(now + timedelta(days=365 * 15))
        .add_extension(x509.BasicConstraints(ca=True, path_length=1), critical=True)
        .add_extension(x509.KeyUsage(False, False, False, False, False, True, True, None, None), critical=True)
        .add_extension(x509.SubjectKeyIdentifier.from_public_key(key.public_key()), critical=False)
        .sign(key, hashes.SHA384())
    )
    protected_write(output / "private" / "traza-fi-root-ca.key.pem", key.private_bytes(
        serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8,
        serialization.BestAvailableEncryption(password)))
    protected_write(output / "public" / "traza-fi-root-ca.crt.pem",
                    cert.public_bytes(serialization.Encoding.PEM), 0o644)
    protected_write(output / "public" / "traza-fi-root-ca.sha256.txt",
                    (cert.fingerprint(hashes.SHA256()).hex() + "\n").encode(), 0o644)


def create_intermediate_csr(output: Path) -> None:
    password = password_twice("la autoridad intermedia")
    key = rsa.generate_private_key(public_exponent=65537, key_size=4096)
    csr = (
        x509.CertificateSigningRequestBuilder()
        .subject_name(subject("Traza FI Issuing CA", "Autoridad Certificadora Intermedia"))
        .add_extension(x509.BasicConstraints(ca=True, path_length=0), critical=True)
        .sign(key, hashes.SHA384())
    )
    protected_write(output / "private" / "traza-fi-intermediate-ca.key.pem", key.private_bytes(
        serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8,
        serialization.BestAvailableEncryption(password)))
    protected_write(output / "requests" / "traza-fi-intermediate-ca.csr.pem",
                    csr.public_bytes(serialization.Encoding.PEM), 0o644)
    protected_write(output / "secrets" / "intermediate-ca.pass", password)


def sign_intermediate(root_key_path: Path, root_cert_path: Path, csr_path: Path, output: Path) -> None:
    password = getpass.getpass("Contraseña de la autoridad raíz: ").encode()
    root_key = serialization.load_pem_private_key(root_key_path.read_bytes(), password=password)
    root_cert = x509.load_pem_x509_certificate(root_cert_path.read_bytes())
    csr = x509.load_pem_x509_csr(csr_path.read_bytes())
    if not csr.is_signature_valid:
        raise SystemExit("La solicitud de certificado intermedio tiene una firma inválida.")
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder().subject_name(csr.subject).issuer_name(root_cert.subject)
        .public_key(csr.public_key()).serial_number(x509.random_serial_number())
        .not_valid_before(now - timedelta(minutes=5)).not_valid_after(now + timedelta(days=365 * 5))
        .add_extension(x509.BasicConstraints(ca=True, path_length=0), critical=True)
        .add_extension(x509.KeyUsage(False, False, False, False, False, True, True, None, None), critical=True)
        .add_extension(x509.SubjectKeyIdentifier.from_public_key(csr.public_key()), critical=False)
        .add_extension(x509.AuthorityKeyIdentifier.from_issuer_public_key(root_key.public_key()), critical=False)
        .sign(root_key, hashes.SHA384())
    )
    protected_write(output, cert.public_bytes(serialization.Encoding.PEM), 0o644)


def service_secrets(output: Path) -> None:
    protected_write(output / "master.key", Fernet.generate_key())
    protected_write(output / "audit-hmac.key", secrets.token_urlsafe(48).encode())


def main() -> None:
    parser = argparse.ArgumentParser()
    sub = parser.add_subparsers(dest="command", required=True)
    for command in ("create-root", "create-intermediate-csr", "create-service-secrets"):
        item = sub.add_parser(command)
        item.add_argument("output", type=Path)
    sign = sub.add_parser("sign-intermediate")
    sign.add_argument("root_key", type=Path)
    sign.add_argument("root_cert", type=Path)
    sign.add_argument("csr", type=Path)
    sign.add_argument("output", type=Path)
    args = parser.parse_args()
    if args.command == "create-root": create_root(args.output.resolve())
    elif args.command == "create-intermediate-csr": create_intermediate_csr(args.output.resolve())
    elif args.command == "sign-intermediate": sign_intermediate(
        args.root_key.resolve(), args.root_cert.resolve(), args.csr.resolve(), args.output.resolve())
    else: service_secrets(args.output.resolve())


if __name__ == "__main__":
    main()

