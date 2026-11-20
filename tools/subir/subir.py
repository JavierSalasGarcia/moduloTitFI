# -*- coding: utf-8 -*-
"""
subir.py — Publica Traza FI en producción por FTP y mantiene sincronizados local, git/GitHub y servidor.

Pensado para personas y agentes de IA. Solo usa la biblioteca estándar de Python 3.9+.
Funciona igual en Windows (D:\\Proyectos\\plataformaFI) que en Linux/macOS.

Flujo normal (ver AGENTS.md y docs/DESPLIEGUE.md):

    1. Editar en local.
    2. python tools/subir/subir.py revisar <archivos .php>      # php -l en el servidor (misma versión de PHP)
    3. git add ... && git commit -m "..."
    4. git push origin main                                       # GitHub
    5. python tools/subir/subir.py publicar                       # sube lo que cambió desde la última publicación

Comandos:

    estado                         Commits y archivos pendientes de publicar y si HEAD ya está en GitHub.
    publicar [--desde C] [--simular] [--sin-push-check]
                                   Sube los archivos desplegables que cambiaron entre la última
                                   publicación (o el commit C) y HEAD. Exige que estén commiteados.
    revisar ARCH...                php -l de cada archivo PHP en el servidor, sin publicarlo.
    subir ARCH... [--simular]      Sube archivos concretos (rutas relativas a la raíz del proyecto).
                                   Úsese solo para urgencias; después hay que commitear y hacer push.
    php ARCHIVO.php | -e "CODIGO"  Ejecuta en producción el cuerpo de una función PHP (con el bootstrap
                                   del sitio cargado) y muestra su valor de retorno como JSON.
    migrar database/migrations/0NN_x.sql
                                   Aplica una migración (debe ser repetible) sentencia por sentencia.
    storage LOCAL RUTA            Sube un archivo de datos a storage/RUTA (p. ej. PDF de un aviso).
                                   storage/ nunca se versiona.
    comparar RUTA...               Compara archivos o carpetas del servidor con la copia local.
    descargar RUTA...              Descarga archivos del servidor a la carpeta de respaldos.

Credenciales: variables TRAZAFI_FTP_HOST/USER/PASS, o tools/subir/ftp.local.json
({"host": ..., "usuario": ..., "contrasena": ...}), o cred.txt en la raíz (USUARIO / CONTRASEÑA).
Ninguno se versiona.

Respaldos: antes de reemplazar un archivo se guarda la versión del servidor en
<carpeta padre del proyecto>/trazafi-backups/ftp/<fecha-hora>/ (fuera del proyecto, nunca dentro del sitio).
"""
import argparse
import ftplib
import hashlib
import io
import json
import os
import re
import secrets
import subprocess
import sys
import time
import urllib.request

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
AQUI = os.path.dirname(os.path.abspath(__file__))
URL_SITIO = "https://fingenieria.mx"
CARPETA_RUNNER = "ca_dsyc"          # carpeta servida por web donde vive un instante el ejecutor PHP
MARCADOR = "storage/.ultimo_commit_publicado"
RESPALDOS = os.path.join(os.path.dirname(RAIZ), "trazafi-backups", "ftp")
# Lo único que se publica (igual que tools/desplegar.sh).
DESPLEGABLE = ("app/", "public/", "lib/", "fila/", "legacy/", "index.php", ".htaccess",
               "database/migrations/", "database/actualizar.sql")
NUNCA = (".bak", ".orig", "config.local.php")

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")


# ------------------------------------------------------------------ conexión
def credenciales():
    h = os.environ.get("TRAZAFI_FTP_HOST", "ftp.fingenieria.mx")
    u, p = os.environ.get("TRAZAFI_FTP_USER"), os.environ.get("TRAZAFI_FTP_PASS")
    if u and p:
        return h, u, p
    cfg = os.path.join(AQUI, "ftp.local.json")
    if os.path.exists(cfg):
        d = json.load(open(cfg, encoding="utf-8"))
        return d.get("host", h), d["usuario"], d["contrasena"]
    cred = os.path.join(RAIZ, "cred.txt")
    if os.path.exists(cred):
        L = [l.strip() for l in open(cred, encoding="utf-8", errors="replace").read().splitlines()]
        for i, l in enumerate(L):
            if l.upper().startswith("USUARIO"):
                u = next(x for x in L[i + 1:] if x)
            if l.upper().startswith("CONTRASE"):
                p = next(x for x in L[i + 1:] if x)
        if u and p:
            return h, u, p
    sys.exit("No hay credenciales FTP (TRAZAFI_FTP_*, tools/subir/ftp.local.json o cred.txt).")


