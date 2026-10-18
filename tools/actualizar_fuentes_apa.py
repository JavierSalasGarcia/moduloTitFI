"""Normaliza citas y referencias APA de las fuentes utilizadas por el módulo."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLAN = ROOT / 'Plan'

for path in PLAN.glob('4*.json'):
    data = json.loads(path.read_text(encoding='utf-8'))
    data['cita_apa'] = '(Sistema de Planeación y Desarrollo Institucional [SPDI], 2026b)'
    data['referencia_apa'] = ("Sistema de Planeación y Desarrollo Institucional. (2026b). Tableros de variables e indicadores "
                              "[Conjunto de tableros]. Universidad Autónoma del Estado de México. "
                              "https://spdi2.uaemex.mx/tableros/public/index.php/mosaico/uaemex")
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

path = PLAN / 'fuentes_externas.json'
data = json.loads(path.read_text(encoding='utf-8'))
apa = {
    'EXT-01': ('(Gobierno del Estado de México, 2024)', 'Gobierno del Estado de México. (2024). Plan de Desarrollo del Estado de México 2023–2029.'),
    'EXT-02': ('(Gobierno del Estado de México, 2024)', 'Gobierno del Estado de México. (2024). Plan de Desarrollo del Estado de México 2023–2029.'),
    'EXT-03': ('(Comisión del Agua del Estado de México, 2024)', 'Comisión del Agua del Estado de México. (2024). Programa Hídrico Integral del Estado de México 2024–2029.'),
    'EXT-04': ('(Universidad Autónoma del Estado de México, 2026)', 'Universidad Autónoma del Estado de México. (2026). Anexo estadístico del Primer Informe 2025.'),
    'EXT-05': ('(Consejo de Acreditación de la Enseñanza de la Ingeniería, 2025)', 'Consejo de Acreditación de la Enseñanza de la Ingeniería. (2025). Marco de referencia 2025 para la acreditación de programas de ingeniería.'),
    'EXT-06': ('(Secretaría de Educación Pública, s. f.)', 'Secretaría de Educación Pública. (s. f.). Marco general del Sistema de Evaluación y Acreditación de la Educación Superior.'),
    'EXT-07': ('(Universidad Autónoma del Estado de México, 2025)', 'Universidad Autónoma del Estado de México. (2025). Plan Rector de Desarrollo Institucional 2025–2029.'),
}
for factor in data['factores']:
    citation, reference = apa[factor['id']]
    factor['cita_apa'] = citation
    factor['referencia_apa'] = reference + ' ' + factor['url']
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Referencias APA actualizadas en fuentes estadísticas y externas.')
