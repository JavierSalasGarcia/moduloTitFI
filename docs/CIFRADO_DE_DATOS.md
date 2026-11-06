# Cifrado de datos sensibles en reposo

Fecha: 6 de noviembre de 2026. Estado: fase 1 (secretos TOTP y respaldos).

## Qué problema resuelve

Si alguien obtiene un volcado de la base de datos (respaldo robado, SQL injection, acceso indebido al servidor de
base de datos), no debe poder usar su contenido. Las contraseñas y los códigos de recuperación ya se guardan como hash.
Lo que estaba en claro y daba acceso directo eran los secretos TOTP: con ellos un atacante genera códigos válidos del
segundo factor de cualquier cuenta. Los respaldos históricos, además, contienen toda la base.

El cifrado se hace en PHP, antes de escribir en MySQL, con una llave que **no** está en la base de datos. Con 3,350
usuarios el costo de cómputo es despreciable (microsegundos por valor). Un atacante que sólo tenga el volcado SQL ve
texto ilegible; necesitaría además comprometer el servidor de aplicación.

## Cómo funciona

`App\Core\Cifrado` usa XChaCha20-Poly1305 (libsodium, ya incluido en PHP 8). Es cifrado autenticado: un valor
modificado no se descifra. Cada valor se liga a un *contexto* (por ejemplo `usuarios.totp_secret#42`, columna más id de
registro), de modo que copiar el texto cifrado de un usuario a otro no sirve. El valor guardado tiene la forma
`enc:v1:{id_llave}:{datos}` y mide unos 90 caracteres. El id de llave permite rotar llaves sin recifrar todo de golpe.
Un valor sin el prefijo `enc:` se trata como texto plano heredado, lo que hace gradual la migración.

Las funciones públicas son `Cifrado::cifrar($valor, $contexto)`, `Cifrado::descifrar($valor, $contexto)` y
`Cifrado::indiceCiego($valor, $contexto)`. Esta última devuelve una huella HMAC determinista, con subllave derivada
distinta, para buscar por igualdad (correo, matrícula) sin descifrar la tabla; aún no se usa en ninguna columna.

## Dónde se guarda la llave

Una de dos opciones, la variable tiene prioridad. La variable de entorno `TRAZAFI_CIFRADO_LLAVE` contiene 32 bytes en
Base64. El archivo `storage/secrets/cifrado.json` (configurable en `app/config.php`, clave `cifrado.archivo`) tiene
la forma `{"activa":"k1","llaves":{"k1":"..."}}`; `storage/` está fuera del webroot, bloqueado por `.htaccess` y la
carpeta `storage/secrets/` está en `.gitignore`. Para crear el archivo con permisos 0600:

```
php tools/cifrado_llave.php
```

`php tools/cifrado_llave.php --generar` sólo imprime una llave nueva, para usarla como variable de entorno.
**Respalda la llave en un gestor de contraseñas, separada del respaldo de la base**: sin ella los datos cifrados no se
pueden recuperar, y con ella junto al respaldo se pierde la protección.

## Secretos TOTP (`usuarios.totp_secret`)

El cifrado se aplica al vincular la aplicación de autenticación (`SeguridadController::totpVincular`) y se descifra al
verificar un código en el inicio de sesión (`AuthController::loginTotp`) y en las dos rutas de firma
(`FirmasController`, `Titulacion\Services\FirmaElectronica`), siempre mediante `Totp::paraGuardar()` y
`Totp::verificarAlmacenado()`. Si el valor no se puede descifrar (llave ausente, dato alterado) la verificación falla:
nunca deja pasar. Los usuarios existentes siguen funcionando mientras su secreto esté en claro.

Orden de puesta en marcha en producción:

1. Respaldar la base y aplicar `database/actualizar.sql` (incluye `042_cifrado_totp.sql`, que ensancha la columna de 32
   a 255 caracteres).
2. Publicar el código y crear la llave (`php tools/cifrado_llave.php`) o definir `TRAZAFI_CIFRADO_LLAVE`. Desde este
   momento los secretos nuevos se guardan cifrados.
3. Cifrar los existentes: `php tools/cifrar_totp.php --simular` para contar y luego `php tools/cifrar_totp.php`. Es
   idempotente, verifica la ida y vuelta de cada valor antes de escribirlo y puede repetirse.