def conectar():
    h, u, p = credenciales()
    try:
        f = ftplib.FTP_TLS(h, timeout=60)
        f.login(u, p)
        f.prot_p()
    except Exception:
        f = ftplib.FTP(h, timeout=60)
        f.login(u, p)
    f.voidcmd("TYPE I")
    return f


def rel(ruta):
    """Ruta relativa a la raíz del proyecto, con '/'."""
    a = os.path.abspath(os.path.join(RAIZ, ruta)) if not os.path.isabs(ruta) else os.path.abspath(ruta)
    r = os.path.relpath(a, RAIZ).replace("\\", "/")
    if r.startswith(".."):
        sys.exit(f"Fuera del proyecto: {ruta}")
    return r


def leer_remoto(f, ruta):
    b = io.BytesIO()
    try:
        f.retrbinary("RETR " + ruta, b.write)
        return b.getvalue()
    except ftplib.all_errors:
        return None


def crear_dirs(f, carpeta, hechos):
    acc = ""
    for p in [x for x in carpeta.split("/") if x]:
        acc = f"{acc}/{p}" if acc else p
        if acc in hechos:
            continue
        try:
            f.mkd(acc)
        except ftplib.all_errors:
            pass
        hechos.add(acc)


def subir_bytes(f, ruta, datos, hechos):
    crear_dirs(f, os.path.dirname(ruta), hechos)
    tmp = ruta + ".subiendo"
    f.storbinary("STOR " + tmp, io.BytesIO(datos))
    try:
        f.delete(ruta)
    except ftplib.all_errors:
        pass
    f.rename(tmp, ruta)
    if f.size(ruta) != len(datos):
        raise RuntimeError(f"Tamaño distinto en el servidor: {ruta}")


# ------------------------------------------------------------------ ejecutor PHP
def ejecutar_php(cuerpo, f=None):
    propio = f is None
    f = f or conectar()
    tok = secrets.token_hex(24)
    nombre = "_x_" + secrets.token_hex(8) + ".php"
    php = ("<?php\ndeclare(strict_types=1);\n"
           "if (!hash_equals('" + hashlib.sha256(tok.encode()).hexdigest() + "', hash('sha256', $_SERVER['HTTP_X_INSTALAR'] ?? ''))) { http_response_code(404); exit; }\n"
           "ini_set('display_errors', '0'); set_time_limit(170);\n"
           "require dirname(__DIR__) . '/app/bootstrap.php';\n"
           "(new ReflectionProperty(\\App\\Core\\App::class, 'config'))->setValue(null, require APP_RAIZ . '/config.php');\n"
           "header('Content-Type: application/json; charset=utf-8');\n"
           "try { $r = \\App\\Core\\AccessControl::internal(function () {\n" + cuerpo + "\n}); "
           "echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); } catch (Throwable $e) { "
           "echo json_encode(['error' => $e->getMessage(), 'en' => basename($e->getFile()) . ':' . $e->getLine()], JSON_UNESCAPED_UNICODE); }\n")
    f.storbinary(f"STOR {CARPETA_RUNNER}/{nombre}", io.BytesIO(php.encode("utf-8")))
    try:
        req = urllib.request.Request(f"{URL_SITIO}/{CARPETA_RUNNER}/{nombre}",
                                     headers={"X-Instalar": tok, "User-Agent": "Mozilla/5.0 (subir.py)"})
        return urllib.request.urlopen(req, timeout=175).read().decode("utf-8", "replace")
    except Exception as e:
        return "ERROR " + str(e) + " " + (getattr(e, "read", lambda: b"")()[:800]).decode("utf-8", "replace")
    finally:
        try:
            f.delete(f"{CARPETA_RUNNER}/{nombre}")
        except ftplib.all_errors:
            print("AVISO: no se pudo borrar el ejecutor temporal " + nombre)
        if propio:
            f.quit()


