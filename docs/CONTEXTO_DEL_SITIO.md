# Contexto del sitio (TRAZAFI) para empezar un chat sin leer todo el código

Documento de arranque para una IA (o persona) que va a modificar el sitio. **Léelo primero**; después lee solo la guía del módulo que
toques (tabla del §7). Actualízalo al terminar cualquier cambio que altere navegación, permisos, reglas o archivos clave.
Última actualización: 2026-11-23 (recoge todos los cambios hechos en la sesión de noviembre de 2026).

## 1. Qué es y cómo está armado

- PHP 8 + MySQL, módulos en `app/Modules/<Modulo>/` con manifiesto (`module.json` o `module.php`): `clave`, `roles`, `menu`, `rutas`,
  `consulta` (catálogo para la búsqueda y el asistente), `pendientes`, `por_firmar`, `vinculacion`, `grafo`. Contrato: `docs/GUIA_MODULOS.md`.
- Núcleo en `app/Core/` (Auth, Database, ModuleManager, InterfaceUi, Router, Audit, AccessControl, Cifrado, Titulos, PorFirmar…).
- Diseño común: `app/Views/layouts/app.php` (encabezado, buscador, menú lateral, migas, h1). Estilos `public/assets/css/app.css` y
  `ux.css` (accesibilidad y móvil); comportamiento común `public/assets/js/ux.js`; service worker `public/sw.js`.
- Sitio en producción: fingenieria.mx. Reglas de seguridad: consultas preparadas, CSRF, roles, auditoría, escape de salida, datos
  personales cifrados (`docs/CIFRADO_DE_DATOS.md`). El repositorio es **público**: nunca datos de personas en commits.

## 2. Cómo se publica (siempre así; no escribir scripts de subida)

1. Editar en local (`D:\Proyectos\plataformaFI`). 2. `python tools/subir/subir.py revisar <archivos.php>` (sintaxis en el servidor).
3. `git add <rutas>` (nunca `-A`; hay archivos ajenos sin versionar) y `git commit` con la línea `Co-Authored-By` de la sesión.
4. `python tools/subir/subir.py push` → `publicar` → `estado` (los tres deben quedar iguales: local, GitHub, producción).
5. Migraciones: archivo en `database/migrations/NNN_*.sql` + registro en `database/construir_actualizar.php` + `php database/construir_actualizar.php`
   (regenera `actualizar.sql`) + `python tools/subir/subir.py migrar database/migrations/NNN_*.sql`. Última: `069_constancias_adjuntos.sql`.
6. Consultas en producción (solo lectura, para diagnosticar): `python tools/subir/subir.py php -e 'return \App\Core\Database::todas("SELECT …");'`.
   La tabla de auditoría no se llama `auditoria`; si cruzas correos entre tablas, usa `COLLATE utf8mb4_unicode_ci`.

