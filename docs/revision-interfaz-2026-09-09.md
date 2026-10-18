# Revisión de interfaz y facilidad de uso — Traza FI

Fecha: 9 de septiembre de 2026. Sitio: https://fingenieria.mx/public/

## Alcance y conclusión

Se revisaron 30 pantallas y variantes de producción con la cuenta administrativa, a anchos de 1440 px (PC) y 390 px (celular simulado). Se contrastaron las pantallas con sus plantillas, estilos y navegación. Todas las rutas recorridas devolvieron HTTP 200. Esto acredita su carga, no el funcionamiento completo de todos los formularios.

La portada ofrece un punto de partida limpio y comprensible. El resto conserva una estructura consistente en algunos componentes, pero presenta diferencias de jerarquía, nombres e interacciones. La mejora principal sería extender esa claridad a las tareas internas y hacer visibles el siguiente paso y la ubicación del usuario.

No se enviaron solicitudes, invitaciones, votos ni publicaciones. No se cambiaron ajustes ni se desplegaron modificaciones. Se cerraron las sesiones de revisión. No se probaron dispositivos físicos, otros roles ni todos los estados de detalle con datos reales. Las recomendaciones sobre esos estados se apoyan en el código y necesitan validación al implementarse. Las capturas móviles definitivas se tomaron con las transiciones terminadas.

## Prioridad 1 — Navegación y obstáculos cotidianos

| Mejora | Evidencia y problema | Propuesta para celular y PC |
|---|---|---|
| 1. Mantener una navegación reconocible | La portada oculta el menú incluso en PC; las pantallas internas muestran una barra lateral. El logo no es un enlace a Inicio. | Hacer el logo un acceso a Inicio y conservar siempre el mismo control de navegación. En PC, menú lateral plegable. En celular, menú compacto con los accesos frecuentes al principio. Mantener la portada minimalista. |
| 2. Corregir la ubicación activa | La selección se calcula por módulo; Ajustes y Usuarios pueden aparecer activos simultáneamente. Los enlaces de regreso varían entre pantallas. | Resaltar solamente la ruta actual, añadir una ruta breve como «Inicio / Reuniones / Nueva» en PC y un regreso contextual en celular. |
| 3. Agrupar por tareas y aclarar nombres | «MI ESPACIO» contiene nueve entradas para el administrador. Conviven Tickets, Solicitudes y Peticiones, además de Comunicados y Avisos. | Agrupar en Inicio, Trámites y seguimiento, Agenda, Comunidad y Recursos; separar Administración. Diferenciar «Mis solicitudes», «Gestión entre áreas» y «Propuestas de la comunidad» sin confundir procesos distintos. |
| 4. Completar el menú móvil | El controlador global solo alterna una clase; no gestiona Escape, foco, cierre exterior ni estado expandido del botón principal. | Abrir sobre un fondo atenuado, permitir cerrar tocando fuera o con Escape, devolver el foco y excluir del recorrido de teclado los enlaces ocultos. |
| 5. Reducir el aviso repetitivo de seguridad | El aviso amarillo aparece sobre casi todas las pantallas internas. A 390 px mide aproximadamente 174 px de alto y retrasa el acceso a la tarea. | Mostrar una franja breve con «Configurar acceso seguro» y llevar la explicación a un asistente. Escalar la urgencia cuando corresponda; mantener la política de seguridad. |
| 6. Aclarar las notificaciones | La portada muestra cuatro iconos y también contadores en cero. Los contadores significan cosas diferentes: tickets activos, reuniones pendientes, comunicados recientes y encuestas abiertas; no todos representan elementos sin leer. | Conservar los iconos solicitados, usar símbolos reconocibles y abrir un panel con nombre y pendientes. Ocultar insignias en cero y definir si cada número significa nuevo, pendiente o total. Llevar cada icono a la bandeja filtrada correspondiente. |
| 7. Completar «Responder disponibilidad» | La rama de esta pestaña en Reuniones solo renderiza «Aquí aparecerán las invitaciones…», sin una lista dinámica en esa vista. | Mostrar invitaciones pendientes y un botón «Elegir horarios» por reunión; si no existen, indicar «Estás al día». Es un pendiente funcional además de visual. |
| 8. Adaptar interacciones de escritorio | Teams pide doble clic para abrir carpetas; Comisiones indica mantener Ctrl/Cmd para seleccionar varios profesores. | En Teams, un toque para navegar y un botón separado «Detalles». En Comisiones, búsqueda con casillas y personas seleccionadas visibles. Mantener los mismos significados en PC. |

