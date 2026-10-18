"""Catálogo reproducible de encuestas y agenda de consulta FI 360."""
from __future__ import annotations
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / "Plan"

careers = [
    ("civil", "Ingeniería Civil", 152, "Licenciatura de Ingeniería Civil", 49),
    ("mecanica", "Ingeniería Mecánica", 156, "Licenciatura de Ingeniería Mecánica", 52),
    ("computacion", "Ingeniería en Computación", 143, "Licenciatura de Ingeniería en Computación", 58),
    ("electronica", "Ingeniería en Electrónica", 114, "Licenciatura de Ingeniería en Electrónica", 55),
    ("sistemas_energeticos", "Ingeniería en Sistemas Energéticos Sustentables", 68, "Licenciatura en Ingeniería en Sistemas Energéticos Sustentables", 61),
    ("inteligencia_artificial", "Ingeniería en Inteligencia Artificial", 67, "Licenciatura en Ingeniería en Inteligencia Artificial", 64),
]

def q(text: str, options: list[str], projects: list[int]) -> dict:
    return {"tipo": "opcion", "texto": text, "opciones": options, "proyectos": projects}

def open_questions() -> list[dict]:
    return [
        {"tipo": "abierta", "texto": "¿Qué necesidades principales percibes en la Facultad de Ingeniería?", "proyectos": list(range(2, 23))},
        {"tipo": "abierta", "texto": "Escribe propuestas concretas de mejora para atender esas necesidades.", "proyectos": list(range(2, 23))},
    ]

likert = ["Muy favorable", "Favorable", "Regular", "Desfavorable", "Muy desfavorable", "No tengo información"]
availability = ["Disponible y suficiente", "Disponible pero insuficiente", "No disponible", "No lo sé"]
followup = ["Sistemático y documentado", "Ocasional", "No se realiza", "No lo sé"]

surveys: list[dict] = []

def add(id: str, title: str, audience: str, segment: str, target: int | None,
        basis: str, questions: list[dict], crosses: list[tuple[int, int, str]],
        *, career: str | None = None, manual_page: int | None = None,
        program: bool = False) -> None:
    numbered = [{**item, "id": f"{id}-P{i:02d}"} for i, item in enumerate(questions + open_questions(), 1)]
    surveys.append({
        "id": id, "titulo": title, "destinatarios": audience, "segmentacion": segment,
        "meta_respuestas": target, "base_meta": basis, "carrera": career,
        "pagina_manual": manual_page, "seleccion_programa": program,
        "anonima": True, "preguntas": numbered,
        "cruces": [{"fila": f"{id}-P{row:02d}", "columna": f"{id}-P{column:02d}", "titulo": label}
                    for row, column, label in crosses],
    })

add("C01", "Estudiantes de materias propedéuticas", "Estudiantes de los primeros semestres de las seis licenciaturas", "carrera", 700,
    "Referencia operativa: suma del nuevo ingreso 2025–2026 por las seis licenciaturas (tablero 4003); confirmar padrón vigente de propedéuticas.", [
    q("¿En qué semestre cursas actualmente la mayoría de tus unidades de aprendizaje?", ["Primero", "Segundo", "Tercero", "Otro de los primeros semestres"], [2]),
    q("¿Qué área te ha presentado mayor dificultad académica?", ["Matemáticas", "Física", "Programación", "Química u otra ciencia básica", "Ninguna dificultad principal"], [2,9]),
    q("¿Qué tan probable consideras continuar sin interrupción el próximo semestre?", ["Muy probable", "Probable", "Incierto", "Poco probable", "No continuaré"], [2]),
    q("¿Qué barrera pesa más en tu trayectoria?", ["Bases académicas", "Recursos económicos", "Horarios o transporte", "Acceso a equipo o software", "Bienestar o integración", "Ninguna"], [2,12,18]),
    q("¿Cómo valoras el acceso a tutoría o asesoría académica?", ["Útil y oportuno", "Existe, pero es insuficiente", "No sé cómo acceder", "No está disponible", "No la he necesitado"], [2,9]),
    q("¿Cómo valoras el acceso a laboratorios, software y conectividad para tus materias?", availability, [11,12]),
    q("¿Qué apoyo tendría mayor efecto en tu permanencia?", ["Asesorías de materias básicas", "Tutoría personalizada", "Apoyo económico", "Horarios flexibles", "Equipo y software", "Bienestar y pertenencia"], [2,9,18]),
    q("¿Cómo percibes el ambiente de inclusión y respeto en la Facultad?", likert, [18,19]),
], [(2,3,"Dificultad académica y riesgo de interrupción"),(4,5,"Barreras y acceso a tutoría")])