4. Definir `TRAZAFI_CIFRADO_OBLIGATORIO=1` para que el sistema falle en lugar de guardar un secreto en claro si algún
   día falta la llave.

Rotación: `php tools/cifrado_llave.php --rotar` agrega una llave nueva y la marca activa, conservando las anteriores
para descifrar; después `php tools/cifrar_totp.php --recifrar` pasa los valores a la llave nueva. Sólo se retira una
llave antigua cuando ya no queda ningún valor con su id.

Si un usuario pierde acceso por un fallo de llave, el procedimiento normal sigue vigente: desvincular y volver a
vincular la aplicación de autenticación desde Seguridad, o que un administrador ponga `totp_secret` en NULL.

## Respaldos

`tools/cifrar_archivo.php` cifra y descifra archivos (ZIP, volcados `.sql.gz`) con una frase de contraseña, derivada con
Argon2id y aplicada por bloques con XChaCha20-Poly1305 en modo *secretstream*, que detecta archivos truncados o
alterados y no carga el archivo completo en memoria. La frase es distinta de la llave de la aplicación a propósito:
quien custodia respaldos no necesita la llave del servidor.

```
php tools/cifrar_archivo.php cifrar    respaldo.zip --borrar   # genera respaldo.zip.tzfi, verifica y borra el original
php tools/cifrar_archivo.php verificar respaldo.zip.tzfi       # comprueba frase e integridad
php tools/cifrar_archivo.php descifrar respaldo.zip.tzfi       # recupera respaldo.zip
```

La frase se toma de `TRAZAFI_BACKUP_FRASE` o se pide sin mostrarla. En Windows, `tools/cifrar_respaldo.ps1` es el
envoltorio (busca PHP en `TRAZAFI_PHP`, el PATH o XAMPP) y ya lo invocan dos scripts: `archivar_respaldos_proyecto.ps1`,
que cifra el ZIP histórico antes de retirar los originales (opción `-SinCifrar` sólo para pruebas), y
`deploy_database.ps1`, que cifra la copia de seguridad previa descargada. Los ZIP históricos que ya existen en
`trazafi-backups` siguen en claro hasta que se cifren con el comando anterior; el respaldo de la migración de secretos
(`secrets-migration-*`) contiene credenciales y debe cifrarse o eliminarse.

Guarda la frase en un gestor de contraseñas. Probar `verificar` en cada respaldo importante evita descubrir un problema
el día de la emergencia.

## Pruebas

`php tools/test_cifrado.php` (sin base de datos) cubre ida y vuelta, rotación, contexto distinto, valores alterados,
texto heredado, índice ciego, verificación TOTP con secreto cifrado y en claro, y los archivos de respaldo (tamaños
límite, frase incorrecta, truncado y alteración).

## Cómo cifrar otra columna (fases siguientes)

El patrón es el mismo: nombrar un contexto estable (`tabla.columna#id`), pasar por `Cifrado::cifrar()` al escribir y
`Cifrado::descifrar()` al leer, ensanchar la columna en una migración nueva (también en
`database/construir_actualizar.php`) y escribir un script de migración idempotente como `tools/cifrar_totp.php`. Si la
columna se usa en `WHERE`, agregar una columna con `Cifrado::indiceCiego()` y buscar por ella. Los módulos nuevos
deben usar esta clase para cualquier dato personal nuevo, de modo que el cifrado quede concentrado en un solo punto.

## Pendientes conocidos

El volcado `storage/backups/plataforma_fi_pre_reuniones_20260717_215915.sql` está versionado en Git; contiene datos de
demostración con hashes de contraseña, pero conviene retirarlo del repositorio y de su historial si el repositorio es
público. El instalador temporal `tools/database_installer.php?action=backup` deja un `.sql.gz` en `public/` durante
unos minutos antes de que el script lo descargue y lo borre; ese archivo viaja y espera sin cifrar, y sólo se cifra
al llegar a la máquina local.
El resto de columnas con datos personales (teléfono, correos, matrículas, justificantes, formularios) y los archivos de
`storage/` quedan para la fase 2, cuando el modelo de datos esté estable.
