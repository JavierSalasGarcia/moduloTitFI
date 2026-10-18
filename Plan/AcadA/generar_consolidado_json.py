# -*- coding: utf-8 -*-
"""
Script que extrae los puntos a desarrollar de los proyectos 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18 y 19
según la Guía para la formulación de planes de desarrollo, y consolida:
1. Puntos temáticos / subtemas por proyecto.
2. Archivos JSON de series estadísticas vinculados (4001 a 4037) con sus métricas.
3. Diagnóstico preliminar e inferencias FODA existentes (de preliminar_fi.json).
4. Hojas correspondientes de la Matriz de consistencia y Memoria de cálculo.
5. Referencias APA y cruces de indicadores / gráficas disponibles.
"""
import json
import os
import re

ROOT = r"D:\Proyectos\plataformaFI"
PLAN = os.path.join(ROOT, "Plan")
ACADA = os.path.join(PLAN, "AcadA")

target_projects = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]

# Cargar inventario de categorías estadísticas
with open(os.path.join(PLAN, "inventario.json"), "r", encoding="utf-8") as f:
    inventario = json.load(f)

# Cargar preliminar_fi.json
with open(os.path.join(PLAN, "preliminar_fi.json"), "r", encoding="utf-8") as f:
    preliminar = json.load(f)
prelim_by_num = {p["numero"]: p for p in preliminar.get("proyectos", [])}

# Mapear archivos 40xx por proyecto
stats_by_project = {p: [] for p in target_projects}
for cat in inventario.get("categorias", []):
    cid = cat["id"]
    fname = cat["archivo"]
    fpath = os.path.join(PLAN, fname)
    if os.path.exists(fpath):
        try:
            with open(fpath, "r", encoding="utf-8") as f_cat:
                cdata = json.load(f_cat)
                proys = cdata.get("proyectos_relacionados", [])
                p_nums = [p.get("numero") if isinstance(p, dict) else p for p in proys]
                for p_num in target_projects:
                    if p_num in p_nums:
                        # Extraer indicadores y métricas disponibles para la FI
                        metricas = []
                        if "facultad" in cdata and "indicadores" in cdata["facultad"]:
                            for ind in cdata["facultad"]["indicadores"]:
                                metricas.extend(ind.get("metricas", []))
                        stats_by_project[p_num].append({
                            "id_categoria": cid,
                            "titulo": cdata.get("categoria", cat.get("titulo", "")),
                            "archivo": fname,
                            "metricas_clave": list(set(metricas)),
                            "cita_apa": cdata.get("cita_apa", ""),
                            "referencia_apa": cdata.get("referencia_apa", "")
                        })
        except Exception as e:
            print(f"Error procesando {fname}: {e}")

