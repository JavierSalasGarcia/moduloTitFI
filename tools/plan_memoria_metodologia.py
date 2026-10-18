#!/usr/bin/env python3
"""Metodología reproducible para completar la memoria del PRDI con datos de FI.

La guía institucional exige que cada meta indique qué se hará, cuánto y cuándo;
la memoria registra variables para indicadores y valores absolutos para actividades.
Este módulo conserva las claves PRDI, usa las series históricas disponibles del sitio
y genera una programación 2027-2030 conservadora y trazable.
"""

from __future__ import annotations

import json
import math
import re
import unicodedata
from copy import deepcopy
from pathlib import Path
from typing import Any

from openpyxl import load_workbook


YEARS = (2027, 2028, 2029, 2030)
INSTITUTIONAL_SPACES = 55

PROJECT_AREAS = {
    2: "Subdirección Académica y coordinaciones de licenciatura",
    3: "Coordinación de Estudios Avanzados",
    4: "Coordinación de Investigación",
    5: "Coordinación de Investigación y Vinculación",
    6: "Coordinación de Investigación y Difusión Científica",
    7: "Coordinación de Difusión Cultural",
    8: "Coordinación de Actividades Deportivas",
    9: "Subdirección Académica",
    10: "Subdirección Administrativa",
    11: "Subdirección Administrativa",
    12: "Departamento de Tecnologías de la Información",
    13: "Dirección y órganos colegiados",
    14: "Dirección y Oficina de la Abogacía General",
    15: "Unidad de Planeación",
    16: "Subdirección Administrativa",
    17: "Contraloría Universitaria y Dirección",
    18: "Unidad de Igualdad de Género",
    19: "Unidad de Igualdad de Género y Dirección",
    20: "Coordinación de Extensión y Vinculación",
    21: "Departamento de Comunicación",
    22: "Comité de Sustentabilidad y Subdirección Administrativa",
}

# Adaptación de los objetivos institucionales a las condiciones y al ámbito de
# actuación de la Facultad. No son citas del PRDI: expresan la decisión local que
# se desprende del diagnóstico y bajo la cual se agrupan las metas operativas.
LOCAL_OBJECTIVES = {
    "PRDI2025-2029-OBJ-58": "Actualizar los planes de estudio de ingeniería para sostener su pertinencia, excelencia y evaluación externa con criterios transversales del SEAES.",
    "PRDI25-29-OBJ-81": "Diversificar la formación disciplinar e integral del estudiantado para fortalecer su desempeño académico, profesional y social.",
    "PRDI25-29-OBJ-82": "Mejorar el acceso, la permanencia, el egreso y la titulación del alumnado para reducir las brechas advertidas en las trayectorias escolares.",
    "PRDI25-29-OBJ-83": "Fortalecer el acompañamiento de egresados y su vinculación con los sectores productivo, público y social para favorecer la empleabilidad y la colaboración.",
    "PRDI2025-2029-OBJ-59": "Ampliar de manera incluyente el acceso a los estudios avanzados de la Facultad para formar capacidades especializadas de impacto regional.",
    "PRDI25-29-OBJ-84": "Consolidar la pertinencia y calidad de los estudios avanzados para mejorar la investigación, la graduación y la vinculación con el entorno.",
    "PRDI2025-2029-OBJ-60": "Fortalecer la investigación abierta, incluyente y socialmente pertinente para elevar la calidad y la colaboración académica de la Facultad.",
    "PRDI25-29-OBJ-85": "Priorizar recursos, infraestructura y vinculación externa para sostener proyectos de investigación de calidad y con resultados verificables.",
    "PRDI25-29-OBJ-86": "Mejorar la gestión de la propiedad intelectual y la transferencia tecnológica para proteger y aprovechar los resultados de la comunidad de Ingeniería.",
    "PRDI25-29-OBJ-87": "Fortalecer la ciencia abierta y la divulgación científica para ampliar el acceso y la calidad de los productos académicos de la Facultad.",
    "PRDI25-29-OBJ-63": "Ampliar la participación cultural de la comunidad de Ingeniería para fortalecer la identidad, la creatividad y la cohesión social.",
    "PRDI25-29-OBJ-88": "Integrar formación y reflexión cultural incluyente para contribuir al desarrollo integral y la transformación social de la comunidad.",
    "PRDI25-29-OBJ-64": "Promover hábitos de activación física y autocuidado para mejorar el bienestar de la comunidad de la Facultad.",
    "PRDI25-29-OBJ-90": "Ampliar la participación deportiva incluyente para desarrollar capacidades recreativas y competitivas del estudiantado.",
    "PRDI2025-2029-OBJ-65": "Fortalecer la formación pedagógica y profesional del personal académico para mejorar la experiencia educativa del estudiantado.",
    "PRDI25-29-OBJ-92": "Impulsar la certificación del profesorado en una segunda lengua para ampliar sus capacidades docentes y de internacionalización.",
    "PRDI25-29-OBJ-93": "Mejorar la habilitación y el reconocimiento científico del personal académico para fortalecer la investigación de la Facultad.",
    "PRDI2025-2029-OBJ-66": "Desarrollar las competencias del personal administrativo para mejorar los servicios, procesos y perfiles de puesto de la Facultad.",
    "PRDI25-29-OBJ-99": "Actualizar la organización y los procedimientos de la Facultad para hacer más clara, eficiente y verificable su gestión.",
    "PRDI25-29-OBJ-101": "Fortalecer las habilidades digitales de la comunidad de Ingeniería para aprovechar de forma segura y efectiva las tecnologías institucionales.",
    "PRDI25-29-OBJ-103": "Mejorar la disponibilidad y el mantenimiento de la infraestructura tecnológica para sostener las actividades académicas y administrativas.",
    "PRDI25-29-OBJ-104": "Fortalecer la autoprotección y la respuesta ante emergencias para reducir riesgos en las instalaciones de la Facultad.",
    "PRDI25-29-OBJ-105": "Mejorar la atención de seguridad con perspectiva de derechos humanos y género para proteger oportunamente a la comunidad.",
    "PRDI25-29-OBJ-108": "Fortalecer la identidad de la Facultad para consolidar el sentido de pertenencia mediante la recuperación, divulgación y valoración de su historia y vida universitaria.",
    "PRDI25-29-OBJ-117": "Diversificar responsablemente los ingresos extraordinarios para apoyar las funciones académicas y administrativas prioritarias de la Facultad.",
    "PRDI2025-2029-OBJ-74": "Fortalecer la igualdad y el reconocimiento de la diversidad para asegurar una participación incluyente en la comunidad de Ingeniería.",
    "PRDI25-29-OBJ-122": "Mejorar las prácticas de prevención de la discriminación para sostener ambientes respetuosos y accesibles en la Facultad.",
    "PRDI25-29-OBJ-124": "Ampliar los apoyos a personas en situación de vulnerabilidad para favorecer su inclusión y desarrollo académico o laboral.",
    "PRDI25-29-OBJ-125": "Incorporar el cuidado, la corresponsabilidad y el bienestar en los procesos de la Facultad para mantener entornos seguros.",
    "PRDI25-29-OBJ-126": "Desarrollar capacidades de mediación y gestión pacífica de conflictos para mejorar la convivencia en la Facultad.",
    "PRDI25-29-OBJ-127": "Prevenir conductas violentas mediante acciones de respeto, igualdad y atención oportuna para proteger a la comunidad.",
    "PRDI25-29-OBJ-128": "Evaluar los resultados de las acciones de convivencia para orientar mejoras verificables en la construcción de paz.",
    "PRDI25-29-OBJ-75": "Fortalecer la educación para la paz, los derechos humanos y la no violencia para favorecer relaciones respetuosas.",
    "PRDI2025-2029-OBJ-76": "Desarrollar competencias de emprendimiento e innovación para convertir conocimiento de ingeniería en proyectos sostenibles de impacto regional.",
    "PRDI25-29-OBJ-129": "Orientar capacidades de la Facultad a proyectos sociales interdisciplinarios para atender necesidades de comunidades y grupos vulnerables.",
    "PRDI25-29-OBJ-130": "Ampliar la colaboración con organizaciones públicas y privadas para fortalecer competencias, empleabilidad y retribución social del estudiantado.",
    "PRDI25-29-OBJ-131": "Fortalecer la formación lingüística para favorecer la movilidad, la empleabilidad y la inclusión cultural de la comunidad.",
    "PRDI25-29-OBJ-132": "Fortalecer la prevención y atención de primer nivel para mejorar el bienestar integral de la comunidad de Ingeniería.",
    "PRDI25-29-OBJ-77": "Mejorar la comunicación de la Facultad para facilitar el diálogo, la participación y la difusión verificable de sus resultados.",
    "PRDI2025-2029-OBJ-78": "Reducir los impactos ambientales de las actividades de la Facultad para avanzar hacia una operación más sostenible.",
    "PRDI25-29-OBJ-135": "Formar agentes de cambio ambiental en la comunidad de Ingeniería para multiplicar prácticas sostenibles.",
    "PRDI25-29-OBJ-136": "Mejorar el uso de agua, energía, papel y materiales para incorporar la sustentabilidad en la operación cotidiana de la Facultad.",
}

