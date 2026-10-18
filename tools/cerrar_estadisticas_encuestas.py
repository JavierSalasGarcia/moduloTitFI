#!/usr/bin/env python3
"""Guarda un corte agregado de encuestas y actualiza sólo el diagnóstico base.

No modifica respuestas ni revisiones de usuarios. Los formularios permanecen abiertos.
"""
from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo


ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / "Plan"
TAG = re.compile(r"\{((?:C\d\d-P\d\d)|(?:AG-\d\d))\}")


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Crea el corte estadístico sin cerrar la recepción de respuestas.")
    parser.add_argument("--responses", type=Path, required=True, help="Carpeta que contiene C01.jsonl, etc.")
    parser.add_argument("--apply", action="store_true", help="Actualiza la propuesta base y escribe el corte en Plan.")
    parser.add_argument("--cutoff", help="Fecha ISO del corte; permite reproducir exactamente una fotografía previa.")
    return parser.parse_args()


def read_json(path: Path):
    return json.loads(path.read_text(encoding="utf-8"))


def records(path: Path) -> list[dict]:
    if not path.is_file():
        return []
    result = []
    for line in path.read_text(encoding="utf-8").splitlines():
        try:
            item = json.loads(line)
        except json.JSONDecodeError:
            continue
        if isinstance(item, dict):
            result.append(item)
    return result


def answer_counts(rows: list[dict], question_id: str) -> Counter:
    return Counter(
        str(value)
        for item in rows
        if (value := item.get("respuestas", {}).get(question_id)) not in (None, "", [])
    )


def joint_count(rows: list[dict], first: str, first_values: set[str], second: str, second_values: set[str]) -> tuple[int, int]:
    selected = [item for item in rows if str(item.get("respuestas", {}).get(first)) in first_values]
    matches = sum(str(item.get("respuestas", {}).get(second)) in second_values for item in selected)
    return matches, len(selected)


def ratio(part: int, total: int) -> str:
    return f"{part} de {total}" if total else "sin respuestas válidas"


