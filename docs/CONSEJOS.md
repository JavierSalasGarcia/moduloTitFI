# Consejos: actas y dictámenes de los HH. Consejos Académico y de Gobierno

Módulo `app/Modules/Consejos/` (clave `consejos`, endpoint base **`/consejos`**). En el menú vive en la sección **CONSTANCIAS**: ahí se
ven «Comisiones» (cada profesor), «Consejos» y «Buscar en las actas» (quien gestiona). La página de Constancias muestra además la
tarjeta **Comisiones** (y **Consejos** para quien gestiona).

Reemplaza el flujo manual de la carpeta `consejos/` (acta en Word → `dictamenes.py` → oficios). Referencias de formato:
`consejos/ActaGobiernoNov2026.docx` (acta) y `consejos/plantilla_dictamen.docx` (oficio; copia en `app/Modules/Consejos/plantillas/`).

## Flujo de trabajo

1. **Nueva reunión** (`/consejos/nueva`). Es el par Consejo Académico + Consejo de Gobierno de un mes: mesa (presidente, secretario),
   consejeros, fecha, hora, lugar y convocatoria de cada Consejo, y las actas anteriores que se aprueban. Parte de los datos de la
   reunión anterior.
2. **Casos** (`P#` profesores, `A#` alumnos, `AG#` asuntos generales). La *solicitud* y los *documentos y/o antecedentes* se escriben en un
   lienzo: **se copia la tabla de Word y se pega** (Ctrl+V); se conservan filas, columnas y celdas combinadas. El lienzo también tiene
   negrita, cursiva, listas y botones para tablas nuevas, +/− fila y columna. Se eligen a mano los **profesores mencionados**
   (alimentan «Comisiones»; nunca se deducen por el nombre, ver `docs/VINCULACION_DE_CUENTAS.md`).
3. **Sesión del Consejo Académico** (`/consejos/reunion/{id}/sesion/academico`). Pantalla para proyectar: un caso a la vez
   (profesores → alumnos → generales), con la opción **Se avala / No se avala** y el cuadro del acuerdo (al elegir la opción se
   propone la redacción habitual: «El H. Consejo Académico avala …»). Cada cambio se guarda solo. «Terminar la sesión» asienta la
   fecha y hora de terminación; se puede reabrir para corregir.
4. **Sesión del Consejo de Gobierno** (en otra reunión; solo después de terminar el Académico). Se proyecta el caso con el *acuerdo del
   Consejo Académico* y se captura el acuerdo del Consejo de Gobierno, **Se aprueba / No se aprueba el acuerdo del Académico** y una
   **nota adicional** (se agrega al acuerdo del acta y del dictamen).
5. **Actas en Word** (`/consejos/reunion/{id}/acta/{academico|gobierno}`), con el formato del acta de ejemplo y las observaciones como comentarios. Una vez firmadas y
   selladas se **sube el PDF escaneado** de cada una.
6. **Dictámenes** (`/consejos/reunion/{id}/dictamenes`). Un oficio por caso (o por alumno si el caso trae una tabla con columnas
   *Nombre* y *Cuenta*). Redacta destinatario, cuerpo y acuerdo con las reglas de `consejos/dictamenes.py`; lo dudoso queda marcado
   «Revisar» (amarillo). Número de oficio `ACAD/FI/0447/2026` asignado en transacción (no se repite aunque dos personas generen a la
   vez; se puede indicar el número inicial). Si cambia un acuerdo, el oficio queda **Desactualizado** y se regenera. Se descarga un
   Word con un oficio por página sobre la hoja membretada, y después se sube el escaneado firmado de cada uno.
7. **Comisiones** (`/consejos/comisiones`). Cada profesor ve los asuntos en que se le mencionó, **cuando ya terminó la sesión del Consejo
   de Gobierno**, con el acuerdo, su dictamen escaneado y un botón que descarga un ZIP con el acta firmada del Consejo de Gobierno y
   la del Consejo Académico asociada.
