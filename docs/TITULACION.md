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

## Firma electrónica: mientras no la aprueben los Consejos

Mientras los HH. Consejos Académico y de Gobierno no aprueben la firma electrónica, los sellos que estampa la plataforma se rotulan **«CONFORMIDAD»** con la leyenda *Registro interno*: sirven como evidencia de control interno (quién dio su visto bueno, cuándo y la huella SHA-256 del archivo), no como firma con validez legal. Los oficios y constancias que deban surtir efectos legales se firman en autógrafo. La **firma automática programada** (piloto) debe mantenerse apagada. Al aprobarse la firma electrónica, basta fijar el ajuste `titulacion_rotulo_firma` en `firma` para volver al rótulo «FIRMA ELECTRÓNICA».

## Asesor y coasesor del trabajo

Las constancias de director y codirector salen de `datos.protocolo` del trámite. Una solicitud de cambio de asesor o de coasesoría solo genera el formato; cuando el Comité o los Consejos la autorizan, el personal la registra en el expediente (panel «Asesor y coasesor»). Si el resultado ya estaba registrado, las constancias de director y codirector se rehacen sobre el mismo archivo y vuelven a firma.

## Registros del departamento (Excel 2015-2026)

La migración `040_titulacion_recientes.sql` solo crea las tablas. Lo que el departamento llevaba en Excel (carpeta `baseDatosTit/recientes`, fuera de git) se carga con `database/datos_privados/040_datos_recientes.sql`, que también queda fuera de git porque el repositorio es público y trae nombres, cuentas, correos y teléfonos de estudiantes: se aplica a mano con `mysql` después de la migración. Ambos archivos los genera `python tools/importar_titulacion_recientes.py` (requiere `openpyxl`) y la carga es repetible: cada tabla tiene una huella o clave única y se usa `INSERT IGNORE`; en `tit_historico` solo se agregan actas que no existen y se llenan vacíos, nunca se sobrescribe lo capturado.

| Excel | Destino |
|---|---|
| Control de libros de licenciaturas (actas) | `tit_historico` (actas nuevas y vacíos) y `tit_actas_aplazamiento` |
| Solicitud EGEL, aprovechamiento y créditos (2015-2026, con las hojas por lote) | `tit_solicitudes_historicas` (las filas repetidas entre hojas se funden en una) |
| Recepción de protocolo y hoja «Protocolos» de Oficios 2026 | `tit_protocolos_recibidos` |
| Control de oficios y Oficios 2026 | `tit_oficios_control` (tipo y persona deducidos del texto) |
| Artículo 5 | `tit_articulo5` |

Consulta: menú **Constancias y oficios → Registros del departamento** (`titulacion/constancias/registros`). Al buscar un expediente en *Registrar o completar un expediente* aparece un recuadro con los antecedentes de esa persona en estos registros.

Fechas: Excel invirtió día y mes en muchas celdas (2026-05-11 era el 5 de noviembre) y repitió años al arrastrar. El importador las corrige por el orden de las filas; las que no pudo decidir se cargan tal cual con `fecha_revisar = 1` (filas en amarillo en la consulta). Las fechas de actas que ya estaban en el historial no se tocan, salvo las que quedaron en el futuro por un año arrastrado (solo cambia el año, y solo si el valor sigue siendo el original). Las demás sospechas quedan en `baseDatosTit/fechas_libro_por_revisar.csv` para revisión humana. La licenciatura de lo recibido sin ella se completa por cuenta con el historial; lo que no tiene cuenta conocida queda sin licenciatura.

## Verificación final del expediente y envío a Servicios Escolares

Requiere `database/migrations/045_titulacion_expediente_final.sql`. Cuando la evaluación profesional concluye con resultado favorable, el personal de Titulación (menú **Titulación → Expedientes a Servicios Escolares**, `titulacion/expedientes`) verifica el expediente con el «Formato de integración de expediente» de la opción de titulación (aprovechamiento, EGEL, créditos, artículo y trabajos escritos; los renglones están en `Services/ExpedienteFinal::renglones`).

