# Plan de pruebas y mantenimiento de los manuales de Titulación (licenciatura)

Documento para quien (persona o agente de IA) deba **volver a probar el módulo Titulación y actualizar sus manuales**
después de cambiar el sistema. Es autosuficiente: no necesita el historial de la conversación que lo originó.

## 1. Qué se produce

- **Manuales en LaTeX modulares**, en `docs/manuales/titulacion/latex/`:
  - `manuales/pasante/<opción>.tex` — **un manual por opción de titulación, solo para el estudiante** (un pasante lee únicamente
    lo de su opción). 11 opciones: `aprovechamiento`, `egel`, `creditos`, `articulo`, `ensayo`, `memoria`, `aplicacion`,
    `autoempleo`, `residencia`, `tesina`, `tesis`.
  - `manuales/<actor>/<opción>.tex` — **un manual por actor y por opción** (autocontenido: repite pasos comunes a propósito, para
    quien asume la función por primera vez).
  - `manuales/<actor>/global.tex` — **manual consolidado por actor** (todos menos el pasante): pasos comunes unificados y, al final,
    particularidades de cada opción.
  - Piezas reutilizables: `actores/<actor>.tex` (qué hace el actor), `modalidades/<opción>/<actor>.tex` (contenido por actor y
    opción), `consolidados/<actor>.tex` (visión global), `preambulo.tex` (paquetes y macros `\captura`, `\capturapendiente`, `\aviso`).
  - Actores: `pasante`, `responsable_titulacion` (Jefatura/Apoyo de Titulación), `subdirector`, `coordinador_carrera`,
    `comision_protocolos`, `asesor`, `coasesor`, `sinodal`, `servicios_escolares` (solo recibe el expediente; no usa la plataforma).
    Las opciones sin trabajo escrito (aprovechamiento, EGEL, créditos) solo involucran a pasante, responsable, subdirector y
    Servicios Escolares; las de trabajo escrito, a todos.
- **Capturas** en `docs/manuales/titulacion/img/` con el nombre `<opción>_<actor>_<NN>_<tema>.png`.
- **PDF** compilados (no se versionan; se regeneran) en `docs/manuales/titulacion/pdf/<actor>/<opción>.pdf` (`bash tools/manual_titulacion/compilar.sh [actor] [opción]`).

Los `.tex` se **editan a mano** una vez generados; el generador **no sobrescribe** archivos existentes salvo con `--forzar`
(que borra ediciones manuales: úsalo solo para regenerar lo que no se haya retocado, o respalda antes).

## 2. Regla de privacidad y de entorno (leer antes de probar)

- **Las pruebas con inicio de sesión se hacen SOLO en una base local o de pruebas** (`http://localhost:8090`, base `plataforma_fi`).
  No crees cuentas de prueba ni escribas contraseñas en producción (`fingenieria.mx`), ni en `fingenieria.mx/pruebas` (comparte
  dominio con producción). Sembrar las cuentas en producción además convertiría a usuarios ficticios en la Subdirección, la
  Dirección y la Jefatura reales (`Autoridades` toma al más reciente), y sus nombres saldrían en oficios reales.
- `tools/titulacion_pruebas_semilla.php` **se niega a correr** contra las bases de producción (`u429286701_*`). No lo evadas.
- Las credenciales de prueba viven en `cred_titulacion_pruebas.txt` (raíz del repositorio; lo ignora git por `/cred*.txt`).
  **Nunca** las copies a los `.tex`, a los commits ni a la documentación. Los manuales no deben mostrar contraseñas.
- El repositorio es público: ver `docs/DATOS_SENSIBLES.md` y correr `php tools/verificar_datos_sensibles.php` antes de cada commit.
  Las capturas solo deben mostrar datos ficticios de la base local.

## 3. Preparación del entorno local (Windows)

1. MySQL de XAMPP: `C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone` (root sin contraseña,
   base `plataforma_fi`). Aplicar migraciones pendientes (`database/migrations/*.sql`, p. ej. `045_titulacion_expediente_final.sql`).
2. Servidor: `C:\xampp\php\php.exe -S localhost:8090 -t public` desde la raíz (el `php` del PATH no tiene `pdo_mysql`).
3. Cuentas de prueba: `php tools/titulacion_pruebas_semilla.php` (con el PHP de XAMPP). Crea 13 cuentas de personal y 11 pasantes
   (`prueba.pasante.<opción>@alumno.uaemex.mx`), asigna Jefatura/Subdirección/Dirección por departamento, Coordinador de Docencia
   de Computación y la comisión de revisión; escribe las contraseñas en `cred_titulacion_pruebas.txt`. Es repetible (restablece
   contraseñas). El inicio de sesión exige dominios `uaemex.mx` o `alumno.uaemex.mx`.
