# -*- coding: utf-8 -*-
"""
Versión en Python de tools/verificar_datos_sensibles.php, con las mismas reglas. Sirve para equipos o agentes sin PHP.
Impide que entren al repositorio público datos de cuentas o de personas (docs/DATOS_SENSIBLES.md).

    python tools/verificar_datos_sensibles.py                  Revisa todo lo versionado.
    python tools/verificar_datos_sensibles.py --staged         Revisa solo lo que está por entrar al commit.
    python tools/verificar_datos_sensibles.py --instalar-hook  Instala el hook pre-commit (usa PHP o Python).

Sale con código 1 si encuentra algo. No imprime los datos, solo el archivo, la línea y el motivo.
Si cambian las reglas, hay que cambiarlas en los dos archivos.
"""
import os
import re
import subprocess
import sys

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(RAIZ)

HOOK = """#!/bin/sh
# Instalado por tools/verificar_datos_sensibles.(php|py). Usa PHP si está disponible y, si no, Python.
if php -v >/dev/null 2>&1; then
  php tools/verificar_datos_sensibles.php --staged
elif python3 -c "" >/dev/null 2>&1; then
  python3 tools/verificar_datos_sensibles.py --staged
elif python -c "" >/dev/null 2>&1; then
  python tools/verificar_datos_sensibles.py --staged
else
  echo 'No hay PHP ni Python para verificar datos sensibles.' >&2
  false
fi || {
  echo 'Commit detenido: hay datos sensibles. Revisa docs/DATOS_SENSIBLES.md.' >&2
  exit 1
}
"""

TABLAS_PERSONALES = ("usuarios|usuario_correos|usuario_roles|usuarios_biometria|usuarios_codigos_otp|profesores|profesor_correos|profesor_adscripciones"
                     "|tit_historico|tit_historico_protocolos|tit_constancias|tit_sinodales|tit_firmas_externas|tit_comision_miembros|tit_tramites"
                     "|tit_solicitudes_historicas|tit_protocolos_recibidos|tit_articulo5|egresado_solicitudes|egresado_documentos|frm_respuestas|cuenta_verificacion"
                     "|tmp_[a-z_]+")

R_STORAGE = re.compile(r"^storage/(?!(?:[^/]+/)*\.htaccess$)")
R_PRIVADOS = re.compile(r"^database/(datos_privados|production-backups)/")
R_CRED = re.compile(r"(^|/)(cred[^/]*\.txt|api-[^/]*\.json|config\.local\.php|[^/]+\.bak([-_.][^/]*)?|[^/]*\.(sqlite|db|pem|key|pfx|p12))$")
R_LOCALES = re.compile(r"^\.codex-remote-attachments/|^\.claude/settings\.local\.json$")
R_DATO = re.compile(r"\.(csv|tsv|json|ndjson|xml|ya?ml)$")
R_CODIGO = re.compile(r"\.(php|py|js|cjs|mjs|ts|sh|ps1|md)$")
R_PRUEBA = re.compile(r"(^|/)(tests?|pruebas)/|(^|/)tools/(test_|prueba_)|fixture")
R_HASH = re.compile(r"\$2[aby]\$\d{2}\$[./A-Za-z0-9]{50,}|\$argon2(id|i|d)\$v=\d+\$")
R_LLAVE = re.compile(r"-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAIza[0-9A-Za-z_\-]{30,}|\bsk-[A-Za-z0-9_\-]{30,}|\bghp_[A-Za-z0-9]{30,}")
R_CORREO = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}")
R_INSERT = re.compile(r"^\s*(INSERT|REPLACE)\b.*\bINTO\s+`?(" + TABLAS_PERSONALES + r")`?\b", re.I)
R_VALUES = re.compile(r"\bVALUES\b", re.I)
R_FILA = re.compile(r"^\s*\(|\bVALUES\b", re.I)
R_FIN = re.compile(r"\bSELECT\b|;\s*$", re.I)
R_RFC = re.compile(r"^\s*\(.*'[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}'")