8. **Búsqueda** (`/consejos/buscar`). Solicitudes, antecedentes y acuerdos de todas las reuniones. Comodines: `*` (cualquier cantidad
   de letras, `sabátic*`) y `?` (una letra, `Garc?a`). No distingue mayúsculas ni acentos. Modo «la frase tal cual» o «todas las
   palabras» (en cualquier orden); se puede limitar a solicitudes, a acuerdos, a una reunión o a un tipo de caso.

## Importar, observaciones y avisos

- **Importar casos de un acta de Word** (`POST /consejos/reunion/{id}/importar`): **no se crean reuniones desde un acta**; la reunión
  se crea con el formulario («Nueva reunión») y después, dentro de ella, se pueden importar los casos de un acta de Word: se leen la
  clave, solicitud, antecedentes (tablas con celdas combinadas incluidas) y acuerdos (`Services/ActaWord`), y se completan la mesa y las
  actas anteriores que falten. **Un caso que ya existe no se duplica ni se pisa**, solo se completa lo que le falta (`Services/Importador`).
- **Importar un Excel** (.xlsx, misma ruta de la reunión): una fila por caso con las columnas Clave, Solicitud, Documentos y/o antecedentes, Acuerdo del
  Consejo Académico, Acuerdo del Consejo de Gobierno y Observación. La plantilla se baja de `GET /consejos/plantilla-casos`. Las tablas no
  caben en una celda: se agregan después con el lienzo (pegando desde Word).
- **Observaciones**: cada caso tiene un campo opcional «Observación de la Subdirección» (no es parte del acuerdo). En el acta en Word sale
  como **comentario** anclado a la clave del caso, con el nombre de quien lo escribió como autor. En la pantalla de sesión se ve, pero no
  se proyecta.
- **Avisos por correo** (`Services/Avisar`): al subir el escaneado de un dictamen se avisa a los profesores mencionados en el caso (correo
  y bandeja de Notificaciones) y, si se escribe, a otro correo; hay también «Avisar de nuevo» en el oficio y «Avisar a los profesores
  mencionados» en la reunión (cuando terminó el Consejo de Gobierno). Usa el mismo envío que Constancias: el servicio de correo de la
  plataforma no admite adjuntos, así que el correo lleva un enlace a «Comisiones» (pide sesión) y el PDF se descarga ahí.

## Sesiones: casos, redacción de acuerdos y proyección

**Los mismos casos para los dos Consejos.** Los casos se crean al preparar la reunión (desde la página de la reunión, antes del Consejo Académico) y son un
solo conjunto: primero los ve el Consejo Académico y después, en otra sesión, el de Gobierno (que ve el caso con el acuerdo del Académico y redacta el suyo).
**Agregar un caso durante la sesión proyectada es la excepción** (p. ej. un asunto general que surge en Gobierno): está recogido en «Caso excepcional…» del
menú lateral de la sesión (no aparece al proyectar) y, al guardar, se regresa a la sesión en ese caso. Un caso agregado desde la sesión de Gobierno, o creado
cuando el Académico ya terminó, queda con `origen = 'gobierno'` (migración 064): no sale en la sesión ni en el acta del Académico, y en el acta de Gobierno no lleva
fila del Académico. Al importar, un caso que solo trae acuerdo de Gobierno también nace así.

**Redacción automática del acuerdo.** Al elegir la decisión el acuerdo cambia a la frase de las actas y se completa a mano:

