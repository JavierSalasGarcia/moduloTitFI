"""Regenera la propuesta FI desde el PRDI y las series públicas de Plan/."""
from __future__ import annotations

import json
import math
import re
import statistics
from pathlib import Path

from plan_memoria_metodologia import complete_projects

ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / "Plan"
prdi = json.loads((PLAN / "PRDI.json").read_text(encoding="utf-8"))
memory = json.loads((PLAN / "memoria_calculo_2026.json").read_text(encoding="utf-8"))


def category(number: int) -> dict:
    return json.loads(next(PLAN.glob(f"{number}_*.json")).read_text(encoding="utf-8"))


def series(number: int, metric: str, *, level: str = "facultad") -> list[tuple[str, float]]:
    data = category(number)
    sections = [data["facultad"]] if level == "facultad" else [
        s for name, s in data["carreras"].items()
        if (name.startswith(("Maestría", "Doctorado", "Especialidad"))) == (level == "posgrado")
    ]
    values: dict[str, float] = {}
    for section in sections:
        for indicator in section["indicadores"]:
            if metric not in indicator["metricas"]:
                continue
            year_field = next((x for x in indicator["metricas"] if x in ("Año", "Ciclo escolar")), None)
            if not year_field:
                continue
            for row in indicator["registros"]:
                value = row.get(metric)
                year = row.get(year_field)
                if isinstance(value, (int, float)) and year is not None:
                    values[str(year)] = values.get(str(year), 0) + value
            break
    return sorted(values.items())


def academic_cross(number: int) -> tuple[str, list[dict]]:
    if number not in (2, 15):
        return '', []
    sources = {n: category(n) for n in (4014, 4015, 4016)}
    common = set.intersection(*(set(data['carreras']) for data in sources.values()))
    rows = []
    for name in common:
        values = {}
        for n, data in sources.items():
            records = [r for i in data['carreras'][name]['indicadores'] for r in i['registros']
                       if isinstance(r.get('Programa educativo'), (int, float))]
            if records:
                last = max(records, key=lambda r: str(r.get('Ciclo escolar', '')))
                values[n] = (str(last.get('Ciclo escolar', '')), float(last['Programa educativo']))
        if len(values) == 3 and len({v[0] for v in values.values()}) == 1:
            rows.append((name, values[4014][1], values[4015][1], values[4016][1], values[4014][0]))
    if len(rows) < 3:
        return '', []
    high_r = max(rows, key=lambda r: r[3]); low_r = min(rows, key=lambda r: r[3])
    high_a = max(rows, key=lambda r: r[2]); low_a = min(rows, key=lambda r: r[2])
    high_e = max(rows, key=lambda r: r[1]); low_e = min(rows, key=lambda r: r[1])
    short = lambda name: name.replace('Licenciatura de Ingeniería en ', '').replace('Licenciatura de Ingeniería ', '').replace('Licenciatura en Ingeniería en ', '')
    idpart = f'p{number:02d}'
    charts = [
        {'archivo': f'cruce_4014_4015_4016_dispersion_{idpart}.jpg', 'tipo': 'dispersion',
         'fuentes': [4014, 4015, 4016], 'periodo': rows[0][4]},
        {'archivo': f'cruce_4014_4015_4016_tendencia_{idpart}.jpg', 'tipo': 'tendencia',
         'fuentes': [4014, 4015, 4016], 'periodo': rows[0][4]},
    ]
    prose = (f"Lectura conjunta de trayectorias. En {rows[0][4]}, entre {len(rows)} licenciaturas con datos simultáneos, "
             f"{short(high_r[0])} tuvo la mayor reprobación ({high_r[3]:.1f} %) y {short(low_r[0])} la menor ({low_r[3]:.1f} %); "
             f"{short(high_a[0])} presentó el mayor abandono ({high_a[2]:.1f} %) y {short(low_a[0])} el menor ({low_a[2]:.1f} %). "
             f"La eficiencia terminal osciló entre {low_e[1]:.1f} % en {short(low_e[0])} y {high_e[1]:.1f} % en {short(high_e[0])}. "
             "Las posiciones no son idénticas entre indicadores: un programa puede registrar reprobación elevada y abandono relativamente bajo, "
             "por lo que conviene contrastar apoyos, reglas de registro y cohortes antes de atribuir causas. "
             "Estos tres indicadores tienen denominadores distintos y describen relaciones, no causalidad individual. "
             "Las licenciaturas sin las tres series comparables se identifican como dato faltante, nunca como cero. "
             f"Ubicación sugerida de las gráficas: {{{charts[0]['archivo']}}} y {{{charts[1]['archivo']}}} "
             "(Sistema de Planeación y Desarrollo Institucional [SPDI], 2026b).")
    return prose, charts


def statistical_goal(number: int, metric: str, *, level: str = "facultad", name: str) -> dict:
    history = series(number, metric, level=level)
    if len(history) < 3:
        return {"descripcion": f"Establecer la línea base de {name} en 2027 y medirla cada año hasta 2030.",
                "tipo": "línea_base", "estado": "pendiente_de_validación", "indicador": metric}
    recent = history[-5:]
    changes = [b[1] - a[1] for a, b in zip(recent, recent[1:])]
    slope = statistics.median(changes)
    last_year, baseline = history[-1]
    # Se conserva como mínimo la línea base y se limita el aumento acumulado
    # a 2.5 %. El redondeo hacia abajo evita rebasar el límite en conteos bajos.
    increment = min(max(slope, 0) * 4, baseline * .025)
    target = max(baseline, math.floor(baseline + increment))
    verb = "Alcanzar" if target > baseline else "Mantener al menos"
    return {
        "descripcion": f"{verb} {target} {name} en 2030.",
        "tipo": "indicador", "indicador": metric, "valor_base": round(baseline, 4),
        "periodo_base": last_year, "valor_meta_2030": target, "unidad": "personas",
        "fuente_categoria": number, "metodo": "Mediana de los cambios anuales de hasta cinco observaciones recientes, proyectada cuatro intervalos y limitada a un aumento acumulado de 2.5 %; redondeo hacia abajo y piso en la línea base. Si la mediana es no positiva, conservar la línea base.",
        "estado": "preliminar_validar_con_memoria_de_calculo",
    }