- Los documentos que la plataforma ya validó se marcan solos; lo que se entrega en físico (fotografías, acta, dictámenes que no se suben) lo confirma la persona responsable, y los renglones «sólo si aplica» pueden quedar como «No aplica». Con todo cubierto se firma la constancia de verificación («Hago constar…») y el trámite pasa a **verificado**.
- **Exportar** genera una carpeta comprimida (uno o varios expedientes) con los PDF en el orden del formato, el formato llenado, el formato de revisión y un índice con SHA-256; los expedientes pasan a **enviado** y queda un lote en la bitácora. Un verificado puede reabrirse mientras no se envíe.
- El pasante ve en su ruta los pasos «Verificación final» y «Envío a Servicios Escolares».

### Cuentas y recorrido de prueba

`php tools/titulacion_pruebas_semilla.php` crea (solo en bases locales) una cuenta por actor y un pasante por opción; escribe las contraseñas en `cred_titulacion_pruebas.txt` (ignorado por git). `tools/manual_titulacion/` contiene el arnés Playwright que recorre el flujo y toma las capturas para los manuales.

---

# Titulación de Posgrado y navegación simplificada (iniciado el 16 de noviembre de 2026)

Esta sección es la memoria de continuidad del trabajo: léela completa antes de retomarlo en otra sesión.

## Encargo y decisiones tomadas

El Subdirector Académico pidió: (1) un módulo de titulación de posgrado basado en `formulariosPosgrado/` (carpeta local, fuera de git) con los oficios en hoja membretada como en licenciatura; (2) un menú principal simple; (3) que el trámite de licenciatura deje de intimidar al mostrar tantos pasos; (4) presentación atractiva e intuitiva. Decisiones confirmadas con él:

- **Alcance**: flujo completo de evaluación de grado, de los requisitos a las constancias posteriores al examen.
- **Quién captura**: el **egresado**. Él captura sus datos, la propuesta de sínodo, fecha y hora; la plataforma genera los formatos para que el director de tesis y el sínodo firmen **en autógrafo**, y el egresado sube los firmados. El personal de Apoyo a Grado (Coordinación de Estudios Avanzados) valida, coteja y emite los oficios. Director y sinodales no necesitan cuenta.
- **Publicación**: directo a producción por **FTP** (`ftp.fingenieria.mx`, credenciales en `cred.txt`, fuera de git). Como no pasa por `tools/desplegar.sh`, el marcador `ultimo_commit` del servidor queda atrás: el siguiente `desplegar.sh` verá estos archivos como cambiados en el servidor y habrá que usar `--forzar` una vez, después de confirmar que el commit local contiene lo mismo que se subió.
- **Menú**: corto, con páginas de entrada. Aplica a todo el sitio.

## Arquitectura

**Módulo nuevo `app/Modules/Posgrado/`** (clave `posgrado`, rutas `posgrado/...`, tablas `pos_*`, migración `048_posgrado_evaluacion_grado.sql`). Es autocontenido conforme a `docs/GUIA_MODULOS.md`; de Titulación solo reutiliza servicios de presentación: `Services\Hoja` (hoja membretada, folio y QR de verificación), `Services\Calendario` (días hábiles) y `App\Core\LemaAnual`. No se mezcló con el motor de licenciatura (`Catalogo`/`Flujo`) porque el proceso, los actores y la dependencia que atiende son distintos. La ruta antigua `titulacion/posgrado` redirige a `posgrado`.

El trámite se presenta al egresado en **cuatro momentos**, no en una lista de pasos:

1. **Reúne tus requisitos**: datos del interesado y los requisitos de `REQUISITOS EVALUACION DE GRADO CEA-FI` (los de doctorado y el art. 53 del REA solo cuando aplican; fotografías y USB son entregas físicas que marca el personal). Genera el Voto aprobatorio (R 7) y el Formato de datos del interesado (R 24). Cierra cuando Apoyo a Grado coteja los originales y emite el **Oficio de cotejo**.
2. **Propón sínodo y fecha**: cinco propietarios y dos suplentes (art. 65 del REA), fecha, hora y modelo de tesis. La fecha debe estar al menos a 10 días hábiles (se sugieren 15). Genera la **Solicitud de evaluación de grado** y el **Visto bueno del sínodo**; el egresado sube ambos firmados y el comprobante de firma electrónica vigente (SICDE) de los sinodales.
3. **Invitaciones al sínodo**: el personal confirma fecha y lugar y emite los **oficios de invitación**, el **acuse** y la **notificación al director**; el egresado sube el acuse firmado a más tardar dos días antes.
4. **Examen y cierre**: el personal registra el resultado; el egresado registra su folio del SICDE (48 horas); se emiten las **constancias** de revisores y de director/codirector/tutor, la de no adeudo de equipo y el **formato de integración de expediente**.

Quien firma los oficios (Coordinación de Estudios Avanzados) y los datos de contacto de Apoyo a Grado se leen de `ajustes` (claves `posgrado_*`), editables en **Posgrado → Configuración**; los costos de los recibos están en `Posgrado\Catalogo`. Personal autorizado: administradores, `posgrado.gestores` y cuentas titular/operativo/coordinador de `posgrado.departamentos` (`app/config.php`), o concesión `posgrado.personal`.

**Navegación** (`App\Core\InterfaceUi` y `app/Views/layouts/app.php`): el menú lateral muestra un enlace por grupo (Inicio, Trámites, Titulación, Agenda, Comunidad, Recursos, Administración…). Un grupo con varias entradas abre una **página de entrada** con tarjetas (`seccion/<grupo>`, módulo Portal) construida con las mismas entradas de los manifiestos, así que permisos, roles y `condicion` no cambian: solo cambia dónde se muestran. Los módulos siguen declarando su menú igual que antes.

**Licenciatura**: solo cambió la presentación (vistas de `Titulacion`), no el flujo ni las rutas. La entrada pregunta primero «¿con o sin trabajo escrito?», el trámite abre con una sola tarjeta «Tu siguiente paso» y el recorrido completo queda plegado y agrupado por etapa.

## Método de trabajo

La sesión de IA que orquesta escribe las especificaciones, reparte el trabajo en tres agentes con archivos disjuntos (Posgrado; navegación; presentación de licenciatura), revisa sus reportes y el código, prueba en un clon local (PHP 8.3 + MariaDB, sesión simulada) y publica. Los archivos de `formulariosPosgrado/` son la fuente de los textos de cada oficio.

## Cambios del 17 de noviembre de 2026 (cuarta entrega)

**Constancias en el menú principal** (módulo `app/Modules/Constancias`, migración `051_constancias.sql`, tablas `cst_folios`, `cst_solicitudes`, `cst_historial`). Grupo «Constancias» con dos categorías: «Constancias emitidas», índice por departamento emisor que reúne sin copiar datos las de Titulación (`tit_constancias`), Posgrado (`pos_generados`) y las solicitadas aquí (`Services/Indice`); cada quien ve las que recibió ya firmadas, el personal las de su departamento y administración y Subdirección todas. «Solicitar una constancia» (solo personal): destinatario («A QUIEN CORRESPONDA» por omisión), cuerpo escrito (negritas con `**`, tablas con `|`) o en .docx (párrafos y tablas; sin macros, máx. 2 MB), persona a quien se extiende y firmante elegido entre titulares y coordinadores. Hoja membretada, folio `<SIGLAS>/CONST/<n>/<AAAA>` consecutivo por departamento firmante (siglas en el ajuste `constancias_siglas` o derivadas del nombre) y QR a `constancias/verificar/{codigo}` (única ruta pública). El firmante recibe un correo con enlace a `constancias/firmar/{id}`, firma con sesión (sello «CONFORMIDAD») o devuelve con observación; firmada, avisa al solicitante y a la persona. Las entradas de constancias de Titulación se movieron a este grupo (mismas rutas). Datos de la solicitud cifrados con `Cifrado`.