# La Guía para la formulación de planes de desarrollo define la política como
# una directriz obligatoria con la sintaxis: elemento o población + verbo en
# futuro + condición soporte. Estas políticas regulan decisiones sustantivas;
# no describen actividades, metas, procedimientos ni repiten el objetivo.
LOCAL_POLICIES = {
    "PRDI2025-2029-OBJ-58": [
        "Los comités curriculares de las licenciaturas deberán sustentar las actualizaciones de los planes de estudio en evidencia de pertinencia, resultados de evaluación y criterios transversales del SEAES.",
    ],
    "PRDI25-29-OBJ-81": [
        "El estudiantado tendrá acceso equitativo a experiencias disciplinares, culturales, científicas e internacionales pertinentes para su formación integral.",
    ],
    "PRDI25-29-OBJ-82": [
        "El personal de tutoría deberá priorizar el acompañamiento de quienes presenten mayores riesgos de rezago, abandono o demora en la titulación, sin menoscabo de la atención universal.",
    ],
    "PRDI25-29-OBJ-83": [
        "Las coordinaciones de los programas educativos deberán incorporar la participación de las personas egresadas en la mejora curricular y en la vinculación ética con los sectores social, público y productivo.",
    ],
    "PRDI2025-2029-OBJ-59": [
        "Las comisiones académicas de los posgrados deberán aplicar criterios de ingreso transparentes, incluyentes y congruentes con la capacidad formativa de cada programa.",
    ],
    "PRDI25-29-OBJ-84": [
        "Los núcleos académicos de los posgrados deberán sostener la calidad de los programas mediante habilitación disciplinar, trayectorias oportunas y proyectos pertinentes para el entorno.",
    ],
    "PRDI2025-2029-OBJ-60": [
        "Las personas investigadoras deberán conducir sus proyectos con integridad, apertura, inclusión y pertinencia social, con respeto a la libertad académica y a los derechos de las personas.",
    ],
    "PRDI25-29-OBJ-85": [
        "Las personas responsables de la gestión de la investigación deberán priorizar el uso compartido de capacidades, la calidad académica y las contribuciones verificables a problemas científicos, tecnológicos o sociales al asignar recursos.",
    ],
    "PRDI25-29-OBJ-86": [
        "Las personas investigadoras deberán someter los resultados susceptibles de protección a evaluación antes de divulgarlos o transferirlos, con respeto a la autoría y a la normatividad aplicable.",
        "Las personas responsables de la transferencia tecnológica deberán formalizar los acuerdos bajo condiciones de pertinencia social, distribución equitativa de beneficios y uso responsable del conocimiento.",
    ],
    "PRDI25-29-OBJ-87": [
        "El personal académico deberá difundir sus productos bajo principios de acceso abierto, rigor científico, accesibilidad y respeto a los derechos de autor.",
        "Las personas responsables de la divulgación científica deberán adaptar los lenguajes y formatos a públicos diversos sin menoscabar la precisión del conocimiento comunicado.",
    ],
    "PRDI25-29-OBJ-63": [
        "La comunidad de Ingeniería tendrá acceso equitativo a una oferta cultural diversa, accesible y compatible con sus condiciones de participación.",
    ],
    "PRDI25-29-OBJ-88": [
        "Las personas responsables de las actividades culturales deberán orientarlas al pensamiento crítico, la inclusión, la identidad universitaria y el diálogo sobre problemáticas sociales contemporáneas.",
    ],
    "PRDI25-29-OBJ-64": [
        "La comunidad de la Facultad tendrá acceso a opciones de activación física seguras, incluyentes y compatibles con la diversidad de jornadas y condiciones personales.",
    ],
    "PRDI25-29-OBJ-90": [
        "Las personas responsables del deporte universitario deberán asegurar condiciones de equidad, accesibilidad y seguridad para la participación recreativa y competitiva del estudiantado.",
    ],
    "PRDI2025-2029-OBJ-65": [
        "El personal académico tendrá acceso equitativo a oportunidades de formación pertinentes a las necesidades disciplinares, didácticas, digitales y socioemocionales de su práctica.",
    ],
    "PRDI25-29-OBJ-92": [
        "El profesorado tendrá acceso equitativo a formación y certificación lingüística pertinente para la docencia, la movilidad y la colaboración internacional.",
    ],
    "PRDI25-29-OBJ-93": [
        "Las comisiones de evaluación académica deberán valorar la habilitación y el desempeño del profesorado por su contribución a la docencia, la investigación colaborativa y la función social del conocimiento.",
    ],
    "PRDI2025-2029-OBJ-66": [
        "El personal administrativo tendrá acceso equitativo a formación vinculada con sus funciones, su desarrollo profesional y la calidad de los servicios universitarios.",
        "Las personas responsables de asignar funciones y oportunidades de desarrollo deberán observar perfiles de puesto actualizados, cargas razonables y trato digno.",
    ],
    "PRDI25-29-OBJ-99": [
        "Las personas responsables de los procesos administrativos deberán operar con atribuciones, criterios de atención y tiempos comprensibles para la comunidad usuaria.",
        "Las personas responsables de los trámites deberán eliminar cargas innecesarias sin debilitar la legalidad, la seguridad de la información ni la rendición de cuentas.",
    ],
    "PRDI25-29-OBJ-101": [
        "La comunidad de Ingeniería deberá utilizar las tecnologías institucionales con criterios de seguridad, accesibilidad, protección de datos y aprovechamiento académico.",
    ],
    "PRDI25-29-OBJ-103": [
        "El personal responsable de la infraestructura tecnológica deberá mantenerla disponible, interoperable y protegida conforme a la criticidad de los servicios que sostiene.",
    ],
    "PRDI25-29-OBJ-104": [
        "Las personas responsables de instalaciones y actividades deberán observar condiciones de prevención, accesibilidad y respuesta proporcional a los riesgos identificados.",
    ],
    "PRDI25-29-OBJ-105": [
        "El personal responsable de seguridad deberá atender las situaciones de riesgo con protección de la integridad y los derechos de las personas, perspectiva de género, confidencialidad y trato digno.",
    ],
    "PRDI25-29-OBJ-108": [
        "Las personas responsables del patrimonio y la memoria de la Facultad deberán preservarlos y difundirlos con rigor, acceso público y participación de la comunidad.",
    ],
    "PRDI25-29-OBJ-117": [
        "Las personas responsables de la gestión financiera deberán destinar los ingresos extraordinarios a prioridades académicas y administrativas compatibles con las funciones sustantivas de la Facultad.",
        "Las personas responsables de obtener y aplicar recursos extraordinarios deberán observar criterios de legalidad, transparencia, sostenibilidad y ausencia de conflictos de interés.",
    ],
    "PRDI2025-2029-OBJ-74": [
        "La comunidad de Ingeniería deberá participar en condiciones de igualdad, respeto a la diversidad y ausencia de discriminación en la vida académica y laboral.",
    ],
    "PRDI25-29-OBJ-122": [
        "El personal académico y administrativo deberá conducir sus prácticas sin exclusión por género, origen, discapacidad, condición social o identidad.",
    ],
    "PRDI25-29-OBJ-124": [
        "Las personas responsables de los apoyos universitarios deberán brindar atención prioritaria y confidencial a quienes se encuentren en situación de vulnerabilidad, con ajustes razonables que favorezcan su permanencia y desarrollo.",
    ],
    "PRDI25-29-OBJ-125": [
        "Las personas titulares de las unidades académicas y administrativas deberán incorporar la corresponsabilidad de los cuidados, el bienestar y la protección de la integridad personal en la organización del trabajo.",
    ],
    "PRDI25-29-OBJ-126": [
        "Las personas responsables de mediar conflictos deberán privilegiar el diálogo, la imparcialidad y la reparación del daño cuando la naturaleza del caso lo permita.",
    ],
    "PRDI25-29-OBJ-127": [
        "El personal responsable de prevenir y atender conductas violentas deberá ofrecer mecanismos accesibles, confidenciales y no revictimizantes a la comunidad de la Facultad.",
    ],
    "PRDI25-29-OBJ-128": [
        "Las comisiones responsables de la convivencia deberán valorar sus intervenciones por la reducción de recurrencias, la restitución de derechos y el fortalecimiento de relaciones respetuosas.",
    ],
    "PRDI25-29-OBJ-75": [
        "El personal docente deberá incorporar principios de paz, derechos humanos, igualdad y no violencia en la formación y en los espacios académicos y comunitarios.",
    ],
    "PRDI2025-2029-OBJ-76": [
        "El estudiantado y el personal académico que desarrollen proyectos de emprendimiento e innovación deberán integrar viabilidad técnica, responsabilidad social, sostenibilidad y beneficio para el entorno.",
    ],
    "PRDI25-29-OBJ-129": [
        "Las personas investigadoras y prestadoras de servicio universitario deberán orientar sus capacidades a necesidades definidas con las comunidades, con respeto a sus saberes, prioridades y participación en las soluciones.",
    ],
    "PRDI25-29-OBJ-130": [
        "Las personas responsables de la vinculación externa deberán condicionar los acuerdos de colaboración a que ofrezcan experiencias formativas pertinentes, seguras y congruentes con la responsabilidad social universitaria.",
    ],
    "PRDI25-29-OBJ-131": [
        "Las personas responsables de la formación lingüística deberán garantizar que ésta sea accesible, pertinente al perfil académico y favorable a la movilidad, la empleabilidad y la inclusión intercultural.",
    ],
    "PRDI25-29-OBJ-132": [
        "El personal responsable del bienestar universitario deberá brindar a la comunidad orientación preventiva y atención de primer nivel de manera oportuna, confidencial e incluyente.",
    ],
    "PRDI25-29-OBJ-77": [
        "Las personas responsables de la comunicación institucional deberán difundir información oportuna, verificable y accesible mediante canales oficiales claramente identificados.",
        "El personal responsable de los canales institucionales deberá favorecer el diálogo, la participación y el acceso equitativo de públicos con necesidades diversas.",
    ],
    "PRDI2025-2029-OBJ-78": [
        "El personal académico y administrativo deberá prevenir y reducir los impactos ambientales de sus actividades conforme a la naturaleza y magnitud de los consumos y residuos que generan.",
    ],
    "PRDI25-29-OBJ-135": [
        "Las personas responsables de la formación ambiental deberán vincular las oportunidades de aprendizaje y participación con problemas reales del campus y su entorno.",
    ],
    "PRDI25-29-OBJ-136": [
        "La comunidad de Ingeniería deberá utilizar agua, energía, papel y materiales con criterios de eficiencia, prevención de residuos y responsabilidad compartida.",
    ],
}