## Prioridad 2 — Formularios, lectura y seguimiento

| Mejora | Evidencia y problema | Propuesta para celular y PC |
|---|---|---|
| 9. Unificar el lenguaje visual | La portada tiene un encabezado claro, mientras las páginas internas usan verde; «Crear convocatoria» usa azul. Hay títulos duplicados y tamaños y separaciones particulares en cada módulo. | Definir un conjunto compartido de encabezados, botones, campos, tarjetas y estados. Mantener verde y dorado institucionales; reservar colores de estado para su significado. Un título principal por pantalla. |
| 10. Mejorar legibilidad y pulsación | Los estilos generales usan 14 px en campos y hay ayudas de 11–12 px; Teams contiene acciones de 28 × 28 px. | Usar alrededor de 16 px en texto de entrada móvil y objetivos táctiles de 44–48 px para acciones frecuentes. Ampliar el área pulsable sin agrandar necesariamente el icono. Comprobar contraste y zoom. |
| 11. Preparar tablas para celular | Usuarios conserva una tabla horizontal y la acción Editar queda a la derecha. Tickets oculta responsable y actualización en móvil. | Mostrar tarjetas móviles con asunto/nombre, estado, responsable y última actividad; abrir el detalle con un toque. En PC conservar tablas con filtros, orden y acciones visibles. El desplazamiento horizontal interno no implica por sí mismo un incumplimiento, pero aquí dificulta tareas comunes. |
| 12. Guiar formularios largos | La convocatoria mide cerca de 1,370 px de alto en la captura móvil vacía; las firmas muestran cuatro pasos y otras opciones antes del envío. | Reuniones: Datos → Horarios → Invitados → Revisar. Firmas: Documentos → Personas → Orden → Revisar. En PC secciones con resumen lateral; en celular pasos cortos con avance visible. |
| 13. Evitar pérdida de trabajo | Los formularios se apoyan en envíos completos y no se aprecia un patrón compartido de borradores o recuperación. | Conservar campos tras errores, mostrar validación junto al campo y añadir borradores en tareas largas. Tras enviar, mostrar folio, destinatario y «Ver seguimiento». Diseñar la persistencia del borrador según el tipo de información. |
| 14. Mejorar los adjuntos | Hay selectores de archivos nativos y ayuda general, pero no una presentación común de límites, archivos elegidos y progreso. | Mostrar formatos y peso admitidos, lista de adjuntos con opción de quitar y resultado de la carga. En celular facilitar elegir archivos o fotos cuando el trámite lo permita; en PC, arrastrar y soltar. |
| 15. Adaptar el calendario al contexto | La cuadrícula mensual de siete columnas aparece antes de la agenda, incluso cuando el mes está vacío. Los detalles importados usan alertas del navegador. | Agenda de próximos eventos como vista inicial móvil; vista mensual en PC y selector entre ambas. Abrir detalles en un panel con fecha, lugar y acción principal. Mantener Hoy siempre disponible. |
| 16. Mejorar el seguimiento de trámites | En Tickets la matriz de Eisenhower aparece al mismo nivel que las bandejas. En reuniones hay estados como `fecha_confirmada` y métricas como «Coincidencia Máx». | Priorizar «Pendientes de mi respuesta», «En atención» y «Finalizados». Presentar una línea de progreso y el próximo paso. Mover la matriz a una vista opcional «Organizar por prioridad». Traducir estados a lenguaje cotidiano. |
| 17. Unificar orientación y búsqueda | La portada y «¿A dónde acudir?» tienen recorridos parecidos; la búsqueda expone porcentajes de similitud y el motor utilizado. La portada devuelve un área que el usuario no puede cambiar en ese formulario. | Compartir la misma experiencia de orientación, añadir ejemplos opcionales y «Elegir otra área». Explicar la recomendación con palabras sencillas. Mantener información del proveedor o del motor en Ajustes. |
| 18. Hacer informativos los estados vacíos | Mis Funciones muestra «Ninguna función coincide con «»» sin una consulta. Otras listas solo indican que no hay registros. | Diferenciar «Aún no hay registros», «No hay resultados para este filtro» y «Tu cuenta no tiene funciones asignadas», con la acción útil correspondiente. |

## Prioridad 3 — Mejoras por módulo

