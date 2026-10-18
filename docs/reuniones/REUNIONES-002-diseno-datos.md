# REUNIONES-002 · Diseño de datos y migración

Estado: diseño autorizado  
Depende de: `REUNIONES-001-especificacion-fase-0.md`  
Código de aplicación: no incluido en esta fase

## 1. Principios

- No modificar tablas existentes salvo la ampliación controlada del rol de usuario y la relación con posgrados.
- Mantener invitados internos y externos en el mismo modelo de participantes.
- Conservar disponibilidad histórica mediante registros versionables o auditoría.
- Separar el expediente, el chat, los archivos, las notificaciones y el respaldo ZIP.
- Todas las tablas deberán usar `utf8mb4`, claves foráneas e índices por expediente y participante.
- El identificador de reunión será visible únicamente dentro del expediente para coordinador autorizado, organizador e invitados autorizados.

## 2. Entidades principales

### `reuniones`

Campos mínimos:

- `id`.
- `asunto`.
- `descripcion`.
- `organizador_id`.
- `creador_id`.
- `posgrado_id` opcional.
- `estado`.
- `rango_inicio`.
- `rango_fin`.
- `hora_default_inicio`.
- `hora_default_fin`.
- `fecha_confirmada` y horas confirmadas.
- `finalizada_en`.
- `zip_archivo`.
- `creado_en` y `actualizado_en`.

El ID será el identificador de expediente y se utilizará para nombrar `<ID>.txt` y `<ID>.zip`.

### `reunion_participantes`

Campos mínimos:

- `id`.
- `reunion_id`.
- `usuario_id` nullable.
- `correo`.
- `nombre` nullable.
- `tipo` (`organizador`, `invitado`, `coordinador`, `externo`).
- `token_hash` nullable para acceso externo.
- `token_expira_en` nullable.
- `estado_invitacion`.
- `respondio_en` nullable.

Restricciones:

- una persona no deberá duplicarse dentro del mismo expediente;
- el correo se normalizará en minúsculas;
- el token se almacenará únicamente como hash.

### `reunion_opciones_horario`

Campos mínimos:

- `id`.
- `reunion_id`.
- `inicio`.
- `fin`.
- `creado_por`.
- `activa`.

Regla: cada opción tendrá duración de 60 minutos y no podrá cruzar el rango de la convocatoria.

### `reunion_disponibilidades`

Campos mínimos:

- `id`.
- `opcion_id`.
- `participante_id`.
- `estado` (`disponible`, `no_disponible`, `sin_respuesta`).
- `version`.
- `actualizado_en`.

Debe existir como máximo una respuesta vigente por participante y opción. Los cambios deberán auditarse.

### `reunion_mensajes`

Campos mínimos:

- `id`.
- `reunion_id`.
- `participante_id` o `usuario_id`.
- `contenido`.
- `tipo` (`normal`, `urgente`).
- `creado_en`.
- `bloqueado_en` nullable.

### `reunion_notificaciones`

Campos mínimos:

- `id`.
- `reunion_id`.
- `mensaje_id` nullable.
- `destinatario`.
- `tipo_mensaje` (`normal`, `urgente`).
- `evento` (`invitacion`, `disponibilidad`, `confirmacion`, `chat`, `finalizacion`).
- `estado_entrega` (`pendiente`, `enviado`, `fallido`).
- `intentos`.
- `ultimo_error` nullable.
- `enviado_en` nullable.

El estado de entrega será obligatorio para mensajes normales y urgentes.

### `reunion_archivos`

Campos mínimos:

- `id`.
- `reunion_id`.
- `subido_por`.
- `nombre_original`.
- `ruta_storage`.
- `extension`.
- `mime`.
- `tamano_bytes`.
- `tipo` (`inicial`, `complementario`, `respaldo_chat`).
- `creado_en`.

La base de datos conservará metadatos; el archivo físico permanecerá fuera del webroot.

### `reunion_categorias` y `reunion_categoria_relacion`

Permitirán que cada usuario organice sus expedientes sin alterar la visibilidad compartida de la reunión.

### `reunion_auditoria`

Registrará creación, invitaciones, cambios de disponibilidad, confirmación, mensajes urgentes, cargas, descargas, finalización, generación de ZIP y eliminación por retención.

### `reunion_respaldo`

Campos mínimos:

- `reunion_id`.
- `ruta_zip`.
- `hash_sha256`.
- `generado_en`.
- `generado_por` o `sistema`.
- `descargas`.
- `ultimo_acceso_en`.

## 3. Coordinadores y posgrados

Se recomienda una tabla de asignación separada, por ejemplo `posgrado_coordinadores`, en lugar de almacenar un solo coordinador directamente en `usuarios`.

Esto permite:

- conservar historial de coordinadores;
- cambiar coordinador sin perder expedientes;
- garantizar un coordinador activo por posgrado;
- mantener el rol `coordinador` y la pertenencia académica separados.

La migración deberá:

1. incorporar `coordinador` al modelo de roles existente;
2. agregar los cinco posgrados si no existen;
3. crear la tabla de asignaciones;
4. no convertir automáticamente a ningún usuario sin una asignación administrativa explícita.

## 4. Búsqueda

La primera versión utilizará índices SQL sobre:

- `reuniones.asunto`;
- `reuniones.descripcion`;
- `reunion_mensajes.contenido`;
- fechas, estado y participantes.

El agente deberá evaluar `FULLTEXT` para asunto, descripción y contenido del chat. La búsqueda siempre tendrá que filtrar primero por permisos del participante.

## 5. Retención

La fecha de retención se calculará a partir de la finalización del expediente, o de la cancelación si nunca se confirmó una fecha.

La tarea de retención deberá:

- detectar expedientes vencidos;
- generar auditoría;
- conservar el registro mínimo requerido para trazabilidad;
- eliminar archivos físicos y datos conforme a la política aprobada;
- no eliminar automáticamente un respaldo que todavía esté marcado como pendiente de descarga administrativa.

## 6. Entregables del agente `db-reuniones`

- `database/schema_reuniones.sql` con creación idempotente y comentarios.
- `database/seed_reuniones.sql` con los cinco posgrados, sin asignar usuarios reales.
- Documento de compatibilidad con `usuarios`, `programas`, `usuario_eventos_outlook` y roles existentes.
- Pruebas SQL de claves, índices, restricciones y cascadas.
- Informe de riesgos sobre la ampliación del enum de roles.

El agente no deberá crear controladores, vistas, JavaScript ni enviar correos.

## 7. Criterios de salida

- El esquema soporta una sola franja confirmada por reunión.
- Invitados y organizador pueden tener disponibilidad por opción.
- El orden por coincidencias puede calcularse con consultas SQL.
- Mensajes normales y urgentes tienen estado de entrega.
- El ID de reunión es referenciable para los participantes autorizados.
- El ZIP, el TXT y sus descargas son auditables.
- La retención de cinco años puede ejecutarse sin perder trazabilidad.