4. Python con Playwright y Chromium (`pip install playwright`; el ejecutable se fija con la variable `TIT_CHROME`, por defecto
   `chromium-1228` en `%LOCALAPPDATA%\ms-playwright`). LaTeX: MiKTeX (`pdflatex`).
5. Para volver a empezar una opción: borrar su trámite (`DELETE FROM tit_tramites WHERE id = …`; cascada) y relanzar su captura.

## 4. Cómo se ejecuta la prueba (arnés `tools/manual_titulacion/`)

| Archivo | Para qué |
|---|---|
| `lib.py` | Sesión de Playwright por actor (`Sesion`), inicio de sesión en dos pasos (correo → contraseña), capturas (`foto`). |
| `flujo.py` | Motores genéricos: `pasante_avanza` (captura, firma/generar, sube PDF, confirma, «No me aplica» a documentos condicionales), `personal_avanza` (valida, coteja, registra hitos), `subdirector_firma`. |
| `capturas_sin_trabajo.py` | Recorre aprovechamiento, EGEL y créditos de principio a fin con capturas por actor. |
| `continuar.py` | Reanuda trámites abiertos hasta concluirlos y toma las capturas finales. |
| `capturas_verificacion.py` | Verifica el expediente final, exporta el ZIP y captura la verificación (`opción:id_trámite`). |
| `exportar_catalogo.php` | Vuelca etapas, documentos y renglones del expediente por opción a `catalogo.json` (fuente de los manuales). |
| `generar_latex.py` | Genera los `.tex` a partir de `catalogo.json` y de las capturas existentes. |
| `compilar.sh` | Compila a PDF. |

Secuencia de una corrida completa:

```bash
cd D:/Proyectos/plataformaFI
C:/xampp/php/php.exe tools/titulacion_pruebas_semilla.php
C:/xampp/php/php.exe tools/manual_titulacion/exportar_catalogo.php          # si cambió Catalogo.php o ExpedienteFinal
cd tools/manual_titulacion
python -u capturas_sin_trabajo.py                    # o con argumentos: egel creditos
python -u continuar.py egel:<id>:2011002             # si algún trámite quedó abierto
python -u capturas_verificacion.py aprovechamiento:<id> egel:<id> creditos:<id>
cd ../.. && python tools/manual_titulacion/generar_latex.py          # sin --forzar no pisa ediciones
bash tools/manual_titulacion/compilar.sh
```

Criterios de éxito por opción: el trámite termina en `estado = concluido` con `resultado` favorable; la verificación final deja
`tit_expediente_final.estado = enviado`; el ZIP contiene una carpeta por trámite con los PDF numerados en el orden del formato,
el formato de integración llenado, el formato de revisión y `00_indice.txt` con SHA-256.

## 5. Estado de cobertura (actualizar al repetir la prueba)

Las **11 opciones** se recorrieron de punta a punta en noviembre de 2026, con capturas tomadas **durante la misma ejecución**
(no en una segunda pasada), y cada una cerró con la verificación final y la exportación del ZIP:

- Sin trabajo escrito: aprovechamiento, EGEL (convocatoria «Pagando menos») y créditos en estudios avanzados
  (`capturas_sin_trabajo.py`, `continuar.py`).
- Con trabajo escrito: artículo, ensayo, memoria, aplicación, autoempleo, residencia, tesina y tesis (`capturas_trabajo.py`).
- Verificación y ZIP de las 11: `capturas_verificacion.py`.

Cómo se resuelven los pasos con enlace por correo (solo pruebas locales): la plataforma deja cada correo en
`storage/logs/titulacion-correos.log` (porque no hay `correo.api_token` y `app.debug` está activo). El arnés lee ese archivo
(`flujo.enlaces_correo`, `trabajo.firmar_correos`), **toma solo la ruta** (`/titulacion/<tipo>/t/<token>`; el dominio del correo es
el de producción y **no debe visitarse**) y la abre en el servidor local con la sesión del destinatario, que debe ser una cuenta
`prueba.*@uaemex.mx` sembrada. Tipos: `firma` (asesor, coasesor, revisores: protocolo, revisión, voto aprobatorio, sustentación),
`jurado` (Coordinador de Carrera), `sinodal` (acuse de los oficios).

