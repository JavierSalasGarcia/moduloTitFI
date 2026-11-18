"""Convierte los Excel de profesores y posgrado (carpeta EXCELES) en un JSON para tools/importar_posgrado_academico.php.

Uso:  python tools/exceles_posgrado_a_json.py <carpeta EXCELES> <salida.json>
El JSON contiene datos de personas: guárdalo fuera del repositorio (p. ej. database/datos_privados/).
Requiere openpyxl.
"""
import ast
import datetime
import json
import os
import sys

import openpyxl

# Siglas de los Excel → siglas de la tabla programas.
PROGRAMAS = {'MVT': 'MMT', 'MIIA': 'MIAA', 'MSC': 'MCI', 'MCI': 'MCI', 'MSU': 'MICS', 'DCI': 'DCI', 'DICT': 'DCTBC'}
# Prefijo de la clave de asignatura → programa.
PREFIJOS = [('DCIN', 'DCI'), ('DCT', 'DCTBC'), ('MIAA', 'MIAA'), ('MSC', 'MCI'), ('MSU', 'MICS'), ('MVT', 'MMT')]
NOMBRES_PROGRAMA = {
    'Doctorado en Ciencias, Tecnología Biomédica y Control': 'DCTBC',
    'Doctorado en Ciencias de la Ingeniería': 'DCI',
    'Maestría en Ciencias de la Ingeniería': 'MCI',
    'Maestría en Movilidad y Transporte': 'MMT',
    'Maestría en Inteligencia Artificial Aplicada': 'MIAA',
    'Maestría en Ingeniería de la Cadena de Suministro': 'MICS',
}
MESES = {'enero': 1, 'febrero': 2, 'marzo': 3, 'abril': 4, 'mayo': 5, 'junio': 6, 'julio': 7, 'agosto': 8,
         'septiembre': 9, 'octubre': 10, 'noviembre': 11, 'diciembre': 12, 'dicembre': 12}


def filas(carpeta, archivo):
    hoja = openpyxl.load_workbook(os.path.join(carpeta, archivo), read_only=True, data_only=True).worksheets[0]
    return [r for r in hoja.iter_rows(values_only=True)]


def fecha(valor):
    if isinstance(valor, (datetime.datetime, datetime.date)):
        return valor.strftime('%Y-%m-%d')
    partes = str(valor or '').strip().lower().split()
    if len(partes) == 2 and partes[0] in MESES and partes[1].isdigit():
        return '%s-%02d-01' % (partes[1], MESES[partes[0]])
    return None


def texto(v):
    return str(v).strip() if v is not None and str(v).strip() != '' else None


def principal(carpeta):
    datos = {'periodo': '2026B', 'asignaturas': [], 'materias': [], 'profesores': [], 'coordinadores': [], 'comites': []}
    for r in filas(carpeta, 'Asignaturas.xlsx')[1:]:
        if not r[1]:
            continue
        clave = str(r[1]).strip().upper()
        prog = next((p for pre, p in PREFIJOS if clave.startswith(pre)), None)
        datos['asignaturas'].append({'clave': clave, 'nombre': texto(r[2]), 'programa': prog,
                                     'ht': int(r[3] or 0), 'hp': int(r[4] or 0)})
    for r in filas(carpeta, 'Profesores_Materias_2026B.xlsx')[1:]:
        if not r[0] or not r[1]:
            continue
        claves = list(ast.literal_eval(str(r[1])).values())
        datos['materias'].append({'rfc': str(r[0]).strip().upper(), 'claves': [str(c).strip().upper() for c in claves]})
    pp = filas(carpeta, 'profesores_posgrado_consolidado.xlsx')
    cab = pp[0]
    for r in pp[1:]:
        if not r[3]:
            continue
        datos['profesores'].append({'rfc': str(r[3]).strip().upper(), 'nombre': texto(r[1]),
                                    'programas': [PROGRAMAS[cab[i]] for i in range(4, len(cab)) if cab[i] in PROGRAMAS and r[i]]})
    for r in filas(carpeta, 'Coordinadores 2026B.xlsx')[1:]:
        if not r[1]:
            continue
        datos['coordinadores'].append({'rfc': str(r[0]).strip().upper() if r[0] else None,
                                       'programa': NOMBRES_PROGRAMA.get(str(r[1]).strip()), 'inicio': fecha(r[2])})
    roles = {'D': ('director', 1), 'C': ('codirector', 1), 'T1': ('tutor', 1), 'T2': ('tutor', 2), 'T3': ('tutor', 3)}
    for r in filas(carpeta, 'Comite.xlsx')[1:]:
        if not r[1]:
            continue
        try:
            comite = ast.literal_eval(str(r[3] or '{}'))
        except (ValueError, SyntaxError):
            comite = {}
        miembros = []
        for k, nombre in comite.items():
            if k not in roles or not texto(nombre):
                continue
            rol, orden = roles[k]
            nombre = str(nombre).strip()
            nota = None
            if '(' in nombre:
                nota = nombre[nombre.index('(') + 1:].rstrip(') ').strip() or None
                nombre = nombre[:nombre.index('(')].strip()
            miembros.append({'rol': rol, 'orden': orden, 'nombre': nombre.rstrip('. ').strip(),
                             'externo': bool(nota and 'extern' in nota.lower()), 'nota': nota})
        datos['comites'].append({
            'cuenta': str(int(r[0])) if isinstance(r[0], (int, float)) else texto(r[0]),
            'nombre': texto(r[1]),
            'genero': {'H': 'masculino', 'M': 'femenino'}.get(str(r[2] or '').strip().upper()),
            'programa': PROGRAMAS.get(str(r[4] or '').strip().upper()),
            'titulo': texto(r[5]),
            'miembros': miembros,
        })
    return datos


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    with open(sys.argv[2], 'w', encoding='utf-8') as f:
        json.dump(principal(sys.argv[1]), f, ensure_ascii=False, indent=1)
    print('Escrito', sys.argv[2])