| Consejo | Decisión | Frase |
|---|---|---|
| Académico | Se avala | «El H. Consejo Académico otorga el aval a / para …» (fórmula de las actas de 2026) repitiendo lo que se solicita, tomado del caso sin el «<Persona>, <cargo>, solicita» del principio: «solicita el aval a las comisiones…» → «otorga el aval a las comisiones…»; «solicita autorización para que sea depurada…» → «otorga el aval para que sea depurada…»; si no encaja, «otorga el aval a la solicitud de …» |
| Académico | Con condición / se turna | «El H. Consejo Académico solicita que » + lo que se pide (revisar, esperar a que se autoricen los exámenes, enviar el caso a la Comisión de Responsabilidades y Sanciones…). Hay frases frecuentes en el botón de abajo. Es un tercer desenlace, distinto de avalar y de no avalar (migración 065) |
| Académico | No se avala | «El H. Consejo Académico no otorga el aval …» + « en virtud de que » + el motivo. Una improcedencia se redacta con la frase frecuente «resuelve que, con base en …» |
| Gobierno | Se aprueba | «Se aprueba el acuerdo del H. Consejo Académico.» (+ lo que se precise); la nota adicional se agrega al acuerdo final |
| Gobierno | No se aprueba | «No se aprueba el aval del H. Consejo Académico en virtud de que » + el motivo |

Lo que se escribió para una decisión se recuerda: si se cambia de decisión y se regresa, reaparece tal cual. Hay frases frecuentes («Se da por enterado el H.
Consejo …», «No es competencia del H. Consejo de Gobierno »).

El Consejo de Gobierno se puede conducir y proyectar **aunque el Académico no esté marcado como terminado** (p. ej. cuando los casos se importaron de un acta); en ese caso la página de la reunión solo lo advierte.

**Guardar y Deshacer.** Los cambios no se guardan solos: **💾 Guardar** (o Ctrl+S) los guarda y **↶ Deshacer** vuelve a lo último guardado en ese caso. Al pasar a
otro caso se guarda el actual; si no se pudo guardar, no se cambia de caso. Un Consejo cuya sesión terminó no se edita (se reabre desde la reunión).

**Proyectar.** En la página de la reunión, cada Consejo tiene **🖥️ Proyectar** (o **🖥️ Iniciar y proyectar**): abre la sesión en modo proyección
(`/consejos/reunion/{id}/sesion/{consejo}?proyectar=1`). La pantalla arranca directamente con el caso —sin menús ni encabezados— en horizontal: a la izquierda la
solicitud y los antecedentes (con sus tablas) y a la derecha el acuerdo del Académico (en Gobierno), la decisión y el acuerdo que se redacta. El tamaño del texto se
adapta a la pantalla hasta que el caso quepa sin desplazarse. Con las flechas ← → se cambia de caso (fuera de los cuadros de texto); Esc o «Salir» cierra la
proyección. La pantalla completa la pide el botón «Modo proyección» de la sesión (el navegador exige un clic). La observación de la Subdirección no se proyecta.
Cada sección (solicitud, antecedentes, acuerdo del Académico y acuerdo que se redacta) tiene botones **−** y **+** (en una franja arriba a la derecha, fuera de la caja de texto) para reducir o ampliar solo su texto, y la barra de abajo tiene **▤ 1 columna** para mostrar todo en una sola columna (con tablas anchas); en ese modo el caso se desplaza en vez de reducir la letra. El tamaño y la columna se recuerdan en el navegador.

## Notificar dictamen (enlace con token)

Con el dictamen escaneado subido, el oficio tiene el botón **«Notificar dictamen»** (`POST /consejos/oficio/{id}/avisar`; también se puede
marcar al subir el PDF). Envía un correo —y deja la notificación en la bandeja— a los profesores mencionados en el caso y a las personas o
correos que se agreguen con el selector. **Cada destinatario recibe su propio enlace** `GET /consejos/dictamen/{token}`:

- El token (256 bits) solo viaja en el correo; en `consejos_enlaces` (migración 063) se guarda su SHA-256, el correo al que se emitió y su
  vencimiento (**60 días**, `Services/Enlaces`). Notificar de nuevo crea otro enlace; los anteriores siguen vigentes hasta vencer.