def project_insights(rows: dict[str, list[dict]], cutoff_date: str) -> dict[str, str]:
    """Interpreta el conjunto completo de respuestas y sus relaciones, sin publicar texto libre."""
    c01, c02, c04 = rows.get("C01", []), rows.get("C02", []), rows.get("C04", [])
    c11, c12, c14, c15 = rows.get("C11", []), rows.get("C12", []), rows.get("C14", []), rows.get("C15", [])
    c16, c17 = rows.get("C16", []), rows.get("C17", [])

    first_risk_bases, first_risk_total = joint_count(
        c01, "C01-P03", {"Incierto", "Poco probable", "No continuaré"}, "C01-P04", {"Bases académicas"}
    )
    first_secure_bases, first_secure_total = joint_count(c01, "C01-P03", {"Muy probable"}, "C01-P04", {"Bases académicas"})
    advanced_risk_repeat, advanced_repeat_total = joint_count(
        c16, "C16-P02", {"Reprobación o repetición de asignaturas"}, "C16-P06", {"Incierto", "Poco probable"}
    )
    advanced_risk_schedule, advanced_schedule_total = joint_count(
        c16, "C16-P02", {"Horarios, seriación u oferta de grupos"}, "C16-P06", {"Incierto", "Poco probable"}
    )
    unaware_personal, unaware_total = joint_count(
        c01, "C01-P05", {"No sé cómo acceder"}, "C01-P07", {"Tutoría personalizada"}
    )
    insufficient_personal, insufficient_total = joint_count(
        c01, "C01-P05", {"Existe, pero es insuficiente"}, "C01-P07", {"Tutoría personalizada"}
    )

    def c(data: list[dict], qid: str, *values: str) -> int:
        frequencies = answer_counts(data, qid)
        return sum(frequencies[value] for value in values)

    common_note = (
        f"La lectura usa todas las respuestas válidas incluidas en el corte del {cutoff_date}; "
        "describe asociaciones y convergencias, no causalidad ni representatividad estadística."
    )
    return {
        "2": (
            f"La dificultad de permanencia se concentra en dos momentos conectados: al ingreso, las bases académicas aparecen en "
            f"{ratio(first_risk_bases, first_risk_total)} casos con continuidad incierta o baja, frente a {ratio(first_secure_bases, first_secure_total)} "
            f"entre quienes consideran muy probable continuar; en semestres avanzados, horarios, seriación u oferta de grupos afectan a "
            f"{c(c16, 'C16-P02', 'Horarios, seriación u oferta de grupos')} de {len(c16)}, pero la incertidumbre sobre concluir a tiempo es relativamente "
            f"mayor cuando ya existe reprobación o repetición ({ratio(advanced_risk_repeat, advanced_repeat_total)}) que ante la restricción de horarios "
            f"({ratio(advanced_risk_schedule, advanced_schedule_total)}). La coincidencia con {c(c02, 'C02-P01', 'Bases de primer ingreso')} de {len(c02)} "
            "respuestas docentes que señalan bases de primer ingreso vincula el problema diagnosticado con apoyos tempranos y con una programación académica que evite acumular rezago. "
            + common_note
        ),
        "3": (
            f"En posgrado, {c(c17, 'C17-P07', 'Probable', 'Muy probable')} de {len(c17)} estudiantes prevén obtener el grado en el plazo esperado, "
            f"pero los obstáculos se distribuyen entre tiempo disponible ({c(c17, 'C17-P02', 'Tiempo disponible')}), financiamiento o beca "
            f"({c(c17, 'C17-P02', 'Financiamiento o beca')}) y dirección o asesoría ({c(c17, 'C17-P02', 'Dirección o asesoría')}). "
            "Las necesidades de apoyo corresponden al obstáculo declarado —beca cuando falta financiamiento y asesoría cuando falta dirección—, por lo que el riesgo no parece generalizado sino segmentado y requiere rutas de atención distintas. "
            "La única respuesta de coordinación sitúa la obtención del grado como etapa prioritaria; debe tratarse como señal de gestión, no como estimación poblacional. " + common_note
        ),
        "4": (
            f"La disposición estudiantil para integrarse a proyectos, publicaciones, movilidad o colaboración externa es favorable o muy favorable en "
            f"{c(c17, 'C17-P06', 'Favorable', 'Muy favorable')} de {len(c17)} respuestas. En contraste, la única respuesta de investigación identifica tiempo y carga como límite, evidencia parcial y una vinculación sólo ocasional entre reconocimientos y resultados pertinentes. "
            "La relación sugiere que el cuello de botella no es el interés del estudiantado, sino la capacidad de documentar, acompañar y convertir esa disposición en productos y cuerpos académicos consolidados; la evidencia de coordinación es indicativa por su tamaño. " + common_note
        ),
        "5": (
            f"Antes del egreso, {c(c16, 'C16-P05', 'Prácticas o estancias profesionales')} de {len(c16)} estudiantes priorizan prácticas o estancias, "
            f"muy por encima de investigación, servicio social aplicado, emprendimiento o movilidad. En posgrado, {c(c17, 'C17-P06', 'Favorable', 'Muy favorable')} de {len(c17)} valoran favorablemente las oportunidades de colaboración externa; sin embargo, la única respuesta de Vinculación señala que los convenios requieren atención y que la opinión de egresados y empleadores se incorpora sólo ocasionalmente. "
            "La demanda formativa y la valoración positiva existen, pero necesitan un mecanismo estable que conecte convenios, retroalimentación externa y experiencias profesionales verificables. " + common_note
        ),
        "6": (
            "No hay respuestas de Difusión Cultural y sólo existe una respuesta de Investigación, que califica la evidencia como parcial. "
            "Por tanto, el problema diagnosticado no puede atribuirse todavía a falta de interés, alcance o canales: el hallazgo defendible es una brecha de medición y trazabilidad que impide relacionar actividades de divulgación con públicos y resultados. " + common_note
        ),
        "7": (
            f"El ambiente de inclusión y respeto es favorable o muy favorable para {c(c01, 'C01-P08', 'Favorable', 'Muy favorable')} de {len(c01)} estudiantes de ingreso y para "
            f"{c(c16, 'C16-P08', 'Favorable', 'Muy favorable')} de {len(c16)} de semestres avanzados, pero sólo {c(c01, 'C01-P11', 'Sí')} de {len(c01)} reportan pertenecer a un capítulo estudiantil. "
            "La relación distingue clima de participación: una percepción generalmente positiva de convivencia no implica incorporación a espacios organizados. Sin respuestas del área cultural, falta comprobar si programación, horarios y difusión explican esa baja participación. " + common_note
        ),
        "8": (
            f"La convivencia se valora positivamente en {c(c01, 'C01-P08', 'Favorable', 'Muy favorable')} de {len(c01)} respuestas de ingreso y "
            f"{c(c16, 'C16-P08', 'Favorable', 'Muy favorable')} de {len(c16)} avanzadas, mientras que los apoyos considerados más útiles para permanecer son académicos, personalizados, de horario o económicos, no deportivos de manera explícita. "
            "Sin respuestas de la coordinación responsable ni una variable directa de práctica deportiva, no es válido inferir demanda o impacto; el diagnóstico debe tratar la falta de medición de acceso, frecuencia y permanencia en actividades como la principal brecha. " + common_note
        ),
        "9": (
            f"La tutoría es útil y oportuna para {c(c01, 'C01-P05', 'Útil y oportuno')} de {len(c01)} estudiantes de ingreso, pero "
            f"{c(c01, 'C01-P05', 'Existe, pero es insuficiente', 'No sé cómo acceder', 'No está disponible')} reportan insuficiencia, desconocimiento o ausencia. "
            f"Entre quienes no saben cómo acceder, {ratio(unaware_personal, unaware_total)} priorizan tutoría personalizada, frente a {ratio(insufficient_personal, insufficient_total)} entre quienes sí la ubican pero la consideran insuficiente. En semestres avanzados, el acompañamiento es regular o desfavorable para "
            f"{c(c16, 'C16-P03', 'Regular', 'Desfavorable', 'Muy desfavorable')} de {len(c16)}, y el profesorado concentra sus necesidades en actualización disciplinar y herramientas digitales. "
            "El problema combina visibilidad del servicio, capacidad de atención y actualización docente; aumentar cobertura sin resolver esas tres capas podría no reducir el rezago. " + common_note
        ),
        "10": (
            f"En la muestra administrativa, {c(c04, 'C04-P02', 'Disponible pero insuficiente', 'No disponible')} de {len(c04)} consideran insuficientes o ausentes las herramientas y "
            f"{c(c04, 'C04-P03', 'Afectación grave')} reportan afectación grave por disponibilidad de personal. La comunicación entre áreas está polarizada, con "
            f"{c(c04, 'C04-P05', 'Favorable', 'Muy favorable')} valoraciones positivas y {c(c04, 'C04-P05', 'Desfavorable', 'Muy desfavorable')} negativas. "
            "Así, la dignificación laboral se relaciona no sólo con equipamiento, sino con carga, continuidad del servicio y coordinación cotidiana. Son seis respuestas, por lo que orientan temas de revisión y no permiten generalizar. " + common_note
        ),
        "11": (
            f"La insuficiencia de recursos converge entre poblaciones: {c(c16, 'C16-P04', 'Disponible pero insuficiente', 'No disponible')} de {len(c16)} estudiantes avanzados, "
            f"{c(c17, 'C17-P04', 'Disponible pero insuficiente', 'No lo sé')} de {len(c17)} de posgrado y {c(c02, 'C02-P03', 'Disponible pero insuficiente', 'No disponible')} de {len(c02)} docentes no confirman disponibilidad suficiente. "
            f"A la vez, el mantenimiento administrativo se reparte por igual entre atención sistemática, ocasional y inexistente ({ratio(2, len(c04))} en cada condición). "
            "La consistencia entre acceso académico y gestión de reportes vincula el problema con inventario, priorización y tiempos de reposición, no únicamente con comprar equipo. " + common_note
        ),
        "12": (
            f"La brecha tecnológica es transversal: {c(c01, 'C01-P06', 'Disponible pero insuficiente', 'No disponible')} de {len(c01)} estudiantes de ingreso, "
            f"{c(c16, 'C16-P04', 'Disponible pero insuficiente', 'No disponible')} de {len(c16)} avanzados y {c(c02, 'C02-P03', 'Disponible pero insuficiente', 'No disponible')} de {len(c02)} docentes reportan insuficiencia o falta de recursos. "
            f"En posgrado, {c(c17, 'C17-P04', 'No lo sé')} de {len(c17)} ni siquiera identifican la disponibilidad, además de {c(c17, 'C17-P04', 'Disponible pero insuficiente')} que la consideran insuficiente. "
            "La coincidencia sugiere un problema combinado de capacidad, actualización y comunicación de acceso; por eso el fortalecimiento debe medir disponibilidad efectiva por curso y no sólo existencia de infraestructura. " + common_note
        ),
        "13": (
            f"Sólo {c(c01, 'C01-P11', 'Sí')} de {len(c01)} estudiantes de ingreso declaran pertenecer a un capítulo, mientras la comunicación interáreas de la muestra administrativa se divide entre "
            f"{c(c04, 'C04-P05', 'Favorable', 'Muy favorable')} valoraciones positivas y {c(c04, 'C04-P05', 'Desfavorable', 'Muy desfavorable')} negativas. "
            "La gobernanza presenta así dos señales relacionadas: baja participación organizada y circulación desigual de acuerdos. La única respuesta de Planeación reporta evidencia completa, por lo que conviene contrastar la existencia formal de registros con su conocimiento y uso por la comunidad. " + common_note
        ),
        "14": (
            f"Entre seis respuestas administrativas, los procesos señalados como urgentes son servicios escolares ({c(c04, 'C04-P01', 'Servicios escolares')}), compras y recursos ({c(c04, 'C04-P01', 'Compras y recursos')}) y mantenimiento ({c(c04, 'C04-P01', 'Mantenimiento')}). "
            "La única respuesta de Planeación identifica la coordinación de procesos como obstáculo principal. En conjunto, la señal apunta menos a ausencia de normas que a posibles interfaces, responsables y secuencias no resueltas entre áreas; el tamaño de muestra exige validarlo con revisión documental. " + common_note
        ),
        "15": (
            f"La única respuesta de Planeación declara evidencia completa y revisión trimestral, pero ubica la falta de responsables como principal dificultad para medir metas. Esa percepción contrasta con el profesorado: "
            f"{c(c02, 'C02-P02', 'No lo sé', 'No se usan')} de {len(c02)} no conocen o no usan datos de trayectoria, y el seguimiento de evaluación externa es sistemático sólo en {c(c02, 'C02-P04', 'Sistemático y documentado')} casos. "
            "La relación sugiere que producir evidencia no garantiza su apropiación operativa; la planeación requiere responsables, ciclos de retroalimentación y acceso comprensible para quienes ejecutan acciones. " + common_note
        ),
        "16": (
            f"Los procesos administrativos urgentes se concentran en servicios escolares, compras y recursos y mantenimiento ({len(c04)} respuestas en total), mientras la única respuesta de Planeación señala coordinación de procesos como obstáculo. "
            "La integridad en el manejo de recursos debe examinar, por tanto, la trazabilidad entre solicitud, autorización, compra, entrega y servicio, no sólo la disponibilidad presupuestal. La muestra es insuficiente para afirmar fallas; identifica la ruta que debe auditarse. " + common_note
        ),
        "17": (
            f"La atención de mantenimiento y seguridad aparece fragmentada: de {len(c04)} respuestas administrativas, {c(c04, 'C04-P04', 'Sistemático y documentado')} describen seguimiento sistemático, "
            f"{c(c04, 'C04-P04', 'Ocasional')} ocasional y {c(c04, 'C04-P04', 'No se realiza')} inexistente. La única respuesta de Planeación vuelve a señalar coordinación de procesos. "
            "La dispersión interna es consistente con un control preventivo que no se experimenta de la misma manera entre áreas; conviene verificar un folio único, responsables, plazos y cierre de reportes. " + common_note
        ),
        "18": (
            f"La inclusión y el respeto reciben valoraciones favorables o muy favorables de {c(c01, 'C01-P08', 'Favorable', 'Muy favorable')} de {len(c01)} estudiantes de ingreso, "
            f"{c(c16, 'C16-P08', 'Favorable', 'Muy favorable')} de {len(c16)} avanzados y {c(c17, 'C17-P08', 'Favorable', 'Muy favorable')} de {len(c17)} de posgrado. "
            f"Sin embargo, persisten {c(c01, 'C01-P08', 'Regular', 'Desfavorable', 'Muy desfavorable')} valoraciones no favorables al ingreso y {c(c16, 'C16-P08', 'Regular', 'Desfavorable', 'Muy desfavorable')} en etapas avanzadas, junto con participación en capítulos de sólo {c(c01, 'C01-P11', 'Sí')} de {len(c01)}. "
            "El promedio positivo puede ocultar grupos con menor pertenencia; la intervención debe localizar por carrera y etapa dónde se concentran esas experiencias. " + common_note
        ),
        "19": (
            f"La percepción de convivencia es mayoritariamente positiva en ingreso ({c(c01, 'C01-P08', 'Favorable', 'Muy favorable')} de {len(c01)}), licenciatura avanzada "
            f"({c(c16, 'C16-P08', 'Favorable', 'Muy favorable')} de {len(c16)}) y posgrado ({c(c17, 'C17-P08', 'Favorable', 'Muy favorable')} de {len(c17)}), pero no es unánime. "
            "Las respuestas regulares o desfavorables en las dos etapas de licenciatura muestran que una cultura de paz no puede medirse sólo por el balance global: requiere seguimiento de pertenencia, conflictos y acceso a mecanismos de atención por segmento. " + common_note
        ),
        "20": (
            f"Existe una base clara para colaboración social: {c(c16, 'C16-P05', 'Prácticas o estancias profesionales')} de {len(c16)} estudiantes avanzados priorizan prácticas o estancias y "
            f"{c(c17, 'C17-P06', 'Favorable', 'Muy favorable')} de {len(c17)} estudiantes de posgrado valoran favorablemente sus oportunidades externas. "
            "No obstante, la única respuesta de Vinculación prioriza el registro de convenios y reporta uso sólo ocasional de la opinión de egresados y empleadores. La oportunidad consiste en convertir la demanda estudiantil y los vínculos existentes en proyectos con contraparte, resultados y retroalimentación documentados. " + common_note
        ),
        "21": (
            f"La comunicación interáreas no tiene una experiencia compartida: en {len(c04)} respuestas administrativas hay {c(c04, 'C04-P05', 'Favorable', 'Muy favorable')} valoraciones positivas, "
            f"{c(c04, 'C04-P05', 'Regular')} regular y {c(c04, 'C04-P05', 'Desfavorable', 'Muy desfavorable')} negativas. Sin respuestas del área cultural sobre medición de alcance, no puede conocerse si los canales llegan a sus públicos o sólo emiten información. "
            "El diagnóstico debe vincular modernización con trazabilidad —quién recibió, comprendió y actuó—, además de la publicación de mensajes. " + common_note
        ),
        "22": (
            f"La encuesta disponible no mide de forma directa prácticas ambientales. Las señales relacionadas son operativas: el mantenimiento se describe como sistemático en sólo {c(c04, 'C04-P04', 'Sistemático y documentado')} de {len(c04)} respuestas administrativas y la única respuesta de Investigación identifica tiempo y carga como restricción. "
            "Esto no permite concluir desempeño ambiental; muestra que cualquier programa de sostenibilidad necesita responsables, registros de mantenimiento y carga asignada para que sus resultados sean verificables. " + common_note
        ),
    }