# Cada proyecto conserva contenido propio de la FI; las relaciones de la matriz
# son explícitas y no reproducen la redacción del plan rector.
# Los textos de Rectoría permanecen íntegros y separados en PRDI.json.
CONTENT = {
 2: ("Fortalecer la formación profesional en Ingeniería mediante programas pertinentes, trayectorias acompañadas y vinculación con personas egresadas y sectores externos, para ampliar las oportunidades de ingreso, desarrollo, egreso y empleabilidad del estudiantado.",
     "Mejorar el seguimiento de las trayectorias de licenciatura para reducir el rezago y favorecer el egreso oportuno.",
     "La Facultad dará seguimiento semestral a estudiantes con riesgo académico, con criterios transparentes y atención diferenciada.",
     "Integrar matrícula, reprobación, abandono, tutoría y egreso por programa en un tablero de alertas y acordar intervenciones con cada coordinación."),
 3: ("Consolidar estudios avanzados pertinentes en ingeniería mediante seguimiento de matrícula, becas y trayectorias de investigación, para formar especialistas capaces de atender problemas regionales.",
     "Ampliar la continuidad y culminación de los programas de posgrado para fortalecer la formación científica y tecnológica.",
     "Los posgrados revisarán anualmente sus trayectorias y apoyos con datos desagregados por programa.",
     "Analizar ingreso, matrícula y egreso de cada posgrado y ajustar tutoría, difusión y apoyos según las brechas observadas."),
 4: ("Fortalecer la investigación de la Facultad mediante el acompañamiento a su planta académica y el seguimiento de productos abiertos y pertinentes, para aumentar la capacidad de respuesta a necesidades sociales.",
     "Consolidar la participación del personal académico en investigación reconocida y abierta para elevar su contribución pública.",
     "La Facultad priorizará proyectos con pertinencia social, ética y resultados verificables.",
     "Acompañar postulaciones al SNII y revisar semestralmente proyectos, productos y colaboraciones de cada cuerpo académico."),
 5: ("Impulsar soluciones de ingeniería con potencial de aplicación mediante colaboración con sectores públicos, sociales y productivos, para atender necesidades concretas del entorno.",
     "Identificar capacidades y necesidades de innovación para construir una cartera de proyectos transferibles.",
     "La Facultad documentará la pertinencia, viabilidad y beneficio social antes de promover transferencias tecnológicas.",
     "Levantar un inventario de laboratorios y prototipos y vincularlo con problemas planteados por actores externos."),
 6: ("Ampliar el acceso público al conocimiento generado en la Facultad mediante comunicación clara y repositorios abiertos, para acercar la ciencia de la ingeniería a la sociedad.",
     "Organizar los productos de divulgación para aumentar su alcance y trazabilidad.",
     "Las publicaciones de divulgación respetarán derechos de autor, accesibilidad y revisión académica.",
     "Establecer un registro de productos de divulgación y publicar una agenda anual con responsables y públicos destinatarios."),
 7: ("Fortalecer la participación cultural de la comunidad de Ingeniería mediante una oferta accesible y evaluada, para contribuir a su formación integral y sentido de pertenencia.",
     "Recuperar la participación en talleres culturales para ampliar experiencias formativas fuera del aula.",
     "La oferta cultural atenderá horarios y necesidades expresadas por el estudiantado.",
     "Consultar preferencias y barreras de asistencia y ajustar la programación semestral de talleres."),
 8: ("Promover la actividad física de la comunidad de Ingeniería mediante una programación incluyente y seguimiento de participación, para contribuir al bienestar y la convivencia.",
     "Conocer y ampliar el acceso a actividades deportivas para fortalecer hábitos saludables.",
     "La oferta deportiva considerará accesibilidad, seguridad y diversidad de horarios.",
     "Realizar un diagnóstico de espacios, horarios y participación y publicar una programación semestral."),
 9: ("Fortalecer la docencia de Ingeniería mediante formación disciplinar, didáctica y digital basada en necesidades verificadas, para mejorar la experiencia de aprendizaje.",
     "Ampliar la cobertura de acompañamiento académico y de formación docente para atender trayectorias diversas.",
     "Cada programa registrará tutoría y necesidades formativas sin divulgar datos personales del estudiantado.",
     "Relacionar datos de tutoría y personal académico con una agenda anual de actualización docente."),
10: ("Dignificar las condiciones de trabajo en la Facultad mediante escucha, formación y mejora de procesos, para favorecer el bienestar del personal académico y administrativo.",
     "Identificar necesidades de desarrollo y condiciones laborales para orientar acciones de mejora.",
     "Las acciones de mejora se definirán con participación del personal y evidencia documentada.",
     "Aplicar un diagnóstico de necesidades y acordar un programa anual de atención y formación."),
11: ("Mejorar los servicios y espacios de Ingeniería mediante mantenimiento planificado y seguimiento de su uso, para sostener las funciones académicas y administrativas.",
     "Priorizar la conservación de aulas, laboratorios y servicios de apoyo para reducir interrupciones.",
     "La asignación de mantenimiento considerará seguridad, uso y condición de cada espacio.",
     "Actualizar el inventario físico y aprobar un calendario anual de mantenimiento preventivo."),
12: ("Fortalecer la infraestructura tecnológica de la Facultad mediante inventarios, mantenimiento y accesibilidad digital, para apoyar enseñanza, investigación y gestión.",
     "Detectar brechas de equipamiento y conectividad para orientar inversiones verificables.",
     "Toda adquisición tecnológica responderá a una necesidad documentada y contemplará mantenimiento.",
     "Levantar un diagnóstico de sistemas y equipos y ordenar las mejoras por impacto y costo."),
13: ("Fortalecer la gobernanza de la Facultad mediante prevención de riesgos, atención de la seguridad con enfoque de derechos y participación en la vida institucional, para consolidar una comunidad segura, informada e identificada con Ingeniería.",
     "Hacer trazables los acuerdos relevantes para mejorar la participación y su cumplimiento.",
     "Los órganos colegiados publicarán acuerdos y responsables conforme a la normatividad aplicable.",
     "Mantener un registro de acuerdos, fechas de revisión y evidencias de cumplimiento."),
14: ("Actualizar la aplicación del marco normativo en Ingeniería mediante revisión periódica y orientación clara, para dar certeza a la comunidad.",
     "Identificar procesos que requieren criterios operativos claros para reducir dudas y retrasos.",
     "Las adecuaciones se someterán a las instancias competentes antes de su aplicación.",
     "Revisar procedimientos prioritarios con las áreas responsables y publicar guías de trámite."),
15: ("Conducir la planeación de Ingeniería mediante indicadores oficiales, evaluación anual y participación de la comunidad, para orientar decisiones y rendir cuentas.",
     "Vincular metas, responsables y evidencia de avance para evaluar el plan de desarrollo.",
     "Cada meta contará con línea base, método de medición y responsable antes de su validación.",
     "Consolidar una matriz de consistencia por proyecto y revisar su avance cada año."),
16: ("Fortalecer el uso íntegro de los recursos de la Facultad mediante programación y seguimiento documentado, para atender prioridades académicas con transparencia.",
     "Relacionar asignaciones y resultados para mejorar la trazabilidad del gasto.",
     "La programación de recursos identificará finalidad, responsable y evidencia de ejecución.",
     "Organizar un registro anual de necesidades, asignaciones y resultados por área."),
17: ("Prevenir riesgos en la gestión de Ingeniería mediante controles y revisión periódica de procesos, para proteger recursos y continuidad de servicios.",
     "Identificar y atender riesgos operativos prioritarios para reducir incidencias.",
     "Los controles preventivos se documentarán con responsables y fechas de revisión.",
     "Elaborar una matriz anual de riesgos y verificar las acciones correctivas."),
18: ("Ampliar la inclusión en la Facultad mediante análisis de brechas, apoyos y accesibilidad, para que más personas participen y concluyan estudios de ingeniería.",
     "Reducir brechas de participación por género y otras condiciones para mejorar el acceso y la permanencia.",
     "Las medidas de inclusión se definirán con datos desagregados y respeto a la privacidad.",
     "Analizar anualmente ingreso, matrícula, becas y egreso por sexo y programa y proponer apoyos focalizados."),
19: ("Fortalecer la convivencia pacífica en Ingeniería mediante prevención, atención y seguimiento oportuno, para sostener un entorno seguro y respetuoso.",
     "Identificar situaciones de conflicto y barreras de atención para orientar la prevención.",
     "Las acciones de atención preservarán confidencialidad y seguirán protocolos institucionales.",
     "Revisar canales existentes, capacitar a responsables y medir tiempos de atención."),
20: ("Ampliar la contribución social de Ingeniería mediante emprendimiento responsable, proyectos comunitarios, colaboración externa, formación lingüística y servicios de bienestar, para vincular la formación profesional con las necesidades de la sociedad mexiquense.",
     "Fortalecer la participación estudiantil en acciones de servicio social con resultados documentados.",
     "Los proyectos de servicio social registrarán población atendida y resultados sin exponer datos personales.",
     "Relacionar programas, estudiantes y aliados externos y revisar resultados al cierre de cada año."),
21: ("Mejorar la comunicación de la Facultad mediante mensajes accesibles, oportunos y medibles, para que la comunidad conozca decisiones, servicios y resultados.",
     "Ordenar los canales institucionales para reducir omisiones y duplicidades.",
     "Toda comunicación pública identificará fuente, vigencia y área responsable.",
     "Inventariar canales y aprobar un calendario editorial con seguimiento de alcance."),
 22: ("Incorporar la sostenibilidad en las actividades de Ingeniería mediante medición ambiental y proyectos formativos, para reducir impactos y desarrollar soluciones responsables.",
     "Conocer consumos y prácticas ambientales para definir prioridades verificables.",
     "Las acciones de reducción fijarán metas sólo después de validar la línea base.",
     "Medir consumo de agua, energía y residuos y formular un plan de mejora con responsables."),
}


# Los proyectos 14, 15 y 17 no contienen renglones propios en la memoria
# institucional. Sus políticas se conservan como excepciones locales, pero
# respetan la sintaxis obligatoria de la Guía y se vinculan sólo por su posición
# en la matriz, sin nombrar al objetivo en el texto.
EXCEPTIONAL_POLICIES = {
 14: [
     {"id": "P1", "objetivos": ["OE1"], "texto": "Las personas responsables de los procedimientos de mayor impacto deberán establecer criterios de aplicación congruentes con la normativa universitaria y accesibles para la comunidad usuaria."},
     {"id": "P2", "objetivos": ["OE2"], "texto": "Las personas titulares de las áreas promoventes deberán supeditar la entrada en vigor de modificaciones normativas y operativas al dictamen de las instancias competentes y a su difusión oficial."},
 ],
 15: [
     {"id": "P1", "objetivos": ["OE1"], "texto": "El personal responsable de la planeación deberá integrar información con definiciones homologadas, cortes verificables y áreas responsables de su integridad."},
     {"id": "P2", "objetivos": ["OE2"], "texto": "Los órganos colegiados responsables de ajustar la planeación deberán sustentar sus decisiones en resultados evaluados y conservar evidencia de sus causas y efectos esperados."},
 ],
 17: [
     {"id": "P1", "objetivos": ["OE1"], "texto": "Las personas titulares de las áreas responsables deberán aplicar controles proporcionales al impacto de los riesgos y mantener atribuciones claramente delimitadas en los procesos expuestos."},
     {"id": "P2", "objetivos": ["OE2"], "texto": "Las personas titulares de las áreas con desviaciones recurrentes deberán activar medidas preventivas antes de comprometer la continuidad, los recursos o la seguridad de la comunidad."},
 ],
}


