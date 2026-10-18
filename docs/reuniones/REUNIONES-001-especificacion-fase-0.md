# REUNIONES-001 · Especificación funcional · Fase 0

Estado: aprobada para diseño técnico  
Fecha: 2026-07-17  
Módulo: `Reuniones` dentro de `MI ESPACIO`  
Código fuente: todavía no implementado

## 1. Objetivo

Crear un módulo para convocar una reunión de una sola sesión, recopilar disponibilidad, confirmar una franja horaria, mantener un expediente colaborativo y conservar una copia histórica durante cinco años.

El módulo tendrá dos pestañas principales:

1. `Convocar reuniones`.
2. `Responder disponibilidad`.

También tendrá un visor de reuniones activas, categorías, búsqueda histórica, chat y archivos.

## 2. Decisiones aprobadas

| Tema | Decisión |
|---|---|
| Sesiones | Cada expediente representa una sola reunión y una sola franja definitiva. |
| Duración | Las franjas se seleccionan en bloques de 60 minutos. |
| Confirmación | Invitados y organizador indican disponibilidad para ordenar las franjas por coincidencia; el organizador confirma la fecha y hora definitivas. No se requiere unanimidad. |
| Cambios | Los invitados pueden modificar su disponibilidad mientras el expediente esté abierto. |
| Invitados externos | Pueden responder disponibilidad y participar en el chat mediante enlace. |
| Finalización | Solo el organizador puede finalizar el asunto. |
| Cierre | Al finalizar se bloquean nuevos mensajes y archivos. |
| Archivos | Máximo 5 MB por archivo; extensiones `.doc`, `.docx`, `.ppt`, `.pptx`, `.pdf`; cantidad ilimitada. |
| Control de archivos | Se muestra un control inicial; después de cada carga aparece un botón `+` para añadir otro archivo. |
| Correo | Remitente `contacto@fingenieria.mx` mediante API de Hostinger. |
| Correo externo | Las invitaciones y notificaciones a correos externos están permitidas. |
| Límite de correo | Si los adjuntos superan 10 MB, se envía un enlace al expediente en lugar de adjuntarlos. |
| Outlook/Teams | Se usará ICS y se conservará la sincronización manual existente. |
| Sincronización | Se intentará sincronizar automáticamente una vez al día. |
| Coordinación | Se añadirá el rol `coordinador`. |
| Conservación | Los expedientes se conservan cinco años. |
| Respaldo | Los archivos y el chat se comprimen en `IDreunion.zip`. |
| Descarga | Cualquier integrante puede descargar el ZIP; el administrador también podrá descargarlo desde su panel. |
| Identificador | El ID de la reunión será visible para el coordinador que la precargue, el organizador y todos los invitados autorizados. |

## 3. Posgrados y rol `coordinador`

El rol `coordinador` estará asociado a uno de estos posgrados:

| Código | Posgrado |
|---|---|
| MCI | Maestría en Ciencias de la Ingeniería |
| DCI | Doctorado en Ciencias de la Ingeniería |
| MIAA | Maestría en Inteligencia Artificial Aplicada |
| MMT | Maestría en Movilidad y Transporte |
| MCS | Maestría en Cadena de Suministro |

Cada posgrado tendrá un coordinador asignado.

El coordinador podrá:

- precargar una reunión;
- nombrar organizador e invitados;
- definir un rango de fechas;
- usar el horario predeterminado de 08:00 a 20:00;
- dejar que el organizador ajuste las franjas antes de enviar la convocatoria.

La migración deberá ampliar el modelo de usuarios para soportar el rol y la relación con el posgrado sin romper los roles existentes.

## 4. Estados de una reunión

```text
precargada
borrador
convocada
en_respuesta
fecha_confirmada
finalizada
cancelada
```

Reglas:

- `precargada`: creada por un coordinador, aún bajo preparación del organizador.
- `borrador`: creada por el organizador, todavía no enviada.
- `convocada`: invitaciones enviadas.
- `en_respuesta`: existe al menos una invitación pendiente o disponibilidad modificable.
- `fecha_confirmada`: el organizador seleccionó una franja definitiva.
- `finalizada`: el organizador cerró el chat y los archivos.
- `cancelada`: la reunión no continuará; conserva su auditoría.

## 5. Reglas de disponibilidad