add("C02", "Profesorado de licenciatura", "Profesoras y profesores que imparten docencia en las seis licenciaturas", "carrera", 284,
    "Referencia operativa: 284 integrantes de personal académico en 2025 (tablero 4031); el padrón por carrera debe confirmarse y una persona puede impartir en más de un programa.", [
    q("¿Qué problema afecta más el avance de tu estudiantado?", ["Bases de primer ingreso", "Reprobación posterior", "Abandono", "Egreso y titulación", "No observo un problema dominante"], [2,9]),
    q("¿Con qué frecuencia se usan datos de trayectoria para activar apoyos?", ["Cada semestre", "Una vez al año", "Sólo ante casos aislados", "No se usan", "No lo sé"], [2,9,15]),
    q("¿Qué tan suficientes son laboratorios, software y materiales para tus unidades de aprendizaje?", availability, [11,12]),
    q("¿Cómo se da seguimiento a recomendaciones de evaluación o acreditación?", followup, [2,9,15]),
    q("¿Cuál es tu principal necesidad de formación docente?", ["Didáctica de ciencias básicas", "Evaluación del aprendizaje", "Herramientas digitales", "Inglés y colaboración internacional", "Actualización disciplinar", "No identifico una"], [9]),
    q("¿Qué limita más el trabajo docente?", ["Carga y tiempo", "Equipo y espacios", "Coordinación de procesos", "Información del estudiantado", "Ninguna de las anteriores"], [9,10,11]),
    q("¿Qué tan útiles son los vínculos con egresados y empleadores para actualizar el programa?", likert, [2,5,20]),
], [(1,2,"Problema de trayectoria y uso de alertas"),(3,4,"Recursos académicos y seguimiento de acreditación")])

add("C03", "Profesorado de estudios avanzados", "Profesoras y profesores de maestría, doctorado y especialidad", "programa", 20,
    "Meta operativa provisional de 20 respuestas; no existe en los tableros consultados un padrón de docentes de posgrado por programa.", [
    q("¿Cuál es la barrera principal para la continuidad del estudiantado de posgrado?", ["Financiamiento o becas", "Tiempo para investigación", "Dirección de tesis", "Infraestructura", "Pertinencia del tema", "No identifico una"], [3]),
    q("¿Cómo valoras la disponibilidad de equipo, software y acervo de investigación?", availability, [3,4,11,12]),
    q("¿Cómo se documentan los resultados de proyectos y cuerpos académicos?", followup, [4,15]),
    q("¿Qué tan frecuente es el retraso en la obtención del grado?", ["Muy frecuente", "Frecuente", "Ocasional", "Raro", "No cuento con datos"], [3]),
    q("¿Cuál es la vía más viable para ampliar la colaboración externa?", ["Sector público", "Sector productivo", "Sector social", "Otras universidades", "Aún debe definirse"], [4,5]),
    q("¿Cómo se usa la opinión de egresados de posgrado para mejorar los programas?", followup, [3,5]),
], [(1,4,"Barreras de continuidad y retraso del grado"),(2,3,"Recursos de investigación y seguimiento de resultados")],program=True)