EXCEPTIONAL_STRATEGIES = {
 14: {
     "E1.1": "Precisar los criterios operativos de los procedimientos prioritarios mediante revisión jurídica colegiada y análisis de dudas recurrentes.",
     "E1.2": "Identificar ambigüedades y demoras en los trámites mediante consulta a las áreas responsables y a la comunidad usuaria.",
     "E2.1": "Simplificar las orientaciones y los procedimientos mediante pruebas de comprensión, dictamen competente y evaluación de los tiempos de respuesta.",
  },
 15: {
     "E1.1": "Integrar la planeación institucional mediante una matriz que relacione diagnóstico, objetivos, indicadores, responsables y resultados.",
     "E1.2": "Examinar la calidad de los indicadores y sus memorias de cálculo mediante revisión de definiciones, fuentes, periodicidad y consistencia histórica.",
     "E2.1": "Corregir metas sin sustento suficiente mediante acuerdos documentados con las áreas responsables y reevaluación anual de sus resultados.",
  },
 17: {
     "E1.1": "Jerarquizar los riesgos operativos mediante la valoración colegiada de su probabilidad, impacto y controles existentes.",
     "E1.2": "Analizar las incidencias recurrentes mediante revisión de causas, procesos afectados y suficiencia de los controles preventivos.",
     "E2.1": "Fortalecer los controles preventivos mediante medidas proporcionales al riesgo y evaluación periódica de su eficacia.",
  },
}


GOALS = {
 2: {
     "descripcion": "Alcanzar al menos 59.5 % de eficiencia terminal global en estudios profesionales en el ciclo 2029-2030.",
     "tipo": "indicador", "indicador": "Espacio académico", "valor_base": 59.1,
     "periodo_base": "2024-2025", "valor_meta_2030": 59.5, "unidad": "porcentaje",
     "fuente_categoria": 4014,
     "metodo": "La última tasa publicada es 59.1 %. La meta de 59.5 % queda por debajo de la mediana de los cinco ciclos más recientes (62.1 %) y equivale a una mejora de 0.4 puntos porcentuales; confirmar definición y cohorte con Control Escolar.",
     "estado": "preliminar_validar_con_memoria_de_calculo",
 },
 3: statistical_goal(4005, "Matricula_UAEMEX", level="posgrado", name="estudiantes de posgrado matriculados"),
 4: statistical_goal(4025, "Suma de Total_SNII", name="integrantes de la Facultad en el SNII"),
 9: statistical_goal(4023, "Alumnos que reciben Tutoría", name="estudiantes que reciben tutoría"),
20: statistical_goal(4018, "Suma de Total_Ser_Social", name="estudiantes que concluyen servicio social"),
}

PROCESS_GOALS = {
 5: "Integrar 1 cartera de capacidades, prototipos y necesidades externas en 2027 y actualizarla cada año hasta 2030.",
 6: "Establecer 1 registro de divulgación en 2027 y publicar 1 informe anual de productos y alcance entre 2028 y 2030.",
 7: "Realizar 1 diagnóstico de participación cultural en 2027 y revisar 1 programa anual de talleres entre 2028 y 2030.",
 8: "Realizar 1 diagnóstico deportivo en 2027 y publicar 1 programa anual de actividades entre 2028 y 2030.",
10: "Realizar 1 diagnóstico de necesidades del personal en 2027 y ejecutar 1 programa anual de mejora de 2028 a 2030.",
11: "Actualizar 1 inventario de infraestructura en 2027 y ejecutar 1 programa anual de mantenimiento preventivo de 2028 a 2030.",
12: "Levantar 1 diagnóstico tecnológico en 2027 y actualizar 1 cartera priorizada de mejoras cada año de 2028 a 2030.",
13: "Crear 1 registro de acuerdos colegiados en 2027 y publicar 1 balance de seguimiento cada año de 2028 a 2030.",
14: "Revisar 1 conjunto priorizado de procedimientos en 2027 y actualizarlo anualmente hasta 2030.",
15: "Completar 1 matriz de consistencia para los proyectos aplicables en 2027 y publicar 1 evaluación anual de 2028 a 2030.",
16: "Integrar 1 registro anual de programación y ejercicio de recursos entre 2027 y 2030.",
17: "Elaborar 1 matriz de riesgos en 2027 y revisarla una vez al año de 2028 a 2030.",
18: "Publicar 1 diagnóstico anual de brechas de participación y apoyos por programa entre 2027 y 2030.",
19: "Realizar 1 diagnóstico de convivencia en 2027 y evaluar 1 plan de prevención cada año de 2028 a 2030.",
21: "Aprobar 1 calendario editorial en 2027 y publicar 1 evaluación de alcance por año de 2028 a 2030.",
22: "Medir 1 línea base de agua, energía y residuos en 2027 y aprobar 1 plan ambiental medible en 2028.",
}


# Tema verificable, instancia de contraste y decisión local por proyecto.
# Las metas de revisión que se derivan de estos temas son actividades, nunca
# incrementos estadísticos inventados.
SUPPORT = {
 2: ("las trayectorias y asignaturas con mayor rezago", "las coordinaciones de licenciatura", "ajustar la tutoría y los apoyos académicos"),
 3: ("el ingreso, la permanencia y el egreso por posgrado", "las coordinaciones de posgrado", "ajustar tutoría, becas y difusión"),
 4: ("los productos y colaboraciones de investigación", "los cuerpos académicos", "priorizar proyectos con resultados públicos verificables"),
 5: ("las capacidades de laboratorios y la demanda externa", "los responsables de laboratorios y aliados externos", "seleccionar prototipos con viabilidad de transferencia"),
 6: ("los públicos y el alcance de la divulgación", "los equipos de investigación y comunicación", "ajustar los formatos de comunicación científica"),
 7: ("las barreras de participación cultural", "el estudiantado y los responsables de talleres", "ajustar horarios y contenidos de talleres"),
 8: ("el acceso a espacios y actividades deportivas", "el estudiantado y los responsables deportivos", "ajustar horarios y condiciones de acceso"),
 9: ("las necesidades didácticas y de tutoría", "el profesorado y las coordinaciones de docencia", "priorizar formación docente pertinente"),
10: ("las condiciones y necesidades del personal", "el personal académico y administrativo", "acordar acciones de mejora laboral"),
11: ("el estado y uso de aulas y laboratorios", "las áreas usuarias y de mantenimiento", "priorizar intervenciones preventivas"),
12: ("la disponibilidad y obsolescencia tecnológica", "las áreas académicas y de soporte", "priorizar renovación y mantenimiento"),
13: ("el cumplimiento de acuerdos colegiados", "los órganos colegiados y responsables de acuerdos", "corregir retrasos en su ejecución"),
14: ("los trámites con dudas o demoras recurrentes", "las áreas responsables y la comunidad usuaria", "simplificar orientaciones y procedimientos"),
15: ("la calidad de indicadores y memorias de cálculo", "las áreas responsables de cada proyecto", "corregir metas sin sustento suficiente"),
16: ("la relación entre asignaciones y resultados", "las áreas solicitantes y administrativas", "priorizar recursos con evidencia de necesidad"),
17: ("los riesgos e incidencias operativas", "los responsables de procesos", "reforzar controles preventivos"),
18: ("las brechas de acceso y permanencia", "los programas educativos y servicios de apoyo", "ajustar apoyos con criterios de inclusión"),
19: ("las barreras para denunciar y recibir atención", "las áreas de atención competentes", "mejorar prevención y tiempos de respuesta"),
20: ("los resultados del servicio social y sus beneficiarios", "las coordinaciones y organizaciones receptoras", "seleccionar proyectos de mayor pertinencia social"),
21: ("el alcance y la claridad de los canales institucionales", "las áreas emisoras y públicos destinatarios", "corregir mensajes y tiempos de publicación"),
22: ("el consumo de agua, energía y la generación de residuos", "las áreas usuarias y de mantenimiento", "priorizar acciones ambientales medibles"),
}

STRATEGY_VALUES = {
 2: ["C", "I"], 3: ["C", "I"], 4: ["P"], 5: ["C", "P"], 6: ["C", "P"],
 7: ["C", "I"], 8: ["C", "I"], 9: ["C", "I", "P"], 10: ["C", "I"],
 11: ["C"], 12: ["I", "P"], 13: ["C", "I"], 14: ["C"], 15: ["C"],
 16: ["C"], 17: ["C"], 18: ["C", "I"], 19: ["C", "I"], 20: ["C", "P"],
 21: ["C", "I"], 22: ["P"],
}

