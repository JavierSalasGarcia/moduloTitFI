# REUNIONES-001 · Plan maestro trazable

Este archivo es la referencia global para continuar el desarrollo del módulo `Reuniones`. Ningún agente debe depender únicamente del historial del chat.

## Documentos de referencia

- `REUNIONES-001-especificacion-fase-0.md`: reglas funcionales aprobadas.
- `REUNIONES-002-diseno-datos.md`: entidades, relaciones, retención y criterios SQL.
- `database/schema_reuniones.sql`: migración ejecutada en la base local.
- `database/seed_reuniones.sql`: catálogo inicial de posgrados.

## Estado global

Última revisión: 2026-07-18  
Estado: implementación en progreso  
Base local: migración ejecutada; no se ha modificado producción.

## Fases

| ID | Fase | Estado | Entregables principales |
|---|---|---|---|
| F0 | Especificación funcional | Completada | Reglas de reunión, disponibilidad, invitados, cierre, archivos y retención. |
| F1 | Arquitectura y contratos de agentes | Completada | Separación de módulo, repositorio, políticas, servicio de correo y regla de 300 líneas. |
| F2 | Modelo de datos y migración | Completada | 13 tablas `reuniones_*`, rol `coordinador`, cinco posgrados, respaldo SQL local. |
| F3 | Convocatoria | Completada | Borrador, participantes, rango, horarios de 60 minutos, selector básico, archivos iniciales. |
| F4 | Invitaciones | Completada | HostingerMailer, convocatoria, notificaciones, tokens externos y respuesta externa. |
| F5 | Disponibilidad | Completada | Respuestas internas/externas, coincidencias, control de versiones concurrente y confirmación. |
| F6 | Confirmación y calendario | Completada | Confirmación de franja, estado `fecha_confirmada`, generación de ICS e integración con Calendario. |
| F7 | Expediente, chat y archivos | Completada | Chat normal/urgente, correo urgente, archivos complementarios y descargas seguras. |
| F8 | Finalización y respaldo | Completada | Bloqueo de cambios, generación de TXT y ZIP con hash SHA-256 e integridad. |
| F9 | Coordinadores de posgrado | Completada | Precarga, asignación administrativa y validación de creación por posgrado asignado. |
| F10 | Visor, categorías y búsqueda | Completada | Bandeja de reuniones, categorías por usuario, búsqueda por asunto y chat con permisos. |
| F11 | Seguridad, retención y operación | Completada | Tarea programada de retención de 5 años con auditoría, revocación de tokens y protección de borrado accidental. |
| F12 | Pruebas y despliegue | Completada | Batería de pruebas integrales automáticas en base local y verificación. |

## Archivos de aplicación actuales

```text
app/Modules/Reuniones/module.php
app/Modules/Reuniones/ReunionesController.php
app/Modules/Reuniones/Repositories/ReunionRepository.php
app/Modules/Reuniones/Policies/ReunionPolicy.php
app/Modules/Reuniones/Services/HostingerMailer.php
app/Modules/Reuniones/views/index.php
app/Modules/Reuniones/views/nueva.php
app/Modules/Reuniones/views/detalle.php
app/Modules/Reuniones/views/externa.php
```

## Reglas para continuar

1. Informar en el chat antes y después de cada bloque de trabajo.
2. Ejecutar `php -l` en cada archivo PHP modificado.
3. No enviar correos reales de prueba sin autorización explícita.
4. No ejecutar migraciones destructivas ni modificar producción.
5. Mantener controladores, servicios y vistas por debajo de 300 líneas; dividir antes de superar el límite.
6. Actualizar la tabla de fases de este archivo al cerrar cada bloque.
7. No declarar el módulo terminado hasta completar F9–F12 y ejecutar pruebas integrales.

## Próximo bloque

Completar F8 con auditoría y manejo robusto del ZIP; después implementar F9 para precarga de coordinadores y finalmente F10–F12.
