# Datos sensibles fuera del repositorio

El repositorio es público. Puede contener la **estructura** de las tablas (sus encabezados), nunca su contenido
personal: cuentas, correos, hashes de contraseña, RFC, números de cuenta, nombres de alumnos o profesores, documentos,
firmas ni bitácoras.

## Dónde vive cada cosa

| Qué | Dónde | ¿Se versiona? |
|---|---|---|
| Estructura de tablas, catálogos institucionales (áreas, dependencias, funciones, programas) | `database/schema*.sql`, `database/migrations/`, `database/actualizar.sql` | Sí |
| Cargas de datos de personas o cuentas | `database/datos_privados/` | No (`.gitignore`) |
| Documentos, firmas, sellos, adjuntos, bitácoras | `storage/` | No (solo sus `.htaccess`) |
| Credenciales y llaves | Variables de entorno, `app/config.local.php`, `storage/secrets/`, `storage/pki/` | No |
| Respaldos | Fuera del proyecto (`docs/DESPLIEGUE.md`, `docs/CIFRADO_DE_DATOS.md`) | No |

## El candado

`tools/verificar_datos_sensibles.php` revisa lo versionado y **detiene el commit** si encuentra:

- hashes de contraseña o llaves privadas y claves de servicios;
- `INSERT … VALUES` en tablas de personas o cuentas, o RFC en filas de datos;
- más de cinco correos en un archivo de datos;
- archivos dentro de `storage/`, `database/datos_privados/`, respaldos, `cred*.txt`, `api-*.json` o `config.local.php`.

```bash
php tools/verificar_datos_sensibles.php                  # revisa todo lo versionado
php tools/verificar_datos_sensibles.php --instalar-hook  # instala el hook pre-commit (una vez por copia del repositorio)
```

El hook vive en `.git/hooks/`, que git no versiona: hay que instalarlo en cada equipo donde se clone el repositorio.
No imprime los datos encontrados, solo el archivo, la línea y el motivo.

## Cómo escribir una migración que carga datos de personas

1. En `database/migrations/NNN_nombre.sql` deja solo la estructura (`CREATE TABLE`, `ALTER`, catálogos no personales).
2. Pon la carga en `database/datos_privados/migraciones/NNN_nombre.sql` y aplícala a mano en el servidor.
3. Si la migración necesita los datos para un `UPDATE`, usa tablas temporales cargadas desde el archivo privado: sin la
   carga, el `UPDATE` no hace nada y la migración sigue siendo repetible.

## Limpieza del 10 de noviembre de 2026

Se retiraron del repositorio (los originales completos quedaron en `database/datos_privados/migraciones/`):

| Archivo | Qué traía |
|---|---|
| `017_titulacion.sql` | Historial de titulación: nombres y números de cuenta (13 cargas) |
| `020_…`, `021_…` | Cuentas institucionales con hash de contraseña |
| `025_historico_sistema_anterior.sql` | 1,254 registros del libro de actas, con nombres |
| `026_directorio_profesores.sql` | 371 profesores con RFC, nombre y correos |
| `036_…`, `039_…` | Números de cuenta y fechas de examen |
| `038_protocolos_historicos.sql` | Protocolos con alumnos, revisores y correos |
| `database/actualizar.sql` | El acumulado de todo lo anterior (pasó de 2 MB a 174 KB) |
| 22 archivos de `storage/` | PDF firmados, oficios, imágenes de firmas y sellos, un adjunto y una bitácora |
| `.codex-remote-attachments/`, `.claude/settings.local.json` | Fotos y configuración local de herramientas |

Producción no cambia: ya tenía los datos aplicados. Una instalación nueva queda con la estructura y sin personas; los
datos se cargan desde la carpeta privada.

## Lo que esta limpieza no resuelve

- **El historial de git conserva todo lo anterior.** Quitar un archivo del repositorio no lo borra de los commits
  pasados, que siguen siendo públicos. Para eliminarlo hay que reescribir el historial y forzar el envío; es una
  decisión aparte porque cambia todos los identificadores de commit.
- Los hashes de contraseña que estuvieron publicados deben considerarse expuestos: conviene cambiar esas contraseñas.
- `app/Modules/Alumnos/AlumnosController.php` da de alta alumnos con una contraseña inicial fija y conocida. No es un
  dato de cuenta, pero es un riesgo: esas altas deberían nacer sin contraseña utilizable.