LISTEN_PROJECTS = {
 2: "estudiantes de cada licenciatura", 3: "estudiantes y profesorado de posgrado",
 7: "estudiantes y responsables culturales", 8: "estudiantes y responsables deportivos",
 9: "profesorado y estudiantes de cada licenciatura", 10: "personal académico y administrativo",
 13: "representantes estudiantiles, docentes y administrativos",
 18: "estudiantes y profesorado de cada licenciatura",
 19: "estudiantes, docentes y administrativos", 20: "estudiantes y organizaciones receptoras",
 21: "estudiantes, docentes y administrativos",
}

def tagged_strategy(wording, values):
    return wording.rstrip(' .') + '. [' + ', '.join(values) + ']'


# Síntesis en lenguaje natural para la redacción; distingue hechos internos de
# factores externos todavía no documentados por el tablero público.
FODA_PROSE = {
 2: "El crecimiento de solicitudes e ingreso muestra capacidad de atracción de los programas de Ingeniería. A la vez, la eficiencia terminal global de 59.1 % en 2024-2025 y las variaciones por cohorte señalan una debilidad en la conclusión oportuna de los estudios. El seguimiento por programa y la tutoría pueden aprovechar la información disponible para atender rezago y abandono.",
 3: "La Facultad dispone de registros de ingreso, matrícula y egreso que permiten seguir las trayectorias de posgrado. La matrícula de 75 estudiantes en 2025-2026 muestra una escala que exige revisar la continuidad y el apoyo específico de cada programa antes de fijar compromisos de expansión.",
 4: "El aumento de 17 a 40 integrantes registrados en el SNII entre 2018 y 2025 constituye una fortaleza de la capacidad académica de Ingeniería. La evidencia disponible todavía no permite valorar por sí sola la pertinencia social de los proyectos y productos; conviene relacionar el reconocimiento con resultados verificables.",
 5: "La infraestructura y los laboratorios de la Facultad ofrecen una base interna para proyectos aplicados. La ausencia de un registro público de prototipos, transferencias y necesidades atendidas limita la selección de prioridades y exige un inventario de capacidades y demanda.",
 6: "La actividad de investigación puede sustentar la divulgación de la ingeniería, pero el tablero no publica productos ni alcance específicos de la Facultad. La falta de esta línea base impide distinguir públicos atendidos y valorar los resultados de comunicación científica.",
 7: "La existencia de talleres culturales permite incorporar actividades de formación integral. Los registros de participación bajan de 112 en 2018 a 5 en 2023; antes de interpretarlo como una caída real, es necesario verificar la cobertura del registro y las barreras de acceso.",
 8: "La Facultad puede organizar actividades deportivas desde sus espacios y comunidad, pero el tablero no ofrece una serie de participación que sustente una valoración de cobertura. Se requiere medir horarios, espacios y acceso antes de priorizar acciones.",
 9: "El registro de 2,914 estudiantes que reciben tutoría en 2025 ofrece una base de acompañamiento académico. La información agregada no muestra todavía diferencias por programa ni su relación con la formación docente y el avance escolar, lo que limita la focalización de apoyos.",
10: "Los datos de personal académico permiten dimensionar parte de la planta de la Facultad. No se dispone en el tablero de una medición de necesidades de formación o condiciones de trabajo; una consulta al personal permitiría reconocer fortalezas y problemas internos con mayor precisión.",
11: "La Facultad dispone de espacios e indicadores de infraestructura y acervo que sirven como punto de partida para planear servicios. Falta información pública sobre estado físico, fallas y mantenimiento, por lo que el inventario debe completarse con una revisión técnica.",
12: "La infraestructura registrada constituye una base para la enseñanza y la gestión digital. La falta de datos sobre antigüedad de equipos, conectividad y accesibilidad tecnológica impide identificar con precisión las brechas prioritarias.",
13: "Los órganos colegiados ofrecen una estructura para la participación y el seguimiento de acuerdos. Sin una serie pública de acuerdos, responsables y cumplimiento, todavía no puede valorarse la eficacia de este proceso en Ingeniería.",
14: "El marco normativo institucional proporciona una base común para los trámites de la Facultad. La ausencia de mediciones de tiempos, dudas frecuentes y aplicación de procedimientos dificulta reconocer dónde se requieren guías operativas.",
15: "La Facultad cuenta con series públicas sobre trayectorias, investigación, servicios e infraestructura, una fortaleza para planear con evidencia. La dispersión de definiciones, responsables y cortes temporales puede dificultar comparaciones y evaluación; la matriz de consistencia debe ordenar esos elementos.",
16: "La programación institucional ofrece reglas para el uso de recursos. El tablero consultado no permite seguir asignaciones y resultados específicos de Ingeniería, lo que limita la trazabilidad de las prioridades de gasto.",
17: "La Facultad puede incorporar controles preventivos en sus procesos existentes. Sin una matriz propia de riesgos e incidencias no es posible determinar qué controles funcionan ni cuáles necesitan refuerzo.",
18: "El aumento de becas registradas, de 1,200 en 2018 a 2,213 en 2025, muestra capacidad de otorgar apoyos. El total agregado no revela si estos alcanzan a quienes enfrentan más barreras; se requiere analizar distribución, permanencia y resultados por programa.",
19: "La Facultad puede apoyarse en los protocolos institucionales para prevenir y atender conflictos. La falta de indicadores públicos de acceso, tiempos de atención y seguimiento limita una valoración de la convivencia; cualquier medición deberá preservar la confidencialidad.",
20: "El número de estudiantes que concluyen servicio social pasó de 293 en 2018 a 335 en 2025, una base para ampliar la contribución comunitaria. La estadística no informa todavía sobre la pertinencia de los proyectos ni sus resultados para las comunidades.",
21: "La Facultad dispone de canales para difundir servicios y acuerdos, pero no de una medición pública de alcance y oportunidad. Un inventario de canales y públicos permitiría reconocer duplicidades y necesidades de información.",
22: "Las capacidades de Ingeniería pueden apoyar proyectos de sostenibilidad. Sin mediciones de agua, energía y residuos propios de la Facultad no es posible identificar fortalezas ambientales ni comprometer reducciones verificables.",
}

