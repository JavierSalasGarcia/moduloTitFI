# Módulo Titulación

Trámite de evaluación profesional en línea. Entradas de menú: **Licenciatura**, **Posgrado** (reservada) y **Validar trámites** (personal).

## Flujo

El pasante elige entre *sin trabajo escrito* (Aprovechamiento, EGEL, Créditos en estudios avanzados) y *con trabajo escrito* (Artículo, Ensayo, Memoria, Aplicación, Autoempleo, Residencia, Tesina, Tesis). El asistente recorre etapas definidas en `Catalogo.php`: captura de datos (una sola vez), formatos llenados para firmar, subida de PDF por documento y pasos propios del departamento (autorizaciones, oficios, fechas y resultado). El personal valida cada PDF (validar o devolver con observaciones) y registra los hitos; el sistema avanza solo cuando la etapa queda completa.

## Instalación

1. Aplicar `database/migrations/017_titulacion.sql` (crea `tit_*` y el ajuste `titulacion_subdirector`).
2. Copiar `app/Modules/Titulacion/`. Los archivos subidos se guardan en `storage/titulacion/<id>/`.
3. Personal autorizado: cuentas con rol titular, operativo o coordinador en las dependencias de `titulacion.departamentos` (`app/config.php`), los correos de `titulacion.gestores` y los administradores.
4. El nombre del Subdirector Académico se captura una vez en **Ajustes** (`titulacion_subdirector`) y se imprime en todos los formatos.

## Cuando cambien los formatos oficiales

Reemplazar los archivos en `docs/licenciatura/` y ejecutar `python3 tools/titulacion_preparar.py` (requiere `pdfplumber`, `pypdf` y `pypdfium2`). Regenera `plantillas/` y `formatos/pdf_specs.json`. Los requisitos y documentos por modalidad se editan en `Catalogo.php`.

## Pruebas

`php tools/test_titulacion_formatos.php` genera todos los PDF y Word con datos de ejemplo en un directorio temporal.

## Configuración de formatos y requisitos (solo administración)

Menú **Titulación → Configurar formatos** (`titulacion/configuracion`). Requiere aplicar también `database/migrations/018_titulacion_configuracion.sql`.

- **Formatos**: se puede reemplazar la plantilla de cualquier formato, abrir el **visor de coordenadas** (arrastrar y redimensionar cada campo sobre la plantilla, colocar casillas, ajustar tamaño y alineación, vista previa con datos de ejemplo), retirar un formato para que deje de generarse o pedirse, restablecerlo al original y añadir formatos nuevos a partir de un PDF. La hoja membretada del protocolo también se reemplaza desde ahí.
- **Requisitos documentales**: por documento y por opción de titulación (o para todas) se decide si se pide, si se entrega en físico (se marca con asterisco) y si el original se coteja. Se pueden añadir documentos o entregas presenciales nuevos.
- **Cotejo**: el personal compara el original con el PDF y lo registra con su firma electrónica: *sello de cotejo* (quién, cuándo y huella SHA-256, con sello visible en el PDF) o *firma PAdES* con su certificado y segundo factor (requiere activar la PKI del módulo Firmas). La etapa no se cierra hasta que se cotejan los documentos marcados.
- Los cambios aplican de inmediato a todos los trámites. Quedan en la bitácora de la misma pantalla.
- Las plantillas reemplazadas se guardan en `storage/titulacion_formatos/`. Los PDF deben ser legibles por FPDI (PDF 1.4 sin xref comprimido); el sistema intenta convertirlos con Ghostscript si está disponible y, si no, indica cómo guardarlos.
- Si los PDF oficiales se vuelven a procesar con `tools/titulacion_preparar.py`, las coordenadas editadas en el visor no se pierden: prevalecen sobre las del archivo hasta que se use «Restablecer al original».

## Revisión de protocolos por comisiones

Requiere `database/migrations/019_titulacion_revision_protocolos.sql`. El menú de cada persona muestra solo lo que le toca (las entradas usan `condicion` en `module.json`).