def aggregate(catalog: dict, response_dir: Path, cutoff: str) -> dict:
    surveys = {}
    rows_by_survey = {}
    for survey in catalog["encuestas"]:
        survey_records = records(response_dir / f"{survey['id']}.jsonl")
        rows_by_survey[survey["id"]] = survey_records
        segments = Counter(str(item.get("segmento") or "facultad") for item in survey_records)
        question_rows = {}
        for question in survey["preguntas"]:
            qid = question["id"]
            values = [item.get("respuestas", {}).get(qid) for item in survey_records]
            present = [value for value in values if value not in (None, "", [])]
            row = {
                "texto": question["texto"],
                "tipo": question["tipo"],
                "respuestas_validas": len(present),
            }
            if question["tipo"] == "opcion":
                counts = Counter(str(value) for value in present)
                row["frecuencias"] = {option: counts.get(option, 0) for option in question.get("opciones", [])}
            elif question["tipo"] == "ranking_asignaturas":
                counts = Counter(subject for value in present if isinstance(value, list) for subject in value)
                row["frecuencias"] = dict(counts.most_common())
            elif question["tipo"] in {"abierta", "correo"}:
                row["detalle_omitido"] = "Las respuestas textuales y los correos no forman parte del corte agregado."
            question_rows[qid] = row
        dates = [str(item.get("fecha") or "") for item in survey_records if item.get("fecha")]
        surveys[survey["id"]] = {
            "titulo": survey["titulo"],
            "respondidas": len(survey_records),
            "meta": survey.get("meta_respuestas"),
            "segmentos": dict(sorted(segments.items())),
            "ultima_respuesta_incluida": max(dates) if dates else None,
            "preguntas": question_rows,
        }
    cutoff_date = datetime.fromisoformat(cutoff).strftime("%d/%m/%Y")
    return {
        "version": 2,
        "corte": cutoff,
        "zona_horaria": "America/Mexico_City",
        "recepcion_posterior": "abierta",
        "nota": "Fotografía agregada para consulta posterior. Las respuestas recibidas después del corte siguen disponibles en las estadísticas en vivo.",
        "cuestionarios": surveys,
        "analisis_por_proyecto": project_insights(rows_by_survey, cutoff_date),
    }