- La página (`EnlaceController`, pública) **le dice a la persona qué hacer**: si ya hay una cuenta con ese correo, «Iniciar sesión» (al entrar
  regresa al enlace, `Auth::recordarDestino`); si no, «Crear mi cuenta» con ese correo (`registro`, o `registro/egresado` si no es
  institucional) y luego volver a abrir el enlace. El correo se muestra enmascarado.
- El token **no basta**: para ver o descargar el PDF hay que iniciar sesión con una cuenta que tenga ese correo (las cuentas se crean con
  correo verificado). Con otra cuenta se pide cerrar sesión. `ReunionController::puedeVer()` acepta esa combinación (`Enlaces::permiteAlUsuario`)
  y el PDF sale por el mismo `GET /consejos/archivo/{id}` de siempre (ver o `?descargar=1`).
- Un enlace inválido o vencido muestra «no es válido o ya venció»; se pide a la Subdirección que vuelva a notificar. Cada apertura queda en
  la auditoría (`consejos::enlace`).

## Personas: SelectorPersonas y menciones con @

Todos los cuadros de Consejos donde se capturan personas usan el componente **`SelectorPersonas`** (`docs/SELECTOR_DE_PERSONAS.md`): se
teclea parte del nombre o del correo, salen las coincidencias, al elegir una queda como elemento con una «×» y el cuadro sigue libre para la
siguiente. Se usa en presidente y secretario (una persona), en los consejeros presentes de cada Consejo (se pueden agregar nombres sin cuenta
escribiéndolos completos y pulsando Enter), en los profesores mencionados de cada caso y en el «otro destinatario» de los avisos del oficio.

**Menciones en el cuerpo del caso.** En la solicitud y en los antecedentes se escribe `@` y parte del nombre (o el botón «@ Mencionar»):
al elegir a la persona su nombre queda en el texto y **se agrega sola a «Profesores mencionados en el caso»**, sin buscarla otra vez.
Quitar a alguien de esa lista no lo borra del texto del caso. Lo que decide qué ve cada profesor en Comisiones sigue siendo esa lista
(`consejos_asunto_profesores`); el sistema nunca deduce personas por coincidencia de nombre.

## Casos cerrados

Una reunión queda **cerrada** cuando se sube el acta escaneada, firmada y sellada de cualquiera de los dos Consejos
(`ConsejosBase::cerrada()`). Con eso sus casos ya no se editan, ni se eliminan, ni se agregan o importan casos: en la reunión y en la
búsqueda el caso se abre en modo consulta (`views/asunto_ver.php`). Si hay que corregir algo, se retira primero el acta escaneada
(«Quitar» en la reunión) y la reunión se reabre.

## Acceso

- **Gestionan**: administración, el Subdirector Académico, su asistente (`SubdireccionFirma::esSubdirector/esAsistente`) y quien tenga
  la concesión con vigencia `consejos.gestor` (`docs/CONCESIONES_DE_ACCESO.md`). Ver `ConsejosAccess::esGestor`.
- **Profesores** (y cualquier cuenta no alumna): solo «Comisiones», con lo que les corresponde. Los archivos (`/consejos/archivo/{id}`)
  se sirven únicamente a gestores y a las personas mencionadas, y solo cuando la sesión de Gobierno terminó.
- Los alumnos no entran al módulo (el manifiesto no los incluye).
- Todo POST lleva CSRF (lo exige el Router); las consultas son preparadas; la salida se escapa; el HTML pegado se limpia en el cliente y
  de nuevo en el servidor (`Services/Html::limpiar`: solo p, ul/ol/li, table/tr/td/th con colspan y rowspan, b, i, u, br).
- Auditoría (`Audit::record`): reuniones, casos, acuerdos, sesiones, actas, oficios, descargas y búsquedas.

> **Privacidad.** El «acta completa asociada» que descarga un profesor incluye los casos de otras personas (también de alumnos).
> Es lo que pidió la Subdirección Académica; si cambia el criterio, basta quitar el botón de `views/comisiones.php` y la ruta
> `GET /consejos/comisiones/{id}/actas`.