# Diagnósticos redactados con la cadencia del PRDI: exponen la situación,
# enlazan cifras y tendencias, interpretan la brecha y desembocan en el rumbo
# estratégico. Las referencias y notas de trazabilidad se conservan fuera de esta
# prosa para no interrumpir su lectura.
DIAGNOSTIC_PROSE = {
 2: ("Entre 2016-2017 y 2025-2026, la matrícula de la Facultad de Ingeniería pasó de 2,553 a 2,989 estudiantes y el nuevo ingreso de 464 a 722. La capacidad de atracción y atención creció, y en 2025 la tutoría alcanzó a 2,914 estudiantes; no obstante, esa cobertura convive con señales que exigen atención diferenciada. Entre 2013-2014 y 2024-2025, la eficiencia terminal por cohorte disminuyó de 7.3 % a 4.2 %, el abandono aumentó de 4.7 % a 8.1 % y la titulación por cohorte descendió de 11.5 % a 2.29 %. La eficiencia terminal global cerró en 59.1 %, por debajo del 62.1 % observado un ciclo antes.\n\nLa expansión de la matrícula no se ha traducido con la misma intensidad en conclusión oportuna y titulación. La alta cobertura tutorial representa una fortaleza para intervenir, pero requiere relacionarse con asignaturas críticas, diferencias entre programas y momentos de mayor riesgo. A la vez, las exigencias de acreditación, los criterios transversales del SEAES y la evolución del mercado laboral demandan revisar la pertinencia curricular y mantener diálogo con personas egresadas y empleadoras. La formación profesional necesita, por ello, integrar actualización de planes, acompañamiento longitudinal, experiencias complementarias y vinculación externa, de modo que el crecimiento de la cobertura se refleje en mejores trayectorias y oportunidades de inserción."),
 3: ("La matrícula de estudios avanzados de la Facultad pasó de 103 estudiantes en 2016-2017 a 55 en 2021-2022 y se recuperó hasta 75 en 2025-2026. La recuperación reciente es favorable, pero aún se encuentra por debajo del punto inicial de la serie y confirma la sensibilidad de cada programa a variaciones en ingreso, permanencia y graduación. La escala actual permite acompañamiento cercano e incorporación temprana a las líneas de investigación, siempre que los núcleos académicos mantengan capacidad suficiente y continuidad en la dirección de trabajos terminales.\n\nLa consolidación del posgrado exige equilibrar recuperación de matrícula y calidad académica. La difusión dirigida, los procesos de admisión claros y el apoyo a movilidad pueden ampliar el acceso, mientras que la tutoría, el seguimiento de protocolos y la integración a proyectos pueden reducir interrupciones. Las agendas regionales de agua, ambiente, empleo e innovación ofrecen campos de pertinencia; la brecha entre formación y mercado laboral, en cambio, demanda fortalecer la relación con sectores externos y observar la trayectoria de las personas graduadas. Así, el crecimiento cobra sentido cuando se acompaña de graduación oportuna, investigación relevante y vinculación profesional."),
 4: ("La capacidad científica de la Facultad ha mostrado una evolución favorable: el personal registrado en el Sistema Nacional de Investigadoras e Investigadores pasó de 17 integrantes en 2018 a 40 en 2025. A ello se suman tres cuerpos académicos, cuya continuidad ofrece una plataforma para organizar proyectos, formar estudiantes y ampliar redes de colaboración. El crecimiento del reconocimiento académico fortalece la posición de Ingeniería, aunque su valor institucional depende de traducirse en productos de calidad, soluciones pertinentes y beneficios identificables para la sociedad.\n\nEl siguiente paso consiste en vincular de manera más estrecha capacidades, infraestructura y resultados. La conservación de laboratorios, la participación en convocatorias, la colaboración con pares y la documentación del impacto social permiten que la investigación abierta no sea sólo un principio, sino una práctica verificable. Esta articulación favorecerá una producción científica más visible, colaborativa y alineada con problemas prioritarios."),
 5: ("La Facultad cuenta con laboratorios, experiencia académica y capacidades disciplinares que pueden convertirse en desarrollos tecnológicos y soluciones aplicadas. La diversidad de especialidades favorece el trabajo interdisciplinario y la atención de problemas de los sectores público, productivo y social. No obstante, cuando los prototipos, activos de conocimiento y necesidades externas permanecen dispersos, disminuye la posibilidad de reconocer proyectos con potencial de protección, transferencia o escalamiento.\n\nLa innovación requiere ordenar esas capacidades alrededor de problemas concretos. Una cartera de proyectos, acompañada de orientación en propiedad intelectual y mecanismos de colaboración, permitirá seleccionar iniciativas viables y socialmente pertinentes. El propósito es pasar de esfuerzos aislados a un proceso continuo que conecte conocimiento, protección de resultados, vinculación y aplicación responsable."),
 6: ("La producción académica y los proyectos de investigación de Ingeniería ofrecen contenidos valiosos para acercar la ciencia y la tecnología a distintos públicos. Esta capacidad puede ampliar la comprensión social de los problemas que aborda la Facultad y fortalecer la formación de estudiantes y personal académico en comunicación científica. Sin una organización común de productos, canales y audiencias, el alcance de ese conocimiento tiende a fragmentarse.\n\nLa divulgación debe integrar calidad académica, acceso abierto y lenguaje comprensible. El desarrollo de productos editoriales y digitales, junto con la formación en ciencia abierta y la evaluación de su alcance, permitirá dar continuidad a la comunicación de resultados y convertirla en una función regular de la vida académica."),
 7: ("La participación cultural registrada en la Facultad pasó de 112 personas en 2018 a 5 en 2023. Más que una lectura aislada de la disminución, la trayectoria muestra la necesidad de recuperar la presencia de la cultura en la formación integral y de adecuar la oferta a los horarios, intereses y formas de participación actuales. Los talleres, presentaciones y proyectos audiovisuales pueden fortalecer creatividad, identidad y convivencia cuando forman parte de una programación accesible y continua.\n\nLa reactivación cultural debe combinar diversidad de actividades con seguimiento de la participación. Una oferta construida con la comunidad permitirá ampliar la asistencia, aprovechar talentos internos y vincular expresiones artísticas con temas de inclusión y transformación social. Así, la cultura puede recuperar su papel como espacio de encuentro y pertenencia dentro de Ingeniería."),
 8: ("La Facultad de Ingeniería reúne a 2,989 estudiantes, 284 personas académicas y 79 administrativas. En una comunidad de esta magnitud, la actividad física contribuye al bienestar, la convivencia y la formación integral; sin embargo, las jornadas escolares y laborales, la disponibilidad de espacios y la diversidad de condiciones físicas pueden limitar una participación constante. Estas diferencias adquieren especial relevancia para quienes requieren horarios flexibles, actividades recreativas de distinta intensidad o condiciones accesibles para incorporarse a la práctica deportiva.\n\nEl fortalecimiento del deporte universitario demanda una oferta cercana a la vida cotidiana de la comunidad y articulada con el uso seguro de la infraestructura. La diversificación de actividades, la coordinación de horarios y el reconocimiento de la participación permitirán extender los beneficios del autocuidado y la vida saludable, además de favorecer la detección y el acompañamiento de talentos deportivos. De esta manera, la práctica física puede consolidarse como un componente permanente de la experiencia universitaria y no como una actividad aislada."),
 9: ("La tutoría pasó de atender a 2,221 estudiantes con 123 tutores en 2014 a 2,914 estudiantes con 64 tutores en 2025. La cobertura actual, cercana al total de la matrícula, constituye una fortaleza para acompañar las trayectorias; sin embargo, la relación registrada aumentó de 18 a 46 estudiantes por tutor, lo que puede limitar la oportunidad y profundidad del seguimiento si no se focaliza la atención. En paralelo, el personal académico registrado pasó de 314 integrantes en 2018 a 284 en 2025, mientras el reconocimiento en el Sistema Nacional de Investigadoras e Investigadores creció de 17 a 40 personas en el mismo periodo.\n\nEstas tendencias muestran capacidades académicas relevantes, pero también una mayor exigencia sobre el acompañamiento y la actualización docente. Las evaluaciones externas requieren evidencias de resultados educativos y ciclos de mejora; por ello, la formación disciplinar, didáctica, digital y lingüística debe vincularse con las dificultades de cada programa y con las asignaturas de mayor rezago. La articulación entre tutoría, desarrollo del profesorado, redes académicas y seguimiento de trayectorias permitirá concentrar los esfuerzos donde puedan incidir con mayor claridad en el aprendizaje y la permanencia."),
10: ("El personal académico de la Facultad pasó de 314 integrantes en 2018 a 284 en 2025, con fluctuaciones que llegaron a 269 en 2024. En el mismo periodo, el personal administrativo disminuyó de 91 a 79 personas. La experiencia acumulada sostiene la docencia, la investigación y los servicios, pero la reducción de la plantilla y la diversidad de funciones incrementan la importancia de distribuir cargas con claridad, conservar conocimiento operativo y responder a cambios tecnológicos y organizativos.\n\nLa dignificación laboral requiere reconocer necesidades diferenciadas y evitar que la formación se reduzca a cursos desvinculados de la práctica. El desarrollo de competencias, la claridad de responsabilidades, la escucha y las condiciones seguras de trabajo pueden fortalecer la colaboración y el bienestar. La atención a estos factores resulta estratégica porque la estabilidad y preparación del personal inciden directamente en la continuidad y calidad de los servicios que recibe la comunidad."),
11: ("Entre 2018 y 2025, el personal académico de la Facultad pasó de 314 a 284 integrantes y el administrativo de 91 a 79. Esta evolución ocurre en una infraestructura que en 2014 registraba 65 aulas, 22 laboratorios, 134 cubículos y seis auditorios, y cuya información posterior presenta actualizaciones parciales por tipo de espacio. La combinación de una comunidad de 2,989 estudiantes, una plantilla más ajustada y registros físicos incompletos aumenta la necesidad de conocer el estado, uso y criticidad de aulas, laboratorios, talleres, acervos y servicios.\n\nLa continuidad académica y administrativa depende de abandonar una lógica exclusivamente reactiva. Un inventario funcional, acompañado de criterios de seguridad, intensidad de uso, costo y efecto sobre la docencia o la investigación, permitiría ordenar las intervenciones y anticipar fallas. Además, los marcos de evaluación externa exigen demostrar suficiencia y conservación de recursos, por lo que la gestión de espacios debe integrar mantenimiento preventivo, responsabilidades claras y tiempos de atención verificables."),
12: ("La enseñanza, la investigación y la gestión de Ingeniería dependen cada vez más de sistemas, conectividad, salas de cómputo y equipos especializados. La disponibilidad de infraestructura constituye una fortaleza, pero su utilidad se reduce cuando la obsolescencia, el mantenimiento o las diferencias de acceso afectan la continuidad de las actividades.\n\nEl fortalecimiento tecnológico debe priorizar funcionalidad y sostenibilidad antes que la adquisición aislada. La identificación de equipos críticos, el mantenimiento preventivo, las guías de uso y el desarrollo de habilidades digitales permitirán orientar inversiones con mayor impacto y asegurar que la tecnología contribuya efectivamente al aprendizaje, la investigación y los servicios."),
13: ("La Facultad dispone de órganos colegiados y espacios de decisión que articulan asuntos académicos, administrativos y de seguridad. Su efectividad depende de que los acuerdos sean comprensibles, tengan responsables y puedan seguirse hasta su cumplimiento. Cuando la información se dispersa o los plazos no son visibles, disminuye la capacidad de participación y se debilita la continuidad entre decisiones y resultados.\n\nUna gobernanza cercana requiere fortalecer la comunicación de acuerdos, la protección de la comunidad y la identidad universitaria. El seguimiento periódico, junto con acciones de autoprotección, atención con perspectiva de derechos e iniciativas que recuperen la historia de Ingeniería, puede consolidar una comunidad más informada, segura y corresponsable."),
14: ("El marco normativo brinda certeza a la vida académica y administrativa de la Facultad. Su efectividad cotidiana depende de que los procedimientos sean comprensibles, congruentes y accesibles para quienes realizan trámites o toman decisiones. Las dudas recurrentes y las diferencias en la aplicación pueden prolongar tiempos de respuesta y generar cargas innecesarias para las áreas y la comunidad usuaria.\n\nEl fortalecimiento normativo debe concentrarse en los procesos de mayor uso e impacto. La revisión coordinada, la simplificación de orientaciones y la actualización periódica de guías permitirán reducir ambigüedades, mejorar la atención y asegurar que cualquier adecuación conserve congruencia con las disposiciones universitarias."),
15: ("La información histórica de la Facultad revela trayectorias que no pueden interpretarse de manera aislada. Entre 2016-2017 y 2025-2026, la matrícula pasó de 2,553 a 2,989 estudiantes y el nuevo ingreso de 464 a 722; entre 2013-2014 y 2024-2025, la eficiencia terminal por cohorte descendió de 7.3 % a 4.2 %, el abandono aumentó de 4.7 % a 8.1 % y la titulación por cohorte bajó de 11.5 % a 2.29 %. Al mismo tiempo, la tutoría creció de 2,221 estudiantes en 2014 a 2,914 en 2025 y el personal reconocido en el SNII pasó de 17 a 40 entre 2018 y 2025. Estas tendencias confirman que una mejora en cobertura o capacidad académica no garantiza por sí sola resultados equivalentes en todas las funciones.\n\nLa diversidad de fuentes constituye una fortaleza sólo si comparte definiciones, cortes, responsables y reglas de interpretación. Las exigencias de acreditación y del SEAES refuerzan la necesidad de documentar atributos de egreso, pertinencia, inclusión y ciclos de mejora. La planeación debe relacionar problemas, decisiones, recursos y resultados, distinguir variaciones circunstanciales de cambios sostenidos y corregir oportunamente metas sin base suficiente. De esta manera, la evaluación se incorpora a la conducción institucional y no queda reducida a un informe posterior."),
16: ("La operación de la Facultad exige recursos para sostener docencia, investigación, mantenimiento y servicios. En un entorno de necesidades crecientes, la asignación eficiente depende de reconocer prioridades y relacionar cada erogación con el resultado que busca producir. La generación de ingresos extraordinarios puede ampliar la capacidad de respuesta, siempre que se conduzca con transparencia y sin desplazar las funciones sustantivas.\n\nEl manejo responsable de los recursos debe integrar programación, seguimiento y evidencia de resultados. Esta relación permitirá comparar alternativas, anticipar compromisos de mantenimiento y orientar recursos hacia acciones de mayor beneficio académico y social, fortaleciendo al mismo tiempo la rendición de cuentas."),
17: ("La continuidad de los servicios académicos y administrativos depende de procesos capaces de anticipar riesgos y responder oportunamente a incidencias. La complejidad de laboratorios, instalaciones, sistemas y trámites genera riesgos de distinta naturaleza que no pueden atenderse con el mismo nivel de prioridad.\n\nLa vigilancia y el control preventivo deben concentrarse en los procesos con mayor impacto sobre personas, recursos y operación. La identificación de causas, responsables y controles permitirá reducir recurrencias y fortalecer la capacidad de respuesta. Una revisión periódica de los riesgos convierte el control en una herramienta de mejora y no sólo de verificación posterior."),
18: ("Los apoyos otorgados en la Facultad crecieron de 1,200 becas en 2018 a 2,213 en 2025, lo que muestra una capacidad sostenida para acompañar la permanencia estudiantil. Sin embargo, el volumen total no expresa por sí mismo si los beneficios alcanzan con oportunidad a quienes enfrentan mayores barreras económicas, sociales, de género, discapacidad o cuidados. La inclusión se materializa cuando el acceso a apoyos se refleja en participación, continuidad y conclusión de los estudios.\n\nEl reconocimiento de la diversidad debe orientar tanto los servicios como la vida académica y laboral. La difusión de derechos, las actividades de sensibilización, los apoyos focalizados y la incorporación del cuidado pueden reducir barreras y fortalecer entornos libres de discriminación. La atención diferenciada permitirá que el crecimiento de los apoyos se traduzca en mejores oportunidades para la comunidad."),
19: ("La convivencia universitaria se construye en aulas, laboratorios, oficinas y espacios comunes donde interactúan diariamente estudiantes, personal académico y administrativo. Los protocolos institucionales ofrecen una base para prevenir y atender violencias, pero su eficacia depende de que la comunidad los conozca, confíe en los canales de atención y encuentre respuestas oportunas y respetuosas.\n\nLa construcción de paz requiere combinar prevención, formación y capacidad de mediación. Las actividades culturales, comunitarias y educativas pueden fortalecer el respeto y la igualdad, mientras que la capacitación del personal responsable mejora la gestión de conflictos. El seguimiento agregado de las acciones permitirá reconocer avances sin comprometer la confidencialidad de las personas."),
20: ("El número de estudiantes que concluyeron servicio social aumentó de 293 en 2018 a 335 en 2025. Esta evolución confirma la capacidad de participación de la comunidad de Ingeniería y ofrece una base para ampliar su contribución a organizaciones y comunidades. El valor social de estas experiencias, no obstante, depende de la pertinencia de las actividades, la continuidad de las colaboraciones y los resultados alcanzados, no sólo del número de participantes.\n\nLa retribución social puede fortalecerse mediante proyectos interdisciplinarios, emprendimiento, economía social, prácticas profesionales y servicios comunitarios vinculados con necesidades concretas. Una relación más estrecha con organizaciones externas favorecerá competencias profesionales y permitirá que las capacidades de la Facultad produzcan beneficios verificables y sostenibles."),
21: ("La comunidad de Ingeniería recibe información por diversos canales institucionales, impresos y digitales. Esta diversidad amplía las posibilidades de contacto, pero también puede generar duplicidades, mensajes dispersos o diferencias en la oportunidad con que estudiantes y personal conocen servicios, acuerdos y actividades. La comunicación cumple su función cuando facilita el diálogo y permite actuar, no sólo cuando incrementa el número de publicaciones.\n\nEl fortalecimiento de los canales debe partir de una programación coordinada, mensajes accesibles y responsabilidades claras. La promoción de los valores universitarios, la difusión de resultados y el seguimiento del alcance permitirán construir una comunicación más cercana, incluyente y útil para la vida cotidiana de la Facultad."),
22: ("Las actividades de la Facultad generan consumo de agua y energía, uso de papel y materiales, así como residuos de distinta naturaleza. Al mismo tiempo, la formación y las capacidades técnicas de Ingeniería ofrecen condiciones favorables para convertir el campus en un espacio de aprendizaje y aplicación de soluciones ambientales. La sostenibilidad adquiere sentido cuando modifica prácticas cotidianas y demuestra mejoras en el uso de recursos.\n\nLa reducción de desechables, el acopio, la reforestación, el compostaje y el manejo responsable de residuos pueden articularse con acciones de ahorro de agua, eficiencia energética y formación ambiental. Esta integración permitirá pasar de actividades aisladas a una cultura compartida de responsabilidad ecológica, con participación de estudiantes, personal académico y administrativo."),
}

