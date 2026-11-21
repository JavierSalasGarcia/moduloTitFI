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
5. **Actas en Word** (`/consejos/reunion/{id}/acta/{academico|gobierno}`), con el formato del acta de ejemplo. Una vez firmadas y
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
| `GET /consejos/personas?q=` | Buscador de personas (JSON) para mencionarlas | gestor |
| `GET /consejos/reunion/{id}/sesion/{consejo}` | Pantalla de la sesión | gestor |
| `POST …/sesion/{consejo}/iniciar`, `…/terminar`, `…/reabrir` | Estado de la sesión | gestor |
| `POST /consejos/asunto/{id}/acuerdo` | Guarda el acuerdo y la decisión (JSON) | gestor |
| `GET /consejos/reunion/{id}/acta/{consejo}` | Acta en Word | gestor |
| `POST /consejos/reunion/{id}/acta/{consejo}/firmada` | Sube el acta firmada (PDF) | gestor |
| `GET /consejos/reunion/{id}/dictamenes`, `POST …/dictamenes/generar`, `GET …/dictamenes/descargar` | Oficios | gestor |
| `GET/POST /consejos/oficio/{id}`, `POST …/regenerar`, `GET …/descargar`, `POST …/escaneado` | Revisar un oficio, regenerarlo, bajarlo y subir su escaneado | gestor |
| `GET /consejos/archivo/{id}` (`?descargar=1`) | PDF firmado | gestor o persona mencionada |
| `POST /consejos/archivo/{id}/quitar` | Retira un PDF | gestor |
| `GET /consejos/buscar` | Búsqueda con comodines | gestor |
| `GET /consejos/comisiones`, `GET /consejos/comisiones/{id}/actas` | Comisiones de la persona; ZIP de actas | cuenta del módulo |

## Datos

Migración `database/migrations/061_consejos.sql` (repetible; entra en `database/construir_actualizar.php`):

- `consejos_reuniones`: datos de la mesa y de cada Consejo, `academico_fin` y `gobierno_fin` (terminación), `estado`.
- `consejos_asuntos`: grupo, número, `solicitud_html`, `antecedentes_html`, `texto_busqueda`, `aval_academico`, `acuerdo_academico`,
  `aprob_gobierno`, `acuerdo_gobierno`, `nota_gobierno`, `version_acuerdo` (sube con cada cambio).
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
  `Services/Busqueda.php`: comodines → `LIKE`. `Services/Texto.php`: fechas y normalización.
- `ConsejosAccess.php`: quién gestiona. Controladores: `ReunionController`, `AsuntoController`, `SesionController`,
  `OficioController`, `BusquedaController`, `ComisionController`.

## Pruebas

```powershell
C:\xampp\php\php.exe tools/prueba_consejos.php <carpeta_de_salida>
```

Contra la base local con la migración 061: captura casos con tablas, genera actas y oficios en Word (quedan en la carpeta indicada, para
abrirlos), busca con comodines y borra lo que creó. En Word conviene abrir `acta_gobierno.docx` y `dictamenes.docx`.

## Fuera de alcance (por ahora)

Firma electrónica de actas y oficios (se suben escaneados hasta que el Consejo avale la firma electrónica), envío por correo, lectura
automática del acta de Word o de PDF, observaciones como comentarios de Word, y alta de reuniones desde un Excel.