# Estrategias con la sintaxis exigida por la Guía: verbo en infinitivo + qué se
# hará + cómo se hará. El método es sustantivo y propio de cada ámbito; no se
# limita a reiterar el objetivo ni a declarar responsables y seguimiento.
LOCAL_STRATEGIES = {
    "PRDI2025-2029-OBJ-58": "Revisar la pertinencia curricular mediante el análisis de resultados educativos, la consulta a grupos de interés y la documentación de los criterios transversales del SEAES.",
    "PRDI25-29-OBJ-81": "Articular la formación complementaria del estudiantado mediante tutoría, movilidad, certificaciones, educación continua y recursos digitales priorizados según las brechas de acceso y aprendizaje.",
    "PRDI25-29-OBJ-82": "Focalizar los apoyos de ingreso, permanencia, egreso y titulación mediante el seguimiento longitudinal de cohortes y la atención temprana de riesgos académicos.",
    "PRDI25-29-OBJ-83": "Vincular a las personas egresadas con la vida académica y el entorno laboral mediante mecanismos permanentes de intercambio con programas educativos y organizaciones externas.",
    "PRDI2025-2029-OBJ-59": "Ampliar la incorporación a los estudios avanzados mediante difusión dirigida, procesos de admisión transparentes y apoyos acordes con la capacidad de cada programa.",
    "PRDI25-29-OBJ-84": "Consolidar las trayectorias de posgrado mediante tutoría académica, integración temprana a proyectos de investigación y seguimiento de la graduación.",
    "PRDI2025-2029-OBJ-60": "Impulsar la producción científica abierta y pertinente mediante colaboración entre cuerpos académicos, acompañamiento a la habilitación y evaluación de la contribución social de los proyectos.",
    "PRDI25-29-OBJ-85": "Orientar los recursos de investigación mediante prioridades colegiadas, uso compartido de infraestructura y colaboración con instituciones y sectores externos.",
    "PRDI25-29-OBJ-86": "Conducir la protección y transferencia de resultados mediante evaluación de su novedad, pertinencia, titularidad, viabilidad y beneficio social.",
    "PRDI25-29-OBJ-87": "Difundir el conocimiento científico mediante productos abiertos, accesibles y revisados académicamente, adecuados a las características de cada público.",
    "PRDI25-29-OBJ-63": "Diversificar la oferta cultural mediante la identificación de intereses, barreras de participación y condiciones de acceso de la comunidad.",
    "PRDI25-29-OBJ-88": "Vincular la experiencia cultural con la formación integral mediante actividades que promuevan creatividad, identidad, inclusión y reflexión sobre el entorno.",
    "PRDI25-29-OBJ-64": "Extender la activación física y el autocuidado mediante una oferta segura, accesible y compatible con las jornadas de estudio y trabajo.",
    "PRDI25-29-OBJ-90": "Fortalecer la participación deportiva mediante opciones recreativas y competitivas, acompañamiento de talentos y aprovechamiento seguro de la infraestructura.",
    "PRDI2025-2029-OBJ-65": "Atender las necesidades de formación del profesorado mediante rutas de actualización disciplinar, didáctica, digital y socioemocional vinculadas con la práctica educativa.",
    "PRDI25-29-OBJ-92": "Fortalecer las capacidades lingüísticas del profesorado mediante formación pertinente y acompañamiento para la certificación en una segunda lengua.",
    "PRDI25-29-OBJ-93": "Acompañar la habilitación académica del profesorado mediante apoyos para estudios avanzados, reconocimiento de perfiles y participación en redes de investigación.",
    "PRDI2025-2029-OBJ-66": "Desarrollar las competencias del personal administrativo mediante formación vinculada con perfiles de puesto, necesidades de servicio y oportunidades de crecimiento profesional.",
    "PRDI25-29-OBJ-99": "Simplificar la gestión administrativa mediante la revisión integral de atribuciones, requisitos, tiempos de respuesta y experiencia de la comunidad usuaria.",
    "PRDI25-29-OBJ-101": "Fortalecer la cultura digital mediante formación práctica en seguridad, accesibilidad, protección de datos y uso académico de las tecnologías institucionales.",
    "PRDI25-29-OBJ-103": "Asegurar la continuidad de los servicios tecnológicos mediante inventarios funcionales, mantenimiento preventivo y renovación priorizada por criticidad y obsolescencia.",
    "PRDI25-29-OBJ-104": "Reducir la exposición a emergencias mediante diagnósticos de riesgo, preparación de brigadas, señalización accesible y protocolos de actuación conocidos por la comunidad.",
    "PRDI25-29-OBJ-105": "Mejorar la atención de seguridad mediante formación del personal, canales accesibles y aplicación de protocolos con enfoque de derechos humanos y género.",
    "PRDI25-29-OBJ-108": "Fortalecer la identidad universitaria mediante recuperación documental, divulgación histórica y participación de la comunidad en la preservación de su patrimonio.",
    "PRDI25-29-OBJ-117": "Diversificar los ingresos extraordinarios mediante servicios y alianzas compatibles con las capacidades académicas, la transparencia y la ausencia de conflictos de interés.",
    "PRDI2025-2029-OBJ-74": "Transversalizar la igualdad y el reconocimiento de la diversidad mediante criterios de inclusión aplicables a la docencia, la gestión y la participación universitaria.",
    "PRDI25-29-OBJ-122": "Prevenir la discriminación mediante formación, revisión de prácticas institucionales y mecanismos accesibles de orientación y atención.",
    "PRDI25-29-OBJ-124": "Focalizar apoyos para personas en situación de vulnerabilidad mediante la identificación confidencial de barreras y la adopción de ajustes razonables.",
    "PRDI25-29-OBJ-125": "Incorporar la perspectiva de cuidados mediante medidas de corresponsabilidad, autocuidado y organización sensible a las necesidades de la comunidad.",
    "PRDI25-29-OBJ-126": "Desarrollar capacidades para la gestión pacífica de conflictos mediante formación en diálogo, mediación, escucha y reparación del daño.",
    "PRDI25-29-OBJ-127": "Prevenir y atender conductas violentas mediante canales confidenciales, protocolos no revictimizantes y coordinación con las instancias competentes.",
    "PRDI25-29-OBJ-128": "Evaluar las intervenciones de convivencia mediante evidencia sobre recurrencia, restitución de derechos y percepción de seguridad y respeto.",
    "PRDI25-29-OBJ-75": "Integrar la educación para la paz mediante contenidos y experiencias formativas sobre derechos humanos, igualdad, diálogo y no violencia.",
    "PRDI2025-2029-OBJ-76": "Impulsar proyectos emprendedores mediante formación, mentoría y validación de soluciones con criterios de viabilidad, sostenibilidad y responsabilidad social.",
    "PRDI25-29-OBJ-129": "Desarrollar proyectos sociales interdisciplinarios mediante diagnóstico participativo, diálogo de saberes y colaboración sostenida con las comunidades.",
    "PRDI25-29-OBJ-130": "Ampliar la colaboración externa mediante convenios y experiencias formativas que relacionen capacidades de Ingeniería con necesidades de organizaciones y sectores.",
    "PRDI25-29-OBJ-131": "Fortalecer la formación lingüística mediante opciones accesibles vinculadas con movilidad, empleabilidad y comunicación intercultural.",
    "PRDI25-29-OBJ-132": "Promover el bienestar integral mediante orientación preventiva, detección oportuna y canalización confidencial a servicios especializados.",
    "PRDI25-29-OBJ-77": "Coordinar la comunicación institucional mediante criterios editoriales comunes, canales accesibles y mecanismos de escucha y retroalimentación.",
    "PRDI2025-2029-OBJ-78": "Reducir los impactos ambientales mediante prevención, separación y manejo responsable de residuos, rehabilitación de áreas verdes y sustitución de consumos evitables.",
    "PRDI25-29-OBJ-135": "Formar agentes de cambio ambiental mediante aprendizaje aplicado, participación comunitaria y proyectos vinculados con el campus y su entorno.",
    "PRDI25-29-OBJ-136": "Mejorar la eficiencia en el uso de recursos mediante medición de consumos, mantenimiento preventivo y adopción de prácticas de ahorro y reutilización.",
}