def archivos(staged):
    cmd = (["git", "diff", "--cached", "--name-only", "--diff-filter=ACMR", "-z"] if staged
           else ["git", "ls-files", "-z"])
    salida = subprocess.run(cmd, capture_output=True).stdout.decode("utf-8", "replace")
    return [a for a in salida.split("\0") if a]


def main():
    if "--instalar-hook" in sys.argv:
        ruta = os.path.join(".git", "hooks", "pre-commit")
        with open(ruta, "w", encoding="utf-8", newline="\n") as fh:
            fh.write(HOOK)
        try:
            os.chmod(ruta, 0o755)
        except OSError:
            pass
        print(f"Hook instalado en {ruta}")
        return 0

    staged = "--staged" in sys.argv
    lista = archivos(staged)
    hallazgos = []

    def anotar(archivo, linea, motivo):
        h = f"{archivo}:{linea}  {motivo}"
        if h not in hallazgos:
            hallazgos.append(h)

    for archivo in lista:
        bajo = archivo.replace("\\", "/").lower()
        if R_STORAGE.search(bajo):
            anotar(archivo, 0, "archivo dentro de storage/ (documentos, firmas, bitácoras)"); continue
        if R_PRIVADOS.search(bajo):
            anotar(archivo, 0, "carpeta de datos privados o respaldos"); continue
        if R_CRED.search(bajo):
            anotar(archivo, 0, "credenciales, llaves o respaldos"); continue
        if R_LOCALES.search(bajo):
            anotar(archivo, 0, "archivos locales de herramientas (pueden traer fotos o rutas personales)"); continue
        if not os.path.isfile(archivo) or os.path.getsize(archivo) > 8 * 1048576:
            continue
        es_sql = bajo.endswith(".sql")
        es_dato = es_sql or bool(R_DATO.search(bajo))
        es_codigo = bool(R_CODIGO.search(bajo))
        if not es_dato and not es_codigo:
            continue
        prueba = bool(R_PRUEBA.search(bajo))
        correos = 0
        en_insert = False
        with open(archivo, encoding="utf-8", errors="replace") as fh:
            lineas = fh.read().splitlines()
        for i, linea in enumerate(lineas):
            n = i + 1
            if not prueba and R_HASH.search(linea) and bajo != "app/modules/alumnos/alumnoscontroller.php":
                anotar(archivo, n, "hash de contraseña")
            if R_LLAVE.search(linea):
                anotar(archivo, n, "llave privada o clave de servicio")
            if not es_sql:
                if es_dato:
                    correos += len(R_CORREO.findall(linea))
                continue
            if R_INSERT.search(linea):
                en_insert = True
                if R_VALUES.search(linea):
                    anotar(archivo, n, "carga de datos en una tabla de personas o cuentas"); en_insert = False
            elif en_insert:
                if R_FILA.search(linea):
                    anotar(archivo, n, "carga de datos en una tabla de personas o cuentas"); en_insert = False
                elif R_FIN.search(linea):
                    en_insert = False
            if R_RFC.search(linea):
                anotar(archivo, n, "RFC en una fila de datos")
            correos += len(R_CORREO.findall(linea))
        if es_dato and not prueba and correos > 5:
            anotar(archivo, 0, f"contiene {correos} direcciones de correo")

    if hallazgos:
        print("\n".join(hallazgos[:60]))
        if len(hallazgos) > 60:
            print(f"… y {len(hallazgos) - 60} más")
        print(f"\n{len(hallazgos)} hallazgo(s). El repositorio es público: mueve los datos a database/datos_privados/ "
              "(ver docs/DATOS_SENSIBLES.md).")
        return 1
    print(f"Sin datos sensibles en {len(lista)} archivo(s)" + (" por confirmar." if staged else " versionados."))
    return 0


if __name__ == "__main__":
    sys.exit(main())
