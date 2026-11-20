# Contexto para agentes de TRAZAFI

TRAZAFI es la plataforma modular PHP 8 + MySQL de la Facultad de Ingeniería de la UAEMéx. Antes de cambiarla, leer `README.md`, `docs/GUIA_MODULOS.md` y la documentación específica del flujo afectado. No exponer archivos fuera de `public/`, no versionar secretos y conservar la autorización por rol, CSRF, consultas preparadas, auditoría y escape de salida.

Para formularios o encuestas privadas por enlace es obligatorio leer `docs/ENCUESTAS_PRIVADAS.md`. El módulo `Formularios` recauda datos identificables y exporta Excel; el módulo `Encuestas` se reserva para votaciones anónimas con tablero público. No mezclar ambos modelos de privacidad.

Las migraciones nuevas se agregan a `database/migrations/` y a `database/construir_actualizar.php`; después se regenera `database/actualizar.sql`. No editar el acumulado a mano.

Los secretos y datos personales nuevos se cifran en reposo con `App\Core\Cifrado` (llave fuera de la base); leer `docs/CIFRADO_DE_DATOS.md`. Nunca guardar secretos TOTP ni llaves en claro.

El acceso se decide por rol del módulo y, para casos especiales, por concesiones por persona con vigencia (`App\Core\Acceso`); leer `docs/CONCESIONES_DE_ACCESO.md`.

El asistente de la página de inicio usa Jev como árbitro y Gemini para orientar; leer `docs/ASISTENTE_JEV_GEMINI.md`. Los prompts viven solo en `app/Modules/Portal/Prompts.php` y las claves solo se leen con `App\Core\SecretosIa`. Ningún modelo recibe registros, esquema ni permisos, ni arma consultas SQL. Un módulo nuevo se integra con la clave `consulta` de su manifiesto.

Los registros de personas sin cuenta se vinculan solo por correo verificado, nunca por nombre, mediante `Identidad::vincularDatos`; leer `docs/VINCULACION_DE_CUENTAS.md`.

El despliegue se hace con `tools/desplegar.sh` (`docs/DESPLIEGUE.md`). No dejar archivos `.bak` ni respaldos dentro del sitio o del repositorio.

## Subir cambios: local → GitHub → producción

El sitio debe quedar idéntico en tres lugares: la copia local (`D:\Proyectos\plataformaFI`), el repositorio en GitHub (`JavierSalasGarcia/moduloTitFI`, rama `main`) y producción (`fingenieria.mx`). Producción solo recibe lo que ya está en un commit. Para subir se usa `tools/subir/subir.py`, que trabaja por FTP y solo necesita Python 3.9+ y las credenciales de `cred.txt` (no versionado). No se escriben scripts de subida nuevos ni se sube a mano por FTP. Cuando se pueda usar SSH, `tools/desplegar.sh` es equivalente.

1. Edita en local y revisa la sintaxis en el servidor, que usa la misma versión de PHP: `python tools/subir/subir.py revisar <archivos.php>`.
2. Haz el commit solo de tus archivos, con `git add <rutas>` y nunca `git add -A`, porque puede haber trabajo ajeno sin commitear. Antes ejecuta `php tools/verificar_datos_sensibles.php`.
3. Sube el commit a GitHub con `git push origin main`. Si en una sesión remota no hay credenciales, genera el parche con `git format-patch -1` y empújalo desde un clon que sí las tenga. Después ejecuta `git fetch origin main && git reset --soft origin/main` en la copia local.
4. Publica con `python tools/subir/subir.py publicar`. El comando se niega si HEAD no está en GitHub, revisa la sintaxis y sube solo los archivos desplegables que cambiaron desde el último commit publicado (marcador en `storage/.ultimo_commit_publicado`). Guarda en `../trazafi-backups/ftp/<fecha>/`, fuera del proyecto, lo que reemplaza. `--simular` muestra qué subiría.
5. Comprueba que todo quedó sincronizado con `python tools/subir/subir.py estado`.

Otros comandos de la herramienta:

- `php -e "return ...;"` o `php archivo.php`: ejecuta código en producción con el bootstrap cargado, para consultas y ajustes de datos.
- `migrar database/migrations/0NN_x.sql`: aplica una migración repetible.
- `storage <archivo_local> <ruta>`: sube datos a `storage/`, por ejemplo el PDF de un aviso en `avisos/<id>/<nombre>.pdf`, y luego se actualiza su fila con `php`. `storage/` nunca se versiona.
- `comparar <rutas>`: compara el servidor con la copia local.
- `descargar <rutas>`: baja archivos del servidor.
- `subir <archivos>`: solo para urgencias. Después hay que hacer commit y push del mismo cambio.

El repositorio es público: solo estructura de tablas, nunca datos de cuentas o de personas. Las cargas de datos van en `database/datos_privados/` y nada de `storage/` se versiona; leer `docs/DATOS_SENSIBLES.md` y ejecutar `php tools/verificar_datos_sensibles.php` antes de cada commit (el hook pre-commit lo hace si está instalado).

Un módulo aporta al grafo de relaciones de la persona con la clave `grafo` de su manifiesto (`docs/GRAFO_DE_PERSONA.md`); quién ve el grafo de quién lo decide `GrafoPersona::idsVisibles`. La ingesta de PDFs, carpetas y enlaces de Teams está en `docs/INGESTA_DOCUMENTAL.md`; toda lectura pasa por `Ingesta::alcance()`.