FI360 = {
 2: ("Académico", "sistema de trayectoria escolar y alerta temprana, asesoría disciplinar, egreso y titulación", "§2.2, pp. 6–8"),
 3: ("Científico", "continuidad de estudios avanzados, eficiencia terminal y empleabilidad de egresados", "§2.5, p. 9"),
 4: ("Científico", "seguimiento de cuerpos académicos, productos y pertinencia social de la investigación", "§2.5, p. 9"),
 5: ("Científico y Social", "proyectos aplicados, prototipos y relación con sectores sociales y productivos", "§§2.5 y 2.8, pp. 9–10"),
 6: ("Científico", "difusión de resultados científicos y tecnológicos con trazabilidad", "§§2.5 y 3.2, pp. 9 y 13"),
 7: ("Humano", "formación integral y participación cultural con acceso equitativo", "§3.2, p. 13"),
 8: ("Humano", "deporte y bienestar como parte de la formación integral", "§3.2, p. 13"),
 9: ("Académico", "formación docente, tutoría y uso de recomendaciones de acreditación", "§§2.2–2.3, pp. 6–8"),
10: ("Humano", "condiciones de trabajo, cargas, reconocimiento y bienestar del personal", "§2.10, p. 10"),
11: ("Tecnológico", "mantenimiento, seguridad y uso compartido de laboratorios y espacios", "§2.6, p. 9"),
12: ("Tecnológico", "software, conectividad y consolidación gradual de Ingeniería en Inteligencia Artificial", "§§2.6–2.7, pp. 9–10"),
13: ("Humano y Financiero", "coordinación, seguimiento de acuerdos y comunicación de resultados", "§§3.1–3.3, pp. 12–14"),
14: ("Financiero", "claridad de procesos y responsabilidades de gestión", "§3.3, pp. 13–14"),
15: ("Transversal", "planeación basada en evidencia, responsables y revisión periódica de resultados", "§§3.1–3.3, pp. 12–14"),
16: ("Financiero", "prioridades documentadas, ingresos propios y rendición de cuentas", "§2.9, p. 10"),
17: ("Financiero y Tecnológico", "gestión responsable de recursos y riesgos de seguridad en instalaciones", "§§2.6 y 2.9, pp. 9–10"),
18: ("Humano", "bienestar, inclusión y apoyos diferenciados", "§§2.10 y 3.1, pp. 10–12"),
19: ("Humano", "clima organizacional, respeto y cultura de paz", "§2.10, p. 10"),
20: ("Social", "servicio social, prácticas y resultados para comunidades y organizaciones", "§2.8, p. 10"),
21: ("Humano", "comunicación interna y seguimiento de solicitudes", "§2.10, p. 10"),
22: ("Tecnológico", "seguridad, manejo de residuos y sostenibilidad de espacios y servicios", "§§2.6 y 3.1, pp. 9 y 12"),
}

