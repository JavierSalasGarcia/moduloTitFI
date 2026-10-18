# Orquestador del Plan de Desarrollo 2026–2030
## Facultad de Ingeniería — UAEMEX (Subdirección / Área Académica)

> **Archivo de Control Maestro:** Este documento mantiene el contexto operativo detallado, las fuentes, la arquitectura de datos, los estándares editoriales y gráficos, y el checklist de ejecución para la formulación de los diagnósticos de los 12 proyectos estratégicos asignados al Área Académica (`AcadA`).

---

## 1. Contexto y Objetivos del Proyecto

El objetivo es formular el **diagnóstico estratégico y sustantivo** de los **12 proyectos clave** correspondientes a la docencia, investigación, personal académico/administrativo, gestión y convivencia comunitaria de la Facultad de Ingeniería:

- **Pilar I (Estudiantado y Docencia):**
  - `PROY-02`: Estudiantado con oportunidades para su pleno desarrollo y pertinencia en los estudios profesionales
  - `PROY-03`: Estudios avanzados de excelencia y pertinencia social
- **Pilar II (Investigación y Desarrollo Tecnológico):**
  - `PROY-04`: Investigación abierta, inclusiva, equitativa y de calidad
  - `PROY-05`: Innovación, desarrollo tecnológico y transferencia sostenible y equitativa
  - `PROY-06`: Difusión y divulgación de la ciencia abierta a través de bienes públicos del conocimiento
- **Pilar IV (Personal Universitario):**
  - `PROY-09`: Fortalecimiento de la formación integral del docente
  - `PROY-10`: Dignificación de las y los trabajadores universitarios
- **Pilar V (Gestión y Gobernanza):**
  - `PROY-11`: Gestión administrativa para responder al quehacer universitario
  - `PROY-12`: Fortalecimiento tecnológico
  - `PROY-13`: Gobernanza
- **Pilar VI (Diversidad y Paz):**
  - `PROY-18`: Reconocimiento de la diversidad social en la universidad
  - `PROY-19`: Construcción de una cultura de paz