add("C04", "Personal administrativo", "Personal administrativo de todas las áreas de la Facultad", "facultad", 79,
    "Referencia operativa: 79 integrantes de personal administrativo en 2025 (tablero 4033); confirmar padrón vigente.", [
    q("¿Qué proceso requiere atención más urgente?", ["Servicios escolares", "Compras y recursos", "Mantenimiento", "Tecnologías de información", "Atención al público", "Otro proceso"], [10,11,12,13]),
    q("¿Cómo valoras los recursos y herramientas para realizar tu trabajo?", availability, [10,11,12]),
    q("¿Cómo afecta la disponibilidad de personal a la continuidad del servicio?", ["Sin afectación", "Afectación leve", "Afectación moderada", "Afectación grave", "No lo sé"], [10,13]),
    q("¿Cómo se priorizan y atienden reportes de mantenimiento y seguridad?", followup, [11,17]),
    q("¿Cómo valoras la comunicación entre áreas sobre acuerdos y trámites?", likert, [10,13,21]),
    q("¿Qué capacitación sería más útil?", ["Atención y procesos", "Herramientas digitales", "Seguridad y protección", "Normatividad", "Gestión de datos", "Otra"], [10,14,17]),
], [(1,3,"Procesos críticos y capacidad de personal"),(2,5,"Herramientas de trabajo y comunicación")])

coord_common = [
    q("¿Cuál es el estado general de los procesos bajo tu coordinación?", ["Con evidencia y seguimiento", "Operan con seguimiento parcial", "Operan sin seguimiento suficiente", "Requieren rediseño", "No es posible valorarlo"], list(range(2,23))),
    q("¿Cuál es el obstáculo principal para cumplir las metas del área?", ["Recursos o equipo", "Personal o carga", "Datos y registros", "Coordinación de procesos", "Vinculación externa", "No hay obstáculo dominante"], list(range(2,23))),
    q("¿Qué calidad tiene la evidencia disponible para evaluar resultados?", ["Completa y actualizada", "Parcial", "Desactualizada", "No disponible"], [15]),
]
docencia_extra = [
    q("¿Qué trayectoria requiere mayor intervención en tu licenciatura?", ["Primer año y propedéuticas", "Permanencia posterior", "Egreso", "Titulación", "Ninguna dominante"], [2,9]),
    q("¿En qué estado está el seguimiento de recomendaciones de evaluación externa?", ["Plan vigente con evidencias", "Plan parcial", "Pendiente de iniciar o actualizar", "Aún no aplica"], [2,9,15]),
    q("¿Qué recurso es más urgente para cumplir el plan de estudios?", ["Docentes", "Laboratorios y seguridad", "Software y conectividad", "Acervo", "Espacios", "Ninguno"], [9,11,12]),
]
for index,(slug,name,_,_,page) in enumerate(careers,5):
    add(f"C{index:02d}",f"Coordinación de Docencia: {name}",f"Persona responsable de la Coordinación de Docencia de {name}","coordinacion",1,
        f"Una respuesta esperada de la coordinación. Manual de Organización FI, pp. {page}–{page+2}.",
        coord_common+docencia_extra,[(1,2,"Estado de procesos y obstáculo principal"),(3,5,"Calidad de evidencia y seguimiento externo")],career=slug,manual_page=page)

coord_units = [
    ("C11","Coordinación de Investigación",75,[
        q("¿Qué limita más la consolidación de proyectos y cuerpos académicos?", ["Financiamiento", "Tiempo y carga", "Colaboración externa", "Infraestructura", "Gestión documental", "Ninguno"], [4,5]),
        q("¿Cómo se vincula el reconocimiento SNII/PRODEP con resultados pertinentes?", followup, [4,15]),
    ]),
    ("C12","Coordinación de Estudios Avanzados",79,[
        q("¿Qué etapa de la trayectoria de posgrado requiere más atención?", ["Ingreso", "Permanencia", "Investigación de tesis", "Obtención del grado", "Seguimiento de egresados"], [3]),
        q("¿Cómo se da seguimiento a recomendaciones de evaluadores de posgrado?", followup, [3,15]),
    ]),
    ("C13","Coordinación de Difusión Cultural",82,[
        q("¿Qué limita más la participación cultural y deportiva?", ["Horarios", "Espacios", "Difusión", "Presupuesto", "Interés no medido", "No lo sé"], [7,8]),
        q("¿Cómo se mide la participación y alcance de las actividades?", followup, [7,8,21]),
    ]),
    ("C14","Coordinación de Extensión y Vinculación",84,[
        q("¿Qué registro requiere atención prioritaria?", ["Egresados", "Empleadores", "Convenios", "Prácticas", "Servicio social", "Becas"], [5,18,20]),
        q("¿Cómo se incorpora la opinión de egresados y empleadores a la mejora académica?", followup, [2,5,20]),
    ]),
    ("C15","Coordinación de Planeación",87,[
        q("¿Qué dificulta más medir las metas del plan?", ["Definiciones inconsistentes", "Registros incompletos", "Falta de responsables", "Cortes temporales distintos", "No hay dificultad dominante"], [15]),
        q("¿Con qué periodicidad se revisan metas, fuentes y evidencias con las áreas?", ["Trimestral", "Semestral", "Anual", "Sólo ante solicitudes", "No se revisan"], [15]),
    ]),
]
for id,title,page,extra in coord_units:
    add(id,title,f"Persona responsable de la {title}","coordinacion",1,
        f"Una respuesta esperada de la coordinación. Manual de Organización FI, pp. {page}–{page+2}.",
        coord_common+extra,[(1,2,"Estado de procesos y obstáculo principal"),(3,4,"Calidad de evidencia y prioridad específica")],manual_page=page)