STOPWORDS = {
    "a", "al", "con", "de", "del", "durante", "el", "en", "entre", "la", "las", "los",
    "para", "por", "que", "se", "su", "una", "un", "y", "o", "cada", "año", "años",
    "facultad", "ingenieria", "realizar", "registrar", "actualizar", "documentar",
}


def normalized(value: str) -> str:
    text = unicodedata.normalize("NFKD", value.lower())
    return "".join(char for char in text if not unicodedata.combining(char)).replace("\xa0", " ").strip()


def key(value: Any) -> str:
    return normalized(str(value or "")).upper().replace(" ", "")


def tokens(value: str) -> set[str]:
    return {token for token in re.findall(r"[a-záéíóúñü]{4,}", normalized(value)) if token not in STOPWORDS}


def number(value: Any, default: float = 0.0) -> float:
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def load_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def records(plan_dir: Path, filename: str) -> list[dict[str, Any]]:
    data = load_json(plan_dir / filename)
    return [row for indicator in data.get("facultad", {}).get("indicadores", []) for row in indicator.get("registros", [])]


def latest_single(plan_dir: Path, filename: str, metric: str) -> float:
    candidates = [row[metric] for row in records(plan_dir, filename) if metric in row and row[metric] is not None]
    if not candidates:
        raise ValueError(f"No se encontró {metric} en {filename}.")
    return number(candidates[-1])


def latest_sum(plan_dir: Path, filename: str, metric: str, period_field: str) -> float:
    rows = [row for row in records(plan_dir, filename) if metric in row and row[metric] is not None and period_field in row]
    if not rows:
        raise ValueError(f"No se encontró {metric} en {filename}.")
    latest = max(str(row[period_field]) for row in rows)
    return sum(number(row[metric]) for row in rows if str(row[period_field]) == latest)


def historical_context(plan_dir: Path) -> dict[str, float]:
    enrollment = latest_single(plan_dir, "4005_matricula_total.json", "Matricula Ciclo Actual1")
    entrants = latest_single(plan_dir, "4003_nuevo_ingreso_uaemex.json", "Ingreso Ciclo Actual")
    graduates = latest_single(plan_dir, "4007_egresados.json", "Egresados Ciclo Actual1")
    tutored = latest_single(plan_dir, "4023_tutores.json", "Alumnos que reciben Tutoría")
    ptc = latest_sum(plan_dir, "4027_ptc_registrados_en_sep.json", "Total_PTC_SEP1", "Año")
    academic = latest_single(plan_dir, "4031_personal_academico.json", "Total")
    administrative = latest_single(plan_dir, "4033_personal_administrativo.json", "Total")
    service_social = latest_single(plan_dir, "4018_servicio_social.json", "Total SS Año Actual")
    return {
        "matricula": enrollment,
        "nuevo_ingreso": entrants,
        "egresados": graduates,
        "tutoria": tutored,
        "ptc": ptc,
        "academicos": academic,
        "administrativos": administrative,
        "personal": academic + administrative,
        "comunidad": enrollment + academic + administrative,
        "servicio_social": service_social,
        "programas": 5.0,
        "cuerpos_academicos": 3.0,
    }


def latest_space_percentage(plan_dir: Path, filename: str) -> float:
    values = [row.get("Espacio académico") for row in records(plan_dir, filename) if row.get("Espacio académico") is not None]
    if not values:
        raise ValueError(f"No hay serie de espacio académico en {filename}.")
    return number(values[-1])


def direct_indicator_baselines(plan_dir: Path, context: dict[str, float]) -> dict[str, float]:
    return {
        "IND-PRDI-353": context["tutoria"] / context["matricula"] * 100,
        "IND-PRDI-40": latest_space_percentage(plan_dir, "4017_estudios_profesionales_indice_de_titulacion.json"),
        "IND-NS-127": latest_space_percentage(plan_dir, "4013_estudios_profesionales_eficiencia_terminal_cohorte.json"),
        "IND-PRDI-34": latest_space_percentage(plan_dir, "4015_estudios_profesionales_abandono_escolar.json"),
        "IND-PRDI-221": 100.0,
    }


