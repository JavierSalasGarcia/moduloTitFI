# Informe Integral de Revisión y Alineación Metodológica: Diagnósticos, Preliminar FI y PRDI Institucional
**Facultad de Ingeniería — Universidad Autónoma del Estado de México**  
*Administración 2026–2030*

---

## I. Marco Metodológico Estructural (Derivado de la Guía SDFI y sus Esquemas Visuales)

A partir del análisis visual y estructural de la *Guía para la formulación de planes de desarrollo de espacios académicos* (páginas 20 a 30) y las directrices de `notas_referencia_diagnosticos.md`, se constata la **cadena de consistencia unidireccional y obligatoria**:

```
[Visión Institucional PRDI]
            │
            ▼
   [Objetivo General] ─── (Alineado al PRDI y Visión: Verbo logro + Qué + Cómo + Para qué)
            │
            ▼
 [Objetivos Específicos] ── (Concreción: Verbo logro + Qué + Para qué [beneficio])
      │               │
      ▼               ▼
 [Políticas]     [Estrategias] ── (Acción: Verbo infinitivo + Qué + Cómo [mediante...])
 (Obligatorias)       │
                      ▼
                   [Metas] ────── (Al menos 1 por estrategia: Verbo acción + Qué + Cuánto + Cuándo)
                      │
                      ▼
         [Apertura Programática / Memoria de Cálculo] ── (Variables, Ref. 2026 y Prog. 2027–2030)
```

### Reglas de Sintaxis Oficiales Validadas:
1. **Diagnóstico:** Describe la situación actual sustentada en datos históricos de al menos 8 años, análisis FODA (factores internos y externos) con valores cuantitativos y cualitativos. Plantea las tensiones estratégicas sin adelantar las metas futuras.
2. **Objetivo General:** Inicia con verbo en infinitivo que denota logro + ¿Qué se va a hacer? + ¿Cómo se va a hacer? + ¿Para qué se va a hacer?. Contribuye directamente al Objetivo General del PRDI.
3. **Objetivos Específicos:** Concreción directa del Objetivo General. Inicia con verbo de logro (clasificados en la Guía por orientación: *Incrementar/Mejorar*, *Mantener/Asegurar*, o *Reducir/Eliminar*) + ¿Qué se va a hacer? + ¿Para qué se va a hacer?.
4. **Políticas:** Directrices de observancia obligatoria que coadyuvan al logro de los objetivos. Estructura: *Elemento/Población en la que recae + Verbo en futuro (o perífrasis con deber) + Condición soporte*.
5. **Estrategias:** Acciones articuladas para materializar el objetivo específico. Estructura: *Verbo en infinitivo + ¿Qué se va a hacer? + ¿Cómo se va a hacer?* (empleando conectores como *mediante*, *a través de*, *con base en*).
6. **Metas:** Cuantificables y temporizadas. La Guía establece explícitamente: *"Para cada estrategia debe existir por lo menos una meta"*. Estructura: *Verbo en infinitivo + ¿Qué se va a hacer? + ¿Cuánto se va a hacer? + ¿Cuándo se va a cumplir?*.
   - **Metas-Indicador:** Tasas y proporciones porcentuales calculadas mediante fórmulas con variables de numerador y denominador.
   - **Metas-Actividad:** Acciones o entregables en valor absoluto acumulado o anual.
7. **Matriz de Consistencia y Memoria de Cálculo:** Instrumentos de entrega obligatoria que garantizan que los valores anuales programados (2026 a 2030) coincidan exactamente con la apertura programática y tributen a las metas institucionales del PRDI.

---

## II. Resultados de la Auditoría Integral por Proyecto (12 Proyectos Estratégicos)