# Definición estructurada de puntos de la Guía (Anexo 1) para cada proyecto objetivo
guia_puntos = {
    2: {
        "pilar": "Pilar I. Estudiantado con oportunidades para su pleno desarrollo personal",
        "nombre": "Estudiantado con oportunidades para su pleno desarrollo y pertinencia en los estudios profesionales",
        "clave": "PROY-02",
        "ejes_tematicos": [
            {
                "eje": "Cobertura de estudios profesionales",
                "puntos": [
                    "Matrícula total y por programa",
                    "Nuevo ingreso y demanda de solicitudes",
                    "Modalidades educativas",
                    "Producción de material digital"
                ]
            },
            {
                "eje": "Plan de estudios pertinente",
                "puntos": [
                    "Infraestructura académica (aulas, talleres, laboratorios, centros de cómputo)",
                    "Acervo bibliográfico (volúmenes y libros por alumno)",
                    "Programas educativos (nuevos, acreditados por organismos como CACEI/CONAIC, evaluables)",
                    "Planes de estudios nuevos o reestructurados (incorporación de criterios del SEAES)",
                    "Programas educativos con doble titulación"
                ]
            },
            {
                "eje": "Internacionalización, multilingüismo e interculturalidad",
                "puntos": [
                    "Movilidad académica (intrainstitucional, nacional e internacional)"
                ]
            },
            {
                "eje": "Formación de estudiantes con conciencia social y constructores de una sociedad justa y equitativa",
                "puntos": [
                    "Eficiencia terminal (global y por cohorte generacional)",
                    "Índice de abandono escolar",
                    "Titulación (global y por cohorte)",
                    "Titulados por examen de egreso (EGEL)",
                    "Tutoría académica (cobertura y atención diferenciada)",
                    "Asesoría disciplinar",
                    "Fomento de competencias profesionales",
                    "Brigadas universitarias multidisciplinares",
                    "Cultura digital",
                    "Becas, apoyos y estímulos universitarios",
                    "Alumnos con servicio médico",
                    "Servicio social",
                    "Prácticas o estancias profesionales",
                    "Participación en competencias o eventos estatales, nacionales o internacionales",
                    "Certificaciones de alumnos"
                ]
            },
            {
                "eje": "Educación continua",
                "puntos": [
                    "Actividades de educación continua en diversas modalidades",
                    "Certificaciones profesionales",
                    "Micro credenciales"
                ]
            },
            {
                "eje": "Acercamiento con el sector laboral",
                "puntos": [
                    "Seguimiento de egresados",
                    "Trayectorias de empleabilidad profesional",
                    "Comunidad Alumni",
                    "Promoción de la empleabilidad (Servicio universitario de empleo, convenios, bolsas de trabajo)"
                ]
            }
        ]
    },
    3: {
        "pilar": "Pilar I. Estudiantado con oportunidades para su pleno desarrollo personal",
        "nombre": "Estudios avanzados de excelencia y pertinencia social",
        "clave": "PROY-03",
        "ejes_tematicos": [
            {
                "eje": "Formación de estudiantes de estudios avanzados con conciencia social y pertinencia",
                "puntos": [
                    "Personas egresadas de especialidad, maestría y doctorado",
                    "Personas graduadas",
                    "Alumnos incorporados en proyectos de investigación",
                    "Tesis de posgrado relacionadas con proyectos de investigación aplicada y ciencia de frontera"
                ]
            },
            {
                "eje": "Oferta educativa de excelencia en los estudios avanzados",
                "puntos": [
                    "Programas educativos de especialidad, maestría y doctorado vigentes",
                    "Programas registrados y consolidados en el Sistema Nacional de Posgrados (SNP - CONAHCYT)",
                    "Evolución y comportamiento histórico de la matrícula de posgrado"
                ]
            }
        ]
    },
    4: {
        "pilar": "Pilar II. Investigación y desarrollo académico de calidad y equidad",
        "nombre": "Investigación abierta, inclusiva, equitativa y de calidad",
        "clave": "PROY-04",
        "ejes_tematicos": [
            {
                "eje": "Ciencia abierta para la transformación social",
                "puntos": [
                    "Cuerpos académicos (consolidados, en consolidación y en formación)",
                    "Líneas de Generación y Aplicación del Conocimiento (LGAC)",
                    "Producción científica de alto impacto",
                    "Recursos financieros asignados a proyectos de investigación internos y externos"
                ]
            }
        ]
    },
    5: {
        "pilar": "Pilar II. Investigación y desarrollo académico de calidad y equidad",
        "nombre": "Innovación, desarrollo tecnológico y transferencia sostenible y equitativa",
        "clave": "PROY-05",
        "ejes_tematicos": [
            {
                "eje": "Innovación y desarrollo tecnológico con equidad para la transformación productiva",
                "puntos": [
                    "Proyectos de investigación por tipo (básica y aplicada)",
                    "Proyectos con enfoque de retribución social y acceso abierto"
                ]
            },
            {
                "eje": "Apoyo a la transferencia tecnológica",
                "puntos": [
                    "Patentes, modelos de utilidad y registros de propiedad intelectual",
                    "Proyectos de desarrollo tecnológico bajo modelos triple, cuádruple o penta hélice",
                    "Infraestructura especializada para la investigación y prototipado",
                    "Apoyos financieros y fondos para escalamiento tecnológico",
                    "Vinculación efectiva de la ciencia y tecnología con el sector industrial"
                ]
            }
        ]
    },
    6: {
        "pilar": "Pilar II. Investigación y desarrollo académico de calidad y equidad",
        "nombre": "Difusión y divulgación de la ciencia abierta a través de bienes públicos del conocimiento",
        "clave": "PROY-06",
        "ejes_tematicos": [
            {
                "eje": "Difusión y divulgación en el marco de la ciencia abierta y diálogo de saberes",
                "puntos": [
                    "Producción intelectual, científica, académica y tecnológica depositada en plataformas de acceso abierto institucional"
                ]
            },
            {
                "eje": "Libros abiertos y revistas científicas como bienes públicos digitales",
                "puntos": [
                    "Procesos editoriales y tecnológicos para generar publicaciones científicas, libros y revistas como bienes públicos digitales"
                ]
            }
        ]
    },
    9: {
        "pilar": "Pilar IV. Dignificación del personal académico y administrativo",
        "nombre": "Fortalecimiento de la formación integral del docente",
        "clave": "PROY-09",
        "ejes_tematicos": [
            {
                "eje": "Formación y evaluación docente en el marco de la Nueva Escuela Mexicana",
                "puntos": [
                    "Participación en capacitaciones y diplomados docentes (disciplinar, didáctica, digital y transversal)",
                    "Certificación de competencias en una segunda lengua"
                ]
            },
            {
                "eje": "Habilitación y trayectoria de la planta docente",
                "puntos": [
                    "Conformación de la planta docente por grado académico (doctorado, maestría, especialidad, licenciatura)",
                    "Promoción laboral (concursos de oposición, juicios de promoción, programa de estímulos PROED)",
                    "Profesores de Tiempo Completo (PTC) con perfil deseable PRODEP/PROMEP",
                    "Docentes miembros del Sistema Nacional de Investigadoras e Investigadores (SNII)"
                ]
            }
        ]
    },
    10: {
        "pilar": "Pilar IV. Dignificación del personal académico y administrativo",
        "nombre": "Dignificación de las y los trabajadores universitarios",
        "clave": "PROY-10",
        "ejes_tematicos": [
            {
                "eje": "Mejora del perfil profesional del personal universitario",
                "puntos": [
                    "Conformación y estructura de la plantilla laboral (personal académico y personal administrativo)",
                    "Capacitación continua, desarrollo técnico y profesional del personal administrativo"
                ]
            },
            {
                "eje": "Gestión de distintivos institucionales en bienestar laboral",
                "puntos": [
                    "Reconocimientos, estímulos y distintivos de bienestar y clima laboral otorgados al personal administrativo"
                ]
            }
        ]
    },
    11: {
        "pilar": "Pilar V. Gestión y administración universitaria al servicio de la comunidad",
        "nombre": "Gestión administrativa para responder al quehacer universitario",
        "clave": "PROY-11",
        "ejes_tematicos": [
            {
                "eje": "Mejora del clima laboral y ambiente institucional",
                "puntos": [
                    "Acciones de integración, bienestar y mejora del clima laboral implementadas en el espacio académico"
                ]
            },
            {
                "eje": "Optimización y cuidado de infraestructura y equipamiento",
                "puntos": [
                    "Inventario y estado de la infraestructura física (aulas, laboratorios, cubículos, auditorios, áreas comunes)",
                    "Servicios de mantenimiento preventivo, correctivo y adecuaciones físicas"
                ]
            },
            {
                "eje": "Certificación de sistemas de gestión",
                "puntos": [
                    "Procesos y laboratorios certificados bajo normas de Calidad y Gestión Ambiental (ISO / ATR)"
                ]
            },
            {
                "eje": "Proceso orgánico-funcional",
                "puntos": [
                    "Actualización de manuales de organización y procedimientos acordes a la operación real"
                ]
            },
            {
                "eje": "Transporte Universitario",
                "puntos": [
                    "Operación de unidades y rutas del sistema de transporte escolar universitario (Potrobús)"
                ]
            }
        ]
    },
    12: {
        "pilar": "Pilar V. Gestión y administración universitaria al servicio de la comunidad",
        "nombre": "Fortalecimiento tecnológico",
        "clave": "PROY-12",
        "ejes_tematicos": [
            {
                "eje": "Transformación y capacidades digitales",
                "puntos": [
                    "Actualización de recursos y equipamiento tecnológico (hardware, conectividad de red, WiFi institucional)",
                    "Fomento de la cultura digital en la comunidad docente, estudiantil y administrativa",
                    "Modernización de procesos administrativos y académicos mediante tecnologías de información y plataformas web",
                    "Seguridad de la información y protección de datos universitarios",
                    "Mantenimiento continuo y sustitución planificada de la infraestructura de TIC"
                ]
            }
        ]
    },
    13: {
        "pilar": "Pilar V. Gestión y administración universitaria al servicio de la comunidad",
        "nombre": "Gobernanza",
        "clave": "PROY-13",
        "ejes_tematicos": [
            {
                "eje": "Participación colegiada y órganos de gobierno",
                "puntos": [
                    "Participación activa de la comunidad en los órganos colegiados (Consejo de Gobierno, Consejo Académico)",
                    "Seguimiento sistemático y cumplimiento transparente de acuerdos colegiados"
                ]
            },
            {
                "eje": "Protocolos de seguridad y protección universitaria",
                "puntos": [
                    "Conformación y capacitación de brigadas de protección civil y seguridad universitaria",
                    "Implementación y supervisión de protocolos institucionales de autocuidado y prevención de riesgos"
                ]
            },
            {
                "eje": "Identidad y vinculación institucional",
                "puntos": [
                    "Actividades para fortalecer la identidad, valores y sentido de pertenencia institucional",
                    "Atención cercana, escucha activa y resolución expedita de demandas estudiantiles"
                ]
            }
        ]
    },
    18: {
        "pilar": "Pilar VI. Incidencia y reconocimiento social",
        "nombre": "Reconocimiento de la diversidad social en la universidad",
        "clave": "PROY-18",
        "ejes_tematicos": [
            {
                "eje": "Inclusión, igualdad sustantiva y respeto a la diversidad",
                "puntos": [
                    "Proyectos de investigación y estudios locales en materia de equidad de género e inclusión",
                    "Capacitación y sensibilización de la comunidad en derechos humanos, igualdad de género y no discriminación",
                    "Difusión y aplicación de protocolos de atención ante casos de violencia de género",
                    "Análisis de la composición de la comunidad universitaria por género (matrícula, docencia y administración)",
                    "Transversalidad de la perspectiva de género en las actividades académicas y administrativas"
                ]
            },
            {
                "eje": "Atención a grupos en condición de vulnerabilidad",
                "puntos": [
                    "Programas de apoyo e inclusión para estudiantes con necesidades educativas especiales y barreras para el aprendizaje",
                    "Accesibilidad física e infraestructura incluyente"
                ]
            },
            {
                "eje": "Transversalización de la perspectiva de cuidados",
                "puntos": [
                    "Programas de autocuidado y salud socioemocional",
                    "Apoyos y flexibilización para personas con responsabilidades de cuidados primarios"
                ]
            }
        ]
    },
    19: {
        "pilar": "Pilar VI. Incidencia y reconocimiento social",
        "nombre": "Construcción de una cultura de paz",
        "clave": "PROY-19",
        "ejes_tematicos": [
            {
                "eje": "Impulso integral a la cultura de paz y convivencia pacífica",
                "puntos": [
                    "Incorporación de contenidos y valores de cultura de paz en las unidades de aprendizaje y planes de estudio",
                    "Instalación, operación y seguimiento de comités locales de cultura de paz",
                    "Acciones comunitarias, talleres y campañas preventivas para fomentar la mediación y resolución no violenta de conflictos",
                    "Implementación de círculos de paz y espacios de diálogo constructivo en la Facultad"
                ]
            }
        ]
    }
}