## Endpoints

| Método y ruta | Acción | Quién |
|---|---|---|
| `GET /consejos` | Lista de reuniones | gestor |
| `GET /consejos/nueva`, `POST /consejos` | Crear reunión | gestor |
| `GET /consejos/reunion/{id}` | Panel de la reunión | gestor |
| `GET /consejos/reunion/{id}/editar`, `POST /consejos/reunion/{id}`, `POST …/eliminar` | Datos / eliminar (solo si no ha iniciado) | gestor |
| `GET /consejos/reunion/{id}/asunto/nuevo?grupo=`, `POST …/asunto` | Nuevo caso | gestor |
| `GET /consejos/asunto/{id}/editar`, `POST /consejos/asunto/{id}`, `POST …/eliminar` | Editar / eliminar caso | gestor |
| `GET /consejos/personas?q=` (`&alumnos=1` incluye alumnos) | Buscador de personas (JSON) del componente `SelectorPersonas`: cada palabra escrita debe estar en el nombre o en el correo | gestor |
| `GET /consejos/reunion/{id}/sesion/{consejo}` | Pantalla de la sesión | gestor |
| `POST …/sesion/{consejo}/iniciar`, `…/terminar`, `…/reabrir` | Estado de la sesión | gestor |
| `POST /consejos/asunto/{id}/acuerdo` | Guarda el acuerdo y la decisión (JSON) | gestor |
| `GET /consejos/reunion/{id}/acta/{consejo}` | Acta en Word | gestor |
| `POST /consejos/reunion/{id}/acta/{consejo}/firmada` | Sube el acta firmada (PDF) | gestor |
| `GET /consejos/reunion/{id}/dictamenes`, `POST …/dictamenes/generar`, `GET …/dictamenes/descargar` | Oficios | gestor |
| `GET/POST /consejos/oficio/{id}`, `POST …/regenerar`, `GET …/descargar`, `POST …/escaneado` | Revisar un oficio, regenerarlo, bajarlo y subir su escaneado | gestor |
| `GET /consejos/archivo/{id}` (`?descargar=1`) | PDF firmado | gestor o persona mencionada |
| `POST /consejos/archivo/{id}/quitar` | Retira un PDF | gestor |
| `POST /consejos/reunion/{id}/importar`, `GET /consejos/plantilla-casos` | Importa casos (Word o Excel) / plantilla de Excel | gestor |
| `POST /consejos/reunion/{id}/avisar` | Aviso de comisiones a los profesores mencionados | gestor |
| `POST /consejos/oficio/{id}/avisar` | «Notificar dictamen»: correo con enlace con token a los implicados | gestor |
| `GET /consejos/dictamen/{token}` | Página del enlace del correo: inicia sesión o crea tu cuenta y ve/descarga el PDF | pública (el PDF exige sesión con el correo del enlace) |
| `GET /consejos/buscar` | Búsqueda con comodines | gestor |
| `GET /consejos/comisiones`, `GET /consejos/comisiones/{id}/actas` | Comisiones de la persona; ZIP de actas | cuenta del módulo |

## Datos

Migraciones `061_consejos.sql`, `062_consejos_observaciones_avisos.sql`, `063_consejos_enlaces.sql` y `064_consejos_origen_caso.sql` (repetibles; entran en `database/construir_actualizar.php`):

- `consejos_reuniones`: datos de la mesa y de cada Consejo, `academico_fin` y `gobierno_fin` (terminación), `estado`.
- `consejos_asuntos`: grupo, número, `solicitud_html`, `antecedentes_html`, `texto_busqueda`, `aval_academico`, `acuerdo_academico`,
  `aprob_gobierno`, `acuerdo_gobierno`, `nota_gobierno`, `version_acuerdo` (sube con cada cambio).