| Módulo | Mejora propuesta |
|---|---|
| Avisos, comunicados y eventos | Consolidar las dos entradas a comunicados. Distinguir claramente información oficial de eventos con fecha. Mostrar vigencia, destinatarios y acción principal; enviar avisos vencidos al archivo. En la cuenta revisada aparecen como oficiales avisos de julio y agosto: conviene definir su caducidad. |
| Foro | Mostrar primero conversaciones y propuestas; abrir el formulario al pulsar «Publicar». Usar «Apoyar propuesta» en lugar de mezclar likes y apoyos, y explicar qué pasa al alcanzar el umbral. |
| Encuestas | Crear opciones con filas y «Añadir opción», en vez de una opción por línea dentro de un texto. Mostrar «Pendiente», «Respondida» y «Cerrada». Permitir copiar el comprobante del voto y acceder claramente a resultados. |
| Comisiones | Mostrar responsable, próximo compromiso, fecha y avance. Sustituir selección múltiple con Ctrl por casillas. Presentar aceptar invitación y ver acuerdos como acciones directas. |
| Firmas | Añadir buscador de personas y resumen del orden. Mostrar solo los firmantes añadidos en vez de varios campos vacíos. Colapsar grupos largos y permitir filtrarlos. Cambiar «Limpiar canvas» por «Borrar trazo». |
| Directorio y funciones | Dar protagonismo a «Qué resuelve esta área» y «Enviar solicitud». Añadir contacto y horarios cuando existan. Expandir siglas para personas nuevas y permitir buscar por necesidad o persona. |
| Recursos y Teams | Separar favoritos, recientes y exploración. Hacer explícito si la acción abre Teams, descarga o muestra información. Sustituir el signo «+» aislado por una acción nombrada en los estados vacíos. |
| Usuarios y alumnos | Búsqueda y filtros por rol, programa y estado; tarjetas móviles y tabla en PC. Separar alta/edición del listado. Mostrar nombres completos de programas junto a siglas. |
| Ajustes | Agrupar en Inteligencia artificial, Departamentos y Comunidad. Sustituir IDs por selectores con nombres. Plegar endpoints y claves técnicas en «Avanzado». Para NVIDIA, indicar «Configurado / Sin configurar» y ofrecer una prueba de conexión explícita. |
| Acceso y seguridad | Simplificar los textos de acceso; evitar «Probar / Entrar» y explicar cada método por su utilidad. La recuperación muestra campos para código y nueva contraseña antes de solicitarlo: dividir en Correo → Código → Nueva contraseña. Guiar el registro de autenticación paso a paso. |

## Accesibilidad y validación propuestas

- Dar nombres accesibles a iconos y etiquetas permanentes a buscadores; no depender exclusivamente del placeholder.
- Mantener foco visible, orden lógico de teclado y estado de pestaña/página actual. Los paneles y diálogos deben anunciarse y gestionar el foco.
- Acompañar colores de texto e iconos: por ejemplo, «Pendiente de tu respuesta».
- Anunciar mensajes de error y éxito a lectores de pantalla. Enfocar el resumen o campo pertinente tras un error.
- Respetar reducción de movimiento y comprobar que el teclado virtual no tape la acción principal.
- Validar 360, 390, 768, 1024 y 1440 px, zoom y dispositivos físicos Android/iPhone; recorrer después las mismas tareas como alumno, profesor y personal.
- Comprobar cinco recorridos completos: localizar un trámite, enviarlo, consultar su estado, responder una reunión y firmar un documento. Los datos de prueba deben permanecer en un entorno adecuado.

Como referencia, WCAG 2.2 establece un mínimo de 24 × 24 píxeles CSS para objetivos de puntero, con excepciones; la propuesta de 44–48 px es un objetivo de comodidad táctil, no una cita de ese mínimo. Fuente: [W3C — Target Size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html). El contenido debe reorganizarse al reducir el ancho sin obligar a desplazarse en dos direcciones, salvo contenidos que requieren dos dimensiones: [W3C — Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html). Esta revisión no constituye una certificación de accesibilidad.

## Orden sugerido de implementación

1. Navegación, ubicación activa, aviso de seguridad, notificaciones y disponibilidad pendiente.
2. Componentes compartidos, tablas móviles, interacciones táctiles y formularios de reuniones/firmas.
3. Calendario, seguimiento, búsqueda y recuperación de formularios.
4. Ajustes por módulo y validación con usuarios de distintos roles en dispositivos reales.

Fuentes locales principales: `app/Views/layouts/app.php`, `public/assets/css/app.css`, `public/assets/js/app.js` y las vistas de `app/Modules/`. La propuesta conserva la entrada conversacional minimalista solicitada y aplica criterios de consistencia, respuesta clara y adaptación al contexto recogidos en las guías de diseño consultadas.
