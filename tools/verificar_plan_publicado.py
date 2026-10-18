"""Comprueba el módulo público sin imprimir enlaces privados ni datos de acceso."""
import json
import re
from pathlib import Path
import requests

base = 'https://fingenieria.mx/public'
links = json.loads((Path(__file__).resolve().parents[1] / 'Plan' / 'enlaces_encuesta.json').read_text(encoding='utf-8'))
session = requests.Session()
paths = {
    'plan': '/plan?proyecto=2&tab=evidencia',
    'token': '/consulta-fi/' + links['C01'],
    'guess': '/consulta-fi/C01',
    'old': '/plan/encuesta/C01',
    'catalog': '/plan/encuestas/api',
    'login': '/login',
    'api': '/plan/api/2',
    'excel': '/plan/exportar/2/xlsx',
}
responses = {name: session.get(base + path, timeout=30, allow_redirects=False) for name, path in paths.items()}
responses['private'] = session.get('https://fingenieria.mx/Plan/enlaces_encuesta.json', timeout=30, allow_redirects=False)
statuses = {name: response.status_code for name, response in responses.items()}
assert all(statuses[k] == 200 for k in ('plan', 'token', 'login', 'api', 'excel')), statuses
assert statuses['catalog'] == 403 and statuses['private'] == 403, statuses
assert statuses['guess'] == 404 and statuses['old'] == 404, statuses
survey = responses['token'].text
assert all(q in survey for q in ('C01-P02', 'C01-P11', 'C01-P13', 'C01-P14'))
assert 'Consulta y redacción sin iniciar sesión' not in responses['login'].text
projects = {}
for number in range(2, 23):
    response = session.get(f'{base}/plan/api/{number}', timeout=30)
    assert response.status_code == 200, (number, response.status_code)
    projects[number] = response.json()['propuesta']
proposal = projects[2]
assert len(proposal['cruces_indicadores']) == 2
forbidden = (
    'Situación actual y alcance', 'Evidencia cuantitativa y factores internos',
    'Referencia base de planeación', 'Entorno y contraste externo',
    'Consulta pendiente', 'Orientación de mejora', 'tablero consultado',
    'este diagnóstico se refiere al proyecto', 'las cifras deben leerse por periodo y ámbito',
    'memoria de cálculo', 'archivo de Excel',
)
method = re.compile(r'\b(?:mediante|a través de|por medio de|con base en)\b', re.I)
obligation = re.compile(
    r'\b(?:deberá|deberán|tendrá|tendrán|será|serán|entrará|entrarán|garantizará|garantizarán)\b|'
    r'\bse\s+(?:destinará|destinarán|orientará|orientarán|regirá|regirán)\b', re.I)
for number, current in projects.items():
    diagnosis = current['diagnostico']
    assert len(diagnosis.split('\n\n')) >= 2, number
    assert not [text for text in forbidden if text.casefold() in diagnosis.casefold()], number
    assert method.search(current['objetivo_general']) and ' para ' in current['objetivo_general'].lower(), number
    assert len(current['politicas']) >= 2 and all(obligation.search(p['texto']) for p in current['politicas']), number
    for objective in current['objetivos_especificos']:
        assert ' para ' in objective['texto'].lower(), (number, objective['id'])
        for strategy in objective['estrategias']:
            assert method.search(strategy['texto']), (number, strategy['id'])
            assert strategy['metas'], (number, strategy['id'])
            for goal in strategy['metas']:
                text = goal['descripcion']
                assert re.search(r'\b(?:2027|2028|2029|2030)\b', text), (number, strategy['id'], text)
                assert re.search(r'\b\d+(?:[.,]\d+)?\b|\b(?:un|una)\b|%', text, re.I), (number, strategy['id'], text)
print('Producción verificada:', statuses)
print('C01, 21 proyectos, diagnósticos, objetivos, políticas, estrategias, metas y Excel comprobados.')