def revisar_sintaxis(f, rutas):
    """php -l en el servidor. Devuelve lista de (ruta, error)."""
    php = [r for r in rutas if r.endswith(".php")]
    if not php:
        return []
    temporales = []
    for r in php:
        n = "storage/_lint_" + secrets.token_hex(6) + ".php"
        with open(os.path.join(RAIZ, r), "rb") as fh:
            f.storbinary("STOR " + n, fh)
        temporales.append(n)
    cuerpo = ("return array_map(fn($n) => trim((string) shell_exec('php -l ' . escapeshellarg(dirname(APP_RAIZ) . '/' . $n) . ' 2>&1')), "
              + json.dumps(temporales) + ");")
    salida = ejecutar_php(cuerpo, f)
    for n in temporales:
        try:
            f.delete(n)
        except ftplib.all_errors:
            pass
    try:
        res = json.loads(salida)
    except ValueError:
        return [("(ejecutor)", salida[:800])]
    if isinstance(res, dict):
        return [("(ejecutor)", json.dumps(res, ensure_ascii=False))]
    errores = []
    for r, txt in zip(php, res):
        if "No syntax errors" not in (txt or ""):
            errores.append((r, txt))
    return errores


# ------------------------------------------------------------------ git
def git(*args, check=True):
    p = subprocess.run(["git", *args], cwd=RAIZ, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if check and p.returncode != 0:
        sys.exit(f"git {' '.join(args)} falló:\n{p.stderr.strip()}")
    return p.stdout.strip()


def desplegable(r):
    return r.startswith(DESPLEGABLE) and not any(x in r for x in NUNCA)


def marcador_remoto(f):
    d = leer_remoto(f, MARCADOR)
    return d.decode().strip() if d else ""


def pendientes(f, desde=None):
    base = desde or marcador_remoto(f)
    if not base:
        sys.exit("El servidor no tiene marcador de publicación. Indique --desde <commit> "
                 "(el último commit que ya está en producción).")
    git("cat-file", "-e", base + "^{commit}")
    cambios = git("diff", "--name-status", "--no-renames", f"{base}..HEAD").splitlines()
    subir, borrados = [], []
    for l in cambios:
        estado, ruta = l.split("\t", 1)
        if not desplegable(ruta):
            continue
        (borrados if estado == "D" else subir).append(ruta)
    return base, sorted(set(subir)), borrados


def en_github():
    git("fetch", "-q", "origin", check=False)
    head = git("rev-parse", "HEAD")
    p = subprocess.run(["git", "merge-base", "--is-ancestor", head, "origin/main"], cwd=RAIZ)
    return p.returncode == 0


# ------------------------------------------------------------------ comandos
def nueva_carpeta_respaldo():
    marca = time.strftime("%Y%m%d-%H%M%S")
    for base in (os.environ.get("TRAZAFI_RESPALDOS"), RESPALDOS,
                 os.path.join(os.path.expanduser("~"), "trazafi-backups", "ftp")):
        if not base:
            continue
        try:
            d = os.path.join(base, marca)
            os.makedirs(d, exist_ok=True)
            return d
        except OSError:
            continue
    sys.exit("No se pudo crear la carpeta de respaldos (defina TRAZAFI_RESPALDOS).")


def publicar_rutas(f, rutas, simular, desde_git=False):
    errores = revisar_sintaxis(f, rutas)
    if errores:
        for r, e in errores:
            print(f"ERROR de sintaxis en {r}:\n  {e}")
        sys.exit("No se publicó nada.")
    resp = nueva_carpeta_respaldo() if not simular else "(simulación)"
    hechos, nuevos, reemplazados, iguales = set(), [], [], []
    for r in rutas:
        datos = (subprocess.run(["git", "show", f"HEAD:{r}"], cwd=RAIZ, capture_output=True).stdout
                 if desde_git else open(os.path.join(RAIZ, r), "rb").read())
        actual = leer_remoto(f, r)
        if actual == datos:
            iguales.append(r)
            continue
        if actual is None:
            nuevos.append(r)
        else:
            reemplazados.append(r)
            if not simular:
                destino = os.path.join(resp, *r.split("/"))
                os.makedirs(os.path.dirname(destino), exist_ok=True)
                open(destino, "wb").write(actual)
        if not simular:
            subir_bytes(f, r, datos, hechos)
        print(("  (simular) " if simular else "  subido ") + r + ("  [nuevo]" if actual is None else ""))
    print(f"Reemplazados {len(reemplazados)}, nuevos {len(nuevos)}, sin cambios {len(iguales)}.")
    if reemplazados and not simular:
        print(f"Respaldo de lo reemplazado: {resp}")
    return reemplazados + nuevos


def cmd_estado(a):
    f = conectar()
    base, subir, borrados = pendientes(f, a.desde)
    print(f"Último commit publicado: {base[:10]}   HEAD: {git('rev-parse', '--short=10', 'HEAD')}")
    print(git("log", "--oneline", f"{base}..HEAD") or "(sin commits nuevos)")
    print(f"\nArchivos por publicar ({len(subir)}):")
    for r in subir:
        print("  " + r)
    if borrados:
        print("\nBorrados en git (no se borran solos del servidor):")
        for r in borrados:
            print("  " + r)
    sucios = [l for l in git("status", "--porcelain").splitlines() if desplegable(l[3:])]
    if sucios:
        print("\nCambios sin commit en archivos desplegables (no se publicarán):")
        for l in sucios:
            print("  " + l)
    print("\nGitHub: " + ("HEAD ya está en origin/main." if en_github() else "FALTA hacer git push origin main."))
    f.quit()


def cmd_publicar(a):
    if not a.sin_push_check and not en_github():
        sys.exit("HEAD no está en GitHub. Haga primero: git push origin main  (o use --sin-push-check).")
    f = conectar()
    base, subir, borrados = pendientes(f, a.desde)
    if not subir:
        print("No hay nada que publicar.")
    sucios = [r for r in subir if git("status", "--porcelain", "--", r)]
    if sucios:
        print("AVISO: estos archivos tienen cambios sin commit; se publica la versión de HEAD:")
        for r in sucios:
            print("  " + r)
    print(f"Publicando {len(subir)} archivo(s) de {base[:10]}..HEAD")
    publicar_rutas(f, subir, a.simular, desde_git=True)
    if borrados:
        print("Borrados en git; elimínelos del servidor solo si ya no se usan:")
        for r in borrados:
            print("  " + r)
    if not a.simular:
        head = git("rev-parse", "HEAD")
        f.storbinary("STOR " + MARCADOR, io.BytesIO(head.encode()))
        print(f"Marcador actualizado: {head[:10]}")
    f.quit()


def cmd_revisar(a):
    f = conectar()
    rutas = [rel(x) for x in a.archivos]
    errores = revisar_sintaxis(f, rutas)
    for r in rutas:
        if r.endswith(".php"):
            print(("ERROR  " if any(e[0] == r for e in errores) else "ok     ") + r)
    for r, e in errores:
        print(f"\n{r}:\n{e}")
    f.quit()
    sys.exit(1 if errores else 0)


def cmd_subir(a):
    f = conectar()
    rutas = [rel(x) for x in a.archivos]
    for r in rutas:
        if not os.path.isfile(os.path.join(RAIZ, r)):
            sys.exit(f"No existe en local: {r}")
        if r.startswith("storage/"):
            sys.exit("storage/ se sube con el comando 'storage'.")
    publicar_rutas(f, rutas, a.simular)
    f.quit()
    sucios = [r for r in rutas if git("status", "--porcelain", "--", r)]
    if sucios:
        print("\nRecuerde: commit y git push de estos archivos para mantener todo sincronizado:")
        for r in sucios:
            print("  " + r)


def cmd_php(a):
    cuerpo = a.codigo if a.e else open(a.codigo, encoding="utf-8").read()
    cuerpo = re.sub(r"^\s*<\?php\s*", "", cuerpo)
    print(ejecutar_php(cuerpo))


def cmd_migrar(a):
    r = rel(a.archivo)
    sql = open(os.path.join(RAIZ, r), encoding="utf-8").read()
    cuerpo = ("$sql = " + json.dumps(sql, ensure_ascii=False) + ";\n"
              "$pdo = \\App\\Core\\Database::pdo(); $n = 0;\n"
              "foreach (array_filter(array_map('trim', explode(';', preg_replace('/^\\s*--.*$/m', '', $sql)))) as $st) { $pdo->exec($st); $n++; }\n"
              "return ['sentencias' => $n];")
    print(ejecutar_php(cuerpo))


def cmd_storage(a):
    f = conectar()
    destino = "storage/" + a.ruta.replace("\\", "/").lstrip("/").removeprefix("storage/")
    datos = open(a.local, "rb").read()
    actual = leer_remoto(f, destino)
    if actual is not None and actual != datos:
        resp = nueva_carpeta_respaldo()
        p = os.path.join(resp, *destino.split("/"))
        os.makedirs(os.path.dirname(p), exist_ok=True)
        open(p, "wb").write(actual)
        print(f"Respaldo de la versión anterior: {p}")
    subir_bytes(f, destino, datos, set())
    print(f"Subido {destino} ({len(datos)} bytes)")
    f.quit()


def recorrer(f, ruta):
    try:
        ents = list(f.mlsd(ruta))
    except ftplib.all_errors:
        return [ruta]
    salida = []
    for n, at in ents:
        if n in (".", ".."):
            continue
        if at.get("type") == "dir":
            salida += recorrer(f, f"{ruta}/{n}")
        elif at.get("type") == "file":
            salida.append(f"{ruta}/{n}")
    return salida


def cmd_comparar(a):
    f = conectar()
    iguales, distintos, solo_srv, solo_local = 0, [], [], []
    for x in a.rutas:
        r = rel(x)
        for s in recorrer(f, r):
            remoto = leer_remoto(f, s)
            lp = os.path.join(RAIZ, s)
            if not os.path.exists(lp):
                solo_srv.append(s)
                continue
            local = open(lp, "rb").read()
            if remoto is not None and remoto.replace(b"\r\n", b"\n") == local.replace(b"\r\n", b"\n"):
                iguales += 1
            else:
                distintos.append(s)
        if os.path.isdir(os.path.join(RAIZ, r)):
            remotos = set(recorrer(f, r))
            for dp, _, fs in os.walk(os.path.join(RAIZ, r)):
                for n in fs:
                    s = rel(os.path.join(dp, n))
                    if s not in remotos:
                        solo_local.append(s)
    print(f"Iguales: {iguales}")
    for t, L in (("Distintos", distintos), ("Solo en el servidor", solo_srv), ("Solo en local", solo_local)):
        if L:
            print(f"{t} ({len(L)}):")
            for s in L:
                print("  " + s)
    f.quit()


def cmd_descargar(a):
    f = conectar()
    d = nueva_carpeta_respaldo()
    for x in a.rutas:
        r = x.replace("\\", "/").lstrip("/")
        datos = leer_remoto(f, r)
        if datos is None:
            print("No existe en el servidor: " + r)
            continue
        p = os.path.join(d, *r.split("/"))
        os.makedirs(os.path.dirname(p), exist_ok=True)
        open(p, "wb").write(datos)
        print("Descargado " + p)
    f.quit()


def main():
    ap = argparse.ArgumentParser(description="Publica Traza FI por FTP (ver AGENTS.md).")
    sp = ap.add_subparsers(dest="cmd", required=True)
    s = sp.add_parser("estado"); s.add_argument("--desde"); s.set_defaults(fn=cmd_estado)
    s = sp.add_parser("publicar"); s.add_argument("--desde"); s.add_argument("--simular", action="store_true")
    s.add_argument("--sin-push-check", action="store_true"); s.set_defaults(fn=cmd_publicar)
    s = sp.add_parser("revisar"); s.add_argument("archivos", nargs="+"); s.set_defaults(fn=cmd_revisar)
    s = sp.add_parser("subir"); s.add_argument("archivos", nargs="+"); s.add_argument("--simular", action="store_true")
    s.set_defaults(fn=cmd_subir)
    s = sp.add_parser("php"); s.add_argument("-e", action="store_true", help="el argumento es código, no archivo")
    s.add_argument("codigo"); s.set_defaults(fn=cmd_php)
    s = sp.add_parser("migrar"); s.add_argument("archivo"); s.set_defaults(fn=cmd_migrar)
    s = sp.add_parser("storage"); s.add_argument("local"); s.add_argument("ruta"); s.set_defaults(fn=cmd_storage)
    s = sp.add_parser("comparar"); s.add_argument("rutas", nargs="+"); s.set_defaults(fn=cmd_comparar)
    s = sp.add_parser("descargar"); s.add_argument("rutas", nargs="+"); s.set_defaults(fn=cmd_descargar)
    a = ap.parse_args()
    a.fn(a)


if __name__ == "__main__":
    main()