PENDING_TAGS = {
 2: ["C01-P02", "C01-P03", "C16-P02", "C16-P06", "C02-P01", "C05-P04", "AG-01", "AG-02", "AG-03"],
 3: ["C17-P02", "C17-P07", "C03-P01", "C03-P04", "C12-P04", "AG-08"],
 4: ["C17-P06", "C03-P03", "C11-P04", "C11-P05", "AG-08"],
 5: ["C16-P05", "C17-P06", "C03-P05", "C14-P04", "C14-P05", "AG-06"],
 6: ["C11-P03", "C13-P05", "AG-08"],
 7: ["C01-P07", "C01-P11", "C01-P13", "C16-P08", "C13-P04", "C13-P05", "AG-07"],
 8: ["C01-P07", "C16-P08", "C13-P04", "AG-07"],
 9: ["C01-P05", "C16-P03", "C02-P05", "C05-P05", "AG-04"],
10: ["C04-P02", "C04-P03", "C04-P05", "AG-04"],
11: ["C16-P04", "C17-P04", "C02-P03", "C04-P04", "C05-P06", "AG-05"],
12: ["C01-P06", "C16-P04", "C17-P04", "C02-P03", "C05-P06", "AG-05"],
13: ["C01-P11", "C01-P13", "C04-P05", "C15-P01", "C15-P03", "AG-08"],
14: ["C04-P01", "C15-P02", "AG-08"],
15: ["C15-P03", "C15-P04", "C15-P05", "AG-08"],
16: ["C15-P02", "C04-P01", "AG-08"],
17: ["C04-P04", "C15-P02", "AG-05"],
18: ["C01-P08", "C01-P11", "C01-P13", "C16-P08", "C17-P08", "C04-P05", "AG-07"],
19: ["C01-P08", "C16-P08", "C17-P08", "C04-P05", "AG-07"],
20: ["C01-P13", "C16-P05", "C17-P06", "C14-P04", "C14-P05", "C03-P05", "AG-06"],
21: ["C04-P05", "C13-P05", "AG-07"],
22: ["C04-P04", "C11-P04", "AG-05"],
}

external = json.loads((PLAN / "fuentes_externas.json").read_text(encoding="utf-8"))


def memory_goal(number: int, previous: dict) -> dict:
    reference = memory["proyectos"][str(number)]
    valid = reference["referencias_validas"]
    if valid:
        selected = valid[0]
        value = selected["valor"]
        unit = "acciones o productos registrados"
        if number == 4:
            unit = "cuerpos académicos de calidad"
        elif number == 19:
            unit = "acciones de capacitación registradas"
        return {"descripcion": f"Mantener al menos {value:g} {unit} en cada medición anual de 2027 a 2030.",
                "tipo": "indicador_ref_2026", "indicador": selected["clave"], "valor_base": value,
                "periodo_base": "Ref. 2026", "valor_meta_2030": value, "unidad": unit,
                "fuente": "memoria_calculo_2026.json",
                "cita_apa": memory["cita_apa_primera"],
                "metodo": "Piso igual a la referencia base 2026; no se proyecta crecimiento sin una serie comparable. Validar unidad, responsable y evidencia del registro.",
                "estado": "preliminar_validar_con_area_responsable"}
    if previous.get("tipo") == "indicador":
        return {"descripcion": "Validar y documentar el valor de Ref. 2026 durante 2027 y, una vez confirmado, mantenerlo o mejorarlo hasta 2030.",
                "tipo": "linea_base", "periodo_base": "Ref. 2026", "valor_observado_anterior": previous.get("valor_base"),
                "periodo_observado_anterior": previous.get("periodo_base"), "indicador": previous.get("indicador"),
                "fuente": "memoria_calculo_2026.json", "cita_apa": memory["cita_apa_primera"],
                "metodo": "La memoria de cálculo no aporta variables completas para este indicador en Ref. 2026; el dato histórico anterior queda como contexto y no como línea base del plan.",
                "estado": "pendiente_ref_2026"}
    return previous


def project_evidence(number: int) -> tuple[list[dict], dict]:
    references = []
    foda = {key: [] for key in ("fortalezas", "oportunidades", "debilidades", "amenazas")}
    for file in sorted(PLAN.glob("[0-9][0-9][0-9][0-9]_*.json")):
        data = json.loads(file.read_text(encoding="utf-8"))
        if number not in [x["numero"] for x in data.get("proyectos_relacionados", [])]:
            continue
        references.append({"categoria": data["id_categoria"], "titulo": data["categoria"], "archivo": file.name,
                           "cita_apa": data.get("cita_apa"), "referencia_apa": data.get("referencia_apa")})
        for key in foda:
            for factor in data["facultad"]["foda"].get(key, []):
                factor = dict(factor)
                factor["categoria"] = data["id_categoria"]
                factor["ambito"] = "Facultad de Ingeniería, agregado"
                foda[key].append(factor)
    for item in external["factores"]:
        if number in item["proyectos"]:
            foda[item["cuadrante"]].append({**item, "tipo": "inferencia_contextual_externa",
                                             "ambito": "Entorno externo; aplicación a FI por verificar"})
    return references, foda


