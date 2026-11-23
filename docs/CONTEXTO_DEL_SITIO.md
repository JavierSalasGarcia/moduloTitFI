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
15. Carpeta `consejos/` (material de contexto con datos personales) excluida en `.gitignore`.