add("C16", "Estudiantes de licenciatura de semestres avanzados",
    "Estudiantes de cuarto semestre en adelante de las seis licenciaturas", "carrera", 360,
    "Meta operativa provisional de 60 respuestas por licenciatura; confirmar el padrón vigente de estudiantes de cuarto semestre en adelante.", [
    q("¿En qué tramo de la licenciatura cursas actualmente la mayoría de tus unidades de aprendizaje?",
      ["Cuarto o quinto semestre", "Sexto o séptimo semestre", "Octavo semestre o posterior", "Trayectoria irregular o sin semestre definido"], [2]),
    q("¿Qué factor dificulta más que avances conforme al tiempo previsto?",
      ["Reprobación o repetición de asignaturas", "Horarios, seriación u oferta de grupos", "Trabajo, economía o transporte", "Acceso a laboratorios, software o materiales", "Bienestar, cuidados o salud", "No identifico una dificultad principal"], [2,9,11,12,18]),
    q("¿Cómo valoras el acompañamiento académico para resolver rezago, elegir carga y planear el egreso?", likert, [2,9]),
    q("¿Cómo valoras la disponibilidad de laboratorios, software, conectividad y materiales para las asignaturas avanzadas?", availability, [11,12]),
    q("¿Qué oportunidad formativa necesitas fortalecer antes de egresar?",
      ["Prácticas o estancias profesionales", "Proyectos de investigación o innovación", "Movilidad o colaboración externa", "Emprendimiento y vinculación", "Servicio social con aplicación disciplinar", "Ninguna de las anteriores"], [4,5,20]),
    q("¿Qué tan probable consideras concluir la licenciatura en el tiempo que actualmente tienes previsto?",
      ["Muy probable", "Probable", "Incierto", "Poco probable", "No puedo estimarlo"], [2]),
    q("¿Cómo valoras la pertinencia de lo aprendido para tu práctica profesional o estudios posteriores?", likert, [2,5,20]),
    q("¿Cómo percibes el ambiente de inclusión, respeto y pertenencia en esta etapa de la licenciatura?", likert, [7,18,19]),
], [(2,6,"Barreras de avance y expectativa de conclusión"),(4,5,"Recursos académicos y oportunidades antes del egreso")])