**Regreso tras el login**: el Router recuerda el destino de un GET sin sesión y `Auth::destinoPermitido` acepta los enlaces de los avisos (firmas de constancias y de posgrado, pendientes y constancias de titulación, notificaciones).

**Constancias históricas**: las 61 que esperaban firma de la Subdirección se regeneraron con fecha 17/11/2026 y se firmaron a nombre del Dr. Javier Salas García (cuenta academica_fi@uaemex.mx). `Persona::tratamiento` ya no antepone «Doctorado» cuando el nombre trae «DR.» y abrevia el grado; el aviso de constancia firmada se reescribió. Los enlaces de correo se arman con `app.url_publica` sin `url()`: un script fuera del front controller los había generado bajo `/ca_dsyc/` (se corrigieron las notificaciones y se mandó aclaración a los 19 profesores). **Regla para scripts auxiliares en el servidor: no generar correos desde ellos sin revisar los enlaces.**

## Cambios del 17 de noviembre de 2026 (tercera entrega)

**Borrar, dejar en pausa y retomar una modalidad** (licenciatura y posgrado; servicios `Titulacion\Services\Abandono` y `Posgrado\Services\Abandono`, migración `050_abandono_modalidades.sql`: estado `abandonado`, `abandonado_en`, `abandonos`). Solo una modalidad activa por persona: iniciar o retomar otra pide confirmar y la actual se borra o se abandona. Se **borra definitivamente** solo si nadie más intervino (cualquier acción ajena: validación, cotejo, firma hecha, revisión o dictamen del comité, oficio, constancia, hito del personal, jurado ratificado); si hubo intervención pasa a **abandonado**: se conserva todo, las constancias siguen vigentes para quienes participaron, las firmas pendientes quedan en pausa, el personal lo ve con su filtro y no como pendiente, y el pasante lo retoma donde se quedó desde «Abandonadas». Rutas `…/tramite/{id}/dejar`, `…/tramite/{id}/retomar` y la confirmación de cambio de modalidad. La antigua acción «cancelar» ya no pone `cancelado`.

**Manuales.** Licenciatura: un PDF por actor y opción y un consolidado por actor (9 actores × 11 opciones). Posgrado: egresado, director, sinodal, Coordinación, Apoyo a Grado y supervisión, por modalidad y consolidado. 123 PDF de 4 a 39 páginas, todos menores a 4 MB, con capturas reales de datos ficticios. Se descargan sin iniciar trámite desde la página de cada opción (`titulacion/licenciatura/modalidad/{clave}`, `posgrado/modalidad/{clave}`), desde las tablas comparativas `titulacion/manuales` y `posgrado/manuales`, y cada actor ve su consolidado en sus pendientes. Rutas `titulacion/manual/{actor}/{opcion}` y `posgrado/manual/{actor}/{modalidad}` (sesión, listas blancas, adjunto `no-store`). Los PDF viven en `storage/manuales/` (no versionado; se suben por FTP). Para regenerar: `php tools/manual_titulacion/semilla_manuales.php`, `python3 tools/manual_titulacion/recorrido.py`, `python3 tools/manual_posgrado/recorrido.py`, `python3 tools/manual_titulacion/recortar_capturas.py <img>`, `bash tools/manuales_publicar.sh`, `bash tools/manual_titulacion/probar_descargas.sh` (solo en una base local; variables `MANUAL_BASE`, `MANUAL_CHROME`, `MANUAL_LOGIN`). Los `.tex` se regeneran completos: el texto se corrige en los diccionarios de cada `generar_latex.py`. Las capturas (`docs/manuales/*/img/`) no se versionan. Los manuales anteriores quedaron en `.tmp/manuales_anteriores_20261116` (local).

