"""Añade preguntas de participación y contacto voluntario sin renumerar las existentes."""
import json
from pathlib import Path

path = Path(__file__).resolve().parents[1] / 'Plan' / 'instrumentos.json'
data = json.loads(path.read_text(encoding='utf-8'))
curricula_path = Path(r'D:\Proyectos\DesarrolloFI\data\mapas_curriculares.json')
curricula = json.loads(curricula_path.read_text(encoding='utf-8'))
subjects = {}
for plan in curricula['planes_estudio']:
    names = []
    for period in plan['periodos']:
        for subject in period.get('asignaturas', []):
            name = subject.get('nombre', '').strip()
            if name and name not in names:
                names.append(name)
    subjects[plan['id_plan']] = names
by_id = {survey['id']: survey for survey in data['encuestas']}
student = by_id['C01']
for question in student['preguntas']:
    if question['id'] == 'C01-P02':
        question['tipo'] = 'ranking_asignaturas'
        question['texto'] = 'Selecciona hasta cinco asignaturas de tu licenciatura que te han resultado más difíciles y ordénalas de mayor a menor dificultad.'
        question['obligatoria'] = True
        question.pop('opciones', None)
        question['opciones_por_segmento'] = subjects
        question['max_selecciones'] = 5
        question['fuente_apa'] = ('Facultad de Ingeniería, Universidad Autónoma del Estado de México. (2026). '
                                  'Mapas curriculares de las licenciaturas [Conjunto de datos institucional].')
student['cruces'] = [c for c in student['cruces'] if c['fila'] != 'C01-P02' and c['columna'] != 'C01-P02']
for sid in ('C01', 'C02', 'C03', 'C04', 'C16', 'C17'):
    survey = by_id[sid]
    survey['anonima'] = False
    survey['anonimato'] = 'Sin nombre obligatorio; correo opcional para invitación de seguimiento.'
    survey['preguntas'] = [q for q in survey['preguntas'] if not q.get('ampliacion_2026_09_22')]
    survey['cruces'] = [c for c in survey['cruces'] if c.get('titulo') != 'Participación en capítulos y percepción de inclusión']
    for q in survey['preguntas']:
        if q['tipo'] == 'abierta' and 'propuestas concretas' in q['texto']:
            q['texto'] = 'Escribe propuestas concretas de mejora para atender esas necesidades. Si quieres participar en una reunión para comentarlas, puedes proporcionar tu correo en el campo opcional final.'
    next_number = max(int(q['id'].split('P')[-1]) for q in survey['preguntas']) + 1
    def add(kind, wording, projects, options=None):
        nonlocal_number = len(survey['preguntas']) - next_number + 1
        q = {'id': f'{sid}-P{next_number + nonlocal_number:02d}', 'tipo': kind,
             'texto': wording, 'proyectos': projects, 'ampliacion_2026_09_22': True}
        if options:
            q['opciones'] = options
        survey['preguntas'].append(q)
    if sid == 'C01':
        add('opcion', '¿Perteneces a un capítulo estudiantil de la Facultad?', [2, 7, 8, 13, 18, 20], ['Sí', 'No'])
        add('abierta', 'Si respondiste sí, especifica el nombre del capítulo estudiantil.', [2, 7, 8, 13, 18, 20])
        add('abierta', 'Si perteneces a un capítulo, escribe exactamente tres necesidades en orden de importancia (la más importante primero), separadas por punto y coma.', [2, 7, 8, 13, 18, 20])
        survey['cruces'].append({'fila': 'C01-P11', 'columna': 'C01-P08',
                                  'titulo': 'Participación en capítulos y percepción de inclusión'})
    add('correo', 'Correo electrónico opcional. Escríbelo sólo si deseas que te inviten a una reunión para profundizar en tu propuesta; tu respuesta dejará de ser plenamente anónima para quien gestione esa invitación.',
        [2, 3, 7, 8, 9, 10, 13, 15, 18, 19, 20, 21])

data['version'] = '2026-09-22-ampliacion'
data['nota_privacidad'] = ('No se solicita nombre ni dirección IP en el registro de encuestas. El correo es opcional en C01–C04, C16 y C17 y se usará sólo para invitar a una reunión de seguimiento de propuestas; si se proporciona, esa respuesta podrá vincularse con la persona por el equipo responsable. El servidor puede conservar registros técnicos. El cargo de una coordinación única puede ser reconocible por su función. Las respuestas abiertas y los correos no se publican. Una cookie limita duplicados por navegador, pero no verifica pertenencia ni impide respuestas desde otro dispositivo.')
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