add("C17", "Estudiantes de posgrado", "Estudiantes inscritos en programas de estudios avanzados", "programa", 60,
    "Meta operativa provisional de 10 respuestas por programa de estudios avanzados; confirmar la matrícula vigente y ajustar el denominador por programa.", [
    q("¿En qué etapa principal de tu programa te encuentras?",
      ["Cursos iniciales", "Cursos avanzados", "Protocolo o proyecto de investigación", "Trabajo de campo, laboratorio o desarrollo", "Redacción de tesis o trabajo terminal", "Trámite de evaluación u obtención del grado"], [3]),
    q("¿Cuál es el principal obstáculo para avanzar conforme a tu plan de trabajo?",
      ["Tiempo disponible", "Financiamiento o beca", "Dirección o asesoría", "Equipo, software o información", "Trámites y coordinación", "Definición o alcance del proyecto", "No identifico un obstáculo principal"], [3,4,11,12]),
    q("¿Cómo valoras la disponibilidad y oportunidad de la dirección, tutoría o comité académico?", likert, [3]),
    q("¿Cómo valoras la disponibilidad de laboratorios, equipo, software, bases de datos y acervo?", availability, [3,4,11,12]),
    q("¿Qué apoyo tendría mayor efecto para concluir tu programa?",
      ["Beca o apoyo económico", "Tiempo protegido para investigación", "Asesoría metodológica o disciplinar", "Acceso a infraestructura e información", "Apoyo para publicación, movilidad o congreso", "Orientación de trámites y obtención del grado"], [3,4]),
    q("¿Cómo valoras las oportunidades de participar en proyectos, publicaciones, movilidad o colaboración externa?", likert, [3,4,5]),
    q("¿Qué tan probable consideras obtener el grado en el plazo que actualmente tienes previsto?",
      ["Muy probable", "Probable", "Incierto", "Poco probable", "No puedo estimarlo"], [3]),
    q("¿Cómo valoras la pertinencia del programa y el ambiente de inclusión, respeto y colaboración?", likert, [3,18,19]),
], [(2,7,"Obstáculos de avance y expectativa de obtención del grado"),(3,4,"Acompañamiento académico y recursos para la investigación")],program=True)

surveys.sort(key=lambda item: int(item["id"][1:]))

agenda = [
    ("AG-01","Trayectorias de primer año","Identificar asignaturas críticas, cambios en reprobación y alertas tempranas por licenciatura."),
    ("AG-02","Permanencia, egreso y titulación","Contrastar abandono, eficiencia por cohorte, EGEL y apoyos que funcionan o faltan."),
    ("AG-03","Evaluación y acreditación","Revisar vigencia, recomendaciones, evidencias y responsables de los planes de mejora."),
    ("AG-04","Docencia y planta académica","Revisar plantilla, formación docente, tutoría, cargas y necesidades de apoyo."),
    ("AG-05","Laboratorios, software y seguridad","Priorizar mantenimiento, equipamiento, conectividad, prácticas y riesgos."),
    ("AG-06","Vinculación y pertinencia","Escuchar observaciones de egresados, empleadores y organizaciones receptoras; identificar vacíos de información."),
    ("AG-07","Participación y bienestar estudiantil","Examinar capítulos, inclusión, actividades culturales/deportivas y barreras de acceso."),
    ("AG-08","Priorización FI 360","Acordar tres necesidades verificables por programa, recursos disponibles, responsables y evidencia requerida."),
]

output = {
    "version": "2026-09-22", "marco": "FI 360, Programa de Trabajo 2026–2030",
    "fuentes": [
        {"nombre":"FI 360", "url":"https://drive.google.com/file/d/1BFLENt1LFr_Ow093_JR547QsQyV-hIER/view?usp=drivesdk"},
        {"nombre":"Manual de Organización de la Facultad de Ingeniería, versión 2, 20/05/2026", "url":"https://drive.google.com/file/d/1QMWh_XDu6QCpoNJKApbUkiItJVfCSy7K/view?usp=drivesdk"},
    ],
    "carreras": [{"id":id,"nombre":name,"nuevo_ingreso_2025_2026":count,"nombre_tablero":source} for id,name,count,source,_ in careers],
    "programas_posgrado": ["Maestría en Ciencias de la Ingeniería","Maestría en Ingeniería de la Cadena de Suministro","Maestría en Movilidad y Transporte","Doctorado en Ciencias de la Ingeniería","Doctorado en Ciencias, Tecnología Biomédica y Control","Otro programa de estudios avanzados"],
    "agenda": [{"id":id,"tema":title,"orientacion":description} for id,title,description in agenda],
    "encuestas": surveys,
    "nota_privacidad": "No se solicitan nombres ni correos y la aplicación no guarda la dirección IP en el registro de encuestas. El servidor puede conservar registros técnicos. La coordinación única puede ser reconocible por su función; sus respuestas abiertas no se publican. Una cookie limita duplicados por navegador, pero no verifica pertenencia ni impide respuestas desde otro dispositivo.",
}
(PLAN / "instrumentos.json").write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding="utf-8")
print(f"{len(surveys)} cuestionarios, {sum(len(s['preguntas']) for s in surveys)} preguntas, {len(agenda)} asuntos de agenda")
