#!/usr/bin/env python3
"""Sincroniza cambios locales con producción mediante SFTP/SSH.

Uso: python tools/sync_produccion.py --config tools/sync_produccion.json --once
     python tools/sync_produccion.py --config tools/sync_produccion.json --watch
Requiere: pip install paramiko
"""
from __future__ import annotations
import argparse, hashlib, json, posixpath, time
from pathlib import Path
import paramiko

DEFAULT_EXCLUDES = {'.git', '.claude', '.agents', 'storage', 'database/backups', '__pycache__'}

def sha256_file(path):
    h = hashlib.sha256()
    with path.open('rb') as f:
        for block in iter(lambda: f.read(1024 * 1024), b''): h.update(block)
    return h.hexdigest()

def ignored(rel, patterns):
    return any(rel == p or rel.startswith(p.rstrip('/') + '/') for p in patterns)

def remote_hash(sftp, remote):
    try:
        h = hashlib.sha256()
        with sftp.open(remote, 'rb') as f:
            for block in iter(lambda: f.read(1024 * 1024), b''): h.update(block)
        return h.hexdigest()
    except IOError:
        return None

def upload(sftp, local, rel, remote_root, dry_run):
    remote = posixpath.join(remote_root, rel)
    if remote_hash(sftp, remote) == sha256_file(local): return False
    print(('DRY-RUN ' if dry_run else '') + f'actualizando {rel}')
    if dry_run: return True
    parent = posixpath.dirname(remote)
    current = ''
    for part in parent.strip('/').split('/'):
        current += '/' + part
        try: sftp.stat(current)
        except IOError: sftp.mkdir(current)
    temp = remote + '.codex-uploading'
    sftp.put(str(local), temp)
    sftp.rename(temp, remote)
    return True

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--config', required=True, type=Path)
    ap.add_argument('--once', action='store_true'); ap.add_argument('--watch', action='store_true'); ap.add_argument('--dry-run', action='store_true')
    args = ap.parse_args()
    if not (args.once or args.watch): ap.error('elige --once o --watch')
    cfg = json.loads(args.config.read_text(encoding='utf-8'))
    root = Path(cfg.get('local_root', Path(__file__).resolve().parents[1])).resolve()
    excludes = list(DEFAULT_EXCLUDES | set(cfg.get('exclude', [])))
    client = paramiko.SSHClient(); client.load_system_host_keys(); client.set_missing_host_key_policy(paramiko.RejectPolicy())
    client.connect(cfg['host'], port=int(cfg.get('port', 22)), username=cfg['username'], key_filename=str(Path(cfg['ssh_key']).expanduser()), timeout=15)
    sftp = client.open_sftp()
    try:
        while True:
            changed = 0
            for local in root.rglob('*'):
                if local.is_file():
                    rel = local.relative_to(root).as_posix()
                    if not ignored(rel, excludes): changed += int(upload(sftp, local, rel, cfg['remote_root'], args.dry_run))
            if args.once: print(f'Listo. Archivos actualizados: {changed}'); return
            time.sleep(int(cfg.get('interval_seconds', 3)))
    finally:
        sftp.close(); client.close()

if __name__ == '__main__': main()