Trampas de entorno (Windows): el PHP local **no tiene mbstring** (no ejecutes vistas con `mb_*` en local; usa `php -l`); en heredocs de
Bash/Python se **pierden las barras invertidas** (`\App\…`, `\b`, `\1`): escribe esos archivos con la herramienta Write o arma las `\` con
`chr(92)`; no lances `python -` sin script (abre un REPL y bloquea); los archivos mezclan LF/CRLF, respeta el que ya tengan.

## 3. Navegación y menú (dónde se decide qué ve cada persona)

- `ModuleManager::menu()` toma las entradas de cada manifiesto filtradas por rol, concesión y `condicion`. `InterfaceUi::menu()` las
  reparte en **grupos** según el primer segmento de la ruta: Inicio, Trámites y seguimiento, Titulación, **Constancias**, **Consejos**,
  Agenda, Fila, Comunidad, Concursos, Recursos (grupo por omisión: cuidado, es un «cajón») y Administración.
  `InterfaceUi::NAMES` renombra las rutas de un solo segmento. El campo `grupo` de una entrada crea subtítulos dentro del grupo.
- **Menú lateral** (`layouts/app.php`): un grupo con varias opciones es un **acordeón** (`<details class="nav-grupo">`) con su icono y sus
  opciones secundarias; con una sola opción, enlace directo. Se abre solo el grupo activo. Las páginas `seccion/<slug>` siguen existiendo
  (migas de pan) pero ya no estorban en el menú.
- **Agenda**: primero Calendario, luego Reuniones, luego lo demás (`InterfaceUi::menu`).
- **Títulos**: el diseño pone un único h1; las migas muestran solo el camino. `App\Core\Titulos::quitarRepetido` retira de la vista el
  encabezado que repite el h1. Una vista no debe repetir el título (`docs/GUIA_MODULOS.md`, «Títulos de página»).
- **Buscador del encabezado**: campo en el encabezado (lupa en celular, tecla `/` o `\`); al escribir sugiere avisos vigentes
  (`/busqueda/sugerencias`) y accesos del menú; Enter abre `/busqueda?q=`, página de resultados (opciones, registros propios, avisos,
  áreas responsables). Reglas de seguridad en `docs/BUSQUEDA.md`. Para que una pantalla se encuentre, su manifiesto debe tener `consulta`
  con descripción y `palabras`; la ruta también cuenta como texto (`Busqueda/BusquedaController::coincidencias`).
- **Nombres acordados** (no volver a los anteriores): «Presentar un asunto a los Consejos», «Pedir una constancia», «Constancias firmadas»,
  «Pedir apoyo a un área» (tickets), «Comisiones de trabajo» (Comunidad), «Mis comisiones de Consejos», «Buscar en Traza FI».
- **Móvil**: áreas táctiles de 44 px (`ux.css`); el menú hamburguesa y «Salir» se ampliaron. Las tablas `.tabla` se vuelven tarjetas en
  celular salvo con `data-no-cards`; el mejorador genérico de listas se desactiva con `data-no-tools`; el filtro de selects largos con
  `data-sin-filtro`; los formularios con `data-draft` guardan borrador, y con `data-draft-auto` lo hacen siempre y en silencio.

## 4. Reglas de negocio vigentes por área

**Consejos** (`docs/CONSEJOS.md`)
- Solo el Subdirector Académico, su asistente y quien tenga la concesión `consejos.gestor` ven «Consejos» y «Buscar en las actas»
  (`ConsejosAccess::esGestor`; Administración ya no gestiona por serlo). Profesores y coordinaciones ven solo «Presentar un asunto a los
  Consejos» y «Mis comisiones de Consejos».
- Solicitud (profesores/coordinaciones): sin filtro en «Tipo de trámite»; el campo **Solicitud** es solo enunciado (sin listas ni tablas:
  `Html::sinTablasNiListas`, y la barra del editor no ofrece tablas ni listas); no existe el editor de «Documentos y/o antecedentes»: el acta
  siempre dice «Oficio de solicitud» y los anexos se enlistan solos. El **apartado del acta** no lo captura quien solicita: profesor → P,
  coordinación → AG (`SolicitudController::grupoPorDefecto`); la Subdirección lo cambia en la solicitud (`POST consejos/solicitudes/{id}/apartado`)
  o al editar un caso (renumera en el nuevo apartado). En el formulario de caso de la Subdirección, antecedentes sí admite listas y tablas.
- Manual de captura actualizado (`Consejos/views/manual.php`).

**Constancias**
- Menú: «Constancias firmadas», «Pedir una constancia». «Por firmar» salió del menú (va a la bandeja única de Firmas).
- «Constancias firmadas» (`IndiceController`, `Services/Indice`): tabla única, minimalista, solo firmadas, con columnas Departamento, Emisión
  y Firma ordenables. **Cada persona ve solo las constancias que se le extendieron** (`alcancePropio`, `solo_recibidas`): no las que firmó,
  solicitó o emitió su departamento. El nombre de la constancia es el enlace de descarga.
- «Pedir una constancia»: formato libre (sin plantillas), la persona es quien solicita (nombre y correo de su cuenta, sin campos),
  firmantes acotados a titulares de coordinaciones/coordinadores de programa, Subdirector Académico y Director (`Firmantes::paraConstancias`,
  sin buscador), evidencia opcional (tabla `cst_adjuntos`, `Services/Adjuntos`, `AdjuntoController`; una fila nueva solo aparece al elegir archivo).
- El firmante puede **devolver sin firmar pidiendo evidencia o datos** (`FirmaController::devolver`); el solicitante corrige y reenvía.

**Firmas** (`docs/FIRMAS_UNICA.md`): bandeja única «Por firmar» en `/firmas`. Todo flujo de firma declara `por_firmar` en su manifiesto
(`Core\PorFirmar`); registrados: Constancias, Titulación, Posgrado. Lo que llega depende del firmante designado, no del departamento.
Coordinadores y personal ven «Firmas de documentos»; **los alumnos no** (ni «Grupos de destinatarios»; las rutas siguen abiertas por enlace).

**Titulación**: el menú ya no repite «Firmas pendientes», «Posgrado: firmas» ni «Mis constancias» (están en Firmas y en Constancias firmadas).
Los alumnos no ven «Seguimiento de casos» (menú y redirección a «Mis pendientes»); sí «Mi trámite», «Estado» y «Mis pendientes».

**Fila / Plan**: profesores y alumnos ven solo las filas de oficinas con la función configurada (al menos un horario activo, `FilaController::paneles`)
y sin pestañas «Para que yo atienda»; el Plan de Desarrollo salió del menú de profesores y alumnos (`Plan/module.php`).

**Reuniones**: «Convocar reunión» guarda el borrador siempre (`data-draft-auto`); `/reuniones` es una tabla y las reuniones en borrador,
canceladas, finalizadas o pasadas salen en gris (`.reunion-inactiva`) con la leyenda «(no activa)».

**Notificaciones / app instalada**: guía «Traza FI en tu teléfono» con los pasos de iOS 26 (botón ⋯ → Compartir → Agregar a pantalla de inicio →
«Abrir como app web») y un paso 3 que diagnostica y prueba el número del icono. Insignia: con la app abierta la fija la página
(`layouts/app.php`, conteo exacto del menú, también guardado en la caché `trazafi-badge`); con la app cerrada la fija `public/sw.js`
(`trazafi-v4`), que suma 1 por push. En iPhone requiere iOS 16.4+, app instalada desde Safari, notificaciones permitidas e «Insignias» activas.
Al cambiar `sw.js`, subir la versión de `CACHE`.

**Ajustes → Usuarios** (`Ajustes/views/usuarios.php`): un solo campo de búsqueda ancho con filtro inmediato (sin acentos), tabla con
encabezados ordenables, páginas de 50, tabla real también en celular, y **el nombre abre la edición** de la cuenta (no hay columna Acciones).

**Documentos (PDF y Word)**: todo en **DM Sans** (obligatorio): `lib/fpdf/fpdf.php` (capa sobre tFPDF) sustituye cualquier fuente base; ver
`docs/GUIA_MODULOS.md` («Documentos PDF y Word»). Reglas del oficio nuevo de la Subdirección (aún por construir): serie única `ACAD/FI/{n}/{AAAA}`
con 3 dígitos y reinicio anual (el último usado en 2026 fue el 446), firmante siempre el titular de la Subdirección Académica con su **e.firma del SAT**
(se pide en cada firma y **nunca se almacena**; independiente de la firma electrónica de la Facultad, aún no aprobada), leyenda de `LemaAnual`,
lienzo solo con negritas/cursivas y tabla pegada con Ctrl+V, interlineado sencillo y 0.8 pt después de cada párrafo, destinatarios reutilizables,
QR de verificación.

## 5. Estado observado en producción (2026-11-23), para no volver a investigarlo

- Ingesta (documentos indizados): 0 carpetas, 0 documentos, 0 concesiones. Los casos y dictámenes de los Consejos nunca pasan por la búsqueda.
- «Oficios enviados» (`/oficios`) funciona (tabla y archivos OK) pero hay solo 2 registros de prueba de la Subdirección Académica; la
  secretaria de la Subdirección **no tiene cuenta** (falta usuario Operativo en el departamento Subdirección Académica) y la tabla
  `tit_asistentes` está vacía.
- La vista del grafo de relaciones es más ancha que la pantalla en celular (desplazamiento horizontal); pendiente si molesta.

## 6. Pendientes y decisiones abiertas

- Titulación y Posgrado conservan pantallas de firma propias (se abren desde la bandeja única).
- Las vistas con un h1 propio distinto del título del diseño conservan dos h1 (mejorable para lectores de pantalla).
- Número de insignia exacto con la app cerrada requeriría calcular pendientes por persona en el servidor y mandarlos en cada push.
- Revisar qué más debería ver un alumno en Titulación y el «cajón» Recursos.

## 7. Dónde leer más (solo lo del módulo que toques)

| Tema | Documento |
|---|---|
| Contrato de módulos, títulos de página | `docs/GUIA_MODULOS.md` |
| Consejos (actas, dictámenes, solicitudes) | `docs/CONSEJOS.md` |
| Firmas únicas | `docs/FIRMAS_UNICA.md` |
| Búsqueda | `docs/BUSQUEDA.md` |
| Titulación y Posgrado | `docs/TITULACION.md` |
| Fila | `docs/fila.md` |
| Asistente de inicio (Jev/Gemini) | `docs/ASISTENTE_JEV_GEMINI.md` |
| Concesiones de acceso | `docs/CONCESIONES_DE_ACCESO.md` |
| Cifrado y datos sensibles | `docs/CIFRADO_DE_DATOS.md`, `docs/DATOS_SENSIBLES.md` |
| Ingesta de documentos | `docs/INGESTA_DOCUMENTAL.md` |
| Despliegue | `docs/DESPLIEGUE.md` |
| Instrucciones para agentes y flujo de publicación | `AGENTS.md` |

## 8. Bitácora de cambios (sesión de noviembre de 2026)

Orden aproximado; cada línea indica archivos clave.

1. **Consejos**: solicitud sin antecedentes ni apartado, sin tablas/listas en la solicitud, sin filtro en «Tipo de trámite», apartado por rol y
   ajustable por la Subdirección (`SolicitudController`, `AsuntoController`, `Services/Html`, `Services/Plantillas`, `views/solicitud_form|asunto_form|solicitud_ver|_herramientas|_editor_js|manual`).
2. **Menú de Constancias** simplificado y reubicación de Consejos y Titulación a su propio grupo (`InterfaceUi`, manifiestos).
3. **Constancias firmadas** (tabla, orden, solo recibidas, minimalista) y **Pedir una constancia** (libre, firmantes acotados, evidencia, devolución) — migración 069.
4. **Bandeja única de firmas** (`Core/PorFirmar`, `por_firmar`, `docs/FIRMAS_UNICA.md`).
5. **Titulación y Posgrado**: menú depurado; alumnos sin Seguimiento de casos ni Firmas.
6. **Agenda/Reuniones**: orden, borrador siempre guardado, tabla con inactivas en gris (`ux.js`, `Reuniones/views`).
7. **Revisión UX**: renombres, buscador del encabezado, áreas táctiles (`layouts/app.php`, `ux.css`).
8. **Menú principal en acordeón** conservando los iconos (`layouts/app.php`, `ux.css`).
9. **Fila y Plan** para profesores y alumnos.
10. **Títulos sin repetir** (`Core/Titulos`, migas).
11. **Búsqueda**: resultados directos, sugerencias de avisos, límite de uso, sin guardar el texto, palabras clave de Ajustes (`Busqueda/*`, `docs/BUSQUEDA.md`).
12. **Consejos solo para gestores** (`ConsejosAccess`).
13. **App instalada**: guía iOS 26, insignia con la app cerrada, diagnóstico (`Notificaciones/views/guia_app.php`, `public/sw.js`).
14. **Usuarios** (Ajustes): tabla ordenable con filtro inmediato y edición por nombre.
16. **DM Sans en todos los PDF y Word** (`lib/fpdf/*`, `Titulacion/Services/Hoja::renglones`, `Consejos/Services/Word.php`, plantilla del dictamen).
15. Carpeta `consejos/` (material de contexto con datos personales) excluida en `.gitignore`.

### Oficios de la Subdirección Académica (módulo `Oficios`, 2026-11-23)

- Rutas `/oficios/subdireccion*`. Redactan: titular, su asistente y quien tenga `consejos.gestor`; firma solo el titular (`SubdireccionFirma::esSubdirector`).
- **Número de oficio único** `ACAD/FI/{n 3 dígitos}/{AAAA}`: serie `subdireccion` en `tit_oficio_series` (`NumeroOficio::reservar`), compartida con los dictámenes de Consejos y Titulación; reinicia cada año (último 2026 = 446). El número se asigna al **enviar a firma**.
- Cuerpo en lienzo (`oficios-lienzo.js`): solo negritas/cursivas; las tablas se pegan con Ctrl+V (sin controles de tabla). PDF con `OficioPdf` (DM Sans, hoja membretada, QR, leyenda de `LemaAnual`, interlineado 1, 0.8 pt tras párrafo, cargo en mayúsculas en dos líneas, sin línea de firma).
- **Firma con e.firma (FIEL) del SAT**: la .cer/.key/contraseña se procesan solo en el navegador (`oficios-firma.js` + node-forge), se piden en cada firma y nunca se envían ni guardan; el servidor recibe el certificado público y la firma CMS, y la incrusta como PAdES (`Pades`, `Cms`). El RFC del titular se registra cifrado (Configuración) solo para comprobar que la e.firma es suya y **nunca se muestra** ni aparece en el PDF. Independiente de la firma electrónica de la Facultad.
- Kit de confianza: la administración carga los certificados del SAT en Configuración (`ofs_confianza`); `/oficios/confianza` (público) los ofrece con la guía para Acrobat. Sin ellos, Adobe muestra «identidad desconocida».
- Verificación pública por QR: `/oficios/verificar/{código}` (compara además la huella SHA-256 del PDF en el navegador).
- Migración 070. Pendiente real: registrar el RFC del titular y cargar los certificados del SAT; primera firma real a cargo del Subdirector.

#### Firma de oficios con e.firma (FIEL): contexto técnico para otra IA

Archivos: `public/assets/js/oficios-firma.js` (UMD, usa `vendor/forge.min.js`), `Oficios/Services/{Pades,Cms,OficiosSub,OficioPdf}.php`, `SubdireccionController` (`firmar`, `firmaPreparar`, `firmaGuardar`), vista `sub_firmar.php`.

Flujo (3 pasos, todo con CSRF y solo el titular):
1. En el navegador, `OficioFiel.abrir(.cer, .key, contraseña)` descifra la llave PKCS#8 (la contraseña y la llave nunca se envían ni se guardan; los campos se vacían al salir de la página) y valida vigencia, `keyUsage` y que la llave corresponda al certificado. Envía a `/firma/preparar` **solo el certificado público** (base64 DER).
2. El servidor valida el certificado (vigencia, firma digital, emisor SAT, RFC igual al registrado), arma el PDF final con el sello y `Pades::preparar` añade, como actualización incremental, el campo de firma `/adbe.pkcs7.detached` con 8192 bytes reservados; devuelve el PDF y el `ByteRange`. El navegador firma localmente esos bytes (CMS/PKCS#7 desprendido, SHA-256) con `OficioFiel.firmar`.
3. `/firma/guardar` recibe solo el hex de la firma: `Pades::incrustar`, `Cms::verificar` (parser DER propio + `openssl_verify`), compara la serie del certificado, `Cms::cadena` contra `ofs_confianza` (si hay certificados cargados), guarda el PDF en `storage/oficios_sub/`, actualiza `ofs_oficios` (sha256, cert_serie, cert_nombre, cadena_estado) y audita.

Reglas que no deben romperse:
- La FIEL **no se almacena** y se pide en cada firma; el procesamiento es en el navegador.
- El RFC del titular se guarda cifrado (`Cifrado`, ajuste `oficios_rfc_titular`), solo para comparar; **nunca** se muestra en pantalla ni en el PDF ni en la verificación pública.
- Es independiente de la firma electrónica de la Facultad (aún no aprobada); no mezclar con `Firmas`/constancias salvo la bandeja `por_firmar`.
- Cualquier cambio al PDF después de firmar invalida la firma: el PDF firmado es inmutable; para corregir hay que cancelar y emitir otro.
- Adobe muestra «identidad desconocida» mientras no tenga instalados los certificados raíz del SAT (kit en `/oficios/confianza`); la firma es igualmente íntegra. La verificación pública `/oficios/verificar/{código}` no depende de Adobe.
- Probado con OpenSSL (`openssl cms -verify`) y pyHanko usando un certificado de prueba; **no** con una e.firma real del SAT (pendiente: primera firma real del Subdirector, registrar su RFC y cargar los certificados del SAT en Configuración).
- Dependencia de terceros: node-forge (licencia en `public/assets/js/vendor/forge.LICENSE.txt`).

#### Oficios de la Subdirección → «Avisos y eventos → Oficios» (migración 071)

- Ruta `/eventos/oficios` (módulo Eventos, controlador `Oficios\RecibidosController`): tabla Fecha · Número de oficio (enlace al PDF firmado, `/eventos/oficios/{id}/pdf`) · Asunto, con filtro. Chip «Oficios» junto a «Avisos y eventos».
- Destinatarios (`ofs_destinatarios`): personas o **grupos** (`tipo='grupo'`, `grupo`): `profesores` (todos), `ptc`, `medio_tiempo`, `asignatura`, `tecnico_academico` y `programa:{id}` (uno por programa). Una persona puede ir enlazada a `profesor_id` (directorio) o `usuario_id`; el selector del lienzo sugiere profesores del directorio y los agrega ya enlazados.
- Al **firmar** se llena `ofs_oficio_destinos` (`Destinatarios::indexar`): grupo → clave; persona → usuario o profesor (si no está enlazada, se busca por nombre con `Profesores::porNombre`, solo si hay una coincidencia). La visibilidad se calcula al consultar (`Destinatarios::recibidos`/`leDirige`): cuenta, directorio (`profesores.usuario_id`, así un profesor que se registra después ve sus oficios) y grupos de la persona (rol `profesor`, `profesores.categoria`, `programa_profesores`).
- Límite conocido: «Tiempo completo/medio tiempo/asignatura/técnicos» dependen de `profesores.categoria`, que el profesor captura en «Mi perfil» (al 2026-11-23, 61 de 371 la tienen). Quien no la haya capturado solo verá los oficios de «Todos los profesores» o de su programa.
- Trampa de producción: no usar `? = ''` en SQL (mezcla de intercalaciones); filtrar en PHP.

#### API de Oficios de la Subdirección con token (migraciones 072 y 073, 2026-11-23)

- `POST /api/oficios/subdireccion` (multipart, `Authorization: Bearer ofi_…`) crea el borrador en `ofs_oficios` (y con `enviar=1` lo deja en firma con el número de la serie única); `POST /api/oficios/subdireccion/{id}/adjuntos` agrega anexos. Se atiende en `App::run` antes del router (sin sesión ni CSRF); la API **nunca firma**. Quien tiene el token debe poder redactar (`ApiOficios::puedeRedactar`: titular, asistente en `tit_asistentes` o concesión `consejos.gestor`).
- Anexos: tabla `ofs_adjuntos`, archivos en `storage/oficios_sub/adjuntos/`; se ven y descargan en la ficha (`/oficios/subdireccion/{id}/adjunto/{aid}`) y **no** se unen al PDF firmado. `OficiosSub::comoActor()` fija el autor sin sesión. Tokens: `oficios_api_tokens` (solo huella SHA-256). Detalle: `docs/OFICIOS_API.md`.
