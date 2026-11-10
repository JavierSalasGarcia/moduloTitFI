# Ingesta documental: PDFs, carpetas y enlaces de Teams

Módulo `Ingesta` («Documentos indizados», `/ingesta`). Sirve para ingresar documentos, indizarlos y consultar sus
metadatos con su enlace. Es distinto del **Archivo documental** (`/documentos`), que maneja expedientes con respuestas,
tareas y compartición documento por documento.

## Qué se puede ingresar

| Modo | Cómo | Qué se guarda |
|---|---|---|
| Un PDF | Formulario con el archivo y metadatos opcionales | El archivo (fuera del sitio público), sus metadatos y el texto que se pueda extraer |
| Una carpeta de PDFs | Se elige la carpeta en el equipo; el navegador sube los PDF uno por uno, con sus subcarpetas | Una carpeta con todos sus documentos y su ruta relativa |
| Un archivo de Teams | Se pega su enlace | El enlace y los metadatos capturados |
| Una carpeta de Teams | Enlace de la carpeta (opcional) y los enlaces de sus archivos, uno por línea («Título \| enlace») | Una carpeta con sus enlaces |

- Solo se admiten PDF reales (se revisa el contenido, no solo la extensión), de hasta 20 MB o lo que permita el servidor.
- Los enlaces deben ser `https` y de Teams, SharePoint u OneDrive (`Ingesta::enlaceValido`). Cualquier otro se rechaza.
- **La plataforma no lee el contenido de Teams.** No hay conexión con Microsoft Graph: se guardan los enlaces y los
  metadatos que la persona captura. Abrir el archivo sigue dependiendo de los permisos de Microsoft 365.
- El mismo archivo o enlace no se duplica dentro de una carpeta.

## Metadatos e índice

Cada documento guarda título, tipo, autor o emisor, fecha, palabras clave, resumen, carpeta y ruta, nombre de archivo,
tamaño, páginas, huella SHA-256, quién lo ingresó y cuándo. Del PDF se toman solos el título, el autor, la fecha, las
páginas y el texto (`app/Modules/Ingesta/Pdf.php`, sin dependencias y acotado en tamaño y tiempo).

El texto extraído solo se usa para buscar; no se muestra completo. Un PDF escaneado, cifrado o con fuentes sin tabla de
caracteres no entrega texto: se encuentra por título, palabras clave, resumen y nombre de archivo. No hay OCR.

La búsqueda usa el índice FULLTEXT de `ing_documentos` (con prefijos) más coincidencia directa en título, palabras
clave, nombre, ruta y carpeta.

## Quién puede ingresar y quién puede consultar

- **Ingresar:** administración, titulares, operativos y coordinadores, o quien tenga la concesión `ingesta.subir`.
- **Consultar:** lo decide quien ingresa, con el alcance de la carpeta o del documento:

| Alcance | Lo ven |
|---|---|
| Solo yo | Quien lo ingresó (ni siquiera la administración) |
| Mi dependencia | Quien lo ingresó, las personas adscritas a esa dependencia y la administración |
| Personal de la Facultad | Todo el personal y los profesores; no los alumnos |
| Toda la comunidad | Cualquier cuenta, incluidos alumnos |

Además, una concesión `ingesta.carpeta` sobre una carpeta (o sobre toda la función) da lectura a una persona por un
tiempo. El acceso se resuelve en SQL con un solo predicado, `Ingesta::alcance()`, que usan la pantalla, las descargas y
el asistente: no hay otra ruta para leer un documento. Editar o eliminar solo puede quien lo ingresó (o la administración,
salvo lo «propio» ajeno).

## Consulta

- `/ingesta`: buscador, carpetas visibles y documentos recientes. Cada resultado muestra sus metadatos y el botón para
  abrir el PDF o el enlace de Teams.
- `/ingesta/carpeta/{id}`: contenido de una carpeta, con búsqueda dentro de ella.
- `/ingesta/documento/{id}`: ficha completa; quien lo ingresó puede corregir los metadatos.
- Los PDF se entregan por un enlace con token (`/archivo/{token}`) que revalida la sesión y el alcance en cada descarga.
- **Asistente de inicio:** «buscar documentos sobre …» lista hasta cinco documentos indizados dentro del acceso de la
  persona (`ConsultasSeguras`), con enlace a su ficha. Solo se muestran metadatos; nada se envía a una IA.

## Piezas

| Pieza | Archivo |
|---|---|
| Tablas `ing_carpetas`, `ing_documentos` | `database/migrations/044_ingesta_documental.sql` |
| Reglas, alcance, búsqueda, ingesta | `app/Modules/Ingesta/Ingesta.php` |
| Lector de PDF | `app/Modules/Ingesta/Pdf.php` |
| Pantallas | `app/Modules/Ingesta/IngestaController.php`, `views/` |
| Almacenamiento privado | `App\Core\DocumentArchive::storage()` (fuera del directorio público) |
| Prueba | `php tools/prueba_ingesta.php` |

## Pendiente

- Lectura automática de carpetas de Teams (requiere registrar la aplicación en Microsoft Entra y permisos de Graph).
- OCR para PDF escaneados y extracción de metadatos con IA (el Archivo documental ya tiene una con Gemini).
- Cambiar el alcance de una carpeta después de creada, y mover documentos entre carpetas.
- El grafo de relaciones vive en este módulo: ver `docs/GRAFO_DE_PERSONA.md`.