def percent_target(text: str) -> float | None:
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*%", text)
    return float(match.group(1).replace(",", ".")) if match else None


def absolute_target(text: str) -> float:
    scrubbed = re.sub(r"\b20(?:2[6-9]|30)\b", "", text)
    scrubbed = re.sub(r"(\d+(?:[.,]\d+)?)\s*%", "", scrubbed)
    matches = re.findall(r"(?<![A-Za-z])\d+(?:[ .]\d{3})*(?:[.,]\d+)?", scrubbed)
    if not matches:
        return 1.0
    value = matches[0].replace(" ", "").replace(".", "") if " " in matches[0] else matches[0]
    return max(1.0, float(value.replace(",", ".")))


def local_quota(text: str) -> int:
    if "%" in text or "espacios académicos" in normalized(text):
        return 1
    return max(1, math.ceil(absolute_target(text) / INSTITUTIONAL_SPACES))


def activity_schedule(item: dict[str, Any], context: dict[str, float] | None = None) -> dict[int, float]:
    base = item.get("valor_ref_2026")
    original = {int(year): number(value) for year, value in item.get("proyeccion_libro", {}).items() if str(year).isdigit()}
    if base is not None and number(base) > 0:
        floor = number(base)
        values = []
        previous = floor
        for year in YEARS:
            proposed = max(floor, original.get(year, floor), previous)
            values.append(proposed)
            previous = proposed
        return dict(zip(YEARS, values))

    if key(item.get("clave")) == "ACT-PRDI-NS-1764" and context:
        baseline = context["matricula"]
        return {year: round(baseline * (1.005 ** (year - 2026))) for year in YEARS}

    text = normalized(str(item.get("meta_prdi", "")))
    quota = local_quota(text)
    if "semestral" in text:
        quota *= 2
    if any(term in text for term in ("anualmente", "cada ano", "al ano", "anual")):
        values = [quota] * 4
    elif "durante la administracion" in text:
        values = [0] * 4
        for index in range(quota):
            values[index % 4] += 1
    elif re.search(r"(?:a|para) 2029", text):
        values = [max(0, math.ceil(quota * 0.5)), max(0, math.ceil(quota * 0.75)), quota, quota]
    elif re.search(r"(?:a|para) 2030", text):
        values = [max(0, math.ceil(quota * 0.25)), max(0, math.ceil(quota * 0.5)), max(0, math.ceil(quota * 0.75)), quota]
    else:
        values = [0, 0, quota, 0]
    return dict(zip(YEARS, values))


def indicator_percentages(item: dict[str, Any], baselines: dict[str, float]) -> tuple[dict[int, float], str]:
    item_key = key(item.get("clave"))
    target = percent_target(str(item.get("meta_prdi", "")))
    if target is None:
        target = 5.0 if item_key == "IND-PRDI-410" else 100.0
    baseline = baselines.get(item_key)
    decreasing = item_key == "IND-PRDI-34" or any(word in normalized(str(item.get("meta_prdi", ""))) for word in ("disminuir", "reducir"))

    if baseline is not None:
        if decreasing:
            end = min(baseline, target)
            step = (baseline - end) / 4
            values = [baseline - step * index for index in range(1, 5)]
        else:
            end = max(baseline, min(target, baseline + 4.0))
            step = (end - baseline) / 4
            values = [baseline + step * index for index in range(1, 5)]
        source = "serie histórica directamente comparable del sitio"
    else:
        start = max(0.0, target - min(4.0, max(1.0, target * 0.05)))
        step = (target - start) / 3
        values = [start, start + step, start + step * 2, target]
        source = "referencia PRDI; no existe serie histórica directamente comparable para la variable"
    return ({year: round(value, 2) for year, value in zip(YEARS, values)}, source)


def denominator_for(name: str, context: dict[str, float]) -> float:
    text = normalized(name)
    if "comunidad universitaria" in text:
        return context["comunidad"]
    if "personal universitario" in text:
        return context["personal"]
    if "personal administrativo" in text or "administrativos" in text:
        return context["administrativos"]
    if "profesores de tiempo completo" in text or re.search(r"\bptc\b", text):
        return context["ptc"]
    if "cuerpos academicos" in text:
        return context["cuerpos_academicos"]
    if "graduados" in text:
        return max(10.0, context["egresados"] * 0.1)
    if "personal academico" in text or "academicos" in text:
        return context["academicos"]
    if any(term in text for term in ("matricula", "estudiantes", "estudiantado", "alumnos")):
        return context["matricula"]
    if "programas educativos" in text:
        return context["programas"]
    if "proyectos de investigacion" in text:
        return 20.0
    if "produccion cientifica" in text:
        return 40.0
    if "guias" in text:
        return 10.0
    if "sustentantes" in text:
        return 100.0
    return 100.0


def indicator_variable_program(item: dict[str, Any], percentages: dict[int, float], context: dict[str, float]) -> list[dict[str, Any]]:
    item_key = key(item.get("clave"))
    variables = deepcopy(item.get("variables", []))
    if not variables:
        return variables

    if item_key == "IND-PRDI-34" and len(variables) >= 4:
        for year in YEARS:
            offset = year - 2027
            current_enrollment = round(context["matricula"] * (1.005 ** offset))
            next_enrollment = round(context["matricula"] * (1.005 ** (offset + 1)))
            entrants = round(context["nuevo_ingreso"] * (1.005 ** offset))
            graduates = round((1 - percentages[year] / 100) * current_enrollment - next_enrollment + entrants)
            values = [next_enrollment, entrants, graduates, current_enrollment]
            for variable, value in zip(variables, values):
                variable.setdefault("programacion", {})[str(year)] = max(0, value)
        return variables

    if item_key == "IND-PRDI-410" and len(variables) >= 2:
        previous = context["servicio_social"]
        for year in YEARS:
            current = round(previous * (1 + percentages[year] / 100))
            variables[0].setdefault("programacion", {})[str(year)] = current
            variables[1].setdefault("programacion", {})[str(year)] = round(previous)
            previous = current
        return variables

    denominator = denominator_for(str(variables[-1].get("nombre", "")), context)
    for year in YEARS:
        projected_denominator = max(1, round(denominator * (1.005 ** (year - 2027))))
        projected_numerator = round(projected_denominator * percentages[year] / 100)
        variables[0].setdefault("programacion", {})[str(year)] = projected_numerator
        if len(variables) > 1:
            variables[1].setdefault("programacion", {})[str(year)] = projected_denominator
        for variable in variables[2:]:
            variable.setdefault("programacion", {})[str(year)] = projected_denominator
    return variables


def calculated_indicator(item: dict[str, Any], variables: list[dict[str, Any]]) -> dict[int, float]:
    item_key = key(item.get("clave"))
    result = {}
    for year in YEARS:
        values = [number(variable.get("programacion", {}).get(str(year))) for variable in variables]
        if item_key == "IND-PRDI-34" and len(values) >= 4:
            value = (1 - ((values[0] - values[1] + values[2]) / values[3])) * 100 if values[3] else 0
        elif item_key == "IND-PRDI-410" and len(values) >= 2:
            value = ((values[0] / values[1]) - 1) * 100 if values[1] else 0
        elif len(values) >= 2:
            value = values[0] / values[1] * 100 if values[1] else 0
        else:
            value = values[0] if values else 0
        result[year] = round(value, 2)
    return result


def memory_structure(plan_dir: Path) -> dict[str, dict[str, str]]:
    """Recupera la estructura PRDI de las columnas B-E sin copiarla como meta local."""
    path = plan_dir / "Memoria de cálculo NS 2026-2030Ultima.xlsx"
    if not path.is_file():
        return {}
    workbook = load_workbook(path, read_only=True, data_only=False)
    result: dict[str, dict[str, str]] = {}
    for sheet in workbook.worksheets:
        if not sheet.title.split(" ", 1)[0].isdigit():
            continue
        for row in range(3, sheet.max_row + 1):
            item_key = key(sheet.cell(row, 8).value)
            if item_key:
                result[item_key] = {
                    "clave_objetivo_general_prdi": str(sheet.cell(row, 2).value or "").strip(),
                    "objetivo_general_prdi": str(sheet.cell(row, 3).value or "").strip(),
                    "clave_objetivo_especifico_prdi": str(sheet.cell(row, 4).value or "").strip(),
                    "objetivo_especifico_prdi": str(sheet.cell(row, 5).value or "").strip(),
                }
    workbook.close()
    return result


