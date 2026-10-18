import json
import sys
sys.stdout.reconfigure(encoding='utf-8')

with open(r"D:\Proyectos\plataformaFI\Plan\instrumentos.json", "r", encoding="utf-8") as f:
    d = json.load(f)

target_projects = [2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 18, 19]
questions_by_project = {p: [] for p in target_projects}

for enc in d.get("encuestas", []):
    enc_id = enc["id"]
    enc_tit = enc["titulo"]
    for q in enc.get("preguntas", []):
        q_proys = q.get("proyectos", [])
        for p in q_proys:
            if p in target_projects:
                questions_by_project[p].append({
                    "encuesta_id": enc_id,
                    "encuesta_titulo": enc_tit,
                    "pregunta_id": q.get("id"),
                    "texto": q.get("texto"),
                    "tipo": q.get("tipo"),
                    "opciones": q.get("opciones", [])
                })

for p in target_projects:
    qs = questions_by_project[p]
    print(f"\n================ PROYECTO {p} ({len(qs)} preguntas asociadas) ================")
    # group by survey
    by_enc = {}
    for q in qs:
        by_enc.setdefault(f"{q['encuesta_id']} - {q['encuesta_titulo']}", []).append(q)
    for enc_name, items in by_enc.items():
        print(f"  * {enc_name} ({len(items)} preguntas):")
        for it in items[:3]:
            print(f"      [{it['pregunta_id']}] {it['texto'][:90]}...")
