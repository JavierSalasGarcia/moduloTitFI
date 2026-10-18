# Propuesta: Intercambio de Información entre Departamentos y con la Comunidad

> **Actualización fase 2**: además de las solicitudes entre departamentos aquí descritas,
> la plataforma incorpora cuatro mecanismos de intercambio con la comunidad estudiantil:
> 1. **Tickets** (`tickets`, `ticket_categorias`, `ticket_eventos`): cualquier usuario
>    levanta un trámite/reporte por categoría y el sistema lo canaliza al departamento
>    que atiende ese asunto (la categoría «coordinación de mi carrera» se resuelve con
>    el programa del alumno). Seguimiento con estados y bitácora, folio TK-AAAA-XXXX.
> 2. **Comunicados** (`comunicados`): las coordinaciones de licenciatura, Estudios
>    Avanzados, Servicios Escolares y Difusión Cultural publican avisos con alcance
>    granular: todos, licenciatura, posgrado o programa específico.
> 3. **Encuestas anónimas** (`encuestas*`): un voto por usuario sin vincular identidad
>    y sentido del voto; token público verificable en tablero sin sesión.
> 4. **Foro → trazabilidad** (`foro_*`, `comisiones*`): las peticiones que reúnen el
>    umbral configurable de likes pasan a trazabilidad; una autoridad forma una comisión
>    de profesores (invitación confirmable o voluntariado) con bitácora y evidencias
>    auditables almacenadas fuera del webroot.

Modelo implementado en la Plataforma FI para que los 32 departamentos de la Facultad
de Ingeniería compartan información **sin perder el aislamiento granular** que exige la
matriz de roles del Manual de Organización.

---

## 1. Principios

1. **Aislamiento por defecto.** Cada usuario solo ve los datos de su propio departamento
   (funciones, bandeja de solicitudes). Nadie consulta libremente los datos de otro.
2. **Canales autorizados.** La comunicación solo fluye por relaciones pre-aprobadas
   registradas en la tabla `relaciones` (origen → destino, dirigida). Esta tabla es la
   traducción directa de la matriz de interacciones del MO.
3. **Todo intercambio deja rastro.** No hay intercambios informales: cada trámite es una
   **solicitud** con folio, prioridad, estado y bitácora auditable.

## 2. El mecanismo: solicitudes con bitácora

```
┌─────────────┐   crea (solo por canal    ┌──────────────┐
│ Depto ORIGEN │ ─── autorizado) ───────► │ Depto DESTINO │
└─────────────┘                           └──────────────┘
      │            solicitud FI-2026-XXXX        │
      │  comenta ──► [bitácora compartida] ◄── comenta
      │                                          │
      │                            cambia estado (exclusivo destino):
      │                            pendiente → en_proceso → resuelta/rechazada
```

- **Crear**: cualquier usuario del departamento origen, únicamente hacia departamentos
  con canal activo. El formulario solo ofrece esos destinos.
- **Comentar**: ambos departamentos, en una bitácora común (`solicitud_eventos`).
- **Resolver**: solo el departamento destino cambia el estado; cada cambio queda
  registrado con autor, nota y fecha (trazabilidad / rendición de cuentas).

## 3. Modelo de datos

| Tabla | Papel |
| --- | --- |
| `relaciones` | Canales dirigidos autorizados (quién puede iniciar trámites con quién y para qué). |
| `solicitudes` | El trámite: folio único, asunto, descripción, prioridad, estado. |
| `solicitud_eventos` | Bitácora: comentarios y cambios de estado con autor y timestamp. |

Ventajas del enfoque:
- **Gobernanza central**: abrir o cerrar un canal es insertar/desactivar una fila en
  `relaciones`; no requiere cambios de código.
- **Sin acoplamiento entre módulos**: los departamentos no leen tablas ajenos; se
  comunican solo a través de solicitudes.
- **Auditable**: la bitácora responde quién pidió qué, cuándo y cómo se resolvió
  (compatible con los ejercicios de auditoría que el MO exige a cada departamento).

## 4. Roles (RBAC)

| Rol | Alcance |
| --- | --- |
| `operativo` | Crea y comenta solicitudes de su departamento. |
| `titular` | Además atiende (cambia estado) las solicitudes recibidas. |
| `admin` (Dirección) | Futuro: administrar canales, usuarios y ver métricas globales. |

*Nota: en esta versión el cambio de estado se valida por departamento; afinar por rol
es un cambio de una condición en `SolicitudesController::cambiarEstado()`.*

## 5. Evolución propuesta (fases)

1. **Fase actual** — Solicitudes de texto con bitácora (implementada).
2. **Fase 2 — Adjuntos**: tabla `solicitud_archivos` (PDF de actas, constancias,
   dictámenes) con almacenamiento fuera del webroot.
3. **Fase 3 — Tipos de trámite estructurados**: catálogo `tipos_solicitud` con campos
   JSON por tipo (p. ej. "corrección de acta" pide núm. de cuenta, grupo y unidad de
   aprendizaje), para poder generar estadística por tipo de trámite.
4. **Fase 4 — Notificaciones**: correo institucional al departamento destino al crear
   la solicitud y al origen al resolverla (cron + tabla `notificaciones`).
5. **Fase 5 — API REST**: exponer `/api/solicitudes` (JSON + token por departamento)
   para que sistemas externos (control escolar central, SIIA) inicien o consulten
   trámites por los mismos canales autorizados.