# Consolidar todo en un único diccionario
consolidado = {
    "titulo": "Consolidado de Referencia Metodológica y Puntos a Desarrollar en los Diagnósticos",
    "espacio_academico": "Facultad de Ingeniería - UAEMEX",
    "periodo_plan": "2026-2030",
    "proyectos_objetivo": target_projects,
    "proyectos": {}
}

for num in target_projects:
    info_guia = guia_puntos.get(num, {})
    prelim_info = prelim_by_num.get(num, {})
    
    # Extraer factores FODA disponibles en preliminar
    foda_raw = prelim_info.get("foda", {})
    foda_resumen = {
        "fortalezas": [f.get("metrica", str(f)) if isinstance(f, dict) else str(f) for f in foda_raw.get("fortalezas", [])],
        "debilidades": [d.get("metrica", str(d)) if isinstance(d, dict) else str(d) for d in foda_raw.get("debilidades", [])],
        "oportunidades": [str(o) for o in foda_raw.get("oportunidades", [])],
        "amenazas": [str(a) for a in foda_raw.get("amenazas", [])]
    }

    consolidado["proyectos"][str(num)] = {
        "numero": num,
        "clave": info_guia.get("clave", f"PROY-{num:02d}"),
        "nombre": info_guia.get("nombre", prelim_info.get("nombre", "")),
        "pilar": info_guia.get("pilar", prelim_info.get("pilar", "")),
        "hoja_matriz_consistencia": f"{num} ...",
        "hoja_memoria_calculo": f"{num} ...",
        "puntos_guia_anexo1": info_guia.get("ejes_tematicos", []),
        "archivos_estadisticos_json": stats_by_project.get(num, []),
        "diagnostico_preliminar_base": prelim_info.get("diagnostico", ""),
        "prosa_foda_base": prelim_info.get("prosa_foda", ""),
        "foda_estructurado": foda_resumen,
        "cruces_indicadores": prelim_info.get("cruces_indicadores", []),
        "referencias_apa": prelim_info.get("referencias_apa", [])
    }

# Guardar archivo JSON consolidado
out_json_path = os.path.join(ACADA, "puntos_proyectos_desarrollo.json")
with open(out_json_path, "w", encoding="utf-8") as f_out:
    json.dump(consolidado, f_out, indent=2, ensure_ascii=False)

print(f"Archivo JSON consolidado creado con éxito en: {out_json_path}")
print(f"Total de proyectos consolidados: {len(consolidado['proyectos'])}")