projects = []
for pillar in prdi["pilares"]:
    for source in pillar["proyectos"]:
        number = source["numero"]
        if not source["aplica_a_facultad_ingenieria"]:
            continue
        general, specific, policy, strategy = CONTENT[number]
        refs, foda = project_evidence(number)
        goal = GOALS.get(number) or {
            "descripcion": PROCESS_GOALS[number], "tipo": "actividad",
            "estado": "preliminar_validar_con_memoria_de_calculo",
            "metodo": "Meta de proceso verificable; no se presenta como proyección estadística porque el tablero no publica una línea base directamente aplicable.",
        }
        goal = memory_goal(number, goal)
        memory_project = {
            **memory["proyectos"][str(number)],
            "periodo_base": memory["periodo_base"],
            "cita_apa": memory["cita_apa_primera"],
            "referencia_apa": memory["referencia_apa"],
            "criterio_integracion": memory["criterio_integracion"],
        }
        axis, context, location = FI360[number]
        topic, participants, decision = SUPPORT[number]
        policies = EXCEPTIONAL_POLICIES.get(number, [
            {"id": "P1", "texto": policy, "objetivos": ["OE1"]},
            {"id": "P2", "texto": f"La Facultad verificará {topic} con {participants} antes de aprobar cambios y conservará la evidencia de esa revisión.", "objetivos": ["OE1"]},
            {"id": "P3", "texto": f"Las decisiones sobre {topic} tendrán responsable, plazo e indicador comprobable y se revisarán anualmente.", "objetivos": ["OE2"]},
        ])
        process_meta = lambda description: {"descripcion": description, "tipo": "actividad",
            "estado": "preliminar_validar_con_memoria_de_calculo",
            "metodo": "Actividad verificable de una revisión anual; confirmar responsables, calendario y evidencia documental antes de aprobarla."}
        specifics = [
            {"id": "OE1", "texto": specific, "estrategias": [
                {"id": "E1.1", "texto": strategy, "metas": [goal]},
                {"id": "E1.2", "texto": f"Contrastar {topic} con {participants} y documentar las brechas por programa o área para orientar decisiones.",
                 "metas": [process_meta(f"Realizar 1 revisión documentada de {topic} en 2027 y actualizarla una vez al año de 2028 a 2030.")]},
            ]},
            {"id": "OE2", "texto": f"Evaluar {topic} para {decision} con base en evidencia y participación de las áreas responsables.",
             "estrategias": [{"id": "E2.1", "texto": f"Acordar con {participants} las acciones para {decision} y revisar sus resultados al cierre de cada año.",
                "metas": [process_meta(f"Registrar 1 acuerdo de mejora sobre {topic} en 2028 y revisar su cumplimiento anualmente hasta 2030.")]}]},
        ]
        if number in LISTEN_PROJECTS:
            audience = LISTEN_PROJECTS[number]
            specifics[1]['estrategias'][0]['texto'] = (
                f"Organizar reuniones y aplicar encuestas anuales a {audience} sobre {topic} "
                f"para {decision} mediante acuerdos de seguimiento documentados.")
            specifics[1]['estrategias'][0]['metas'][0] = process_meta(
                f"Realizar 1 reunión y 1 encuesta a {audience} cada año de 2028 a 2030 "
                f"y documentar los ajustes derivados.")
        for objective in specifics:
            for item in objective['estrategias']:
                exceptional_text = EXCEPTIONAL_STRATEGIES.get(number, {}).get(item['id'])
                if exceptional_text:
                    item['texto'] = exceptional_text
                values = STRATEGY_VALUES[number] if item['id'] != 'E1.2' else ['C'] + (['I'] if 'I' in STRATEGY_VALUES[number] else [])
                item['criterios'] = values
                item['texto'] = tagged_strategy(item['texto'], values)
        source_categories = sorted({str(item['categoria']) for item in refs})
        cross_prose, cross_charts = academic_cross(number)
        source_list = ", ".join(source_categories[:8])
        if len(source_categories) > 8:
            source_list += f" y otras {len(source_categories)-8} categorías"
        if goal.get('valor_base') is not None:
            baseline = (f"La serie que sustenta la meta preliminar registra {goal['valor_base']:g} "
                        f"{goal.get('unidad', 'unidades')} en {goal['periodo_base']} "
                        f"(indicador {goal['indicador']}; {memory['cita_apa_subsecuente']}). "
                        "Ese corte permite fijar una referencia inicial, pero debe cotejarse con la definición "
                        "del indicador, su unidad de medida, el programa educativo correspondiente y la memoria de cálculo antes de cerrar la meta.")
        else:
            baseline = (f"La memoria de cálculo establece Ref. 2026 como corte base, pero para este proyecto no aporta un valor "
                        f"numérico verificable con variables completas {memory['cita_apa_subsecuente']}. Por ello se propone validar "
                        "ese corte o ejecutar una actividad medible; una celda vacía, una fórmula incompleta o un cero pendiente de confirmación no se interpreta como desempeño real.")
        external_matters = [x for key in ('oportunidades', 'amenazas')
                            for x in foda[key] if x.get('tipo') == 'inferencia_contextual_externa']
        external_summary = " ".join(
            f"{x['texto']} {x['cita_apa']} La aplicación a la Facultad está por verificar: {x['limite']}"
            for x in external_matters[:2])
        tags = PENDING_TAGS[number]
        tag_text = ", ".join("{" + tag + "}" for tag in tags)
        diagnosis = DIAGNOSTIC_PROSE[number]
        context_factors = [next((x for x in foda[key] if x.get("tipo") == "inferencia_contextual_externa"), None)
                           for key in ("oportunidades", "amenazas")]
        external_text = " ".join(
            f"Como inferencia contextual {('favorable' if x['cuadrante'] == 'oportunidades' else 'de riesgo')}, "
            f"{x['texto'][0].lower() + x['texto'][1:]} {x['cita_apa']} ({x['limite']})"
            for x in context_factors if x
        )
        pending_text = "Para verificar los aspectos locales aún no medidos: " + ", ".join("{" + tag + "}" for tag in tags) + "."
        prose = " ".join(part for part in (FODA_PROSE[number], external_text, pending_text) if part)
        projects.append({
            "numero": number, "clave": source["clave"], "nombre": source["nombre"],
            "pilar": pillar["numero_romano"], "diagnostico": diagnosis,
            "alineacion_fi360": {"eje": axis, "prioridad": context, "referencia": location,
                                  "fuente": "https://drive.google.com/file/d/1BFLENt1LFr_Ow093_JR547QsQyV-hIER/view?usp=drivesdk"},
            "objetivo_general": general,
            "criterios_estrategias_fuente": "https://drive.google.com/file/d/1RBux9_NWokOvc3oTUgSlLdxmwHk0XPod/view?usp=drivesdk",
            "politicas": policies, "objetivos_especificos": specifics,
            "cruces_indicadores": cross_charts,
            "referencia_base_2026": memory_project,
            "referencias_apa": sorted({memory["referencia_apa"],
                *[json.loads((PLAN / item["archivo"]).read_text(encoding="utf-8"))["referencia_apa"] for item in refs],
                *[item["referencia_apa"] for item in external_matters]}),
            "foda": foda, "prosa_foda": prose, "pendientes_foda": tags,
            "solicitar_integracion_foda": False, "prosa_foda_interpretada": None, "evidencia": refs,
            "validaciones_pendientes": ["Confirmar metas con la memoria de cálculo y el área responsable.",
                                       "Resolver referencias de cuestionarios y agenda antes de interpretar el FODA local."],
        })

projects, memory_report = complete_projects(projects, PLAN, preserve_edits=False)

# El diagnóstico es prosa sustantiva, no un informe de trazabilidad. Esta
# guardia evita que vuelvan a publicarse los encabezados y párrafos genéricos
# de la primera versión del módulo.
for project in projects:
    diagnosis = project["diagnostico"]
    forbidden = (
        "Situación actual y alcance",
        "Evidencia cuantitativa y factores internos",
        "Referencia base de planeación",
        "Entorno y contraste externo",
        "Consulta pendiente",
        "Orientación de mejora",
        "tablero consultado",
        "se consultaron las categorías",
        "este diagnóstico se refiere al proyecto",
        "las cifras deben leerse por periodo y ámbito",
        "memoria de cálculo",
        "archivo de Excel",
    )
    matches = [phrase for phrase in forbidden if phrase.casefold() in diagnosis.casefold()]
    if matches:
        raise ValueError(
            f"El diagnóstico del proyecto {project['numero']} conserva texto de trazabilidad: {matches}"
        )
    if len(diagnosis.split("\n\n")) < 2:
        raise ValueError(f"El diagnóstico del proyecto {project['numero']} debe desarrollar al menos dos párrafos.")

    for policy in project.get("politicas", []):
        text = str(policy.get("texto", "")).strip()
        if not re.search(
            r"\b(?:deberá|deberán|tendrá|tendrán|será|serán|entrará|entrarán|garantizará|garantizarán)\b|"
            r"\bse\s+(?:destinará|destinarán|orientará|orientarán|regirá|regirán)\b",
            text,
            flags=re.I,
        ):
            raise ValueError(
                f"La política {policy.get('id')} del proyecto {project['numero']} no denota obligatoriedad futura: {text}"
            )
        forbidden_policy = (
            "vinculadas con el OE", "vinculadas con el objetivo", "resultados del objetivo",
            "metas anuales", "se programarán con responsables", "se revisarán cada año",
            "tendrán responsable, plazo e indicador", "antes de aprobar cambios",
        )
        matches_policy = [phrase for phrase in forbidden_policy if phrase.casefold() in text.casefold()]
        if matches_policy:
            raise ValueError(
                f"La política {policy.get('id')} del proyecto {project['numero']} conserva redacción operativa o redundante: {matches_policy}"
            )

output = {"titulo": "Propuesta preliminar del Plan de Desarrollo de la Facultad de Ingeniería 2026-2030",
          "origen": "Borrador analítico del asistente, separado del PRDI y de las revisiones de usuarios",
          "metodologia": prdi["metadatos"]["metodologia_sintactica_consistencia"],
          "periodo": "2026-2030", "proyectos": projects,
          "metodologia_memoria": {
              "documentos": ["fuentes/criterios_cpi.pdf", "fuentes/Guía para la formulación de planes de desarrollo.pdf"],
              "filas_prdi": memory_report["filas_prdi"],
              "metas_adicionales": len(memory_report["metas_adicionales"]),
          }}
(PLAN / "preliminar_fi.json").write_text(json.dumps(output, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"Generados {len(projects)} proyectos preliminares")