**Pendientes.** Las modalidades de posgrado distintas de tesis comparten el recorrido de tesis (capturas hasta el voto del director). `tools/titulacion_pruebas_semilla.php` falla en bases sin `programa_id=1`; `docs/PLAN_PRUEBAS_MANUALES_TITULACION.md` describe el arnés anterior.

## Cambios del 17 de noviembre de 2026 (segunda entrega)

**Firmas en línea en Posgrado (sin firmas autógrafas).** Igual que en licenciatura: cada firmante recibe un enlace personal por correo (`/posgrado/firma/t/{token}`, token de 32 bytes, solo su SHA-256 en `pos_firmas`, vigencia `posgrado_token_dias`, reenvío que invalida el anterior, 30 intentos por IP cada 15 min) y la plataforma estampa con `Titulacion\Services\SelloPdf` un sello «CONFORMIDAD · registro interno» mientras los Consejos no aprueben la firma electrónica. Firman: el director (voto aprobatorio y solicitud), cada sinodal (visto bueno con sus respuestas Sí/No en su renglón, y acuse de su invitación), la Coordinación de Estudios Avanzados con sesión en `posgrado/firmas` (cotejo, no adeudo, invitaciones, acuse, notificación, constancias, integración) y el egresado (formato de datos). Los externos firman sin cuenta; quien tiene cuenta con ese correo firma también desde sus pendientes. Firma por la Coordinación quien tenga el correo del ajuste `posgrado_coordinador_correo` o sea `titular` de un departamento de `posgrado.departamentos`; administración y Subdirección solo supervisan. Si cambian los datos antes de que firmen todos, el documento se regenera y sus firmas se cancelan; firmado por todos, sus datos quedan fijos (Apoyo a Grado puede anular un voto o formato de datos antes del cotejo). Servicio `Posgrado\Services\Firmas`, controlador `FirmaController`, migración `049_posgrado_firmas.sql`.

