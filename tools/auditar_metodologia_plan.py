#!/usr/bin/env python3
"""Audita la estructura metodológica del plan antes de generar los Excel.

Las reglas se derivan de la Guía para la formulación de planes de desarrollo:
diagnóstico interpretativo; objetivo general (infinitivo + qué + cómo + para
qué); objetivos específicos (infinitivo + qué + para qué); políticas como
obligaciones; estrategias (infinitivo + qué + cómo); y metas cuantificadas y
calendarizadas. No sustituye la revisión sustantiva, pero impide que regresen
los patrones de redacción ya detectados.
"""

from __future__ import annotations

import json
import re
import unicodedata
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / "Plan"
SOURCE = PLAN / "preliminar_fi.json"

METHOD = re.compile(r"\b(?:mediante|a través de|por medio de|con base en)\b", re.I)
PURPOSE = re.compile(r"\bpara\b", re.I)
DEADLINE = re.compile(r"\b(?:2027|2028|2029|2030)\b")
QUANTITY = re.compile(r"(?:\b\d+(?:[.,]\d+)?\b|\b(?:un|una)\b|%)", re.I)
OBLIGATION = re.compile(
    r"\b(?:deberá|deberán|tendrá|tendrán|será|serán|entrará|entrarán|garantizará|garantizarán)\b|"
    r"\bse\s+(?:destinará|destinarán|orientará|orientarán|regirá|regirán)\b",
    re.I,
)
POLICY_SUBJECT = re.compile(
    r"^(?:El|La|Los|Las)\s+(?:personal|personas?|estudiantado|profesorado|comunidad|"
    r"comités?|coordinaciones?|comisiones?|núcleos?|órganos?)\b",
    re.I,
)
FORBIDDEN_TRACE = re.compile(
    r"tablero consultado|se consultaron las categorías|este diagnóstico se refiere|"
    r"las cifras deben leerse|memoria de cálculo|archivo de excel|proyecto \d+|"
    r"situación actual y alcance|consulta pendiente",
    re.I,
)
FORBIDDEN_POLICY = re.compile(
    r"\bOE\d+\b|\bobjetivo\b|\bmetas?\s+anuales?\b|\bcada\s+año\b|"
    r"\b20(?:2[6-9]|30)\b|responsable, plazo e indicador|antes de aprobar cambios",
    re.I,
)
GENERIC_STRATEGY = re.compile(
    r"coordinar responsables, recursos y seguimiento|documentar las brechas por programa o área|"
    r"acordar con .+ las acciones para .+ y revisar sus resultados",
    re.I,
)
STOP = {
    "para", "mediante", "desde", "hasta", "entre", "cada", "facultad", "ingenieria",
    "como", "sobre", "esta", "este", "estos", "estas", "todo", "toda", "todos", "todas",
    "del", "las", "los", "una", "uno", "con", "por", "que", "sus", "más", "años",
}


def normalized(text: str) -> str:
    value = unicodedata.normalize("NFKD", text.casefold())
    return "".join(c for c in value if not unicodedata.combining(c))


def starts_infinitive(text: str) -> bool:
    first = normalized(text.strip()).split(" ", 1)[0].strip("¿¡.,;:()")
    return len(first) > 2 and first.endswith("r")


def tokens(text: str) -> set[str]:
    return {
        word for word in re.findall(r"[a-záéíóúñü]{4,}", normalized(text))
        if word not in STOP
    }


def similarity(left: str, right: str) -> float:
    a, b = tokens(left), tokens(right)
    return len(a & b) / len(a | b) if a | b else 0.0


