import os
import sys

DIAG_DIR = r"D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos"

DIAGNOSTICOS_APA = {
    "diagnostico_proy_02_estudios_profesionales.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 2: ESTUDIANTADO CON OPORTUNIDADES PARA SU PLENO DESARROLLO Y PERTINENCIA EN LOS ESTUDIOS PROFESIONALES
================================================================================

DIAGNÓSTICO

1. Cobertura de estudios profesionales

La Facultad de Ingeniería (FI) de la Universidad Autónoma del Estado de México (UAEMéx) se posiciona como una institución de referencia en la formación de profesionales de la ingeniería en la región centro del país (UAEMéx, 2025a). Durante el periodo de análisis 2016–2025, la matrícula total de licenciatura registró un crecimiento del 17.1%, transitando de 2,553 estudiantes en el ciclo 2016–2017 a 2,989 en el ciclo 2024–2025, alcanzando 3,015 alumnos en el periodo 2025–2026 (UAEMéx, 2025a). Esta expansión responde a una alta demanda social: el nuevo ingreso anual creció un 55.6%, pasando de 464 estudiantes en 2016 a 722 en 2025, mientras que las solicitudes de ingreso se incrementaron de 1,520 a 1,745 aspirantes (Facultad de Ingeniería [FI-UAEMéx], 2025a).

[[GRAFICA: grafica_p02_01_matricula_licenciatura.png | Figura 1. Evolución de la matrícula total y nuevo ingreso en licenciatura en la Facultad de Ingeniería (2016–2025).]]

La matrícula se distribuye en programas consolidados: Ingeniería en Computación (812 estudiantes), Ingeniería Mecánica (620 estudiantes), Ingeniería Civil (595 estudiantes) e Ingeniería en Electrónica (480 estudiantes), conviviendo con programas orientados a la transición tecnológica como Ingeniería en Sistemas Energéticos Sustentables e Ingeniería en Inteligencia Artificial (FI-UAEMéx, 2025a). La oferta opera bajo modalidad presencial escolarizada, complementada con mediación tecnológica en la plataforma SEDUCA y el desarrollo de Guías de Estudio Independiente (GEI) y recursos digitales que favorecen la flexibilidad curricular (Dirección de Aprendizaje Digital [DAD-UAEMéx], 2024).

Tabla 1. Evolución de la matrícula, demanda e indicadores de licenciatura (2016–2025)
| Indicador Clave | Periodo Inicial (2016) | Periodo Final (2025) | Variación Neta | Tasa de Crecimiento |
|---|---|---|---|---|
| Matrícula total de licenciatura | 2,553 alumnos | 2,989 alumnos | +436 alumnos | +17.1% |
| Nuevo ingreso anual | 464 alumnos | 722 alumnos | +258 alumnos | +55.6% |
| Solicitudes anuales de ingreso | 1,520 aspirantes | 1,745 aspirantes | +225 solicitudes | +14.8% |
| Personas egresadas por año | 324 egresados | 318 egresados | -6 egresados | -1.9% |
| Cobertura de tutoría académica | 2,221 tutorados | 2,914 tutorados | +693 tutorados | +31.2% |
| Becas y apoyos otorgados | 580 beneficiarios | 691 beneficiarios | +111 apoyos | +19.1% |
Fuente: UAEMéx (2025a) y Facultad de Ingeniería (2025a).

2. Plan de estudios pertinente

La calidad académica está respaldada por 42 aulas equipadas, 18 laboratorios especializados de docencia e investigación y centros de cómputo con conectividad institucional de alta velocidad (FI-UAEMéx, 2025a). El acervo bibliográfico supera los 18,500 volúmenes físicos en biblioteca especializada y acceso a bases de datos internacionales como IEEE Xplore, ScienceDirect y SpringerLink (Dirección de Recursos Bibliotecarios [DRB-UAEMéx], 2024).

En cuanto a la acreditación externa, la carrera de Ingeniería Mecánica cuenta con acreditación internacional vigente otorgada por el Consejo de Acreditación de la Enseñanza de la Ingeniería (CACEI, 2023) vigente hasta 2027; Ingeniería en Computación está acreditada por el Consejo Nacional de Acreditación en Informática y Computación (CONAIC, 2022) con vigencia a 2027; e Ingeniería en Electrónica e Ingeniería en Sistemas Energéticos Sustentables cuentan con reconocimiento de calidad de los Comités Interinstitucionales para la Evaluación de la Educación Superior (CIEES, 2024). En particular, la licenciatura en Ingeniería Civil concluyó la vigencia de su acreditación CACEI en 2024, encontrándose en proceso de autoevaluación y atención de recomendaciones para su reacreditación programada para 2026–2027 (FI-UAEMéx, 2025a). Asimismo, los comités curriculares han incorporado los criterios transversales de sustentabilidad, perspectiva de género y derechos humanos del Sistema de Evaluación y Acreditación de la Educación Superior (SEAES, 2023) en apego a la Ley General de Educación Superior (Secretaría de Educación Pública [SEP], 2021).

3. Internacionalización, multilingüismo e interculturalidad

Durante 2016–2025, un promedio anual de 15 a 22 estudiantes de licenciatura realizó estancias de movilidad académica nacional e internacional en instituciones de América Latina, Norteamérica y Europa (Dirección de Relaciones Internacionales [DRI-UAEMéx], 2025). En materia de multilingüismo, la Facultad articula cursos de inglés y francés orientados a alcanzar niveles B2 y C1 según el Marco Común Europeo de Referencia, requisito para acceder a becas de posgrado y convocatorias globales (FI-UAEMéx, 2025b).

4. Formación de estudiantes con conciencia social y constructores de una sociedad justa y equitativa

El índice de abandono escolar se redujo de 9.4% en 2016 a 8.1% en 2025, manteniendo una retención de primer a segundo año del 84.5% (UAEMéx, 2025a). La eficiencia terminal por cohorte generacional oscila entre 4.2% y 8.2%, y la titulación por cohorte en 2.29%, debido a la exigencia académica en ciencias básicas (FI-UAEMéx, 2025b). No obstante, en el Examen General para el Egreso de la Licenciatura (EGEL-CENEVAL), el 71.57% de las personas sustentantes obtiene testimonios satisfactorios o sobresalientes, situando a los programas de la Facultad en el Padrón de Alto Rendimiento Académico (Centro Nacional de Evaluación para la Educación Superior [CENEVAL], 2025).

El acompañamiento integral se garantiza con el Programa Institucional de Tutoría Académica, que cubre al 97.5% de la matrícula (2,914 estudiantes) y asesorías disciplinares (Dirección de Servicios al Estudiante [DSE-UAEMéx], 2024). Asimismo, 691 estudiantes cuentan con becas institucionales, el 98% de la matrícula activa tiene afiliación vigente al IMSS y más de 345 prestadores anuales de servicio social colaboran en proyectos de retribución comunitaria en municipios mexiquenses (FI-UAEMéx, 2025a).

Fuentes y Referencias (Formato APA 7ma ed.)
- CACEI. (2023). *Marco de referencia para la acreditación de programas de ingeniería: Criterios internacionales*. Consejo de Acreditación de la Enseñanza de la Ingeniería, A.C.
- CENEVAL. (2025). *Resultados institucionales del Examen General para el Egreso de la Licenciatura (EGEL): Facultad de Ingeniería*. Centro Nacional de Evaluación para la Educación Superior, A.C.
- CIEES. (2024). *Padrón de programas de educación superior evaluados y acreditados*. Comités Interinstitucionales para la Evaluación de la Educación Superior, A.C.
- CONAIC. (2022). *Constancia de acreditación nacional del programa de Ingeniería en Computación*. Consejo Nacional de Acreditación en Informática y Computación, A.C.
- DAD-UAEMéx. (2024). *Informe de operación de plataformas y contenidos educativos digitales*. Dirección de Aprendizaje Digital, Universidad Autónoma del Estado de México.
- DRI-UAEMéx. (2025). *Estadísticas de movilidad estudiantil y convenios internacionales 2016–2025*. Dirección de Relaciones Internacionales, UAEMéx.
- DSE-UAEMéx. (2024). *Reporte del Programa Institucional de Tutoría y Apoyos Estudiantiles*. Dirección de Servicios al Estudiante, UAEMéx.
- Facultad de Ingeniería. (2025a). *Segundo Informe Anual de Actividades: Gestión 2024–2025*. UAEMéx.
- Facultad de Ingeniería. (2025b). *Estudio de seguimiento de trayectorias escolares y eficiencia terminal en licenciatura*. Subdirección Académica, UAEMéx.
- SEAES. (2023). *Política nacional de evaluación y acreditación de la educación superior*. Secretaría de Educación Pública.
- SEP. (2021). *Ley General de Educación Superior*. Diario Oficial de la Federación, 20 de abril de 2021.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. Dirección de Planeación y Desarrollo Institucional, UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_03_estudios_avanzados.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 3: ESTUDIOS AVANZADOS DE EXCELENCIA Y PERTINENCIA SOCIAL
================================================================================

DIAGNÓSTICO

1. Formación de estudiantes de estudios avanzados y pertinencia social

Los estudios avanzados de la Facultad de Ingeniería forman recursos humanos de alta especialización científica y tecnológica (UAEMéx, 2025a). En el ciclo 2024–2025, la matrícula total de posgrado alcanzó 121 estudiantes (69 en maestría, 42 en doctorado y 10 en especialidad), registrando un incremento del 16.7% en la matrícula doctoral respecto a 2016 y atendiendo una demanda anual de 104 solicitudes de ingreso (Coordinación de Estudios Avanzados [CEA-FI], 2025).

[[GRAFICA: grafica_p03_01_matricula_posgrado.png | Figura 1. Evolución de la matrícula y graduación en estudios avanzados de la Facultad de Ingeniería (2016–2025).]]

Durante 2024–2025 egresaron 28 personas graduadas de posgrado, logrando una tasa de graduación oportuna superior al 75% en maestría y 70% en doctorado (CEA-FI, 2025). El 100% de las y los estudiantes inscritos en programas con registro vigente cuenta con beca nacional del Consejo Nacional de Humanidades, Ciencias y Tecnologías (CONAHCYT, 2024). Asimismo, se realizan entre 8 y 12 estancias de investigación anuales en centros de excelencia nacional e internacional, generando productos científicos indizados en JCR y Scopus (Secretaría de Investigación y Estudios Avanzados [SIEA-UAEMéx], 2025).

Tabla 1. Indicadores de matrícula, eficiencia y posgrados en el SNP (2016–2025)
| Indicador de Posgrado | Periodo 2016–2017 | Periodo 2024–2025 | Variación Neta | Situación / Tendencia |
|---|---|---|---|---|
| Matrícula total de posgrado | 126 estudiantes | 121 estudiantes | -5 estudiantes | Estabilidad cualitativa |
| Matrícula de Doctorado | 36 estudiantes | 42 estudiantes | +6 estudiantes | +16.7% crecimiento |
| Solicitudes de ingreso anuales | 98 solicitudes | 104 solicitudes | +6 solicitudes | +6.1% demanda |
| Personas graduadas por año | 24 graduados | 28 graduados | +4 graduados | +16.7% incremento |
| Programas en el SNP (CONAHCYT) | 4 programas | 5 programas | +1 programa | 100% elegibles en SNP |
Fuente: UAEMéx (2025a) y Coordinación de Estudios Avanzados de la Facultad de Ingeniería (2025).

2. Oferta educativa de excelencia y diversificación en estudios avanzados

La oferta comprende la Maestría y Doctorado en Ciencias del Agua, la Maestría y Doctorado en Ciencia de Materiales, la Maestría en Ciencias de la Computación y la Especialidad en Valuación de Bienes Inmuebles, programas registrados en el Sistema Nacional de Posgrados (CONAHCYT, 2024). El 85% del claustro docente de posgrado pertenece al Sistema Nacional de Investigadoras e Investigadores (SNII, 2025). Entre los retos prioritarios se ubica la diversificación hacia áreas de Inteligencia Artificial Aplicada, Ciberseguridad y Energías Renovables, así como profundizar proyectos con impacto y retribución comunitaria en la entidad (CEA-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CEA-FI. (2025). *Informe de gestión académica e indicadores de posgrado 2024–2025*. Coordinación de Estudios Avanzados, Facultad de Ingeniería, UAEMéx.
- CONAHCYT. (2024). *Padrón del Sistema Nacional de Posgrados (SNP) y lineamientos de becas de posgrado*. Consejo Nacional de Humanidades, Ciencias y Tecnologías.
- SIEA-UAEMéx. (2025). *Reporte anual de investigación y posgrado*. Secretaría de Investigación y Estudios Avanzados, UAEMéx.
- SNII. (2025). *Padrón de beneficiarios del Sistema Nacional de Investigadoras e Investigadores*. Secretaría de Ciencia, Humanidades, Tecnología e Innovación.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. Dirección de Planeación y Desarrollo Institucional, UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_04_investigacion_calidad.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 4: INVESTIGACIÓN ABIERTA, INCLUSIVA, EQUITATIVA Y DE CALIDAD
================================================================================

DIAGNÓSTICO

1. Investigación para la transformación, proyectos con financiamiento y redes académicas

La investigación en la Facultad de Ingeniería se articula en 8 Cuerpos Académicos (5 Consolidados y 3 En Consolidación) y Líneas de Generación y Aplicación del Conocimiento (LGAC) orientadas a recursos hídricos, materiales avanzados, computación y energía sustentable (Subdirección Académica [SA-FI], 2025). Durante 2024–2025 se ejecutaron 18 proyectos formalmente registrados, de los cuales 6 captaron financiamiento externo del Consejo Nacional de Humanidades, Ciencias y Tecnologías (CONAHCYT, 2024) y convenios sectoriales por más de 4.8 millones de pesos (SIEA-UAEMéx, 2025).

[[GRAFICA: grafica_p04_01_investigadores_snii.png | Figura 1. Evolución del número de investigadores adscritos al SNII en la Facultad de Ingeniería (2016–2025).]]

La Facultad participa en redes académicas como la Red del Agua de la Academia Mexicana de Ciencias y la Red Mexicana de Supercómputo, favoreciendo estancias y proyectos conjuntos (SA-FI, 2025).

Tabla 1. Evolución de la planta de investigación, Cuerpos Académicos y SNII (2016–2025)
| Indicador de Investigación | Periodo 2016–2017 | Periodo 2024–2025 | Variación | Tasa de Cambio |
|---|---|---|---|---|
| Investigadores adscritos al SNII | 32 miembros | 46 miembros | +14 investigadores | +43.8% crecimiento |
| SNII Nivel II y III | 5 miembros | 11 miembros | +6 miembros | +120.0% incremento |
| Cuerpos Académicos Consolidados (CAC) | 3 cuerpos | 5 cuerpos | +2 CAC | +66.7% consolidación |
| Proyectos de investigación registrados | 14 proyectos | 18 proyectos | +4 proyectos | +28.6% actividad |
| Publicaciones indizadas (JCR / Scopus) | 28 artículos/año | 48 artículos/año | +20 artículos | +71.4% producción |
Fuente: UAEMéx (2025a) y Subdirección Académica de la Facultad de Ingeniería (2025).

2. Ciencia Abierta, ética y pertinencia social de la investigación

Conforme a la Ley General en materia de Humanidades, Ciencias, Tecnologías e Innovación (Cámara de Diputados, 2023), los productos científicos y bases de datos generadas se incorporan al Repositorio Institucional de Acceso Abierto (RI-UAEMéx, 2025). Las investigaciones son dictaminadas por comités de ética, priorizando la retribución social hacia zonas mexiquenses con alta vulnerabilidad técnica e hídrica (SA-FI, 2025).

3. Desarrollo de la planta de investigación y equidad de género en la ciencia

La membresía en el Sistema Nacional de Investigadoras e Investigadores (SNII) creció un 43.8%, transitando de 32 integrantes en 2016 a 46 en 2025, con 11 investigadores en los niveles II y III (SNII, 2025). Las mujeres investigadoras representan el 24% del padrón SNII de la Facultad, por lo que es prioritario instrumentar programas de mentoría científica y apoyos a la corresponsabilidad en el cuidado (Comité de Género [CG-FI], 2024; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- Cámara de Diputados. (2023). *Ley General en materia de Humanidades, Ciencias, Tecnologías e Innovación*. Diario Oficial de la Federación, 8 de mayo de 2023.
- CG-FI. (2024). *Diagnóstico sobre la participación de las mujeres en las áreas STEM en la Facultad de Ingeniería*. UAEMéx.
- CONAHCYT. (2024). *Programas y convocatorias de investigación científica y desarrollo tecnológico*. Secretaría de Ciencia, Humanidades, Tecnología e Innovación.
- RI-UAEMéx. (2025). *Estadísticas de consulta y depósito de producción científica en acceso abierto*. Repositorio Institucional, UAEMéx.
- SA-FI. (2025). *Informe de Cuerpos Académicos, proyectos de investigación y producción científica 2024–2025*. Subdirección Académica, Facultad de Ingeniería, UAEMéx.
- SIEA-UAEMéx. (2025). *Informe de financiamiento externo y convenios de investigación*. Secretaría de Investigación y Estudios Avanzados, UAEMéx.
- SNII. (2025). *Padrón de Investigadoras e Investigadores vigentes*. SECIHTI.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_05_innovacion_transferencia.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 5: INNOVACIÓN, DESARROLLO TECNOLÓGICO Y TRANSFERENCIA SOSTENIBLE Y EQUITATIVA
================================================================================

DIAGNÓSTICO

1. Ecosistema de innovación, desarrollo tecnológico y transferencia de conocimiento

Ubicada en el corredor industrial Toluca-Lerma, la Facultad de Ingeniería impulsa la transferencia tecnológica hacia los sectores automotriz, químico, metalmecánico y de software (Coordinación de Vinculación [CV-FI], 2025). Durante el periodo 2016–2025, la Facultad tramitó 12 solicitudes de patentes y modelos de utilidad ante el Instituto Mexicano de la Propiedad Industrial (IMPI, 2025), obteniendo 6 títulos concedidos en tratamiento de aguas, mecatrónica y maquinaria agrícola (Secretaría de Extensión y Vinculación [SEV-UAEMéx], 2025).

Tabla 1. Indicadores de propiedad industrial y vinculación tecnológica (2016–2025)
| Indicador de Innovación y Transferencia | Línea Base (2016) | Situación Actual (2025) | Variación Acumulada |
|---|---|---|---|
| Solicitudes de Patente y Modelos IMPI | 1 solicitud/año | 3 solicitudes/año | +200% incremento anual |
| Títulos de Patente Otorgados por IMPI | 2 títulos vigentes | 6 títulos vigentes | +4 títulos consolidados |
| Convenios con Sector Productivo y Gob. | 8 convenios | 15 convenios activos | +7 alianzas formales |
| Proyectos de Desarrollo Tecnológico | 4 proyectos | 9 proyectos | +125% dinamismo |
| Registros de Derechos de Autor (Software)| 6 registros | 18 registros | +12 certificados INDAUTOR |
Fuente: CV-FI (2025) e IMPI (2025).

El 68% de los desarrollos tecnológicos se ubica en niveles de madurez tecnológica intermedios (TRL 4 a 6), requiriendo mecanismos de licenciamiento, protección intelectual y aceleración de emprendimientos de base tecnológica (Spin-offs) para concretar su inserción en el mercado productivo (CV-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CV-FI. (2025). *Reporte anual de convenios, proyectos tecnológicos y servicios especializados*. Coordinación de Vinculación, Facultad de Ingeniería, UAEMéx.
- IMPI. (2025). *Gaceta de la Propiedad Industrial: Registro de títulos y solicitudes de patente*. Instituto Mexicano de la Propiedad Industrial.
- INDAUTOR. (2024). *Registro público del derecho de autor: Programas de computación y obras técnicas*. Instituto Nacional del Derecho de Autor.
- SEV-UAEMéx. (2025). *Informe institucional de vinculación y transferencia del conocimiento*. Secretaría de Extensión y Vinculación, UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_06_difusion_ciencia_abierta.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 6: DIFUSIÓN Y DIVULGACIÓN DE LA CIENCIA ABIERTA A TRAVÉS DE BIENES PÚBLICOS DEL CONOCIMIENTO
================================================================================

DIAGNÓSTICO

1. Publicaciones científicas, bienes públicos de conocimiento y divulgación social de la ciencia

Durante 2016–2025, la producción científica indizada en JCR y Scopus aumentó un 71.4%, transitando de 28 a 48 artículos anuales, publicándose el 65% en revistas de acceso abierto de cuartiles Q1 y Q2 (SIEA-UAEMéx, 2025). La producción se difunde como bienes públicos de conocimiento mediante el Repositorio Institucional (RI-UAEMéx, 2025).

Tabla 1. Producción editorial científica y actividades de divulgación (2016–2025)
| Indicador de Difusión y Divulgación | Periodo 2016–2017 | Periodo 2024–2025 | Variación | Crecimiento % |
|---|---|---|---|---|
| Artículos en revistas indizadas (JCR/Scopus) | 28 artículos | 48 artículos | +20 artículos | +71.4% |
| Capítulos de libro y libros científicos | 16 publicaciones | 24 publicaciones | +8 obras | +50.0% |
| Eventos de divulgación científica anuales | 4 eventos | 10 eventos | +6 eventos | +150.0% |
| Asistentes a actividades de divulgación | ~850 personas | ~2,400 personas | +1,550 personas | +182.4% |
| Ponencias en congresos internacionales | 35 ponencias | 56 ponencias | +21 ponencias | +60.0% |
Fuente: UAEMéx (2025a) y Subdirección Académica de la Facultad de Ingeniería (2025).

En divulgación comunitaria, se celebran el Coloquio de Investigación en Ingeniería y talleres de robótica para estudiantes de nivel básico y medio superior (Subdirección Académica [SA-FI], 2025). Se identifica la necesidad de ampliar los formatos digitales transmedia y pódcast de divulgación técnica orientados a la ciudadanía (UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- Clarivate Analytics. (2024). *Journal Citation Reports (JCR): Engineering multidisciplinary and science indices*. Web of Science Group.
- Elsevier. (2024). *Scopus Custom Data: Producción científica institucional de la UAEMéx*. Elsevier B.V.
- RI-UAEMéx. (2025). *Métricas de consulta de publicaciones en acceso abierto*. Repositorio Institucional, UAEMéx.
- SA-FI. (2025). *Memoria anual de eventos de difusión y divulgación científica*. Subdirección Académica, Facultad de Ingeniería, UAEMéx.
- SIEA-UAEMéx. (2025). *Reporte de productividad editorial científica*. Secretaría de Investigación y Estudios Avanzados, UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_09_formacion_docente.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 9: FORTALECIMIENTO DE LA FORMACIÓN INTEGRAL DEL DOCENTE
================================================================================

DIAGNÓSTICO

1. Formación y actualización del personal académico y fortalecimiento de la planta docente

La planta docente de la Facultad de Ingeniería en el ciclo 2024–2025 está conformada por 268 profesoras y profesores (74 de Tiempo Completo - PTC, 12 Técnicos Académicos y 182 de Asignatura) (UAEMéx, 2025a). El 91.9% del profesorado de tiempo completo cuenta con estudios de posgrado (56.8% Doctorado y 35.1% Maestría), y 58 PTC (78.4%) ostentan el Reconocimiento de Perfil Deseable otorgado por la Secretaría de Educación Pública a través del PRODEP (SEP, 2024).

[[GRAFICA: grafica_p09_01_planta_docente_snii.png | Figura 1. Composición de la planta docente, grado académico y perfil deseable PRODEP (2016–2025).]]

Anualmente se imparten más de 25 cursos a través del Programa Institucional de Formación Docente (Dirección de Desarrollo del Personal Académico [DiDePA-UAEMéx], 2024). En la consulta al profesorado, el 74% de las y los docentes solicita fortalecer cursos en metodologías activas (Aprendizaje Basado en Proyectos), integración de Inteligencia Artificial en ingeniería y profesionalización pedagógica para el personal de asignatura (Subdirección Académica [SA-FI], 2025; UAEMéx, 2025b).

Tabla 1. Indicadores del personal académico y habilitación docente (2016–2025)
| Indicador del Personal Académico | Ciclo 2016–2017 | Ciclo 2024–2025 | Variación Neta | Situación / Desempeño |
|---|---|---|---|---|
| Total de personal docente activo | 245 profesores | 268 profesores | +23 docentes | +9.4% crecimiento |
| Profesorado de Tiempo Completo (PTC) | 71 PTC | 74 PTC | +3 PTC | Plazas estables |
| PTC con grado de Doctorado | 36 doctores (50.7%) | 42 doctores (56.8%) | +6 doctores | +16.7% habilitación |
| PTC con Perfil Deseable PRODEP | 51 PTC (71.8%) | 58 PTC (78.4%) | +7 PTC | Reconocimiento SEP |
| Miembros del SNII en la planta docente | 32 miembros | 46 miembros | +14 investigadores | +43.8% membresía |
Fuente: UAEMéx (2025a) y Subdirección Académica de la Facultad de Ingeniería (2025).

Fuentes y Referencias (Formato APA 7ma ed.)
- DiDePA-UAEMéx. (2024). *Informe del Programa Institucional de Formación y Actualización Docente*. Dirección de Desarrollo del Personal Académico, UAEMéx.
- SA-FI. (2025). *Censo de personal académico, habilitación docente y evaluación curricular*. Subdirección Académica, Facultad de Ingeniería, UAEMéx.
- SEP. (2024). *Padrón de Profesores de Tiempo Completo con Perfil Deseable: Convocatoria PRODEP*. Dirección General de Educación Superior Universitaria e Intercultural, SEP.
- SNII. (2025). *Directorio de investigadoras e investigadores adscritos*. SECIHTI.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_10_trabajadores_universitarios.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 10: DIGNIFICACIÓN DE LAS Y LOS TRABAJADORES UNIVERSITARIOS
================================================================================

DIAGNÓSTICO

1. Desarrollo, capacitación y dignificación del personal administrativo

La plantilla administrativa y operativa de la Facultad de Ingeniería se compone de 105 personas trabajadoras (64 sindicalizadas ante el SUTESUAEM, 24 de confianza y 17 mandos medios y directivos) (Coordinación Administrativa [CA-FI], 2025). El personal acumula más de 1,250 horas anuales de capacitación en seguridad en laboratorios, primeros auxilios y ofimática coordinadas por la Dirección de Recursos Humanos (DRH-UAEMéx, 2024).

[[GRAFICA: grafica_p10_01_personal_administrativo.png | Figura 1. Distribución del personal administrativo y horas de capacitación anual (2016–2025).]]

Tabla 1. Composición del personal administrativo y programas de capacitación (2016–2025)
| Indicador del Personal Administrativo | Periodo 2016–2017 | Periodo 2024–2025 | Variación | Desempeño |
|---|---|---|---|---|
| Total de personal administrativo | 98 trabajadores | 105 trabajadores | +7 personas | Crecimiento moderado (+7.1%) |
| Personal sindicalizado (SUTESUAEM) | 61 trabajadores | 64 trabajadores | +3 personas | Estabilidad contractual |
| Horas anuales de capacitación laboral | ~750 horas | ~1,250 horas | +500 horas | +66.7% profesionalización |
| Cursos de seguridad e higiene impartidos| 4 cursos/año | 8 cursos/año | +4 cursos | +100% prevención de riesgos |
| Evaluaciones de desempeño aprobadas | 92% del personal | 96% del personal | +4.0 puntos | Alto compromiso laboral |
Fuente: CA-FI (2025) y DRH-UAEMéx (2024).

Las demandas prioritarias identificadas en las encuestas laborales incluyen la renovación de mobiliario ergonómico, dotación oportuna de equipo de protección personal para laboratorios pesados y el fortalecimiento de programas de salud ocupacional y salud mental (CA-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CA-FI. (2025). *Informe de gestión administrativa, infraestructura y recursos humanos*. Coordinación Administrativa, Facultad de Ingeniería, UAEMéx.
- DRH-UAEMéx. (2024). *Reporte del Programa Institucional de Capacitación y Desarrollo del Personal Administrativo*. Dirección de Recursos Humanos, UAEMéx.
- SUTESUAEM. (2024). *Contrato Colectivo de Trabajo celebrado entre la UAEMéx y el Sindicato Único de Trabajadores y Empleados al Servicio de la UAEM*. UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_11_gestion_administrativa.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 11: GESTIÓN ADMINISTRATIVA PARA RESPONDER AL QUEHACER UNIVERSITARIO
================================================================================

DIAGNÓSTICO

1. Mejora del clima laboral, procesos de gestión de calidad y simplificación administrativa

La gestión administrativa opera bajo el Sistema de Gestión de la Calidad institucional certificado bajo la norma ISO 9001:2015 (International Organization for Standardization [ISO], 2015), abarcando procesos de control escolar, compras, laboratorios y titulación, manteniendo cero no conformidades en auditorías externas (Coordinación de Calidad [CC-FI], 2025).

Tabla 1. Indicadores de gestión de calidad y tiempos de respuesta administrativa (2016–2025)
| Proceso Administrativo Clave | Tiempo Promedio (2016) | Tiempo Actual (2025) | Reducción de Tiempo | Eficiencia Lograda |
|---|---|---|---|---|
| Emisión de constancias y certificados | 5 días hábiles | 2 días hábiles | -3 días | -60.0% tiempo de espera |
| Trámite de registro de titulación | 20 días hábiles | 10 días hábiles | -10 días | -50.0% optimización |
| Liberación de servicio social | 15 días hábiles | 5 días hábiles | -10 días | -66.7% simplificación |
| Procesos certificados en ISO 9001 | 4 procesos | 7 procesos | +3 procesos | Cobertura integral SGC |
| Satisfacción de usuarios (encuestas) | 78.5% satisfacción | 86.2% satisfacción | +7.7 puntos | Percepción favorable |
Fuente: CC-FI (2025) y Dirección de Organización y Sistemas (2024).

Las áreas de oportunidad radican en la erradicación del uso de papel, la adopción de firma electrónica avanzada institucional y la automatización del control de laboratorios (CC-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CC-FI. (2025). *Informe anual de auditoría del Sistema de Gestión de la Calidad ISO 9001:2015*. Coordinación de Calidad, Facultad de Ingeniería, UAEMéx.
- DOS-UAEMéx. (2024). *Manual institucional de procesos y procedimientos administrativos*. Dirección de Organización y Sistemas, UAEMéx.
- ISO. (2015). *ISO 9001:2015 Quality management systems — Requirements*. International Organization for Standardization.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_12_fortalecimiento_tecnologico.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 12: FORTALECIMIENTO TECNOLÓGICO
================================================================================

DIAGNÓSTICO

1. Infraestructura, cultura digital, conectividad y sistemas de información

La Facultad dispone de 450 computadoras en 7 salas y laboratorios, conectadas mediante red de fibra óptica con ancho de banda de 1 Gbps y red inalámbrica Eduroam (Centro de Cómputo [CC-FI], 2025; Dirección de Tecnologías de Información [DTI-UAEMéx], 2024).

Tabla 1. Diagnóstico del parque tecnológico, servidores y conectividad (2016–2025)
| Componente Tecnológico | Capacidad 2016 | Capacidad 2025 | Variación Neta | Condición / Reto Actual |
|---|---|---|---|---|
| Equipos de cómputo en laboratorios | 320 equipos | 450 equipos | +130 equipos | 38% requiere actualización de GPU |
| Servidores de cálculo y virtualización| 2 servidores | 5 servidores | +3 servidores | Clúster para IA y simulación |
| Ancho de banda de conexión institucional| 200 Mbps | 1,000 Mbps (1 Gbps)| +800 Mbps | Alta demanda en horas pico |
| Puntos de acceso inalámbrico (AP WiFi) | 12 APs | 32 APs | +20 APs | Zonas de sombra en edificios antiguos |
| Licenciamiento de software especializado| 8 paquetes | 15 paquetes | +7 suites | MATLAB, SolidWorks, ANSYS, AutoCAD |
Fuente: CC-FI (2025) y DTI-UAEMéx (2024).

El 38% de los equipos de docencia supera los 5 años de servicio, requiriendo actualización de tarjetas gráficas (GPU) y memoria RAM para simuladores y Deep Learning, además de reforzar protocolos de ciberseguridad (CC-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CC-FI. (2025). *Diagnóstico del estado físico y tecnológico de equipos de cómputo y servidores*. Centro de Cómputo, Facultad de Ingeniería, UAEMéx.
- DTI-UAEMéx. (2024). *Reporte de conectividad, enlaces institucionales y seguridad de la información*. Dirección de Tecnologías de la Información y Comunicaciones, UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_13_gobernanza.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 13: GOBERNANZA
================================================================================

DIAGNÓSTICO

1. Participación institucional de la comunidad y toma de decisiones colegiada

El gobierno colegiado opera mediante el H. Consejo de Gobierno y el H. Consejo Académico con representación paritaria de docentes y estudiantes, sesionando formalmente 24 veces al año en apego a la Legislación Universitaria (Secretaría del Consejo [SC-FI], 2025; UAEMéx, 2021).

2. Protocolos de seguridad, protección civil y prevención de riesgos en instalaciones

La Unidad Interna de Protección Civil coordina la seguridad en los 18 laboratorios y talleres de la Facultad, contando con 48 brigadistas acreditados, 85 extintores vigentes y 36 cámaras de videovigilancia (Unidad de Protección Civil [UPC-FI], 2025; Dirección de Seguridad y Protección Universitaria [DSPU-UAEMéx], 2024).

Tabla 1. Indicadores de seguridad, protección civil y sesiones de gobierno colegiado (2016–2025)
| Rubro de Gobernanza y Seguridad | Periodo 2016–2017 | Periodo 2024–2025 | Variación | Desempeño |
|---|---|---|---|---|
| Sesiones ordinarias de Consejos (Gob/Acad)| 22 sesiones/año | 24 sesiones/año | +2 sesiones | Operación colegiada continua |
| Simulacros de protección civil anuales | 2 simulacros | 3 simulacros | +1 simulacro | 100% de cumplimiento normativo |
| Brigadistas universitarios capacitados | 25 brigadistas | 48 brigadistas | +23 personas | +92.0% personal habilitado |
| Incidentes graves en laboratorios | 0 incidentes | 0 incidentes | 0 incidentes | Saldo blanco sostenido |
| Cámaras de videovigilancia operativas | 14 cámaras | 36 cámaras | +22 cámaras | Cobertura perimetral al 90% |
Fuente: UPC-FI (2025) y SC-FI (2025).

3. Identidad Universitaria y memoria histórica de la ingeniería mexiquense

Con 68 años de trayectoria, la Facultad preserva su acervo documental y conmemorativo, siendo prioritario consolidar el archivo histórico digital y fomentar la identidad verde y oro (SC-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- DSPU-UAEMéx. (2024). *Manual institucional de seguridad y protocolos de emergencia en campus universitarios*. Dirección de Seguridad y Protección Universitaria, UAEMéx.
- SC-FI. (2025). *Actas de sesiones ordinarias y extraordinarias del H. Consejo de Gobierno y Consejo Académico*. Secretaría del Consejo, Facultad de Ingeniería, UAEMéx.
- UAEMéx. (2021). *Legislación Universitaria: Estatuto General y Reglamentos Orgánicos*. UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.
- UPC-FI. (2025). *Programa Interno de Protección Civil y Gestión Integral del Riesgo*. Unidad Interna de Protección Civil, Facultad de Ingeniería, UAEMéx.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_18_diversidad_social.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 18: RECONOCIMIENTO DE LA DIVERSIDAD SOCIAL EN LA UNIVERSIDAD
================================================================================

DIAGNÓSTICO

1. Favorecer la inclusión, la accesibilidad universal y el respeto a la diversidad social

La presencia de mujeres en la matrícula de licenciatura pasó de 18.2% (465 alumnas) en 2016 a 24.6% (735 alumnas) en 2025, superando el 35% en programas computacionales e inteligencia artificial, y alcanzando el 32.2% en posgrado (UAEMéx, 2025a). En accesibilidad física, el 85% de los edificios cuenta con rampas y sanitarios adaptados (Comité de Género e Inclusión [CGI-FI], 2025).

Tabla 1. Evolución de la participación de mujeres e inclusión en la Facultad de Ingeniería (2016–2025)
| Indicador de Género e Inclusión | Periodo 2016–2017 | Periodo 2024–2025 | Variación Neta | Tendencia |
|---|---|---|---|---|
| Mujeres en matrícula de licenciatura | 465 alumnas (18.2%) | 735 alumnas (24.6%) | +270 alumnas | +58.1% incremento de mujeres |
| Mujeres en matrícula de posgrado | 35 alumnas (27.8%) | 39 alumnas (32.2%) | +4 alumnas | Mayor presencia en ciencias |
| Mujeres en planta de PTC | 15 PTC (21.1%) | 19 PTC (25.7%) | +4 profesoras | Avance gradual en claustro |
| Infraestructura con rampas de acceso | 60% edificios | 85% edificios | +25% cobertura | Adecuación física continua |
| Talleres de sensibilización impartidos | 2 talleres/año | 8 talleres/año | +6 talleres | +300% actividades formativas |
Fuente: UAEMéx (2025a) y CGI-FI (2025).

2. Transversalización de la perspectiva de cuidados y prevención de discriminación

La Facultad dispone de espacios de lactancia para la comunidad y difunde los apoyos para madres de familia (Dirección de Género [DG-UAEMéx], 2024). Es indispensable continuar impartiendo talleres sobre masculinidades y prevención de discriminación (CGI-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- CGI-FI. (2025). *Diagnóstico de inclusión, accesibilidad universal y perspectiva de género*. Comité de Género e Inclusión, Facultad de Ingeniería, UAEMéx.
- DG-UAEMéx. (2024). *Informe del Programa de Transversalización de la Perspectiva de Género e Inclusión*. Dirección de Género y Diversidad, UAEMéx.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.
- UNESCO. (2023). *Cerrar la brecha de género en ciencia, tecnología, ingeniería y matemáticas (STEM)*. Organización de las Naciones Unidas para la Educación, la Ciencia y la Cultura.

================================================================================
OBJETIVO GENERAL
""",

    "diagnostico_proy_19_cultura_de_paz.txt": """================================================================================
PLAN DE DESARROLLO DE LA FACULTAD DE INGENIERÍA 2026–2030
PROYECTO 19: CONSTRUCCIÓN DE UNA CULTURA DE PAZ
================================================================================

DIAGNÓSTICO

1. Impulsar la cultura de paz, resolución pacífica de conflictos y convivencia universitaria

La Facultad cuenta con el Comité de Mediación y el Enlace con la Defensoría de los Derechos Universitarios (DDU-UAEMéx, 2024), aplicando mecanismos alternativos de solución de controversias con 14 atenciones por mediación en 2024–2025 (Enlace de Derechos Universitarios [EDU-FI], 2025).

Tabla 1. Acciones para la cultura de paz, mediación y prevención de violencia (2016–2025)
| Indicador de Cultura de Paz y Convivencia | Periodo 2016–2017 | Periodo 2024–2025 | Variación | Desempeño |
|---|---|---|---|---|
| Casos atendidos vía mediación y diálogo | 4 casos/año | 14 casos/año | +10 atenciones | Resolución pacífica oportuna |
| Quejas canalizadas a Defensoría de DD.UU.| 2 quejas | 1 queja | -1 queja | Disminución de conflictos graves |
| Jornadas y conferencias de Cultura de Paz | 2 eventos/año | 6 eventos/año | +4 eventos | +200% actividades anuales |
| Participantes en talleres de no violencia | ~250 personas | ~890 personas | +640 asistentes | +256% alcance en la comunidad |
| Difusión del Protocolo de Género UAEMéx | Semestral | Permanente digital | Continua | 100% inducción a nuevo ingreso |
Fuente: EDU-FI (2025) y DDU-UAEMéx (2024).

Se difunde el Protocolo para Prevenir, Atender y Sancionar la Violencia de Género en la UAEMéx (UAEMéx, 2023). Las áreas de oportunidad demandan reforzar los talleres vivenciales de asertividad, manejo de estrés y resolución de conflictos (EDU-FI, 2025; UAEMéx, 2025b).

Fuentes y Referencias (Formato APA 7ma ed.)
- DDU-UAEMéx. (2024). *Informe anual de atención a los derechos universitarios y cultura de paz*. Defensoría de los Derechos Universitarios, UAEMéx.
- EDU-FI. (2025). *Reporte del Comité de Mediación y Convivencia Escolar*. Enlace de Derechos Universitarios, Facultad de Ingeniería, UAEMéx.
- UAEMéx. (2023). *Protocolo para prevenir, atender y sancionar la violencia de género en la Universidad Autónoma del Estado de México*. Gaceta Universitaria.
- UAEMéx. (2025a). *Agenda Estadística Universitaria 2025*. UAEMéx.
- UAEMéx. (2025b). *Plan Rector de Desarrollo Institucional 2025–2029*. UAEMéx.

================================================================================
OBJETIVO GENERAL
"""
}

def write_and_update():
    print("Writing files with APA citations and references...")
    for fname, text in DIAGNOSTICOS_APA.items():
        fpath = os.path.join(DIAG_DIR, fname)
        with open(fpath, "w", encoding="utf-8") as f:
            f.write(text.strip() + "\n")
        print(f" -> Updated {fname}")
    print("All diagnostic text files updated with APA citations and references!")

if __name__ == "__main__":
    write_and_update()
