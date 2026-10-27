# Flujo de Trabajo del Módulo de Comunidad

El módulo de **Comunidad** de la plataforma está compuesto por tres componentes interconectados: **Foro**, **Encuestas** y **Comisiones**. A continuación se describe el flujo de trabajo y la relación entre estos componentes.

---

## 1. Foro de la Comunidad

El foro permite la comunicación y participación de todos los miembros de la comunidad (Administradores, Titulares, Operativos, Profesores y Alumnos).

### Tipos de Publicaciones
*   **Publicación:** Hilos de conversación estándar para debatir y compartir información.
*   **Petición:** Propuestas comunitarias que buscan recolectar apoyo mediante "likes" (Me gusta) de otros usuarios para ser atendidas formalmente por las autoridades.

### Ciclo de Vida de una Petición
1.  **Creación:** Cualquier usuario con acceso al foro puede publicar una petición.
2.  **Recolección de Apoyos (Likes):** Los usuarios pueden dar o quitar su "Me gusta" (un voto por usuario por publicación).
3.  **Umbral de Apoyos:**
    *   Cada petición tiene un **umbral de apoyos configurable** (por defecto **200 likes**).
    *   Al alcanzar este umbral, el estado de la petición cambia automáticamente de `abierta` a `en_trazabilidad`.
4.  **Trazabilidad y Elevación:**
    *   Una vez en estado `en_trazabilidad`, las autoridades competentes (Dirección, Subdirecciones o titulares de departamento) pueden revisar la petición y presionar el botón **"Formar comisión"** para dar seguimiento formal al problema planteado.
5.  **Atendida:** Al crearse la comisión asociada, la petición cambia su estado a `atendida`.

---

## 2. Comisiones de Seguimiento (Trazabilidad)

Las comisiones son grupos de profesores dedicados a resolver, auditar o dar seguimiento a una problemática de la comunidad.

### Origen de una Comisión
Una comisión puede tener dos orígenes:
*   **Petición:** Originada desde el foro tras alcanzar el umbral de apoyos.
*   **Reunión:** Creada directamente por una autoridad a raíz de acuerdos de juntas o reuniones.

### Gestión de Miembros
*   **Invitación:** Los administradores o gestores invitan a profesores específicos. Estos profesores deben **aceptar** o **rechazar** formalmente la invitación en la plataforma.
*   **Voluntariado:** Cualquier profesor activo puede postularse de manera voluntaria a comisiones que estén activas.

### Bitácora de Avances
*   Los miembros confirmados de la comisión y los administradores pueden añadir registros a la bitácora para reportar avances.
*   Cada entrada de la bitácora permite subir archivos adjuntos de forma auditable (almacenados de forma segura fuera del directorio web).
*   Los archivos solo son accesibles para usuarios autenticados.

### Estados de la Comisión
*   `formacion`: La comisión está reuniendo miembros.
*   `en_seguimiento`: Se están registrando avances y bitácoras.
*   `concluida`: Se cierra el seguimiento.

---

## 3. Encuestas y Votaciones

Sistema de votación diseñado para consultas estudiantiles o de la comunidad, con un enfoque estricto en el **anonimato y la auditabilidad**.

### Flujo de Votación
1.  **Creación:** Los alumnos y administradores pueden crear encuestas definiendo un título, descripción y hasta 10 opciones de respuesta.
2.  **Participación Única:**
    *   Para evitar la duplicidad de votos, la tabla `encuesta_participantes` registra de forma persistente que el `usuario_id` ha votado en la encuesta.
3.  **Voto Anónimo:**
    *   La opción seleccionada se inserta de forma independiente en la tabla `encuesta_votos` asociada a un token aleatorio único (ej. `V-E83A92`), **sin ninguna relación o llave foránea al usuario que lo emitió**.
4.  **Constancia de Votación:**
    *   Al confirmar el voto, la plataforma muestra al usuario su **token de voto** por única vez en la respuesta. No lo conserva en su cuenta, sesión ni navegador; la persona decide si guarda una copia.
5.  **Tablero Público y Auditoría:**
    *   Cualquier persona (incluso sin iniciar sesión) puede acceder al tablero público de la encuesta para ver el conteo de votos en tiempo real.
    *   El tablero lista todos los tokens generados y la opción correspondiente (ordenados alfabéticamente por token para impedir correlaciones temporales).
    *   Los usuarios pueden buscar su token específico para validar que su voto fue registrado correctamente.
    *   Los datos se pueden exportar a formato CSV para auditoría física.