- `consejos_asuntos.observacion` y `observacion_autor` (migración 062): comentario de Word.
- `consejos_oficios.avisado_en` (062): último aviso por correo.
- `consejos_asuntos.origen` (064): Consejo ante el que se presentó el caso (`academico` o `gobierno`).
- `consejos_enlaces` (063): token (solo su SHA-256), correo, oficio y vencimiento del enlace de «Notificar dictamen».
- `consejos_asunto_profesores`: profesores (cuentas) mencionados en el caso.
- `consejos_oficios`: oficio por caso o alumno; `version_acuerdo` menor que la del caso = desactualizado.
- `consejos_archivos`: PDF escaneados (actas y dictámenes) en `storage/consejos/<reunión>/` (nunca versionado).
- Ajustes (`ajustes`): `consejos_oficio_prefijo`, `consejos_oficio_digitos`, `consejos_oficio_ultimo`. Para fijar el consecutivo:
  `python tools/subir/subir.py php -e 'return \App\Core\Database::ejecutar("UPDATE ajustes SET valor = ? WHERE clave = ?", ["446", "consejos_oficio_ultimo"]);'`
  (el siguiente será el 447) o escribir el «primer número» al generar.

Repositorio público: solo estructura. Los casos, nombres y escaneados viven en la base y en `storage/`.

## Piezas del código

- `Services/Html.php`: limpieza del HTML pegado y texto plano. `Services/Word.php`: acta y utilidades de WordprocessingML (sin
  librerías; usa `ZipArchive`). `Services/Dictamenes.php`: reglas de redacción, número de oficio y Word sobre la plantilla.
  `Services/Busqueda.php`: comodines → `LIKE`. `Services/ActaWord.php`, `Services/ExcelCasos.php` e `Services/Importador.php`: lectura de actas y hojas. `Services/Avisar.php`: avisos. `Services/Texto.php`: fechas y normalización.
- `ConsejosAccess.php`: quién gestiona. Controladores: `ReunionController`, `AsuntoController`, `SesionController`,
  `OficioController`, `BusquedaController`, `ComisionController`.

## Pruebas

```powershell
C:\xampp\php\php.exe tools/prueba_consejos.php <carpeta_de_salida>
```

Contra la base local con la migración 061: captura casos con tablas, genera actas (con comentarios) y oficios en Word, vuelve a leer el acta generada, importa un Excel (quedan en la carpeta indicada, para
abrirlos), busca con comodines y borra lo que creó. En Word conviene abrir `acta_gobierno.docx` y `dictamenes.docx`.

## Pendiente

**Firma electrónica** de actas y oficios: todavía no la autoriza el Consejo. Mientras tanto se suben los escaneados firmados y sellados.
Cuando se autorice, la firma entra en `Services/Dictamenes` (oficios) y `ReunionController` (actas) con el mismo esquema de firmas de
`Constancias` y `Posgrado`; el resto del flujo no cambia.

## Hora y convocatorias

En la reunión se captura una sola hora por Consejo: la de la **segunda convocatoria**. En una sesión **ordinaria** el acta anota además la primera convocatoria media hora antes («A LAS 08:30 HORAS EN PRIMERA CONVOCATORIA Y A LAS 09:00 HORAS EN SEGUNDA CONVOCATORIA»). En una **extraordinaria** solo hay una convocatoria («A LAS 09:00 HORAS EN ÚNICA CONVOCATORIA»). Al importar un acta que dice «primera convocatoria», la hora se corrige sumando 30 minutos.

## Plantillas de solicitud por tipo de trámite

Al crear un caso, «Tipo de trámite» rellena la solicitud (y los antecedentes y, si aplica, una tabla con encabezados) con la redacción de las actas de 2026. Los datos que cambian van entre [corchetes] y el formulario avisa si alguno queda sin llenar. Los tipos viven en `Services/Plantillas.php`; el selector muestra primero los del apartado del caso (profesor, alumno, general) y luego los de otros apartados. «Escribir libremente» sigue disponible.