**Vigencia de la firma electrónica UAEMéx (FISE, la del acta en el SICDE de Control Escolar; distinta del sello de la plataforma).** Campo «Vigencia de mi firma electrónica UAEMéx» en Mi perfil (`usuarios.fise_vigencia`, con la indicación de verificarla en https://fise.uaemex.mx/). Cada sinodal la declara al dar su visto bueno (precargada si tiene cuenta; al firmar se actualiza su perfil). El examen solo se programa si la vigencia de cada integrante llega al menos a un mes después de la fecha de examen; si no, se le pide renovarla y corregir la fecha desde su mismo enlace (el personal puede programar con justificación escrita en bitácora). Ya no se sube comprobante en PDF. Aviso único en Notificaciones cuando falta un mes o menos (`usuarios.fise_avisada`), generado por el proveedor `pendientes` de Perfil (`Perfil\FirmaUaemex`), que se ejecuta al navegar el sitio.

**Visibilidad por actores.** Administración y Subdirector Académico (correo que resuelve `SubdireccionFirma::correoSubdirector()`) ven siempre Licenciatura y Posgrado. Licenciatura (`TitulacionAccess::menuLicenciatura` y `esActor`): pasantes de licenciatura, personal de Titulación, evaluadores, encargados de comisión, quien firma, quien tiene constancias y quien aparece como asesor, sinodal o coordinador de docencia. Posgrado (`PosgradoAccess::veTramite`, `menuGestion`, `tieneFirmas`): alumnos de posgrado, personal de Estudios Avanzados y quien tiene firmas de Posgrado con su correo (director o sinodal, que ven solo «Posgrado: firmas»). Las rutas privadas responden 403/404 a quien no es actor. `posgrado.publicado` = `true`.

**Pendiente de esta entrega.** Envío real de correo sin probar (solo el registro de depuración); reprogramación completa después de «aplazado» sin probar; una cuenta con correo aún no verificado no puede firmar desde su enlace sin cerrar sesión. Siguen vigentes los pendientes 2 a 7 de la sección siguiente, salvo el 2 (ya resuelto con la anulación por Apoyo a Grado).

## Estado al 17 de noviembre de 2026 (publicado en producción)

**Publicado.** El 17 de noviembre de 2026 se subieron por FTP 39 archivos a `fingenieria.mx` (módulo `Posgrado` completo, navegación corta, vistas de licenciatura, `app/config.php`, `ux.css`, migración 048 y `actualizar.sql`) y se aplicó la migración `048_posgrado_evaluacion_grado.sql` en la base de producción (tablas `pos_tramites`, `pos_documentos`, `pos_hitos`, `pos_eventos`, `pos_generados`). Comprobado en el servidor (PHP 8.5): sin errores de sintaxis, 36 módulos válidos, la hoja membretada genera PDF y la llave de cifrado está disponible. La copia de los 16 archivos reemplazados está en `.tmp/respaldo_servidor_antes_20261117.tgz` (local, fuera de git) por si hay que revertir. Como no se usó `tools/desplegar.sh`, el siguiente despliegue con ese guion necesitará `--forzar` una vez.

**Qué quedó.**
- Navegación: `InterfaceUi::GRUPOS` y `InterfaceUi::navegacion()` (un cálculo por petición), un enlace por grupo en `layouts/app.php`, páginas de entrada `seccion/<slug>` (`Portal\SeccionController`), migas Inicio / Grupo / Página. Las tarjetas toman su texto del campo `descripcion` de cada entrada de menú del manifiesto (nuevo, opcional) o del catálogo `consulta`. `posgrado` y `concursos` se agrupan en Titulación y Comunidad; `seguridad` y `perfil` en Recursos → «Mi cuenta». Administrador: de 58 enlaces a 9; alumno: de 28 a 8.
- Licenciatura (solo vistas): entrada con «¿Cómo quieres titularte?», tarjeta «Tu siguiente paso», barra por etapas. **Regla del Subdirector: nunca mostrar conteos de pasos («1 de 41 pasos»); solo etapas, con «Lista», «En curso» o «Más adelante».**
- Posgrado: `app/Modules/Posgrado/` (controladores `Tramite`, `Gestion`, `Configuracion`; `Catalogo` con los requisitos; `Flujo` con los cuatro momentos; `Services/Formatos` con los 12 documentos; `Services/Aviso`). `php tools/test_posgrado_formatos.php` genera los 24 PDF de prueba.

**Particularidades de Posgrado que conviene conocer.**
- `datos` de trámites e hitos se guardan cifrados con `App\Core\Cifrado`.
- Los oficios de posgrado no llevan QR de verificación (el verificador depende de `tit_tramites`); llevan folio `PG-AAAA-0000` y número de hoja en un pie propio (`Formatos`, escrito por reflexión sobre FPDF porque `Hoja` es `final`).
- Los avisos usan `Notificaciones::crear` y `HostingerMailer`; el envío real de correo no se probó.
- Quien firma y el contacto de Apoyo a Grado se editan en `posgrado/configuracion`; por omisión el nombre de contacto va vacío y no se incluyen correos personales ni celulares.

**Pendiente.**
1. Probar en producción con cuentas reales de cada papel (egresado de maestría y de doctorado, Apoyo a Grado): la verificación con sesión solo se hizo en el clon local.
2. Pantalla para que el personal corrija los datos del interesado después del cotejo (hoy quedan bloqueados).
3. Invitación al sínodo cuando la integrante es la propia Coordinadora (debe firmarla el Director).
4. Formato de pago de sinodales (`FORMATO para pago de sinodales...xlsx`).
5. Decidir si las cuentas de egresado externo (correo personal) pueden entrar a `posgrado` y a `seccion/*`: hoy `Router` y `ModuleManager` las limitan a `titulacion`, por eso su menú se pinta sin páginas de entrada.
6. En el servidor siguen 21 archivos `.bak*` en `app/Modules/Titulacion` (contrario a `docs/DESPLIEGUE.md`).
7. Revisar en móvil las pantallas de gestión y configuración de Posgrado.