def main() -> int:
    data = json.loads(SOURCE.read_text(encoding="utf-8"))
    projects = data.get("proyectos", [])
    errors: list[str] = []
    counts = {"diagnosticos": 0, "objetivos": 0, "politicas": 0, "estrategias": 0, "metas": 0}

    if [p.get("numero") for p in projects] != list(range(2, 23)):
        errors.append("La colección debe contener, en orden, los proyectos 2 a 22.")

    historical_markers = {
        2: ("2013-2014", "2024-2025"),
        3: ("2016-2017", "2025-2026"),
        4: ("2018", "2025"),
        9: ("2014", "2025"),
        10: ("2018", "2025"),
        11: ("2014", "2025"),
        15: ("2013-2014", "2025-2026"),
        18: ("2018", "2025"),
        20: ("2018", "2025"),
    }

    for project in projects:
        number = int(project["numero"])
        diagnosis = str(project.get("diagnostico", "")).strip()
        counts["diagnosticos"] += 1
        if len(diagnosis) < 600 or diagnosis.count("\n\n") < 1:
            errors.append(f"P{number}: el diagnóstico no desarrolla al menos dos párrafos sustantivos.")
        if FORBIDDEN_TRACE.search(diagnosis):
            errors.append(f"P{number}: el diagnóstico contiene trazabilidad o lenguaje de proceso.")
        if not re.search(r"\b(?:sin embargo|no obstante|pero|aunque|a la vez|al mismo tiempo|sin|cuando|depende|siempre que)\b", diagnosis, re.I):
            errors.append(f"P{number}: el diagnóstico no contrasta fortalezas y problemas.")
        if number in historical_markers:
            missing = [marker for marker in historical_markers[number] if marker not in diagnosis]
            if missing:
                errors.append(f"P{number}: no integra los extremos de la serie histórica {missing}.")

        general = str(project.get("objetivo_general", "")).strip()
        if re.search(r"\bfomentar\b", general, re.I):
            errors.append(f"P{number}: el objetivo general usa 'fomentar', que no expresa un compromiso verificable.")
        if not starts_infinitive(general) or not METHOD.search(general) or not PURPOSE.search(general):
            errors.append(f"P{number}: el objetivo general no cumple infinitivo + qué + cómo + para qué.")
        if DEADLINE.search(general) or re.search(r"\d+(?:[.,]\d+)?\s*%", general):
            errors.append(f"P{number}: el objetivo general contiene una cantidad o plazo propio de meta.")

        objectives = project.get("objetivos_especificos", [])
        if not objectives:
            errors.append(f"P{number}: no contiene objetivos específicos.")
        objective_ids = {str(o.get("id")) for o in objectives}
        for objective in objectives:
            counts["objetivos"] += 1
            oid = str(objective.get("id"))
            text = str(objective.get("texto", "")).strip()
            if re.search(r"\bfomentar\b", text, re.I):
                errors.append(f"P{number} {oid}: usa 'fomentar', que no expresa un compromiso verificable.")
            if not starts_infinitive(text) or not PURPOSE.search(text):
                errors.append(f"P{number} {oid}: no cumple infinitivo + qué + para qué.")
            if DEADLINE.search(text) or re.search(r"\d+(?:[.,]\d+)?\s*%", text):
                errors.append(f"P{number} {oid}: contiene una cantidad o plazo propio de meta.")
            strategies = objective.get("estrategias", [])
            if not strategies:
                errors.append(f"P{number} {oid}: no contiene estrategias.")
            for strategy in strategies:
                counts["estrategias"] += 1
                sid = str(strategy.get("id"))
                strategy_text = re.sub(r"\s*\[[CI, ]+\]\s*$", "", str(strategy.get("texto", "")).strip())
                if not starts_infinitive(strategy_text) or not METHOD.search(strategy_text):
                    errors.append(f"P{number} {sid}: no cumple infinitivo + qué + cómo.")
                if GENERIC_STRATEGY.search(strategy_text):
                    errors.append(f"P{number} {sid}: conserva una estrategia genérica u operativa.")
                if similarity(text, strategy_text) >= 0.72:
                    errors.append(f"P{number} {sid}: repite casi literalmente el objetivo específico.")
                goals = strategy.get("metas", [])
                if not goals:
                    errors.append(f"P{number} {sid}: carece de meta.")
                for goal in goals:
                    counts["metas"] += 1
                    goal_text = str(goal.get("descripcion", "")).strip()
                    if not starts_infinitive(goal_text):
                        errors.append(f"P{number} {sid}: la meta no inicia con verbo en infinitivo: {goal_text}")
                    if not QUANTITY.search(goal_text):
                        errors.append(f"P{number} {sid}: la meta no indica cuánto: {goal_text}")
                    if not DEADLINE.search(goal_text):
                        errors.append(f"P{number} {sid}: la meta no indica cuándo: {goal_text}")
                    if normalized(goal_text).count("facultad de ingenieria") > 1:
                        errors.append(f"P{number} {sid}: la meta duplica el ámbito de la Facultad.")
                    if re.search(r"\bun\s+\w+\s+(?:anuales|articulados|orientados|academicos)\b", normalized(goal_text)):
                        errors.append(f"P{number} {sid}: la meta contiene una discordancia gramatical.")

        policies = project.get("politicas", [])
        covered: set[str] = set()
        if len(policies) < 2:
            errors.append(f"P{number}: debe contener al menos dos políticas estratégicas.")
        for policy in policies:
            counts["politicas"] += 1
            pid = str(policy.get("id"))
            policy_text = str(policy.get("texto", "")).strip()
            covered.update(str(x) for x in policy.get("objetivos", []))
            if not OBLIGATION.search(policy_text):
                errors.append(f"P{number} {pid}: la política no expresa obligación futura.")
            if not POLICY_SUBJECT.search(policy_text):
                errors.append(
                    f"P{number} {pid}: la política no inicia con la población o instancia específica "
                    "que asume la obligación."
                )
            if starts_infinitive(policy_text) or FORBIDDEN_POLICY.search(policy_text):
                errors.append(f"P{number} {pid}: está redactada como actividad o repite otros componentes.")
        if covered != objective_ids:
            errors.append(f"P{number}: las políticas cubren {sorted(covered)} y se esperaban {sorted(objective_ids)}.")

    expected = {"diagnosticos": 21, "objetivos": 48, "politicas": 54, "estrategias": 51, "metas": 130}
    for label, value in expected.items():
        if counts[label] != value:
            errors.append(f"Se encontraron {counts[label]} {label}; se esperaban {value}.")

    if errors:
        raise RuntimeError("Auditoría metodológica fallida:\n- " + "\n- ".join(errors))

    print("Auditoría metodológica superada.")
    print(", ".join(f"{value} {label}" for label, value in counts.items()) + ".")
    print("Objetivos, estrategias y metas cumplen su sintaxis metodológica.")
    print("Las políticas identifican una población o instancia específica y expresan una obligación futura.")
    print("Los proyectos con series comparables integran sus extremos históricos en el diagnóstico.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