def tag_labels(catalog: dict) -> dict[str, str]:
    labels = {}
    for survey in catalog["encuestas"]:
        for question in survey["preguntas"]:
            labels[question["id"]] = question["texto"].strip().rstrip(".")
    for item in catalog.get("agenda", []):
        labels[item["id"]] = f"{item['tema'].strip().rstrip('.')}: {item['orientacion'].strip().rstrip('.')}"
    return labels


def clean_tags(value: str, labels: dict[str, str]) -> str:
    cleaned = TAG.sub(lambda match: labels.get(match.group(1), match.group(1)), value)
    cleaned = cleaned.replace("Para verificar los aspectos locales aún no medidos:", "Aspectos locales que requieren contraste:")
    return re.sub(r"\s+([,.;:])", r"\1", cleaned).strip()


def observation(project: dict, snapshot: dict, cutoff_date: str) -> str:
    insight = snapshot.get("analisis_por_proyecto", {}).get(str(project.get("numero")), "")
    if not insight:
        insight = f"No hay evidencia agregada suficiente para relacionar variables de consulta en el corte del {cutoff_date}."
    return "Análisis de cuestionarios y entrevistas: " + insight


def clean_pending_paragraph(value: str, labels: dict[str, str]) -> str:
    value = clean_tags(value, labels)
    value = re.sub(r"\s*Aspectos locales que requieren contraste:.*\Z", "", value, flags=re.S).rstrip()
    return value