def display_number(value: float) -> str:
    if abs(value - round(value)) < 1e-9:
        return f"{int(round(value)):,}"
    return f"{value:,.2f}".rstrip("0").rstrip(".")


def replace_first_non_year_number(text: str, replacement: float) -> str:
    pattern = re.compile(r"(?<![A-Za-z])\d+(?:[ .]\d{3})*(?:[.,]\d+)?")
    for match in pattern.finditer(text):
        raw = match.group(0)
        compact = raw.replace(" ", "").replace(".", "").replace(",", ".")
        try:
            value = float(compact)
        except ValueError:
            continue
        if 2026 <= value <= 2030 or text[match.end():].lstrip().startswith("%"):
            continue
        return text[:match.start()] + display_number(replacement) + text[match.end():]
    return text


def faculty_prefix(text: str) -> str:
    text = re.sub(r"\s+", " ", text.strip()).rstrip(".")
    if not text:
        return text
    if "facultad de ingeniería" not in normalized(text):
        text = "En la Facultad de Ingeniería, " + text[0].lower() + text[1:]
    return text[0].upper() + text[1:]


def faculty_scope(text: str) -> str:
    """Añade el ámbito sin desplazar el verbo en infinitivo al inicio de la meta."""
    text = re.sub(r"\s+", " ", text.strip()).rstrip(".")
    if not text:
        return text
    if "facultad de ingeniería" not in normalized(text):
        text += " en la Facultad de Ingeniería"
    return text[0].upper() + text[1:]


def improve_activity_grammar(text: str) -> str:
    one_forms = {
        "planes": "un plan", "programas": "un programa", "proyectos": "un proyecto",
        "convocatorias": "una convocatoria", "intervenciones": "una intervención",
        "eventos": "un evento", "pláticas": "una plática", "talleres": "un taller",
        "colaboraciones": "una colaboración", "campañas": "una campaña",
        "evaluaciones": "una evaluación", "actividades": "una actividad",
        "presentaciones": "una presentación", "festivales": "un festival",
        "manuales": "un manual", "guías": "una guía", "protocolos": "un protocolo",
        "expedientes": "un expediente", "registros": "un registro", "patentes": "una patente",
        "cursos": "un curso", "jornadas": "una jornada", "contenidos": "un contenido",
        "grupos": "un grupo", "servicios": "un servicio", "cuerpos": "un cuerpo",
        "espacios": "un espacio",
        "participantes": "una persona participante", "materiales": "un material",
    }
    for plural, singular in one_forms.items():
        text = re.sub(rf"\b1\s+{plural}\b", singular, text, flags=re.I)
    for singular, article in {"programa": "un", "proyecto": "un", "grupo": "un", "plan": "un",
                              "campaña": "una", "actividad": "una", "evaluación": "una"}.items():
        text = re.sub(rf"\b1\s+{singular}\b", f"{article} {singular}", text, flags=re.I)
    plural_forms = {value.split(" ", 1)[1]: plural for plural, value in one_forms.items()}
    for singular, plural in plural_forms.items():
        text = re.sub(rf"\b([2-9]|\d{{2,}})\s+{singular}\b", rf"\1 {plural}", text, flags=re.I)
    text = re.sub(r"\bseaes\b", "SEAES", text, flags=re.I)
    phrase = "cada año de 2027 a 2030"
    if phrase in text.lower():
        prefix, suffix = text.lower().split(phrase, 1)
        if "cada año" in prefix or "al año" in prefix:
            text = re.sub(r"\b(?:cada año|al año)\b", "", text, count=1, flags=re.I)
    if text.lower().count(phrase) > 1:
        first = text.lower().find(phrase)
        before = text[:first + len(phrase)]
        after = re.sub(re.escape(phrase), "", text[first + len(phrase):], flags=re.I)
        text = before + after
    replacements = {
        "un plan de estudio reestructurados": "un plan de estudios reestructurado",
        "unidades de aprendizaje de estudios profesionales apegados": "unidades de aprendizaje de estudios profesionales apegadas",
        "una intervención de impacto anuales": "una intervención de impacto anual",
        "una convocatoria externas": "una convocatoria externa",
        "una plática o talleres": "una plática o un taller",
        "un espacio académicos": "un espacio académico",
        "un evento socioculturales": "un evento sociocultural",
        "un taller articulados que fortalezcan": "un taller articulado que fortalezca",
        "una campaña anuales que promuevan": "una campaña anual que promueva",
        "una campaña anuales": "una campaña anual",
        "un contenido anuales": "un contenido anual",
    }
    for source, target in replacements.items():
        text = re.sub(re.escape(source), target, text, flags=re.I)
    text = re.sub(r"\ben 2026\s+entre 2027 y 2030\b", "entre 2027 y 2030", text, flags=re.I)
    text = re.sub(r"\bAperturar\b", "Abrir", text, flags=re.I)
    text = re.sub(r"\bOfertar\b", "Ofrecer", text, flags=re.I)
    # El texto institucional puede traer el ámbito más de una vez después de
    # sustituir referencias a los 55 espacios. Se conserva una sola mención.
    while normalized(text).count("facultad de ingenieria") > 1:
        text = re.sub(r"\s+en la Facultad de Ingeniería(?=[.,]|$)", "", text, count=1, flags=re.I)
    return re.sub(r"\s{2,}", " ", text).replace(" ,", ",").strip()