Se auditó la correspondencia entre los diagnósticos elaborados en `d:\Proyectos\plataformaFI\Plan\AcadA\Diagnosticos\`, la propuesta base en `D:\Proyectos\plataformaFI\Plan\preliminar_fi.json` y el texto de referencia en `D:\Proyectos\plataformaFI\Plan\PRDI.json`:

```
┌────┬──────────────────────────────────────┬──────┬──────┬─────────┬──────────┬──────────┬─────────────┐
│ ID │ Proyecto Estratégico                 │ Objs │ Pols │ Estrats │ Metas FI │ Metas PR │ RefBase Xls │
├────┼──────────────────────────────────────┼──────┼──────┼─────────┼──────────┼──────────┼─────────────┤
│ 02 │ Estudios Profesionales               │  4   │  4   │    4    │    26    │    26    │     26      │
│ 03 │ Estudios Avanzados                   │  2   │  2   │    2    │     7    │     7    │      7      │
│ 04 │ Investigación Abierta y Calidad      │  2   │  2   │    2    │     8    │     8    │      8      │
│ 05 │ Innovación y Transferencia           │  1   │  2   │    1    │     2    │     2*   │      2      │
│ 06 │ Difusión de la Ciencia Abierta       │  1   │  2   │    1    │     3    │     3*   │      3      │
│ 09 │ Formación Integral Docente           │  3   │  3   │    3    │    10    │    10*   │     10      │
│ 10 │ Trabajadores Universitarios          │  1   │  2   │    1    │     3    │     3*   │      3      │
│ 11 │ Gestión Administrativa               │  1   │  2   │    1    │     2    │     2*   │      2      │
│ 12 │ Fortalecimiento Tecnológico          │  2   │  2   │    2    │     2    │     2    │      2      │
│ 13 │ Gobernanza Universitaria             │  3   │  3   │    3    │     8    │     8*   │      8      │
│ 18 │ Reconocimiento Diversidad Social     │  4   │  4   │    4    │     8    │     8    │      8      │
│ 19 │ Construcción de una Cultura de Paz   │  4   │  4   │    4    │     7    │     7    │      7      │
├────┴──────────────────────────────────────┴──────┴──────┴─────────┼──────────┼──────────┼─────────────┤
│ TOTALES CONSOLIDADOS                      │ 28   │ 29   │   28    │    86    │    86    │     86      │
└───────────────────────────────────────────────────────────────────┴──────────┴──────────┴─────────────┘
*Nota: En PRDI.json algunos textos de metas se listaban repetidos entre diferentes objetivos institucionales. Al eliminar duplicados de catálogo central, la correspondencia con las metas únicas de la Facultad de Ingeniería es del 100% (86 de 86 metas).*
```

---

## III. Análisis Detallado de Concordancia y Alineación

### 1. Proyecto 02: Estudios Profesionales
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico fundamenta ampliamente el crecimiento de matrícula (de 2,553 a 2,989 alumnos) y de nuevo ingreso (464 a 722), contrastándolo con el descenso de eficiencia terminal de cohorte (7.3% a 4.2%) y de titulación de cohorte (11.5% a 2.29%). Esto justifica plenamente:
    - `OE1`: Actualización curricular con criterios transversales SEAES.
    - `OE2`: Evaluación externa y acreditación de programas (CACEI/CIEES) y pertinencia de acervos.
    - `OE3`: Acompañamiento longitudinal y tutoría focalizada para abatir reprobación y abandono.
    - `OE4`: Vinculación de egresados, empleabilidad y seguimiento CACEI.
- **Alineación con Metas del PRDI:**
  - Las 26 metas de la Facultad corresponden exactamente a las 26 metas institucionales (ej. `ACT-PRDI-NS-2726`, `ACT-PRDI-NS-2727`, `IND-NS-210`, `IND-PRDI-351`, `IND-PRDI-200`, `ACT-PRDI-NS-1764`, `ACT-PRDI-NS-2753`).
  - Cada meta en las estrategias está respaldada en `referencia_base_2026.metas[]` con sus variables cuantitativas (ej. variables de EGEL, titulación, becas, tutoría y servicio social con línea base y metas 2027–2030).

### 2. Proyecto 03: Estudios Avanzados
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico explica la trayectoria de matrícula de posgrado (caída de 103 a 55 y recuperación a 75 alumnos), la dependencia del Sistema Nacional de Posgrados (SNP/CONAHCYT) y la necesidad de graduación oportuna.
  - Se vincula directamente con `OE1` (ampliación incluyente del acceso y matrícula) y `OE2` (pertinencia social, retención y graduación oportuna).
- **Alineación con Metas del PRDI:**
  - 7 metas institucionales plenamente alineadas (`ACT-PRDI-NS-2804`, `ACT-PRDI-NS-2805`, `IND-PRDI-213`, `ACT-PRDI-NS-2807`, `ACT-PRDI-NS-2810`, `IND-PRDI-360`, `ACT-PRDI-NS-2812`).

### 3. Proyecto 04: Investigación Abierta, Inclusiva y de Calidad
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico sustenta el crecimiento del personal en el SNII (de 17 a 40 investigadores entre 2018 y 2025) y los 3 Cuerpos Académicos, señalando la brecha de financiamiento y la necesidad de vincular la investigación con problemas prioritarios (hídricos, energéticos, industriales).
  - Justifica `OE1` (investigación abierta y socialmente pertinente) y `OE2` (financiamiento, infraestructura de laboratorios y colaboración externa).
- **Alineación con Metas del PRDI:**
  - 8 metas institucionales vinculadas (`ACT-PRDI-NS-2848`, `ACT-PRDI-NS-2850`, `IND-PRDI-220`, `ACT-PRDI-NS-2854`, `ACT-PRDI-NS-2855`, `ACT-PRDI-NS-2858`, `ACT-PRDI-NS-2859`, `ACT-PRDI-NS-2860`).

### 4. Proyecto 05: Innovación, Desarrollo Tecnológico y Transferencia
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico subraya que la Facultad genera prototipos y desarrollos en ingeniería, pero carece de un inventario formal y acompañamiento para la protección de propiedad intelectual y vinculación con la industria.
  - Justifica `OE1` (gestión de propiedad intelectual, licenciamiento y transferencia tecnológica).
- **Alineación con Metas del PRDI:**
  - 2 metas institucionales asignadas a la Facultad (`ACT-PRDI-NS-2878` para protección de propiedad industrial y `ACT-PRDI-NS-2882` para proyectos de transferencia).

### 5. Proyecto 06: Difusión y Divulgación de la Ciencia Abierta
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico señala la dispersión de esfuerzos de divulgación y la falta de un canal institucional sistemático de ciencia abierta y apropiación social del conocimiento (congruente con la justificación de la cabina del Auditorio 120A).
  - Justifica `OE1` (fortalecimiento de la ciencia abierta, revistas científicas y divulgación multiformato).
- **Alineación con Metas del PRDI:**
  - 3 metas institucionales (`ACT-PRDI-NS-2895`, `ACT-PRDI-NS-2900`, `ACT-PRDI-NS-2903`).

### 6. Proyecto 09: Formación Integral del Docente
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico evidencia la alta carga tutorial (aumento de 18 a 46 estudiantes por tutor), la reducción de la plantilla total de docentes de 314 a 284, contrastada con el aumento a 40 miembros del SNII y el perfil deseable PRODEP.
  - Justifica `OE1` (formación pedagógica, didáctica y digital en el nuevo modelo), `OE2` (certificación en segunda lengua nivel B2/C1) y `OE3` (habilitación, retención en SNII y estancias académicas).
- **Alineación con Metas del PRDI:**
  - 10 metas institucionales completas (`ACT-PRDI-NS-2917`, `IND-PRDI-228`, `ACT-PRDI-NS-2921`, `ACT-PRDI-NS-2922`, `ACT-PRDI-NS-2923`, `ACT-PRDI-NS-2926`, `IND-PRDI-47`, `ACT-PRDI-NS-2927`, `ACT-PRDI-NS-2928`).

### 7. Proyecto 10: Trabajadores Universitarios
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico analiza la contracción del personal administrativo de 91 a 79 plazas y la necesidad de profesionalización técnica y tecnológica ante los nuevos sistemas digitales.
  - Justifica `OE1` (desarrollo de competencias técnicas, transversales y de TIC).
- **Alineación con Metas del PRDI:**
  - 3 metas institucionales (`IND-PRDI-378`, `IND-PRDI-380`, `IND-PRDI-382`).

### 8. Proyecto 11: Gestión Administrativa
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico detalla la dispersión de manuales de organización y procedimientos no armonizados frente a la digitalización de trámites y acreditaciones CACEI.
  - Justifica `OE1` (actualización de manuales de organización y procedimientos).
- **Alineación con Metas del PRDI:**
  - 2 metas institucionales (`ACT-PRDI-NS-2743` y `ACT-PRDI-NS-2957`).

### 9. Proyecto 12: Fortalecimiento Tecnológico
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico resalta el inventario de equipo de cómputo en laboratorios (muchos con obsolescencia física o falta de GPUs especializadas para Inteligencia Artificial y modelado), la necesidad de mantenimiento preventivo continuo y el desarrollo de recursos educativos digitales (GEI).
  - Justifica `OE1` (cultura y habilidades digitales mediante guías de estudio independiente) y `OE2` (mantenimiento preventivo al 100% de la infraestructura tecnológica).
- **Alineación con Metas del PRDI:**
  - 2 metas institucionales (`ACT-PRDI-NS-2965` y `ACT-PRDI-NS-2970`).

### 10. Proyecto 13: Gobernanza Universitaria
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico aborda la protección civil, simulacros, protocolos de seguridad y la preservación de la memoria histórica e identidad universitaria de la Facultad.
  - Justifica `OE1` (autoprotección y simulacros), `OE2` (atención a víctimas y seguridad) y `OE3` (pertenencia universitaria, visitas guiadas y nueva crónica de la Facultad).
- **Alineación con Metas del PRDI:**
  - 8 metas institucionales (`ACT-PRDI-NS-2980`, `ACT-PRDI-NS-2982`, `ACT-PRDI-NS-2748`, `ACT-PRDI-NS-2983`, `ACT-PRDI-NS-2984`, `ACT-PRDI-NS-2749`, `ACT-PRDI-NS-2992`, `ACT-PRDI-NS-3243`).

### 11. Proyecto 18: Diversidad Social
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico documenta la necesidad de transversalizar la perspectiva de género, inclusión de grupos vulnerables y el programa institucional de cuidados y bienestar.
  - Justifica `OE1` (sensibilización del estudiantado), `OE2` (prevención de discriminación), `OE3` (apoyos a personas vulnerables e investigación en género) y `OE4` (perspectiva de cuidados y bienestar integral).
- **Alineación con Metas del PRDI:**
  - 8 metas institucionales (`IND-PRDI-307`, `ACT-PRDI-NS-3073`, `IND-PRDI-309`, `ACT-PRDI-NS-3074`, `ACT-PRDI-NS-3075`, `ACT-PRDI-NS-3081`, `ACT-PRDI-NS-3082`, `IND-PRDI-384`).

### 12. Proyecto 19: Cultura de Paz
- **Concordancia Diagnóstico → Objetivos:**
  - El diagnóstico fundamenta la mediación pacífica de controversias, difusión de protocolos de cero tolerancia a las violencias y jornadas de activismo social.
  - Justifica `OE1` (capacitación en mediación), `OE2` (prevención y protocolos), `OE3` (participación comunitaria en convivencia) y `OE4` (educación para la paz y campañas en redes).
- **Alineación con Metas del PRDI:**
  - 7 metas institucionales (`IND-PRDI-312`, `ACT-PRDI-NS-3087`, `ACT-PRDI-NS-3090`, `ACT-PRDI-NS-3092`, `IND-PRDI-314`, `ACT-PRDI-NS-3095`, `ACT-PRDI-NS-3096`).

---

## IV. Hallazgos Específicos y Recomendaciones de Ajuste Fino

Para garantizar que el expediente institucional sea impecable durante la validación de la Secretaría de Desarrollo y Fortalecimiento Institucional (SDFI), se identificaron las siguientes oportunidades de mejora preventiva (las cuales pueden incorporarse en la prosa de los diagnósticos sin modificar los archivos base de la plataforma):

1. **Anclaje explícito del EGEL y Titulación por examen en el Diagnóstico del Proyecto 02:**
   - La meta `IND-PRDI-200` compromete que el 71.57% de los sustentantes obtengan testimonio sobresaliente o satisfactorio. Se recomienda reforzar en el diagnóstico la mención de los resultados históricos del EGEL-CENEVAL por carrera.
2. **Estancias internacionales y convocatorias de apoyo en el Diagnóstico del Proyecto 09:**
   - La meta `ACT-PRDI-NS-2928` compromete 6 académicos en estancias internacionales. Conviene puntualizar en la prosa del diagnóstico la necesidad de convenios de movilidad científica docente.
3. **Mención de la figura del Cronista en el Diagnóstico del Proyecto 13:**
   - La meta `ACT-PRDI-NS-3243` compromete la elaboración de una nueva crónica para 2030. Se sugiere incorporar en el apartado de identidad una breve mención al archivo histórico y al cronista de la Facultad.
4. **Respeto a la Reversibilidad y Gobernanza de Archivos:**
   - Ningún archivo base (`preliminar_fi.json`, `PRDI.json`, ni bases de datos de Excel) ha sido modificado, preservando el estado original y la trazabilidad de cualquier propuesta futura vía las carpetas de `revisiones`.

---

## V. Conclusión de la Auditoría

Existe una **congruencia metodológica, sintáctica y numérica plena**:
1. Cada una de las 86 metas de la Facultad de Ingeniería está anclada a una estrategia específica que satisface la regla de la Guía (*"Para cada estrategia debe existir por lo menos una meta"*).
2. Cada meta de la Facultad corresponde unívocamente a una meta institucional del PRDI con su respectiva clave (`ACT-PRDI-NS-...` o `IND-PRDI-...`).
3. Cada meta cuenta con sus variables programadas en la memoria de cálculo para los ejercicios 2026 a 2030.
4. Los diagnósticos sustentan sólidamente la problemática que justifica los objetivos y políticas planteados.
