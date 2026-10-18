# -*- coding: utf-8 -*-
"""
Agente Generador del Mapa Curricular de la Facultad de Ingeniería
-----------------------------------------------------------------
Extrae y consolida exclusivamente:
- Los nombres de las carreras (Licenciaturas de la Facultad de Ingeniería)
- Sus asignaturas correspondientes
- El semestre al que corresponden

Formato estricto: sin horas, créditos, códigos ni metadatos adicionales.
Destino: D:\\Proyectos\\plataformaFI\\Plan\\AcadA\\mapacurricularFI.json
"""
import json
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')

ORIGEN_MAPAS = r"D:\Proyectos\DesarrolloFI\data\mapas_curriculares.json"
DESTINO_JSON = r"D:\Proyectos\plataformaFI\Plan\AcadA\mapacurricularFI.json"
DESTINO_PLAN = r"D:\Proyectos\plataformaFI\Plan\mapacurricularFI.json"

def main():
    if not os.path.exists(ORIGEN_MAPAS):
        print(f"Error: no se localizó el archivo fuente en {ORIGEN_MAPAS}")
        return

    with open(ORIGEN_MAPAS, "r", encoding="utf-8") as f:
        data = json.load(f)

    resultado = {}

    for plan in data.get("planes_estudio", []):
        carrera = plan.get("nombre_programa")
        if not carrera:
            continue

        asignaturas_lista = []
        nombres_registrados = set()

        # 1. Asignaturas de los periodos/semestres
        for per in plan.get("periodos", []):
            semestre = per.get("periodo")
            for asig in per.get("asignaturas", []):
                nombre_asig = asig.get("nombre", "").strip()
                # Omitir etiquetas genéricas de optativas cuando existen optativas específicas
                if nombre_asig.lower().startswith("optativa"):
                    continue
                if nombre_asig and nombre_asig not in nombres_registrados:
                    asignaturas_lista.append({
                        "semestre": semestre,
                        "asignatura": nombre_asig
                    })
                    nombres_registrados.add(nombre_asig)

        # 2. Asignaturas optativas con sus nombres reales asignadas al semestre correspondiente
        # Si la optativa no tiene periodo sugerido explícito, se asigna al semestre de optativas de la carrera
        periodos_optativas = [
            per.get("periodo") for per in plan.get("periodos", [])
            if any("optativa" in a.get("nombre", "").lower() for a in per.get("asignaturas", []))
        ]
        semestre_optativa_default = periodos_optativas[0] if periodos_optativas else plan.get("duracion_periodos", 10)

        for opt in plan.get("asignaturas_optativas", []):
            nombre_opt = opt.get("nombre", "").strip()
            semestre_sug = opt.get("periodo_sugerido") or semestre_optativa_default
            if nombre_opt and nombre_opt not in nombres_registrados:
                asignaturas_lista.append({
                    "semestre": semestre_sug,
                    "asignatura": nombre_opt
                })
                nombres_registrados.add(nombre_opt)

        # Ordenar por semestre y luego por nombre
        asignaturas_lista.sort(key=lambda x: (x["semestre"], x["asignatura"]))
        resultado[carrera] = asignaturas_lista

    # Guardar en AcadA
    with open(DESTINO_JSON, "w", encoding="utf-8") as f_out:
        json.dump(resultado, f_out, indent=2, ensure_ascii=False)
    print(f"Archivo guardado exitosamente en: {DESTINO_JSON}")

    # Guardar copia en Plan/
    with open(DESTINO_PLAN, "w", encoding="utf-8") as f_out2:
        json.dump(resultado, f_out2, indent=2, ensure_ascii=False)
    print(f"Copia guardada en: {DESTINO_PLAN}")

    # Resumen
    for carrera, asigs in resultado.items():
        print(f"- {carrera}: {len(asigs)} asignaturas registradas.")

if __name__ == "__main__":
    main()