def describe_activity(item: dict[str, Any], schedule: dict[int, float]) -> str:
    item_key = key(item.get("clave"))
    text = str(item.get("meta_prdi", "")).strip()
    normalized_text = normalized(text)
    values = list(schedule.values())

    if item_key == "ACT-PRDI-NS-2870":
        return (f"Mantener {display_number(max(values))} cuerpos académicos de calidad en la Facultad de Ingeniería "
                "en cada medición anual de 2027 a 2030.")
    if item_key == "ACT-PRDI-NS-1764":
        return (f"Atender una matrícula de al menos {display_number(values[0])} estudiantes de estudios profesionales "
                f"en 2027 y alcanzar {display_number(values[-1])} en 2030, sin reducir la cobertura anual.")

    # Adaptaciones al ámbito de un solo espacio académico. Evitan copiar metas
    # institucionales que hablan de todos los espacios o de la Universidad.
    local_wording = {
        "ACT-PRDI-NS-2753": "Realizar una intervención anual que vincule a personas egresadas y estudiantes de la Facultad de Ingeniería cada año de 2027 a 2030.",
        "ACT-PRDI-NS-2844": "Ofrecer un programa de estudios avanzados o diplomado superior vinculado con una entidad pública o productiva cada año de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-2848": "Aplicar un protocolo anual para mejorar la graduación y la eficiencia terminal de los estudios avanzados de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-2890": "Aplicar un programa anual de sostenibilidad y fortalecimiento del acceso abierto no comercial a las publicaciones científicas de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3205": "Realizar un festival cultural anual que reúna talentos de los talleres y programas de la Facultad de Ingeniería de 2027 a 2030.",
        "ACT-PRDI-NS-2743": "Armonizar 2 manuales de organización cada año de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-2957": "Armonizar un manual de procedimientos cada año de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3242": "Dar mantenimiento preventivo cada año de 2027 a 2030 al 100% de los equipos registrados en las salas de cómputo de la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3243": "Elaborar una nueva crónica sobre la historia de la Facultad de Ingeniería para 2030.",
        "ACT-PRDI-NS-3244": "Realizar cada año de 2027 a 2030 una actividad cultural sobre igualdad de género y cultura de paz en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-2766": "Realizar una jornada anual por los 16 días de activismo, con actividades presenciales y virtuales, de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3103": "Coordinar un encuentro anual de emprendimiento con los programas educativos de 2027 a 2030 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3116": "Participar cada año de 2027 a 2030 en un servicio comunitario o proyecto de Brigadas Universitarias Multidisciplinarias en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3135": "Abrir cada año de 2027 a 2030 un grupo de lengua originaria accesible a la comunidad de la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3245": "Mantener disponible un servicio de atención psicológica para la comunidad de la Facultad de Ingeniería de 2027 a 2030.",
        "ACT-PRDI-NS-3155": "Operar cada año de 2027 a 2030 una campaña de acopio de aceite vegetal usado en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3247": "Operar el Plan Institucional de Manejo de Residuos Sólidos Urbanos en la Facultad de Ingeniería a más tardar en 2029 y mantenerlo en 2030.",
        "ACT-PRDI-NS-3163": "Implementar un proceso de compostaje de residuos orgánicos de poda y jardinería a más tardar en 2029 en la Facultad de Ingeniería.",
        "ACT-PRDI-NS-3248": "Implementar un programa de cuidado y ahorro de agua a más tardar en 2029 y mantenerlo en 2030 en la Facultad de Ingeniería.",
    }
    if item_key in local_wording:
        return local_wording[item_key]

    text = re.sub(r"en los 55 espacios académicos", "en la Facultad de Ingeniería", text, flags=re.I)
    if any(term in normalized_text for term in ("anualmente", "cada ano", "al ano", "anual")) and len(set(values)) == 1:
        target = values[0]
        text = replace_first_non_year_number(text, target)
        text = re.sub(r"\banualmente\b", "cada año de 2027 a 2030", text, flags=re.I)
        text = re.sub(r"\banual\b", "cada año de 2027 a 2030", text, count=1, flags=re.I)
        if "2027" not in text:
            text += " cada año de 2027 a 2030"
    elif "administracion" in normalized_text:
        text = replace_first_non_year_number(text, sum(values))
        text = re.sub(r"(?:durante|al cierre de|al final de) la administración", "de manera acumulada entre 2027 y 2030", text, flags=re.I)
        if "%" in text:
            text = text.replace("de manera acumulada entre 2027 y 2030", "para 2030")
    else:
        target = max(values)
        text = replace_first_non_year_number(text, target)
        deadline = re.search(r"(?:a|para)\s+(202[7-9]|2030)", normalized_text)
        if deadline:
            year = deadline.group(1)
            text = re.sub(rf"\b(?:a|para)\s+{year}\b", f"a más tardar en {year}", text, flags=re.I)
        elif "2027" not in text and "2028" not in text and "2029" not in text and "2030" not in text:
            text += " entre 2027 y 2030"
    return improve_activity_grammar(faculty_scope(text)).rstrip(".") + "."


def describe_indicator(item: dict[str, Any], percentages: dict[int, float], source: str) -> str:
    text = str(item.get("meta_prdi", "")).strip()
    item_key = key(item.get("clave"))
    final = percentages[2030]
    baseline_match = source.startswith("serie histórica")
    baseline = item.get("linea_base_historica")
    direct_labels = {
        "IND-PRDI-353": "la cobertura de tutoría académica del estudiantado",
        "IND-PRDI-40": "la titulación por cohorte",
        "IND-NS-127": "la eficiencia terminal por cohorte",
        "IND-PRDI-34": "el abandono escolar",
        "IND-PRDI-221": "la proporción de cuerpos académicos que realizan investigación con contribución social",
    }
    if baseline is not None and item_key in direct_labels:
        verb = "Reducir" if item_key == "IND-PRDI-34" else ("Mantener" if abs(final - baseline) < 0.01 else "Elevar")
        if verb == "Mantener":
            return (f"Mantener en {display_number(final)}% {direct_labels[item_key]} en la Facultad de Ingeniería "
                    "durante las mediciones anuales de 2027 a 2030.")
        return (f"{verb} {direct_labels[item_key]} en la Facultad de Ingeniería de "
                f"{display_number(baseline)}% a {display_number(final)}% para 2030, con revisión anual.")
    target_match = re.search(r"\d+(?:[.,]\d+)?\s*%", text)
    if target_match:
        text = text[:target_match.start()] + f"{display_number(final)}%" + text[target_match.end():]
    text = re.sub(r"\b(?:a|para)\s+202[7-9]\b", "para 2030", text, flags=re.I)
    text = re.sub(r"\banualmente\b", "cada año de 2027 a 2030", text, flags=re.I)
    if "2030" not in text:
        text += " para 2030"
    text = faculty_scope(text)
    if baseline_match:
        if baseline is not None:
            direction = "reducir gradualmente" if item_key == "IND-PRDI-34" else "mantener o mejorar"
            text += f", a partir de la línea base histórica de {display_number(baseline)}% y con el criterio de {direction} el resultado"
    return text.rstrip(".") + "."


def build_matrix_structure(project: dict[str, Any], metas: list[dict[str, Any]]) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    groups: dict[str, list[dict[str, Any]]] = {}
    labels: dict[str, str] = {}
    for item in metas:
        objective_key = key(item.get("clave_objetivo_especifico_prdi")) or f"LOCAL-{project['numero']}"
        groups.setdefault(objective_key, []).append(item)
        labels[objective_key] = str(item.get("objetivo_especifico_prdi", "")).strip()

    objectives = []
    policies = []
    policy_number = 0
    for index, (objective_key, items) in enumerate(groups.items(), 1):
        objective_id = f"OE{index}"
        objective_text = LOCAL_OBJECTIVES.get(objective_key)
        if not objective_text:
            source = re.sub(r"^PRDI[^-]*-?\d*\s*-?", "", labels.get(objective_key, ""), flags=re.I).strip(" -")
            objective_text = faculty_prefix(source or f"Atender las prioridades del proyecto {project['numero']}") + "."
        strategy_id = f"E{index}.1"
        strategy_base = LOCAL_STRATEGIES.get(objective_key)
        if not strategy_base:
            raise ValueError(
                f"Falta una estrategia sustantiva para {objective_key} en el proyecto {project['numero']}."
            )
        strategy_text = strategy_base.rstrip(".") + " [C, I]"
        goals = []
        for goal_index, item in enumerate(items, 1):
            description = str(item["meta_espacio_academico"]).strip()
            goals.append({
                "id": f"M{index}.{goal_index}", "descripcion": description,
                "tipo": normalized(str(item.get("tipo", ""))), "indicador": item.get("clave"),
                "periodo_base": "último dato histórico comparable o Ref. 2026",
                "metodo": str(item.get("criterio_programacion", "")),
                "programacion_variables": [
                    {
                        "nombre": str(variable.get("nombre", "")).strip(),
                        "programacion": {
                            str(year): variable.get("programacion", {}).get(str(year))
                            for year in YEARS
                        },
                    }
                    for variable in item.get("variables", [])
                ],
            })
            item["objetivo_matriz"] = objective_id
            item["estrategia_matriz"] = strategy_id
            item["meta_matriz"] = f"M{index}.{goal_index}"
        objectives.append({
            "id": objective_id, "texto": objective_text,
            "estrategias": [{"id": strategy_id, "texto": strategy_text, "criterios": ["C", "I"], "metas": goals}],
        })
        policy_texts = LOCAL_POLICIES.get(objective_key)
        if not policy_texts:
            raise ValueError(
                f"Falta una política estratégica para {objective_key} en el proyecto {project['numero']}."
            )
        for policy_text in policy_texts:
            policy_number += 1
            policies.append({
                "id": f"P{policy_number}",
                "texto": policy_text,
                "objetivos": [objective_id],
            })
    if len(policies) < 2:
        raise ValueError(f"El proyecto {project['numero']} debe contener por lo menos dos políticas sustantivas.")
    return objectives, policies


