# Sincronización local → producción

Usa SFTP sobre SSH, no FTP. Copia `sync_produccion.example.json` a
`sync_produccion.json`, completa host/usuario/ruta/clave y no subas ese archivo.

```powershell
python -m pip install paramiko
python tools/sync_produccion.py --config tools/sync_produccion.json --dry-run --once
python tools/sync_produccion.py --config tools/sync_produccion.json --watch
```

El servidor debe aceptar la clave pública SSH del usuario de despliegue. El
script compara SHA-256, excluye almacenamiento y configuración local, y sube
cada archivo mediante un temporal antes de renombrarlo.