- El organizador selecciona días y franjas mediante calendario.
- Cada bloque válido representa 60 minutos.
- Podrán proponerse varias franjas en distintos días, pero solo una se convertirá en definitiva.
- Las horas ocupadas de invitados se mostrarán sin título, origen, descripción ni detalles.
- Las fuentes de ocupación serán eventos internos, reuniones confirmadas y eventos importados de Outlook/Teams.
- Cada invitado podrá marcar y modificar su disponibilidad mientras el expediente no esté finalizado.
- La coincidencia de una franja no confirma automáticamente la reunión.
- Invitados y organizador participarán indicando su disponibilidad; el sistema ordenará las franjas por cantidad de coincidencias para facilitar la decisión.
- La confirmación final corresponde exclusivamente al organizador.
- Después de confirmar una fecha, se notificará a todos los implicados y se generará el evento correspondiente.

## 6. Invitados externos

- Se invitará mediante correo y enlace seguro.
- El token será aleatorio, revocable y con expiración configurable.
- El invitado externo podrá responder disponibilidad.
- El invitado externo podrá leer y escribir en el chat mientras tenga permiso y el expediente esté abierto.
- No podrá consultar detalles de calendarios privados.
- El enlace no deberá revelar el ID secuencial de la reunión.

## 7. Chat y finalización

Cada mensaje deberá guardar al menos:

- expediente;
- emisor;
- contenido;
- fecha y hora;
- tipo `normal` o `urgente`;
- estado de entrega de la notificación, siempre, tanto para mensajes `normal` como `urgente`;
- destinatarios y fecha de cada intento de notificación, cuando corresponda.

El emisor elegirá entre:

- conservar el mensaje únicamente en la plataforma;
- marcarlo como urgente y enviar correo a los implicados autorizados.

Para ambos tipos de mensaje se registrará el estado de entrega. La diferencia entre `normal` y `urgente` será el nivel de notificación, no la existencia del registro de entrega.

Cuando el organizador pulse `Finalizar Asunto de Reunión`:

1. se solicitará confirmación;
2. se registrará el actor y la fecha;
3. se bloquearán nuevos mensajes;
4. se bloquearán nuevos archivos;
5. se generará un `.txt` con la conversación completa;
6. se comprimirá el `.txt` junto con los archivos en `IDreunion.zip`;
7. el ZIP quedará disponible para los integrantes;
8. se conservará el registro de auditoría.

El nombre del archivo de texto será el ID de la reunión, con extensión `.txt`. El ZIP se nombrará con el mismo ID y extensión `.zip`.

## 8. Archivos

Extensiones permitidas:

```text
.doc
.docx
.ppt
.pptx
.pdf
```

Reglas:

- tamaño máximo por archivo: 5 MB;
- cantidad total de archivos: sin límite funcional;
- validación de extensión y MIME;
- almacenamiento fuera del webroot;
- descargas protegidas por autorización;
- nombres físicos generados por el sistema;
- nombre original conservado como metadato;
- archivos iniciales en la invitación y archivos complementarios en el expediente;
- si el correo excede 10 MB de adjuntos, se utilizará enlace seguro.

## 9. Conservación y respaldos

- La retención del expediente será de cinco años.
- El sistema generará un ZIP final con nombre `<ID_de_reunión>.zip`.
- El ZIP incluirá archivos y copia completa del chat.
- El ID de la reunión será visible en el expediente para el coordinador que la haya precargado, el organizador y todos los invitados autorizados.
- Cualquier integrante podrá descargarlo desde el expediente.
- El administrador podrá descargarlo desde un panel de respaldos.
- La eliminación después de cinco años deberá ser una operación controlada, registrada y preferentemente ejecutada por tarea programada.
- El respaldo posterior en Microsoft Teams será responsabilidad del administrador; el módulo solo entregará el ZIP.

## 10. Criterios de aceptación de la Fase 0

- Las reglas de negocio anteriores son la referencia obligatoria para todos los agentes.
- Las decisiones sobre consenso, duración, cierre, archivos, correo y conservación no deberán reinterpretarse durante las fases técnicas.
- Toda excepción deberá registrarse como cambio de especificación antes de implementarse.
- El diseño técnico deberá preservar el aislamiento modular existente.
- Ningún controlador, vista o script podrá superar 300 líneas; si se aproxima a ese límite deberá dividirse.

## 11. Próxima fase autorizada

La siguiente fase es `REUNIONES-002`, diseño de modelo de datos y migración. Esta fase queda autorizada para elaborar el diseño y la migración, pero todavía no se autoriza crear código de aplicación ni scripts JavaScript.
