# Despliegue a producción y respaldos

## Un solo comando

```bash
tools/desplegar.sh --simular     # qué subiría, sin tocar el servidor
tools/desplegar.sh               # sube lo que cambió desde el último despliegue
```

Requiere `tools/despliegue.local.conf` (copia `tools/despliegue.example.conf`; no se versiona) y acceso SSH por llave.

Qué hace, en orden:

1. Toma como base el commit que el servidor tiene anotado (`~/trazafi-despliegue/ultimo_commit`) y calcula con git qué
   archivos cambiaron hasta `HEAD`. Solo se despliega lo que está en un commit.
2. Publica únicamente `app/`, `public/`, `lib/`, `fila/`, `legacy/`, `index.php`, `.htaccess`, `database/migrations/` y
   `database/actualizar.sql`. Documentación, herramientas, pruebas, `Plan/` y temporales no se suben.
3. Compara cada archivo del servidor con la base; si alguien lo cambió allá, se detiene (o `--forzar`).
4. Guarda **un** respaldo comprimido de lo que va a reemplazar en `~/trazafi-despliegue/respaldos/`, fuera del sitio.
5. Sube todo en un solo flujo, revisa la sintaxis PHP y, si algo falla, restaura el respaldo.
6. Anota el nuevo commit y conserva solo los últimos 10 respaldos.

## Subir un módulo nuevo

```bash
php tools/validar_modulos.php
tools/desplegar.sh --modulo NombreModulo        # sube completa app/Modules/NombreModulo
tools/desplegar.sh --migrar 0NN_nombre.sql      # aplica su migración (debe ser repetible)
```

Si el módulo también tocó archivos del núcleo, usa `tools/desplegar.sh` sin opciones: sube el módulo y el resto del
commit juntos. Las migraciones se despliegan como archivo pero **no se ejecutan solas**; se aplican con `--migrar`.

Deshacer el último despliegue: `tools/desplegar.sh --restaurar`.

## Política de respaldos

- **Dentro del sitio no hay respaldos.** Nada de `archivo.php.bak-AAAAMMDD` junto al código: el historial está en git y
  el respaldo de cada despliegue está fuera del sitio, con retención.
- `.gitignore` excluye `*.bak*`, `*.orig`, `Plan/respaldos/`, `storage/backups/` y `tmp/`.
- Los respaldos de base de datos y de datos van cifrados y fuera del proyecto (`tools/archivar_respaldos_proyecto.ps1`,
  `docs/CIFRADO_DE_DATOS.md`).

## Limpieza del 10 de noviembre de 2026

- Servidor: 49 archivos `.bak-*` (684 KB) retirados del sitio; copia en `trazafi-backups/limpieza-20261110/servidor-bak-20261110.tgz`.
- Repositorio: se dejaron de versionar `Plan/respaldos/`, `Plan/AcadA/Diagnosticos_backup_original/`,
  `Plan/preliminar_fi.json.bak_original`, `storage/backups/` y `tmp/` (90 archivos). Los respaldos se movieron a
  `trazafi-backups/limpieza-20261110/repo/`; `tmp/` sigue en disco, sin versionar.
- **Siguen en el historial de git.** `storage/backups/plataforma_fi_pre_reuniones_20260717_215915.sql` incluye la tabla
  `usuarios` con hashes de contraseña y el repositorio es público: conviene cambiar esas contraseñas si alguna sigue
  vigente y decidir si se reescribe el historial.

## Pendiente de decidir (no se borró)

- En el servidor: `pruebas/` (58 MB, clon de pruebas), `graphify-out/` (12 MB, no lo usa el sitio), `Plan/`, `docs/`,
  `tools/` y `api-1.json` (archivo de credenciales en la raíz; no se sirve por web, pero no debería estar ahí).
- En el repositorio: 20 manifiestos `tools/*-deploy-files.json` y los `tools/deploy_*.ps1` por FTP, que este flujo reemplaza.
