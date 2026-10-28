# Guía de clasificación de avisos y eventos (para IA y para Difusión Cultural)

Esta guía define cómo se clasifica cada aviso del módulo «Avisos y eventos» de Traza FI. La usan la persona encargada
de Difusión Cultural al llenar el formulario y el botón «Sugerir clasificación», que se la entrega a la IA junto con el
título y la descripción del aviso. Solo la sección entre las marcas `reglas` se envía a la IA; si se cambian los
catálogos en `app/Modules/Eventos/Clasificacion.php` o `EventosController::CATEGORIAS`, hay que actualizar esta guía.

<!-- reglas -->
## Reglas de clasificación

Clasifica un aviso de la Facultad de Ingeniería de la UAEMEX. Devuelve solo los atributos pedidos, usando
exclusivamente las claves de los catálogos. Si un dato no aparece en el aviso, no lo inventes: deja el valor vacío o
nulo, porque un atributo vacío significa «sin restricción» (llega a todos).

### tema (uno solo, el propósito principal)
- `reconocimientos`: premios, galardones, distinciones, registro de logros, ganadores de concursos.
- `desarrollo_profesional`: habilidades blandas, liderazgo, empleabilidad, bolsa de trabajo, reclutamiento, programas de
  trainees, ferias de empleo, charlas de empresas, CV y entrevistas.
- `academico`: clases, tutorías, asesorías, exámenes, cursos o talleres de contenido de la carrera, cambios de horario.
- `investigacion`: congresos, publicaciones, proyectos de investigación, posgrado, seminarios de investigación.
- `emprendimiento`: emprendimiento, innovación, hackatones, incubadoras, concursos de proyectos.
- `cultural`: arte, música, teatro, danza, cine, lectura, exposiciones, actividades culturales.
- `deportivo`: torneos, activación física, deporte y salud física.
- `becas`: becas, apoyos económicos, manutención.
- `movilidad`: movilidad, intercambio, estancias.
- `servicio_social`: servicio social, prácticas profesionales.
- `tramites`: inscripción, reinscripción, titulación, credenciales, constancias, fechas de Control Escolar.
- `comunidad`: bienestar, salud mental, inclusión, igualdad de género, voluntariado.
- `otro`: si nada de lo anterior aplica.
Si hay duda entre dos temas, elige el que describe lo que la persona obtiene: una conferencia de una empresa sobre su
programa de trainees es `desarrollo_profesional`, no `academico`.

### categoria (formato del aviso)
`conferencia` (una charla o ponencia), `taller` (práctico), `curso` (varias sesiones o diplomado), `academico`,
`cultural`, `deportivo`, `convocatoria` (invita a registrarse, postularse o enviar algo antes de una fecha límite y no
es una sesión con hora), `reunion` (reunión de trabajo, normalmente de profesores), `otro`.

### publico (lista; vacía = toda la comunidad)
`alumno`, `egresado`, `profesor`. Marca solo a quienes el aviso nombra o a quienes claramente sirve. Una conferencia
para estudiantes es `["alumno"]`. Un programa de trainees o una bolsa de trabajo suma `egresado`. Un registro de premios
para «alumnos, egresados y profesores» lleva los tres. Una reunión de academia es `["profesor"]`.

### semestre_min y semestre_max (1 a 10; nulos = todos)
Solo si el aviso lo dice o el propósito lo exige: «últimos semestres», «por egresar», programas de trainees, prácticas
profesionales o servicio social → 7 a 10 (servicio social suele ser desde 6). «Nuevo ingreso» o «primer semestre» → 1 a
2. Si no se menciona, déjalos nulos. No apliques semestres a profesores ni egresados.

### modalidad
`presencial` (lugar físico: auditorio, aula, explanada), `en_linea` (Zoom, Teams, Meet, transmisión, o solo un enlace
para conectarse), `hibrida` (ambas). Nula si no se sabe.

### formacion_integral y puntos
`formacion_integral` es verdadero cuando asistir aporta a la formación del alumno y conviene tener evidencia para los
organismos acreditadores: conferencias, talleres, cursos, actividades culturales y deportivas. Es falso en
convocatorias, trámites, reuniones y avisos informativos. Puntos sugeridos por asistencia validada: conferencia 10,
actividad cultural o deportiva 10, taller 15, curso 20; 0 si `formacion_integral` es falso.

### Ejemplos
1. «¿Ganaste un premio en 2026? Regístralo. Si eres alumna, alumno, egresada/o o profesor/a de la FI y en 2026 ganaste
   un premio… Fecha límite: 12 de noviembre.» → tema `reconocimientos`, categoria `convocatoria`, publico
   `["alumno","egresado","profesor"]`, semestres nulos, modalidad `en_linea`, formacion_integral falso, puntos 0.
2. «Conferencia: Liderazgo. Imparte Andrea Elizabeth García Villafaña (LABSA). Martes 10 de noviembre, 13:00.
   Conéctate con el enlace.» → tema `desarrollo_profesional`, categoria `conferencia`, publico `["alumno"]`,
   semestres nulos, modalidad `en_linea`, formacion_integral verdadero, puntos 10.
3. «Conferencia: Campaña de Trainees 2027 Avante Textil… para quienes están por terminar la carrera.» → tema
   `desarrollo_profesional`, categoria `conferencia`, publico `["alumno","egresado"]`, semestre_min 7, semestre_max
   10, modalidad `en_linea`, formacion_integral verdadero, puntos 10.
<!-- /reglas -->

## Lo que no clasifica la IA (lo decide Difusión Cultural)

Las **carreras** se marcan a mano en el formulario: sin carreras marcadas, el aviso llega a todas. Se marcan solo cuando
el evento es de una carrera (por ejemplo, una conferencia de estructuras para Ingeniería Civil). Tampoco los decide la
IA: la imagen y el enlace del post de Facebook (obligatorios), el enlace del evento (obligatorio si es en línea o
híbrido), la fecha límite, las horas de formación y la palabra clave de la sesión (si se deja vacía se genera).

## Cómo se usan los atributos

En «Avisos y eventos», la vista «Para mí» muestra lo dirigido al público, la carrera y el semestre de cada persona y,
si eligió temas en su perfil, solo esos temas; «Todos» muestra todo con filtros por carrera, semestre, tema, formato y
modalidad. Al publicar, se avisa en su bandeja a quienes siguen el tema y a quienes va dirigido.

Los eventos con formación integral registran asistencia de tres formas: el QR que se proyecta en el evento y la palabra
clave que se dice en la sesión validan la asistencia al momento; una constancia o capturas de pantalla quedan
pendientes hasta que Difusión Cultural las revisa. Cada asistencia validada suma los puntos del evento y tiene un folio
verificable en `/verificar/asistencia/FOLIO`, que el alumno comparte con el profesor que le dio permiso o pone en su
CV. Los niveles por periodo son Participante (1+), Bronce (30+), Plata (60+) y Oro (100+).

La bitácora de evidencias de cada evento (fotografías, listas de asistencia, grabación, constancias, publicación) se
marca por carrera cuando aplica. El reporte «Evidencias por carrera (acreditación)» reúne eventos, asistentes
validados y evidencias por carrera y periodo, y se descarga en Excel para los organismos acreditadores.