Orden real de un trámite con trabajo escrito (el arnés lo recorre en rondas hasta que ya no hay nada que hacer):
1. Pasante: datos y protocolo (incluye asesor, coasesor y cronograma) → «Firmar y enviar a mi asesor».
2. Asesor y coasesor firman por enlace → el protocolo pasa solo a la comisión.
3. Encargada de comisión asigna a los 3 integrantes → cada uno aprueba y firma → la encargada dictamina.
4. Titulación registra la fecha de aprobación; Jefatura da el Vo. Bo. y el Subdirector firma el oficio de aprobación del tema.
5. Pasante confirma el desarrollo; examen previo: solicitud de revisión (pasante y asesor), agenda con fecha y 5 sinodales.
6. Coordinador de Carrera ratifica y firma (enlace `jurado`); los sinodales dan acuse (enlace `sinodal`).
7. Sustentación: voto aprobatorio (asesor y revisores), solicitud de sustentación, oficio de impresión (Subdirector) y **oficio de
   estructura capitular (lo firma el Director)**.
8. Documentación final, nueva agenda (examen definitivo) con jurado, resultado y acta; luego verificación y exportación.

Hallazgos de esas pruebas:
- Corregido en `views/tramite.php`: el «Dictamen de los HH. Consejos» (solo si se excedió el plazo) es un documento generado y no
  ofrecía «No me aplica»; el pasante quedaba atascado antes de la toma de protesta.
- `Services/ExpedienteFinal.php`: el logotipo PNG no lo admite FPDF; ahora el formato se genera sin él si falla.

## 6. Qué hacer cuando se modifique el sistema

| Cambio | Acción |
|---|---|
| Documentos o etapas de una opción (`Catalogo.php`, `Configuracion`) | `exportar_catalogo.php`, repetir la opción, regenerar y revisar `modalidades/<opción>/*.tex`. |
| Renglones del expediente final (`Services/ExpedienteFinal.php`) | Cambiar `renglones()` según el formato oficial (están en `Downloads/finales` del autor; un formato por opción), `exportar_catalogo.php`, rehacer `capturas_verificacion.py`. |
| Pantallas o textos de la interfaz | Repetir las capturas afectadas; los nombres de archivo se conservan, así los `.tex` no cambian. |
| Nuevo actor o función | Añadirlo a `ACTORES`, `GENERAL` y `para_otro` en `generar_latex.py`, crear su cuenta en la semilla. |
| Nueva opción de titulación | Aparecerá sola en `catalogo.json`; añadir su pasante a la semilla y a `PASANTES` en `capturas_sin_trabajo.py`. |

Tras cualquier cambio: recompilar, revisar visualmente al menos un PDF por tipo (pasante, actor por opción, global), correr
`php tools/verificar_datos_sensibles.php` y actualizar la sección 5 de este documento.

## 7. Problemas conocidos del arnés (para no repetirlos)

- El formulario de EGEL pide marcar la convocatoria (`input[name=variante]`) antes de aceptar.
- Los documentos condicionales (Dictamen de los HH. Consejos, constancia de inglés…) bloquean la etapa hasta que el pasante pulse
  «No me aplica»; `pasante_avanza` ya lo hace.
- La pantalla ofrece «Reemplazar» aun con el archivo en revisión: el arnés recuerda lo ya subido en `ctx['subidos']` para no
  subirlo en bucle. Igual con documentos ya validados (`ctx['validados']`).
- Un `PDF` de muestra mínimo se crea en `tools/manual_titulacion/muestra.pdf` (no se versiona su contenido real).
- No usar `taskkill /IM chrome.exe` ni cerrar Python en general: cierra procesos del usuario.
- Un esperar «hasta que no haya `python.exe`» no termina nunca si hay otros Python abiertos; usa el PID o la salida del script.
- Cada opción necesita **fechas de examen propias**: el espacio y el sínodo se bloquean al enviar la solicitud, y reutilizar la misma
  fecha deja sin horarios (`agenda_pasante` busca el siguiente día hábil con horario y `capturas_trabajo.py` reparte las fechas).
- El desplazamiento de la bitácora de correos se mide en **bytes**: léela en binario (`rb`) o se pierden correos con acentos.
- Un manual sin capturas casi siempre es una opción cuyo recorrido no llegó a su fin: revisa `*_pasante_07_concluido.png`.
- Las capturas de página completa se cortan en tramos legibles (`img/cortes/`, no se versiona; lo regenera `generar_latex.py`).
- Los pies de foto salen de `img/_pies.json` (lo escribe `Sesion.foto(..., pie=...)`); sin entrada, se usa un texto por tema.
