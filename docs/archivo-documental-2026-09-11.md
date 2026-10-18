# Archivo documental · diseño e implementación

## Base de datos y hosting

Se mantiene MySQL/MariaDB como fuente principal. El catálogo combina índices por encabezados y FULLTEXT sobre título, tipo, folio, emisor, destinatario, palabras clave y resumen. Los binarios no se guardan en la base: se almacenan con nombres aleatorios fuera del directorio público. Se conservan hash SHA-256, tamaño, MIME y nombre original.

RAG no requiere una base de datos de formato especial. Es un patrón de recuperación de información para alimentar al modelo. En esta etapa se necesita búsqueda documental y extracción de metadatos, no un motor vectorial adicional. MySQL evita otro servicio, costo y proceso residente que un hosting básico no necesariamente permite. Un futuro índice semántico sería derivado, reconstruible y sujeto a los mismos permisos; nunca sustituiría al catálogo ni sus controles.

Referencias: https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html y https://ai.google.dev/gemini-api/docs/document-processing.

## Tablas

El SQL reproducible está en `database/schema_documentos.sql`; la activación administrativa usa las mismas definiciones en `app/Core/DocumentArchive.php`.

| Tabla | Información y relación |
| --- | --- |
| archivo_documentos | Ficha principal/expediente: departamento, coordinación, programa, usuario que subió, fechas de carga y documento, título, tipo, folio, emisor, destinatario, palabras clave, resumen, nivel, estado, versión y fecha de indización. |
| archivo_adjuntos | Uno a muchos con documento: originales y respuestas, usuario que anexó, nombre, clave privada, MIME, tamaño, SHA-256 y fecha. |
| archivo_accesos | Uno a muchos: usuario destinatario, permiso leer/responder, quién lo autorizó y cuándo. |
| archivo_acciones | Uno a muchos: historial de carga, edición, indización, compartición, revocación, respuestas y tareas; responsable, vencimiento, estado y fecha de finalización. |
| archivo_extracciones | Una propuesta por documento original inmutable: resultado estructurado, versión documental, solicitante y fechas. No modifica automáticamente la ficha. |

No se alteran tablas de negocio existentes. La FK documento de las cuatro tablas auxiliares impide registros huérfanos. Las referencias a usuarios/departamentos/programas se validan en servidor contra los catálogos existentes. La organización se selecciona al crear el borrador y no la cambia la IA.

## Flujo

1. Administrador o coordinador (rol coordinador o asignación activa de coordinación) sube de 1 a 3 PDF/JPG/PNG y elige organización autorizada, programa si aplica y confidencialidad.
2. Se guarda un borrador privado, sin indizar automáticamente ni compartir.
3. El propietario puede solicitar extracción de metadatos en Gemini con consentimiento explícito y proyecto de pago configurado. Los confidenciales quedan bloqueados antes de enviar datos.
4. Se muestran campos propuestos, archivo/página de origen y advertencias. Se pueden copiar al formulario, pero no se guardan hasta una acción explícita del usuario. La IA no selecciona organización ni permisos.
5. El propietario revisa y confirma para indizar. Se puede buscar por datos revisados; no se promete una transcripción OCR completa de todo el documento.
6. Puede compartir internamente con usuarios o grupos de destinatarios, permitiendo leer/descargar o responder. No se envían correos ni copias públicas.
7. Las respuestas se anexan al expediente y las tareas quedan asignadas y pueden marcarse completadas por su responsable autorizado o el propietario.

## Permisos