def update_proposal(snapshot: dict, catalog: dict) -> dict:
    path = PLAN / "preliminar_fi.json"
    proposal = read_json(path)
    labels = tag_labels(catalog)
    cutoff_date = datetime.fromisoformat(snapshot["corte"]).strftime("%d/%m/%Y")
    for project in proposal["proyectos"]:
        project["diagnostico"] = re.sub(
            r"\n\n(?:Observaciones derivadas de cuestionarios y entrevistas|Análisis de cuestionarios y entrevistas):.*\Z",
            "",
            clean_tags(project.get("diagnostico", ""), labels),
            flags=re.S,
        ).rstrip()
        project["diagnostico"] += "\n\n" + observation(project, snapshot, cutoff_date)
        project["prosa_foda"] = clean_pending_paragraph(project.get("prosa_foda", ""), labels)
    return proposal


def write_json(path: Path, value: dict) -> None:
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def main() -> int:
    args = arguments()
    catalog = read_json(PLAN / "instrumentos.json")
    cutoff = args.cutoff or datetime.now(ZoneInfo("America/Mexico_City")).replace(microsecond=0).isoformat()
    snapshot = aggregate(catalog, args.responses.resolve(), cutoff)
    print("Corte:", cutoff)
    print("Respuestas:", sum(item["respondidas"] for item in snapshot["cuestionarios"].values()))
    if args.apply:
        write_json(PLAN / "estadisticas_encuestas.json", snapshot)
        write_json(PLAN / "preliminar_fi.json", update_proposal(snapshot, catalog))
        print("Escritos: Plan/estadisticas_encuestas.json y Plan/preliminar_fi.json")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