El producto final consiste en **12 archivos de texto independientes** en la carpeta:  
[`D:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos\`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/)  
bajo el patrón: `diagnostico_proy_XX_<nombre_corto>.txt`.

---

## 2. Fuentes de Información y Articulación de Datos

El sistema opera bajo la **regla de congruencia sustantiva**: los diagnósticos integran cuatro fuentes de evidencia complementarias sin recurrir a frases de bitácora ni listados mecánicos.

```text
       [Agendas Estadísticas JSON 4001–4037]         [Encuestas a Estudiantes y Sectores C01–C17]
                       │                                                  │
                       ▼                                                  ▼
      [Trayectoria Cuantitativa (2016–2025)]        [Percepción, Necesidades y Clima Escolar]
                                       \                /
                                        ▼              ▼
                    [Documentos de Apoyo: PDEM, PND, LGES, ODS, ANUIES]
                                               │
                                               ▼
                              [Matriz FODA Sustentada por Proyecto]
                                               │
                                               ▼
                         [Diagnóstico en Prosa Continua (7 Momentos)]
                                               │
                                               ▼
              [Archivos en AcadA/Diagnosticos/ con Etiquetas de Gráficas e Imágenes]
```

### A. Dimensión Cuantitativa (Agendas Universitarias)
- **33 categorías procesadas** en archivos JSON (`4001` a `4037` en `D:\Proyectos\plataformaFI\Plan\`).
- Cobertura de series históricas de al menos 8 a 10 años (2016–2025/2026) a nivel Facultad y desglosado por programa (6 licenciaturas y posgrados).
- Cruces analíticos (abandono vs. reprobación vs. eficiencia terminal).

### B. Dimensión Cualitativa y Perceptual (Encuestas FI 360)
- **17 instrumentos de consulta** formalizados en [`instrumentos.json`](file:///d:/Proyectos/plataformaFI/Plan/instrumentos.json):
  - **Estudiantado:** Primeros semestres (`C01` con 184 respuestas), semestres avanzados (`C16` con 73 respuestas) y posgrado (`C17`). Registran factores de rezago, hábitos de estudio, ambiente de respeto, tutoría, servicio social y necesidades de laboratorios/infraestructura.
  - **Docencia:** Profesorado de licenciatura (`C02`) y posgrado (`C03`). Registran capacitación, condiciones de trabajo, actualización disciplinar y tecnológica.
  - **Personal Administrativo:** `C04`. Clima laboral, herramientas de trabajo, capacitación.
  - **Coordinaciones de Docencia:** 6 licenciaturas (`C05` Civil, `C06` Mecánica, `C07` Computación, `C08` Electrónica, `C09` Sistemas Energéticos, `C10` Inteligencia Artificial). Evalúan planes de estudio, retención, reprobación y actualización SEAES.
  - **Coordinaciones de Área:** Investigación (`C11`), Posgrado (`C12`), Difusión Cultural (`C13`), Extensión y Vinculación (`C14`) y Planeación (`C15`).

### C. Dimensión del Entorno Externo e Institucional (Subagente Extractor)
- Procesamiento automatizado de los documentos en `G:\Para EA\Documentos de apoyo` consolidado en [`evidencia_documentos_apoyo.json`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/evidencia_documentos_apoyo.json):
  - **Estatal:** Plan de Desarrollo del Estado de México 2023–2029 (PDEM). Retos regionales, industria mexiquense y cobertura.
  - **Nacional:** Plan Nacional de Desarrollo 2025–2030 (PND), Ley General de Educación Superior (LGES, criterios SEAES), Ley General de Educación, Visión ANUIES 2030.
  - **Internacional:** Agenda 2030 (ODS 4 Educación, ODS 5 Igualdad, ODS 8 Trabajo decente, ODS 9 Industria e Innovación), contribución de IES a ODS.
  - **UAEMEX:** Plan General de Desarrollo 2021–2033 (PGD) y PRDI 2025–2029.

### D. Mapas Curriculares de la Facultad de Ingeniería
- Archivo estructurado exclusivo: [`mapacurricularFI.json`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/mapacurricularFI.json).
- Contiene estrictamente el nombre de las 6 licenciaturas de la FI, con sus asignaturas y el semestre correspondiente, sin metadatos sobrantes.

---

## 3. Especificaciones Gráficas y Etiquetas para Renderizado a Word

### A. Estilo Visual y Paleta Institucional
Las gráficas se generarán en la carpeta [`D:\Proyectos\plataformaFI\Plan\AcadA\graficas\`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/graficas/) mediante scripts de Python (`matplotlib`), replicando la identidad cromática del sitio web (`plan.css` y `plan.js`):
- **Verde Institucional Principal:** `#526855` / `#355a42`
- **Verde Oscuro de Énfasis:** `#384033` / `#283a2d`
- **Oro Universitario:** `#CA9E52` / `#b78a3b`
- **Gris Claro de Fondo / Grillas:** `#F2F2F2` / `#dce3da` / `#d9dfd8`
- **Tinta / Texto de Ejes y Títulos:** `#243026` / `#334539`
- **Contraste / Alerta / Rezago:** `#8b5454` (terracota / vino)
- **Tipografía:** DM Sans / Inter / Arial, limpia, sobria, sin saturación.

### B. Formato de Etiquetas de Inserción en los Textos de Diagnóstico
Para que un script posterior pueda procesar los archivos de diagnóstico y renderizar automáticamente el texto e incrustar las figuras en un documento de Word (`.docx`), se define un estándar de etiquetas unívocas, delimitadas y no ambiguas:

```text
[[GRAFICA: graficas/nombre_archivo.png | PIE: Gráfica X. Título descriptivo de la gráfica con periodos. Fuente: UAEMex. Agendas estadísticas 2016-2025.]]
```

---

## 4. Checklist Maestro de Ejecución

### Fase 1: Consolidación de Contexto y Herramientas
- [x] Extracción en JSON de los puntos de la Guía SDFI (Anexo 1) para proyectos 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18 y 19.
- [x] Generación del archivo estructurado [`puntos_proyectos_desarrollo.json`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/puntos_proyectos_desarrollo.json).
- [x] Elaboración de notas metodológicas y cuaderno de trabajo [`notas_referencia_diagnosticos.md`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/notas_referencia_diagnosticos.md).
- [x] Mapeo e inspección de las encuestas estudiantiles y sectoriales (`instrumentos.json`).
- [x] Creación del script del Subagente Extractor de Documentos de Apoyo ([`subagente_extractor_apoyo.py`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/subagente_extractor_apoyo.py)).
- [x] Creación de la carpeta [`D:\Proyectos\plataformaFI\Plan\AcadA\graficas\`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/graficas/).
- [x] Generación de [`mapacurricularFI.json`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/mapacurricularFI.json) con únicamente carreras, asignaturas y semestres.

### Fase 2: Extracción Documental del Entorno
- [x] Ejecución del Subagente Extractor sobre `G:\Para EA\Documentos de apoyo` (PDEM, PND, LGES, LGE, ODS, ANUIES, PGD).
- [x] Generación y verificación del archivo [`evidencia_documentos_apoyo.json`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/evidencia_documentos_apoyo.json) (365 extractos catalogados para los 12 proyectos).
- [x] Integración de los extractos externos en el repositorio de trabajo de `AcadA`.

### Fase 3: Integración de Evidencia Cualitativa y Cruces
- [x] Sistematización de hallazgos clave de encuestas de estudiantes (`C01`, `C16`, `C17`), docentes (`C02`, `C03`) y coordinaciones (`C05`–`C15`).
- [x] Articulación de los factores FODA cuantitativos y cualitativos por cada proyecto.
- [x] Especificación de gráficas requeridas con etiquetas estandarizadas `[[GRAFICA: ... | PIE: ...]]`.

### Fase 4: Redacción Exhaustiva de Diagnósticos Individuales (`Diagnosticos/`)
- [x] **Proyecto 02:** [`diagnostico_proy_02_estudios_profesionales.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_02_estudios_profesionales.txt) (Estudiantado, retención, SEAES, CACEI, encuestas C01/C16/C05–C10, series 4001–4024).
- [x] **Proyecto 03:** [`diagnostico_proy_03_estudios_avanzados.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_03_estudios_avanzados.txt) (Posgrados SNP, graduación, investigación, encuestas C03/C12/C17, series 4001–4008/4020).
- [x] **Proyecto 04:** [`diagnostico_proy_04_investigacion_calidad.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_04_investigacion_calidad.txt) (Cuerpos académicos, SNII, LGAC, ciencia abierta, series 4025–4028, encuesta C11).
- [x] **Proyecto 05:** [`diagnostico_proy_05_innovacion_transferencia.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_05_innovacion_transferencia.txt) (Desarrollo tecnológico, patentes, vinculación industrial, pentahélice, infraestructura 4037).
- [x] **Proyecto 06:** [`diagnostico_proy_06_difusion_ciencia_abierta.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_06_difusion_ciencia_abierta.txt) (Revistas científicas, libros abiertos, repositorio, bienes públicos digitales).
- [x] **Proyecto 09:** [`diagnostico_proy_09_formacion_docente.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_09_formacion_docente.txt) (Planta docente, PRODEP, PROED, tutoría, habilitación, series 4023–4032, encuestas C02/C03).
- [x] **Proyecto 10:** [`diagnostico_proy_10_trabajadores_universitarios.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_10_trabajadores_universitarios.txt) (Personal administrativo, capacitación, dignificación laboral, series 4031–4034, encuesta C04).
- [x] **Proyecto 11:** [`diagnostico_proy_11_gestion_administrativa.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_11_gestion_administrativa.txt) (Infraestructura 4037, mantenimiento, certificación ISO/ATR, manuales, transporte Potrobús).
- [x] **Proyecto 12:** [`diagnostico_proy_12_fortalecimiento_tecnologico.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_12_fortalecimiento_tecnologico.txt) (TIC, conectividad, modernización digital, ciberseguridad, infraestructura de cómputo).
- [x] **Proyecto 13:** [`diagnostico_proy_13_gobernanza.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_13_gobernanza.txt) (Órganos colegiados, Consejo de Gobierno, protección civil, identidad, cercanía estudiantil).
- [x] **Proyecto 18:** [`diagnostico_proy_18_diversidad_social.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_18_diversidad_social.txt) (Género, becas 4020, inclusión, perspectiva de cuidados, no discriminación, equidad).
- [x] **Proyecto 19:** [`diagnostico_proy_19_cultura_de_paz.txt`](file:///d:/Proyectos/plataformaFI/Plan/AcadA/Diagnosticos/diagnostico_proy_19_cultura_de_paz.txt) (Convivencia pacífica, mediación, círculos de paz, prevención de violencias, transversalidad).

### Fase 5: Tareas Pendientes / Renderizado
- [ ] Desarrollar script de generación de las imágenes `.png` en la carpeta `graficas/` con la paleta de color del sitio web.
- [ ] *(Tarea pendiente)* Desarrollo del script de renderizado a documento Word (`.docx`) procesando el texto de los diagnósticos y las etiquetas de figuras `[[GRAFICA: ... | PIE: ...]]`.