- Carga inicial: administrador, rol coordinador o coordinación activa. Debe verificarse el correo para escribir. Se comprueban departamento/programa/coordinación permitidos; asignaciones sin organización válida deben corregirse en los catálogos antes de cargar.
- Borrador: solo su propietario. Indizado: propietario y destinatarios expresos. Ser administrador técnico o pertenecer al mismo departamento no da lectura automática de documentos ajenos.
- Solo propietario edita ficha, indiza, comparte, revoca y crea tareas. Un destinatario con responder puede anexar respuestas y completar tareas asignadas, no editar el documento ni conceder acceso.
- Toda lista, búsqueda, ficha y descarga usa la misma condición de acceso. La revocación se aplica a nuevas consultas y descargas; no puede retirar una copia ya descargada por un destinatario.
- Los anexos heredan la audiencia del expediente completo; no hay permisos diferentes por archivo. No hay envío anónimo, enlaces de descarga públicos ni edición de originales después de subirlos.
- La clasificación confidencial bloquea la extracción externa, no impide al propietario compartir explícitamente dentro de la plataforma.
- Respuestas privadas sin caché; MIME validado en servidor, rutas aleatorias y fuera del webroot, PDF/imagen con sandbox y nosniff. No se aceptan ejecutables, SVG ni documentos Office en esta primera versión. No se incluye antivirus: se recomienda revisar el archivo antes de abrirlo con aplicaciones externas.

## Límites

- Carga: 4 MB por archivo, 8 MB por envío, hasta 3 archivos por envío y 30 archivos por expediente. También rigen los límites PHP del hosting mostrados en el formulario.
- Imágenes: máximo 24 megapíxeles. Extracción: hasta 3 páginas en total, máximo 8 MB, salida hasta 2,000 tokens, modelo Gemini 2.5 Flash-Lite y una llamada sin reintentos automáticos.
- PDF: se comprueba número de páginas antes de enviar. Si el lector PDF disponible no puede comprobarlo, se conserva el PDF y se ofrece indización manual o usar imágenes JPG/PNG para extraer. PDFs de más de tres páginas se conservan, pero no se procesan en esta primera versión.
- Máximo 5 reservas de extracción al día por usuario y 20 globales, además de las cuotas generales de Gemini. Se reserva fuera de la transacción documental para que un error no deshaga el contador. Sin proveedor configurado no se consume cuota. Se reutiliza la propuesta obtenida, sin volver a procesar los originales.
- Se mantienen las operaciones acotadas en PHP, sin servidores vectoriales, OCR local pesado ni servicios residentes. Deben vigilarse el espacio total y los respaldos del hosting. Los archivos privados requieren respaldo además del respaldo de la BD.

## Pruebas y despliegue

- `tools/test_document_archive.cjs`: en QA, tres imágenes a borrador, fechas inválidas, versión obsoleta, indización, filtros, usuario ajeno, carga no autorizada, lectura/descarga, escritura bloqueada al lector, respuestas, tarea, revocación inmediata, límites de organización del coordinador, confidencialidad y CSRF. Vistas 360/390/768/1440 sin desbordamiento horizontal.
- `tools/test_document_extractor.php`: transporte simulado, tres imágenes enviadas, límite separado de 2,000 tokens, esquema de salida, referencias de páginas y rechazo de campos de permisos. Sin llamadas a Google.
- En producción se activaron las cinco tablas y se verificó almacenamiento privado escribible. Archivo, carga, búsqueda, Ajustes IA, Teams e inicio respondieron HTTP 200 sin errores PHP. La descarga sin sesión redirige a login. El script tuvo un error de selector de prueba posterior a estas verificaciones, corregido en el script; no fue un error del sitio.
- No se cargaron documentos de prueba ni se enviaron correos o llamadas Gemini en producción. La extracción real y su calidad están pendientes de validación con una muestra y la cuenta Gemini configurada.
- Guía emil-design-eng aplicada a controles nativos, revisión explícita y diseño adaptable; pruebas de navegador, no de equipos físicos.
- Despliegue Hostinger mediante manifiesto `tools/documentos-deploy-files.json`. Respaldo de tres archivos previos: `database/production-backups/site-update-20260911-101201`. Ajuste final del controlador respaldado en `site-update-20260911-101312`.

Rutas principales: `/public/documentos`, `/public/documentos/nuevo`, `/public/documentos/{id}`. La activación es una acción administrativa autenticada, no un instalador público temporal.