**Estructura de toda solicitud:** (1) sujeto y calidad con que actúa, (2) «solicita / presenta a los H.H. Consejos Académico y de Gobierno», (3) acción solicitada («el aval a / para», «autorización para», «la aprobación de», «el reconocimiento de»), (4) objeto del trámite, (5) finalidad o fundamento («con la finalidad de…», «con fundamento en…», «en virtud de que…»).

**Regla de simetría:** el acuerdo del Consejo Académico repite el núcleo de la solicitud a partir de la acción pedida («otorga el aval a / para …»), por eso conviene redactar la solicitud con esa estructura desde el origen.

Tipos incluidos: profesores (año sabático: goce, informe y dictamen; estancia o permiso; finiquito técnico; salida de capítulo estudiantil), alumnos (homologación preliminar y definitiva, depuración de trayectoria, exención de servicio social, cambio de carrera o plantel, baja extemporánea, validación en el SICDE, titulación, otro) y generales (comisiones de programas de estudio o guías, de examen especial o por competencias, de evaluación de profesores, de admisión y de tesis o proyecto terminal de posgrado; movimientos de plantilla; visita académica y transporte; diplomados; fe de erratas; otro). También hay plantillas de calendarios escolares y de evaluación, presea o postulación de aspirantes, reconocimientos administrativos, informes para conocimiento, plan de desarrollo, y las propias del Consejo de Gobierno (Nota Laudatoria, comisiones de Glosa o de Procesos Electorales, auscultación y calificación de la elección de Director), que se marcan «solo Consejo de Gobierno». Solo se agregan plantillas con redacción vista en las actas.

Las actas no dicen a quién se notifica un acuerdo: los destinatarios del dictamen se capturan siempre a mano (ver Notificar dictamen).

## Manual de captura para los departamentos

`/consejos/manual` (botón «Manual de captura» en Consejos) explica el recorrido de un caso, cómo capturarlo, la estructura de una solicitud y, por cada trámite, los datos por llenar y cómo queda la solicitud. Se arma con el mismo catálogo de `Services/Plantillas.php`, así que al agregar o cambiar una plantilla el manual se actualiza solo. Se puede imprimir (abre todos los trámites).

## Solicitudes de profesores y coordinadores (Vo. Bo. de la coordinación)

Por acuerdo del H. Consejo de Gobierno del 19 de noviembre de 2026 (caso AG28 de la reunión de noviembre), **toda solicitud a los HH. Consejos Académico y de Gobierno lleva el Vo. Bo. o de enterado de la Coordinación de Docencia respectiva o de la Coordinación de Estudios Avanzados, según sea el caso**. La plataforma lo aplica así:

- **Dónde:** Constancias → «Solicitudes a Consejos» (`/consejos/solicitudes`), para administración, titulares, operativos, coordinadores y profesores. Cada persona ve solo las suyas; la coordinación a la que se dirigen y quienes gestionan los Consejos, también.
- **Flujo:** *borrador → enviada → (observaciones y correcciones, las rondas que hagan falta) → con Vo. Bo. = en la cartera del Consejo → en el acta*. Quien solicita elige el tipo de trámite (las plantillas de `Services/Plantillas.php`), redacta, elige la coordinación (se ofrecen las de Docencia de cada licenciatura y la de Estudios Avanzados, con su titular vigente; no puede elegirse a sí misma) y envía. La coordinación recibe un aviso y da el **Vo. Bo.** o el **de enterado**, o hace **observaciones y la regresa** a quien la redactó.
- **Observaciones por rondas:** la coordinación escribe sus observaciones y la solicitud regresa a quien la creó (aviso en bandeja y por correo); la persona la edita, anota opcionalmente qué corrigió y la reenvía, y la coordinación vuelve a revisarla. Puede haber varias rondas: cada observación y cada respuesta queda en el historial de la solicitud (`consejos_solicitud_obs`, migración 067), con la ronda, el autor y la fecha. La solicitud no avanza hasta que la coordinación da el Vo. Bo.
- **Cartera para el Consejo:** con el Vo. Bo. la solicitud pasa a la **cartera de casos** y llega un aviso (bandeja y correo) al **secretario académico (Subdirector Académico) y a su asistente** (`Solicitudes::destinatariosCartera`, de `Autoridades::subdirector` y `tit_asistentes`). La cartera se ve en «Solicitudes a Consejos» (quienes gestionan Consejos) y en cada reunión en preparación; de ahí se llevan **una por una o toda la cartera** a la reunión como casos (apartado P, A o AG según el trámite), con una nota «Vo. Bo.: nombre, coordinación» en los antecedentes y con la persona que solicita mencionada si es profesor. Nada entra a una reunión sin que el secretario o su asistente lo incorporen. En la lista de casos de la reunión se marca de dónde viene («Solicitud SC-… · firmada / sin escaneado»).
- **Oficio en hoja membretada:** al enviar y al dar el Vo. Bo. se genera el PDF del oficio (folio `SC-AAAA-NNNN`, solicitud, antecedentes, tablas, el fundamento del acuerdo AG28 y los bloques de firma de quien solicita y de la coordinación) con `Services/SolicitudOficio.php`, sobre la misma hoja membretada de Titulación.
- **Firma autógrafa (la que rige mientras no esté autorizada la firma electrónica):** la revisión y el Vo. Bo. se hacen siempre dentro de la plataforma; la firma a mano va **después del Vo. Bo. o del de enterado**: se imprime el oficio, firman quien solicita y la coordinación y se **sube el escaneado** en la solicitud (solo después del Vo. Bo.; lo puede subir quien solicita, la coordinación o quien gestiona) para incluirlo en el acta. Un caso que vino de una solicitud sin escaneado se marca «sin escaneado».
- **Mismo lienzo:** la solicitud se redacta con el mismo lienzo editable que usa la Subdirección para los casos (pegado de Word, listas, tablas, menciones con @). Al incorporarse, el HTML pasa tal cual al caso y la Subdirección o su asistente lo editan con ese mismo lienzo (`views/_editor_js.php`, compartido por los dos formularios); el oficio original no cambia.
- **Oficio original en la proyección:** en la sesión (y en la proyección) los casos que vienen de una solicitud tienen el botón **📄 Oficio**, que abre el oficio original (o el escaneado firmado, si ya se subió) sobre la pantalla; `Esc` o «Cerrar» regresan al caso sin salir de la proyección.
- **Firma interna:** si se configura así, quien solicita y la coordinación firman dentro de la plataforma y el oficio recibe el sello de conformidad con la huella del documento (el mismo de Titulación, `FirmaElectronica::sellar`; registro interno, no firma electrónica). No exige escaneado.

### Configuración de firmas

`/consejos/firmas` («Firmas» en la pantalla de Consejos; solo quien gestiona) define, por documento, el modo de firma: **autógrafa**, **firma interna** o **electrónica**. Se guarda en `ajustes` (`consejos_firma_<documento>`). Hoy operan «Solicitud: firma de quien solicita» y «Solicitud: Vo. Bo. de la coordinación»; el oficio de dictamen y las actas se muestran como autógrafas y se activarán cuando se firmen dentro de la plataforma. Lo configurado como **electrónica** se trata como autógrafa hasta que los Consejos la autoricen (casilla «ya autorizaron la firma electrónica», `consejos_firma_electronica`) **y** la firma PAdES de la plataforma esté habilitada (`firma_pki.enabled`); mientras no, la regla vigente es imprimir con el Vo. Bo. y subir el escaneado con firma autógrafa. Por omisión todo es autógrafo.

Datos: `consejos_solicitudes` (migración 066) y `consejos_asuntos.solicitud_id`. Archivos en `storage/consejos/solicitudes/<id>/` (nunca se versionan).