1. **Pasante**: sube su protocolo firmado en la etapa «Protocolo y aprobación del tema» y ve una línea de tiempo (subido, recibido, en la comisión, en evaluación, dictamen). Solo ve el dictamen consolidado, nunca quién evaluó. Un pasante de licenciatura no ve la entrada de posgrado.
2. **Personal operativo** (*Protocolos recibidos*): recibe aviso por correo, revisa y turna cada protocolo a la comisión de la licenciatura.
3. **Encargado de comisión** (*Mi comisión*): elige a los evaluadores de su comisión; cada uno recibe un enlace personal con fecha límite (por defecto 7 días hábiles). Ve un semáforo por evaluador (verde, ámbar, rojo, azul = firmada), envía recordatorios y, cuando todos firmaron, consolida y firma el dictamen.
4. **Evaluador** (*Protocolos por evaluar*): redacta recomendaciones de forma y de fondo, indica si se aprueba y si requiere al coordinador, y firma; se genera el formato «Revisión de protocolo» (`RevisionProtocolo.pdf`, editable en el visor).
5. Si el dictamen no aprueba, el pasante corrige y sube de nuevo (2ª revisión); si aprueba, el documento queda validado y Titulación continúa con el oficio de aprobación.

**Estructura** (administración): integrantes de cada comisión por licenciatura, uno o más encargados. **Parámetros** (Configurar formatos → Parámetros de revisión): plazo en días hábiles, aviso del semáforo, nivel de firma, días no laborables y vigencia extra del enlace.

### Enlaces con token (seguridad)

El correo del evaluador lleva un enlace personal (`/titulacion/revision/t/<token>`). El token es aleatorio de 256 bits, se guarda solo su huella SHA-256, abre únicamente esa asignación y vence a la fecha límite más la vigencia extra; un recordatorio lo reemplaza y el anterior deja de funcionar. Con el enlace se puede leer el protocolo y guardar borradores sin cuenta, y un banner (no una notificación del navegador) indica que para firmar hace falta una cuenta verificada con ese mismo correo; si ya hay sesión con ese correo, el enlace redirige a la vista normal. Quienes asignan, turnan o dictaminan siempre inician sesión. Los intentos con tokens inválidos tienen límite por IP y quedan en la bitácora de auditoría.

Los avisos usan el correo de la plataforma (`correo.api_token` en `config.local.php`); sin ese token no se envían (en modo `debug` se registran en `storage/logs/titulacion-correos.log`).

## Captura transitoria (hasta la inauguración)

Requiere `database/migrations/035_captura_transitoria.sql` (incluida en `actualizar.sql`). Menú **Titulación → Trámites → Captura transitoria** (personal de Titulación, Subdirección y Jefatura). Cada ficha guarda un trámite atendido en papel (tema aprobado, prórroga, afinidad CEA, trabajo escrito en revisión): alumno, cuenta, licenciatura, modalidad, título, asesor y coasesor, revisores, fechas, observaciones y si hay datos por confirmar. Una ficha está completa con nombre, cuenta, licenciatura y modalidad; con trabajo escrito, además, título, asesor y alguna fecha de aprobación, dictamen o examen; por créditos, posgrado y fecha de afinidad. Se exporta a CSV.

La migración carga lo emitido hasta el 20/10/2026 («EvaluacionProfesional.docx»). Los alumnos que ya presentaron examen se enlazan a su renglón del libro de actas (`tit_historico`) y solo se llenan sus datos vacíos; ahí se generan sus constancias. Con «Pasar al historial» se hace lo mismo con los que examinen después.

Al inaugurar: **Cerrar captura** (Subdirección, Jefatura o administración) la deja en solo lectura (ajuste `titulacion_captura_cerrada`). En el expediente de cada trámite en línea aparece la ficha con la misma cuenta y el botón **Vincular y precargar**, que llena título, asesor y coasesor no capturados y deja en la bitácora las fechas de lo hecho en papel; el personal registra cada etapa ya cumplida con esas fechas.
