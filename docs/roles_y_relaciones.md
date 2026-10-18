# Matriz de Roles, Permisos Granulares y Relaciones Interdepartamentales

Este documento detalla el diseño técnico del sistema de permisos granulares y los canales de comunicación cruzados entre los departamentos de la Facultad de Ingeniería de la UAEMéx. Este diseño sirve como base para implementar el Control de Acceso Basado en Roles (RBAC) y la API de flujo de trabajo en la plataforma.

---

## 🔑 1. Principio de Aislamiento Granular

Para evitar que los coordinadores y jefes de departamento se saturen de información irrelevante o comprometan la seguridad de otros módulos, la plataforma implementa una división estricta:
1. **Acceso Local Principal (Mis Funciones)**: Al iniciar sesión, el usuario entra directamente a las pantallas y bases de datos exclusivas de su área.
2. **Acceso Colaborativo Conectado (Relaciones)**: Canales de interacción directa con otros departamentos pre-aprobados. No otorga control total, sino la capacidad de iniciar solicitudes, enviar datos y consultar estados específicos (ej: un coordinador no puede editar un acta escolar, pero puede solicitar a Servicios Escolares que la habilite).

---

## 📊 2. Matriz de Relaciones e Interacciones de la Demo

La siguiente tabla resume qué departamentos se conectan en el flujo administrativo para resolver trámites:

| Rol del Usuario | Módulo Propio | Departamento Relacionado | Acción / Interacción Permitida |
| :--- | :--- | :--- | :--- |
| **Coordinador de Docencia** (ej: Ing. en Computación) | Planeación de clases, asignación de profesores | **Servicios Escolares** | Consultar listas de alumnos, verificar folios de inscripción y solicitar correcciones de actas. |
| | | **Tutoría Académica** | Asignar tutores a alumnos reprobados, reportar alumnos con riesgo de baja. |
| | | **Evaluación Profesional** | Proponer profesores jurados para exámenes profesionales de egresados. |
| **Jefe de Servicios Escolares** | Inscripciones, actas de calificaciones, certificados | **Coordinadores de Docencia** | Solicitar cupos de materias, notificar el cierre de periodos de inscripción. |
| | | **Evaluación Profesional** | Emitir constancias de 100% de créditos obligatorias para iniciar la titulación. |
| **Coord. de Investigación** | Proyectos científicos, patentes, investigadores SNII | **Estudios Avanzados** | Integrar alumnos de posgrado a proyectos de investigación con financiamiento. |
| | | **Subdirección Administrativa** | Enviar requerimientos de compra de insumos de laboratorio. |
| | | **Centros de Investigación** | Supervisar la disponibilidad de infraestructura de investigación. |
| **Subdirector Administrativo** | Recursos humanos, presupuestos, mantenimiento | **Departamento de TIC** | Aprobar compras de hardware, renovaciones de licencias y mantenimiento del site de red. |
| | | **Coordinación de Investigación** | Validar fondos financieros de convocatorias externas. |

---

## 🛠️ 3. Especificación Técnica de los Endpoints de Interacción (Sugerido para API)

Para implementar estos módulos interconectados, la arquitectura de microservicios o base de datos deberá soportar solicitudes cruzadas estructuradas. Ejemplos de flujos:

### Flujo 1: Asignación de Jurado de Examen Profesional
- **Iniciador**: `Coordinador de Docencia`
- **Destinatario**: `Departamento de Evaluación Profesional`
- **Acción API**: `POST /api/evaluacion-profesional/solicitar-jurado`
- **Payload**:
  ```json
  {
    "alumno_cuenta": "1826354",
    "programa_id": "IIA",
    "fecha_propuesta": "2026-07-15",
    "jurados": ["Dr. Javier Salas", "Dra. Laura Díaz", "M. en C. Roberto Gómez"]
  }
  ```

### Flujo 2: Reporte de Alumno en Riesgo Académico
- **Iniciador**: `Coordinador de Docencia`
- **Destinatario**: `Departamento de Tutoría Académica`
- **Acción API**: `POST /api/tutorias/reportar-riesgo`
- **Payload**:
  ```json
  {
    "alumno_cuenta": "1923456",
    "motivo": "Reprobación consecutiva de Física Clásica",
    "semestre": 3,
    "solicita_tutor_especifico": "Dr. Javier Salas"
  }
  ```

### Flujo 3: Solicitud de Mantenimiento de Laboratorio
- **Iniciador**: `Coordinación de Investigación` (o responsable de laboratorio)
- **Destinatario**: `Subdirección Administrativa`
- **Acción API**: `POST /api/mantenimiento/solicitar`
- **Payload**:
  ```json
  {
    "laboratorio_id": "LAB_METROLOGIA",
    "edificio": "Edificio G",
    "tipo_mantenimiento": "Calibración preventiva de equipos de medición",
    "prioridad": "Alta",
    "estimado_costo": 12500.00
  }
  ```