def site_goals(project: dict[str, Any]) -> list[dict[str, Any]]:
    result = []
    for objective in project.get("objetivos_especificos", []):
        for strategy in objective.get("estrategias", []):
            for meta in strategy.get("metas", []):
                result.append({**deepcopy(meta), "objetivo_id": objective.get("id"), "estrategia_id": strategy.get("id")})
    return result


def editable_goal_map(project: dict[str, Any]) -> dict[str, dict[str, Any]]:
    result = {}
    for objective in project.get("objetivos_especificos", []):
        for strategy in objective.get("estrategias", []):
            for meta in strategy.get("metas", []):
                item_key = key(meta.get("indicador"))
                if item_key:
                    result[item_key] = {
                        "descripcion": str(meta.get("descripcion", "")).strip(),
                        "objetivo": str(objective.get("id", "")).strip(),
                        "estrategia": str(strategy.get("id", "")).strip(),
                        "meta": str(meta.get("id", "")).strip(),
                        "programacion_variables": deepcopy(meta.get("programacion_variables", [])),
                    }
    return result


def best_alignment(goal: dict[str, Any], metas: list[dict[str, Any]], used: set[int]) -> int | None:
    goal_key = key(goal.get("indicador"))
    if goal_key:
        for index, item in enumerate(metas):
            if index not in used and key(item.get("clave")) == goal_key:
                return index
    goal_tokens = tokens(str(goal.get("descripcion", "")))
    scored = []
    for index, item in enumerate(metas):
        if index in used:
            continue
        candidate = tokens(str(item.get("meta_prdi", "")) + " " + " ".join(str(v.get("nombre", "")) for v in item.get("variables", [])))
        score = len(goal_tokens & candidate) / max(1, len(goal_tokens | candidate))
        scored.append((score, index))
    if not scored:
        return None
    return max(scored)[1]


def complete_projects(projects: list[dict[str, Any]], plan_dir: Path,
                      preserve_edits: bool = True) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    context = historical_context(plan_dir)
    baselines = direct_indicator_baselines(plan_dir, context)
    structure = memory_structure(plan_dir)
    completed = deepcopy(projects)
    report = {"filas_prdi": 0, "metas_sitio_alineadas": 0, "metas_adicionales": [], "contexto_historico": context}

    for project in completed:
        project_number = int(project["numero"])
        memory = project.get("referencia_base_2026") or {}
        metas = memory.get("metas") or []
        used: set[int] = set()
        alignments: list[dict[str, Any]] = []
        goals = site_goals(project)
        editable = editable_goal_map(project)
        memory_keys = {key(item.get("clave")) for item in metas}
        # Una revisión antigua puede contener sólo una parte de la matriz o una
        # meta que todavía no tenía clave PRDI. Se conserva la estructura que
        # ve el usuario y se completan con IA únicamente los renglones que no
        # tengan una edición identificable por clave.
        preserve_edited_matrix = preserve_edits and bool(goals)

        for item in metas:
            item.update(structure.get(key(item.get("clave")), {}))
            item["area_responsable"] = PROJECT_AREAS[project_number]
            item["medicion"] = "Anual"
            # Se reconstruye en cada ejecución para evitar duplicados cuando la
            # propuesta local ya fue sincronizada por una corrida anterior.
            item["alineaciones_sitio"] = []
            if normalized(str(item.get("tipo", ""))).startswith("actividad"):
                schedule = activity_schedule(item, context)
                item["variables"] = deepcopy(item.get("variables", []))
                if item["variables"]:
                    item["variables"][0]["programacion"] = {str(year): value for year, value in schedule.items()}
                item["proyeccion_libro"] = {str(year): value for year, value in schedule.items()}
                item["proyeccion_indicador"] = {str(year): value for year, value in schedule.items()}
                item["meta_espacio_academico"] = describe_activity(item, schedule)
                item["criterio_programacion"] = (
                    "Cuota local mínima: valor institucional absoluto distribuido entre 55 espacios académicos; "
                    "se conserva o mejora cualquier línea base positiva ya verificada."
                )
            else:
                percentages, source = indicator_percentages(item, baselines)
                item["variables"] = indicator_variable_program(item, percentages, context)
                calculated = calculated_indicator(item, item["variables"])
                item["proyeccion_indicador"] = {str(year): value for year, value in calculated.items()}
                item["linea_base_historica"] = baselines.get(key(item.get("clave")))
                item["meta_espacio_academico"] = describe_indicator(item, calculated, source)
                item["criterio_programacion"] = (
                    "Trayectoria conservadora: parte del último dato histórico comparable cuando existe; "
                    "si no existe, usa una aproximación gradual al referente PRDI y la identifica como supuesto."
                )
            report["filas_prdi"] += 1

        if preserve_edited_matrix:
            for item in metas:
                edit = editable.get(key(item.get("clave")))
                if edit is None:
                    continue
                item["meta_espacio_academico"] = edit["descripcion"]
                item["objetivo_matriz"] = edit["objetivo"]
                item["estrategia_matriz"] = edit["estrategia"]
                item["meta_matriz"] = edit["meta"]
                submitted = edit.get("programacion_variables")
                if isinstance(submitted, list):
                    submitted_by_name = {
                        key(variable.get("nombre")): variable
                        for variable in submitted if isinstance(variable, dict) and variable.get("nombre")
                    }
                    for index, variable in enumerate(item.get("variables", [])):
                        candidate = submitted_by_name.get(key(variable.get("nombre")))
                        if candidate is None and index < len(submitted) and isinstance(submitted[index], dict):
                            candidate = submitted[index]
                        if not candidate:
                            continue
                        program = candidate.get("programacion", {})
                        if not isinstance(program, dict):
                            continue
                        for year in YEARS:
                            value = program.get(str(year))
                            if isinstance(value, (int, float)) and math.isfinite(float(value)) and float(value) >= 0:
                                variable.setdefault("programacion", {})[str(year)] = value
                    item["proyeccion_libro"] = {
                        str(year): item.get("variables", [{}])[0].get("programacion", {}).get(str(year))
                        for year in YEARS
                    } if item.get("variables") else item.get("proyeccion_libro", {})
                    item["proyeccion_indicador"] = {
                        str(year): value for year, value in calculated_indicator(item, item.get("variables", [])).items()
                    }

        # Las metas editadas que no tienen renglón PRDI se conservan como
        # adicionales; esto incluye revisiones creadas con el formato anterior.
        if preserve_edited_matrix:
            report["metas_adicionales"].extend(
                {"proyecto": project_number, **goal}
                for goal in goals
                if key(goal.get("indicador")) not in memory_keys
            )

        # Los proyectos sin renglones en la memoria institucional generan
        # adicionales para todas sus metas.
        if not metas:
            report["metas_adicionales"].extend(
                {"proyecto": project_number, **goal} for goal in goals
                if not any(x.get("proyecto") == project_number and x.get("descripcion") == goal.get("descripcion")
                           for x in report["metas_adicionales"])
            )
        else:
            if not preserve_edited_matrix:
                objectives, policies = build_matrix_structure(project, metas)
                project["objetivos_especificos"] = objectives
                project["politicas"] = policies
            alignments = [{"clave_prdi": item.get("clave"), "objetivo": item.get("objetivo_matriz"),
                           "estrategia": item.get("estrategia_matriz"), "meta": item.get("meta_matriz")}
                          for item in metas]
            report["metas_sitio_alineadas"] += len(metas)

        memory["metas"] = metas
        memory["programacion_metodologica"] = {
            "periodo": "2027-2030",
            "criterio": ("Alineación por clave y proyecto PRDI; metas con verbo en infinitivo, cantidad y plazo; "
                         "variables históricas de FI; programación conservadora; adicionales sólo cuando el PRDI no contiene renglón."),
            "fuentes": ["criterios_cpi.pdf", "Guía para la formulación de planes de desarrollo.pdf",
                        "series históricas publicadas en Plan/*.json"],
            "alineaciones": alignments,
        }
        project["referencia_base_2026"] = memory

    unique = {}
    for item in report["metas_adicionales"]:
        unique[(item.get("proyecto"), item.get("descripcion"))] = item
    report["metas_adicionales"] = list(unique.values())
    return completed, report
