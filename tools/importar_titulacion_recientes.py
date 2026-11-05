#!/usr/bin/env python3
"""
Genera database/migrations/040_titulacion_recientes.sql (solo tablas) y database/datos_privados/040_datos_recientes.sql (los datos, fuera de git) a partir de los Excel que el departamento de Titulación lleva desde 2015
(carpeta baseDatosTit/recientes):

  CONTROL DE LIBROS DE LICENCIATURAS 19.xlsx    -> tit_historico (actas nuevas; completa vacíos de las existentes) y tit_actas_aplazamiento
  Solicitud EGEL, Aprovechamiento y Creditos    -> tit_solicitudes_historicas (EGEL, aprovechamiento, créditos: 2015-2026)
  RECEPCION DE PROTOCOLO + Oficios 2026/Protocolos -> tit_protocolos_recibidos
  Control de Oficios Titulación + Oficios 2026  -> tit_oficios_control
  ARTICULO 5 2025.xlsx                          -> tit_articulo5

Reglas: nunca se sobrescribe un dato capturado (solo se llenan vacíos); cada tabla tiene una clave natural (huella) y la migración
usa INSERT IGNORE, así que se puede repetir sin duplicar. Las fechas de Excel con día y mes invertidos (2026-05-11 que era 5 de
noviembre) o con año equivocado se corrigen por el orden de las filas; las que no se pueden decidir se cargan tal cual y se marcan
con fecha_revisar = 1.

Uso:  python tools/importar_titulacion_recientes.py [carpeta_excel] [--informe informe.json]
Requiere openpyxl.
"""
import argparse
import datetime as dt
import hashlib
import json
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

import openpyxl

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / 'database' / 'migrations' / '040_titulacion_recientes.sql'          # solo estructura (va a git)
SALIDA_DATOS = RAIZ / 'database' / 'datos_privados' / '040_datos_recientes.sql'      # datos personales (NO va a git: el repositorio es público)
CARPETA = RAIZ / 'baseDatosTit' / 'recientes'
HOY = dt.date(2026, 11, 5)

SIGLAS = {'ICI': 'civil', 'IME': 'mecanica', 'ICO': 'computacion', 'IEL': 'electronica', 'ISES': 'sistemas', 'IIA': 'ia', 'IA': 'ia'}
HOJAS_LIBRO = {'CIVIL': 'civil', 'ING. MECANICA': 'mecanica', 'ING. EN COMPU': 'computacion', 'INGENIERIA EN ELECTRONICA': 'electronica', 'ISES': 'sistemas'}
COLS_MODALIDAD = {'APRO': 'aprovechamiento', 'CEA': 'creditos', 'EAP': 'autoempleo', 'ENS': 'ensayo', 'EXA': 'egel', 'INA': 'articulo',
                  'MEL': 'memoria', 'RAC': 'aplicacion', 'RRI': 'residencia', 'TES': 'tesis'}


# ---------------------------------------------------------------- utilidades de texto
def sin_acentos(s) -> str:
    return unicodedata.normalize('NFKD', str(s or '')).encode('ascii', 'ignore').decode()


def limpia(s) -> str:
    return re.sub(r'\s+', ' ', str(s if s is not None else '').replace('\xa0', ' ')).strip()


def clave_nombre(s) -> str:
    """Nombre sin acentos, solo letras, palabras ordenadas: iguala «APELLIDOS NOMBRE» con «NOMBRE APELLIDOS»."""
    t = re.sub(r'[^a-z ]', ' ', sin_acentos(s).lower()).split()
    return ' '.join(sorted(w for w in t if len(w) > 1 and w not in ('de', 'del', 'la', 'las', 'los', 'y')))


def cuenta_norm(v) -> str:
    s = limpia(v)
    if isinstance(v, float) and v == int(v):
        s = str(int(v))
    d = re.sub(r'\D', '', s)
    if not d or len(d) > 8:
        return ''
    if re.fullmatch(r'\d+[A-Za-z]?', s) and len(d) <= 7:
        return d.zfill(7)
    return d.zfill(7) if len(d) >= 5 else ''


def huella(*partes) -> str:
    return hashlib.sha1('|'.join(str(p) for p in partes).encode('utf-8')).hexdigest()


def sql(v) -> str:
    if v is None:
        return 'NULL'
    if isinstance(v, bool):
        return '1' if v else '0'
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, (dt.date, dt.datetime)):
        return "'" + v.strftime('%Y-%m-%d') + "'"
    s = str(v).replace('\\', '\\\\').replace("'", "''").replace('\r', '').replace('\n', ' ').replace('\x00', '')
    return "'" + s + "'"


# ---------------------------------------------------------------- fechas
class Fecha:
    """Resultado de interpretar una celda de fecha."""
    __slots__ = ('valor', 'revisar', 'origen')

    def __init__(self, valor, revisar=False, origen=''):
        self.valor, self.revisar, self.origen = valor, revisar, origen


def _fecha_ok(y, m, d):
    try:
        return dt.date(y, m, d)
    except ValueError:
        return None


def candidatos(celda):
    """Lecturas posibles de una celda, la literal primero. Devuelve (lista de fechas, texto_original)."""
    if celda is None or (isinstance(celda, str) and not celda.strip()):
        return [], ''
    if isinstance(celda, dt.datetime):
        celda = celda.date()
    if isinstance(celda, dt.date):
        y, m, d = celda.year, celda.month, celda.day
        c = [celda]
        if d <= 12 and d != m:
            c.append(_fecha_ok(y, d, m))
        return [x for x in c if x], celda.isoformat()
    s = limpia(celda)
    m = re.fullmatch(r'(\d{1,2})\s*[/.\-]\s*(\d{1,2})\s*[/.\-]?\s*(\d{2,4})', s) or re.fullmatch(r'(\d{1,2})/(\d{1,2})(\d{4})', s)
    if m:
        a, b, y = int(m.group(1)), int(m.group(2)), int(m.group(3))
        y += 2000 if y < 100 else 0
        c = []
        lit = _fecha_ok(y, b, a)  # dd/mm/aaaa
        alt = _fecha_ok(y, a, b)  # mm/dd/aaaa
        if lit:
            c.append(lit)
        if alt and (not lit or a != b):
            c.append(alt)
        return c, s
    m = re.fullmatch(r'(\d{4})-(\d{2})-(\d{2})', s)
    if m:
        return candidatos(dt.date(int(m.group(1)), int(m.group(2)), int(m.group(3)))) if _fecha_ok(*map(int, m.groups())) else ([], s)
    return [], s


class Secuencia:
    """Reconstruye fechas de una lista ordenada cronológicamente: elige entre la lectura literal, la de día/mes invertidos y el año
    vecino la que mejor encaja con las fechas anteriores. `ventana` = (atrás, adelante) en días para dar la fecha por buena."""

    def __init__(self, ventana=(-7, 21), tope=(-10, 120)):
        self.ventana, self.tope = ventana, tope
        self.hist = []

    def referencia(self):
        if not self.hist:
            return None
        ult = sorted(self.hist[-3:])
        return ult[len(ult) // 2]

    def resolver(self, celda) -> Fecha:
        cands, orig = candidatos(celda)
        if not cands:
            return Fecha(None, bool(orig), orig)
        ref = self.referencia()
        lit = cands[0]
        if ref is None:
            ok = lit if dt.date(2010, 1, 1) <= lit <= HOY + dt.timedelta(days=60) else None
            if ok:
                self.hist.append(ok)
            return Fecha(ok or lit, ok is None, orig)
        d0 = (lit - ref).days
        if self.ventana[0] <= d0 <= self.ventana[1] and lit <= HOY + dt.timedelta(days=7):
            self.hist.append(lit)
            return Fecha(lit, False, orig)
        # otras lecturas: invertida y años vecinos
        todas = []
        for c in cands:
            for yy in dict.fromkeys([c.year, c.year - 1, c.year + 1, ref.year, ref.year - 1, ref.year + 1]):
                x = _fecha_ok(yy, c.month, c.day)
                if x:
                    todas.append((x, c is not cands[0] or yy != c.year))
        validas = [(abs((x - ref).days), x, cambio) for x, cambio in todas if self.tope[0] <= (x - ref).days <= self.tope[1] and x <= HOY + dt.timedelta(days=7)]
        if validas:
            validas.sort(key=lambda t: (t[0], t[2]))
            x = validas[0][1]
            self.hist.append(x)
            return Fecha(x, False, orig)
        return Fecha(lit, True, orig)  # no se pudo decidir: se carga la lectura literal y se marca


def fecha_simple(celda, minimo=dt.date(2010, 1, 1)):
    """Fecha sin secuencia (hojas no ordenadas): literal si es plausible, si no la invertida; None si ninguna lo es."""
    cands, orig = candidatos(celda)
    for c in cands:
        if minimo <= c <= HOY + dt.timedelta(days=120):
            return Fecha(c, False, orig)
    return Fecha(cands[0] if cands else None, bool(orig), orig)


# ---------------------------------------------------------------- lectura de Excel
def filas(ruta, hoja, max_col=40):
    wb = openpyxl.load_workbook(ruta, read_only=True, data_only=True)
    ws = wb[hoja] if hoja else wb.worksheets[0]
    for i, r in enumerate(ws.iter_rows(values_only=True, max_col=max_col), 1):
        yield i, list(r)
    wb.close()


def es_correo(s):
    return bool(re.fullmatch(r"[A-Za-z0-9._%+\-']+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}", s.replace(',', '.').replace(' ', '')))


def arregla_correo(s):
    s = limpia(s).lower().replace(' ', '').replace(',', '.').replace('@@', '@')
    s = re.sub(r'gmailcom$', 'gmail.com', s)
    s = re.sub(r'@gmail$|@hotmail$|@outlook$|@yahoo$', lambda m: m.group(0) + '.com', s)
    s = re.sub(r'\.con$|\.comm$|\.coom$', '.com', s)
    return s if es_correo(s) else ''


def telefono(s):
    d = re.sub(r'\D', '', limpia(s))
    return d if 7 <= len(d) <= 13 else ''


def modalidad_de_texto(t):
    u = sin_acentos(t).upper()
    if re.search(r'EGEL|EXAMEN GENERAL|\bEXA\b', u):
        return 'egel'
    if re.search(r'APRO', u):
        return 'aprovechamiento'
    if re.search(r'CREDITO|\bCEA\b', u):
        return 'creditos'
    if 'AFINIDAD' in u:
        return 'afinidad'
    if re.search(r'TESINA', u):
        return 'tesina'
    if re.search(r'TESIS|\bTES\b', u):
        return 'tesis'
    if re.search(r'MEMORIA|\bMEL\b', u):
        return 'memoria'
    if re.search(r'APLIC|\bRAC\b', u):
        return 'aplicacion'
    if re.search(r'RESIDENCIA|\bRRI\b', u):
        return 'residencia'
    if re.search(r'ARTIC|ATIC|\bINA\b', u):
        return 'articulo'
    if re.search(r'ENSAYO|\bENS\b', u):
        return 'ensayo'
    if re.search(r'AUTOEMPLEO|\bEAP\b', u):
        return 'autoempleo'
    return None


# ================================================================= LIBRO DE ACTAS
def leer_libro(ruta, informe):
    nuevas, completar, aplaz = [], [], []
    wb = openpyxl.load_workbook(ruta, read_only=True, data_only=True)
    for hoja, carrera in HOJAS_LIBRO.items():
        ws = wb[hoja]
        rows = [list(r) for r in ws.iter_rows(values_only=True, max_col=32)]
        h0 = next(i for i, r in enumerate(rows) if any('nombre del alumno' in sin_acentos(c).lower() for c in r))
        hdr = [sin_acentos(c).lower().strip() for c in rows[h0]]

        def col(*claves):
            for j, c in enumerate(hdr):
                if any(k in c for k in claves):
                    return j
            return None
        i_acta, i_alu, i_cta = col('no. de acta'), col('nombre del alumno'), col('no. de cuenta')
        i_flag = {k: j for j, c in enumerate(hdr) for k in COLS_MODALIDAD if c == k.lower()}
        i_aut, i_v1, i_v2 = col('aut. egel'), col('primera'), col('segunda')
        i_sat, i_sob = col('satisf'), col('sobresal')
        seq = Secuencia(ventana=(-45, 400), tope=(-200, 400))
        for i, r in enumerate(rows, 1):
            if i <= h0 + 1:
                continue
            g = lambda j: r[j] if j is not None and j < len(r) else None
            alumno = limpia(g(i_alu))
            acta_raw = g(i_acta)
            if not alumno or re.search(r'nombre del alumno|primero de enero', alumno, re.I):
                continue
            if not (isinstance(acta_raw, (int, float)) or re.fullmatch(r'\d+', limpia(acta_raw))):
                continue
            acta = int(float(acta_raw))
            flags = [COLS_MODALIDAD[k] for k, j in i_flag.items() if limpia(g(j))]
            resto = [(j, c) for j, c in enumerate(r) if j > (i_cta or 4) and c not in (None, '')]
            fechas = [(j, c) for j, c in resto if candidatos(c)[0]]
            textos = [limpia(c) for j, c in resto if isinstance(c, str) and not candidatos(c)[0]]
            mod = flags[0] if flags else None
            origen = ''
            if not flags:  # diseño nuevo: «Fecha | MODALIDAD | testimonio»
                for t in textos:
                    m = modalidad_de_texto(t)
                    if m and m != 'afinidad':
                        mod, origen = m, t[:60]
                        break
            else:
                origen = next((k for k, j in i_flag.items() if limpia(g(j))), '')
            testimonio = 'sobresaliente' if (limpia(g(i_sob)) or any(t.lower() == 'sobresaliente' for t in textos)) else \
                         'satisfactorio' if (limpia(g(i_sat)) or any(t.lower() == 'satisfactorio' for t in textos)) else ''
            if flags:
                f_aut = fecha_simple(g(i_aut)) if g(i_aut) else Fecha(None)
                f_ex = None
                for j, c in fechas:
                    if j != i_aut:
                        f_ex = seq.resolver(c)
                        break
            else:
                f_aut = Fecha(None)
                f_ex = seq.resolver(fechas[0][1]) if fechas else Fecha(None)
            f_ex = f_ex or Fecha(None)
            lit_ex = (candidatos(next((c for j, c in fechas if j != i_aut), None))[0] or [None])[0] if flags else (candidatos(fechas[0][1])[0] or [None])[0] if fechas else None
            vez = '1' if limpia(g(i_v1)) else '2' if limpia(g(i_v2)) else ''
            rec = dict(hoja=hoja, fila=i, carrera=carrera, acta=acta, alumno=alumno.upper(), cuenta=cuenta_norm(g(i_cta)),
                       modalidad=mod, modalidad_origen=origen, testimonio=testimonio, vez=vez, fecha_aut_egel=f_aut.valor,
                       fecha_examen=f_ex.valor, fecha_revisar=f_ex.revisar, fecha_literal=lit_ex, fecha_sugerida=seq.referencia() if f_ex.revisar else None)
            rec['_n'] = sum(1 for j, c in enumerate(r) if c not in (None, ''))
            nuevas.append(rec)
    wb.close()
    # consecutivo de actas de aplazamiento
    wb = openpyxl.load_workbook(ruta, read_only=True, data_only=True)
    ws = [w for w in wb.worksheets if 'APLAZ' in w.title.upper()][0]
    for i, r in enumerate(ws.iter_rows(values_only=True, max_col=8), 1):
        r = list(r) + [None] * 8
        cons, nombre = r[0], limpia(r[1])
        if not nombre or not isinstance(cons, (int, float)):
            continue
        sig = limpia(r[3]).upper()
        f = fecha_simple(r[4], dt.date(2015, 1, 1)) if r[4] not in (None, '') else Fecha(None)
        aplaz.append(dict(fila=i, consecutivo=int(cons), alumno=nombre.upper(), cuenta=cuenta_norm(r[2]),
                          carrera=SIGLAS.get(sig, None), siglas=sig, fecha=f.valor, fecha_revisar=f.revisar))
    wb.close()
    return nuevas, aplaz


# ================================================================= SOLICITUDES SIN TRABAJO ESCRITO
def tipo_solicitud(t):
    u = sin_acentos(t).upper()
    if re.search(r'EGEL|EGLE|AGEL|^EGE$|NACIONAL', u):
        return 'egel'
    if 'APRO' in u or u.startswith('APR'):
        return 'aprovechamiento'
    if 'CREDITO' in u or u.strip() in ('CEA',):
        return 'creditos'
    if 'AFINIDAD' in u:
        return 'afinidad'
    return u.lower()[:20] or 'otro'


def leer_solicitudes(ruta, informe):
    out = []
    wb = openpyxl.load_workbook(ruta, read_only=True, data_only=True)
    for ws in wb.worksheets:
        lote = ws.title
        sec = Secuencia(ventana=(-120, 120), tope=(-400, 200))
        convoc = ''
        for i, r in enumerate(ws.iter_rows(values_only=True, max_col=36), 1):
            r = list(r)
            vals = [(j, c) for j, c in enumerate(r) if c not in (None, '')]
            if not vals:
                continue
            if ws.title.startswith('Modalidad') and i == 1:
                convoc = limpia(vals[0][1])
                continue
            nombre = limpia(r[1]) if len(r) > 1 else ''
            if not nombre or re.search(r'nombre del alumno', nombre, re.I):
                continue
            cuenta = cuenta_norm(r[2]) if len(r) > 2 else ''
            siglas = limpia(r[3]).upper() if len(r) > 3 else ''
            sig = re.sub(r'[^A-Z]', ' ', siglas).split()
            carrera = next((SIGLAS[s] for s in sig if s in SIGLAS), 'computacion' if 'CUI' in sig else None)
            tipo_o = limpia(r[4]) if len(r) > 4 else ''
            if not tipo_o and not cuenta:
                continue
            tipo = tipo_solicitud(tipo_o)
            if tipo not in ('egel', 'aprovechamiento', 'creditos', 'afinidad'):
                tipo = 'otro'
            f_pres = fecha_simple(r[5]) if len(r) > 5 and r[5] not in (None, '') else Fecha(None)
            f_sol = sec.resolver(r[6]) if len(r) > 6 and r[6] not in (None, '') else Fecha(None)
            vez = '1' if len(r) > 7 and limpia(r[7]) else '2' if len(r) > 8 and limpia(r[8]) else ''
            correo, tel, nivel, obs = '', '', '', []
            for j, c in enumerate(r):
                if j < 9 or c in (None, ''):
                    continue
                if isinstance(c, (dt.datetime, dt.date)):
                    continue
                s = limpia(c)
                if es_correo(s.replace(' ', '')) or '@' in s:
                    correo = correo or arregla_correo(s)
                elif telefono(s) and len(re.sub(r'\D', '', s)) >= 8 and not re.search(r'[A-Za-z]', s):
                    tel = tel or telefono(s)
                elif re.fullmatch(r'0?\.5|50\s*%|50%.*', s) or s.upper() in ('NACIONAL', 'NACIO'):
                    nivel = '50%' if s.startswith(('0.5', '.5', '50')) else 'nacional'
                elif s not in ('!',) and not re.fullmatch(r'\d{1,2}', s):
                    obs.append(s[:80])
            # hoja de lote («08 marzo 2024»): la última fecha de la fila es el día del examen
            f_lote = None
            if ws.title != 'Modalidad sin trabajo escrito':
                for j in range(len(r) - 1, 8, -1):
                    cs, _ = candidatos(r[j])
                    if cs:
                        f_lote = fecha_simple(r[j]).valor
                        break
                if f_lote is None:
                    m = re.search(r'(\d{1,2})\s*(?:de\s*)?([a-z]{3})[a-z]*\s*(\d{2,4})', sin_acentos(ws.title).lower())
                    meses = {'ene': 1, 'feb': 2, 'mar': 3, 'abr': 4, 'may': 5, 'jun': 6, 'jul': 7, 'ago': 8, 'sep': 9, 'oct': 10, 'nov': 11, 'dic': 12}
                    if m and m.group(2) in meses:
                        y = int(m.group(3)) + (2000 if int(m.group(3)) < 100 else 0)
                        f_lote = _fecha_ok(y, meses[m.group(2)], int(m.group(1)))
            if ws.title == 'Modalidad sin trabajo escrito' and f_pres.valor:
                f_lote = f_pres.valor
            out.append(dict(hoja=ws.title, fila=i, alumno=nombre.upper(), cuenta=cuenta, carrera=carrera, siglas=siglas[:20],
                            tipo=tipo, tipo_origen=tipo_o[:40], fecha_solicitud=f_sol.valor, fecha_revisar=f_sol.revisar,
                            fecha_programada=f_lote, vez=vez, nivel=nivel, correo=correo, telefono=tel, lote=(convoc if ws.title.startswith('Modalidad') else ws.title)[:80],
                            observaciones='; '.join(dict.fromkeys(obs))[:200]))
    wb.close()
    return out


# ================================================================= RECEPCIÓN DE PROTOCOLOS
def leer_protocolos(carpeta, informe):
    out = []
    fuentes = [('RECEPCION DE PROTOCOLO.xlsx', 'Hoja1'), ('RECEPCION DE PROTOCOLO.xlsx', 'Hoja2'), ('Oficios Titulación 2026.xlsx', 'Protocolos')]
    for archivo, hoja in fuentes:
        ruta = next((p for p in carpeta.iterdir() if sin_acentos(p.name).lower() == sin_acentos(archivo).lower()
                     or (archivo.startswith('Oficios') and sin_acentos(p.name).lower().startswith('oficios') and '2026' in p.name)), None)
        if ruta is None:
            continue
        sec = Secuencia(ventana=(-10, 40), tope=(-60, 150))
        for i, r in filas(ruta, hoja, 10):
            nombre = limpia(r[1]) if len(r) > 1 else ''
            if not nombre or 'NOMBRE DEL ALUMNO' in nombre.upper():
                continue
            cuenta = cuenta_norm(r[2])
            mod_o = limpia(r[3])
            f = sec.resolver(r[4]) if r[4] not in (None, '') else Fecha(None)
            out.append(dict(carrera=None, hoja=f'{archivo.split(".")[0]} / {hoja}', fila=i, alumno=nombre.upper(), cuenta=cuenta,
                            modalidad=modalidad_de_texto(mod_o) if mod_o else None, modalidad_origen=mod_o[:60],
                            fecha_entrega=f.valor, fecha_revisar=f.revisar, correo=arregla_correo(r[5]) if r[5] else '',
                            telefono=telefono(r[6]) if r[6] else '', incorporada=limpia(r[7])[:30] if len(r) > 7 and r[7] else ''))
    return out


# ================================================================= OFICIOS
PAL_ASUNTO = r'impresi|sinodal|sinodo|jurado|toma de protesta|previo|definitivo|evaluacion|presidente|cesu|\bcui\b|incorporada|oficio|respaldo|titulos|creditos|coordinador|revision|integrantes|asignacion|sustentacion|solicitud'


def tipo_oficio(txt):
    u = sin_acentos(txt).lower()
    if 'impresi' in u:
        return 'impresion'
    if 'protesta' in u:
        return 'toma_protesta'
    if 'incorporada' in u:
        return 'incorporadas'
    if 'presidente' in u and ('cesu' in u or 'cui' in u):
        return 'asignacion_presidente'
    if 'cesu' in u or re.search(r'\bcui\b', u):
        return 'cesu_cui'
    if 'previo' in u:
        return 'sinodales_previo'
    if 'definitivo' in u or 'sustentacion' in u:
        return 'sinodales_definitivo'
    if 'asignacion' in u and ('jurado' in u or 'sinod' in u):
        return 'asignacion_jurado'
    if 'sinod' in u or 'jurado' in u:
        return 'sinodales'
    if 'titulos' in u:
        return 'titulos'
    return 'otro'


def leer_oficios(carpeta, informe):
    out = []
    for p in sorted(carpeta.iterdir()):
        n = sin_acentos(p.name).lower()
        if n.startswith('control de oficios'):
            serie, hoja = '2025', 'Hoja1'
        elif n.startswith('oficios titulacion 2026') or (n.startswith('oficios') and '2026' in n):
            serie, hoja = '2026', 'Oficios 2026'
        else:
            continue
        sec = Secuencia(ventana=(-7, 21), tope=(-10, 100))
        for i, r in filas(p, hoja, 8):
            num = r[1] if len(r) > 1 else None
            if not isinstance(num, (int, float)):
                continue
            dirigido, asunto, tomado = limpia(r[3]), limpia(r[4]), limpia(r[5]) if len(r) > 5 else ''
            f = sec.resolver(r[2]) if r[2] not in (None, '') else Fecha(None)
            if not (dirigido or asunto):
                continue  # número reservado sin usar
            # a veces «dirigido a» y «asunto» van cambiados o el nombre va dentro del asunto: la persona es lo que queda sin palabras de trámite
            ALTO = {'sustentacion', 'revision', 'previo', 'definitivo', 'trabajo', 'integrantes', 'intregantes', 'integrante', 'sinodo', 'sinodos', 'sinodales', 'sinodal',
                    'oficio', 'oficios', 'impresion', 'asignacion', 'jurado', 'toma', 'protesta', 'cesu', 'cui', 'tesis', 'int', 'solicitud', 'presidente', 'incorporadas',
                    'respaldo', 'cpu', 'titulos', 'elaboracion', 'control', 'escolar', 'rectoria', 'evaluacion', 'profesional', 'creditos', 'estudios', 'avanzados', 'coordinador', 'para'}

            def resto(t):
                pal = [w for w in t.split() if sin_acentos(w).lower().strip('.,') not in ALTO]
                while pal and sin_acentos(pal[0]).lower() in ('de', 'del', 'a', 'dr', 'dra', 'ing', 'm'):
                    pal.pop(0)
                while pal and sin_acentos(pal[-1]).lower() in ('de', 'del', 'a'):
                    pal.pop()
                x = limpia(' '.join(pal))
                if len(x) < 4 or re.search(r'\d{1,2}\s*/\s*\d{1,2}', x):
                    return ''
                return x
            restos = [resto(dirigido), resto(asunto)]
            persona = max(restos, key=len)
            tipo = tipo_oficio(dirigido + ' ' + asunto)
            out.append(dict(serie=serie, numero=int(num), fecha=f.valor, fecha_revisar=f.revisar, dirigido=dirigido[:200], asunto=asunto[:200],
                            tomado_por=tomado[:40], tipo=tipo, persona=persona[:160].upper(), hoja=f'{p.stem[:30]} / {hoja}', fila=i))
    return out


# ================================================================= ARTÍCULO 5
def leer_art5(carpeta, informe):
    out = []
    p = next(x for x in carpeta.iterdir() if x.name.upper().startswith('ARTICULO') or x.name.upper().startswith('ARTÍCULO'))
    for i, r in filas(p, None, 8):
        if i == 1:
            continue
        cuenta, alumno = cuenta_norm(r[0]), limpia(r[1]).upper()
        oficio = limpia(r[5]) if len(r) > 5 else ''
        if not alumno or not oficio:
            continue
        plan = limpia(r[2]).upper()
        sig = plan.split('-')[0]
        f_ev = fecha_simple(r[3], dt.date(2020, 1, 1)) if r[3] not in (None, '') else Fecha(None)
        f_a5 = fecha_simple(r[4], dt.date(2020, 1, 1)) if r[4] not in (None, '') else Fecha(None)
        out.append(dict(fila=i, cuenta=cuenta, alumno=alumno, plan=plan[:20], carrera=SIGLAS.get(sig), fecha_evaluacion=f_ev.valor,
                        fecha_articulo5=f_a5.valor, oficio=oficio[:40], fecha_revisar=f_ev.revisar or f_a5.revisar))
    return out


# ================================================================= generación del SQL
DDL = """
-- Registros del departamento de Titulación que se llevaban en Excel (2015-2026): solicitudes sin trabajo escrito, protocolos
-- recibidos, control de oficios, «artículo 5», actas de aplazamiento y las actas más recientes del libro. Repetible: cada
-- tabla tiene una huella única y se usa INSERT IGNORE; en tit_historico solo se agregan actas que no existen y se llenan vacíos.
CREATE TABLE IF NOT EXISTS tit_solicitudes_historicas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  siglas VARCHAR(20) NOT NULL DEFAULT '',
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  tipo VARCHAR(20) NOT NULL DEFAULT '',
  tipo_origen VARCHAR(40) NOT NULL DEFAULT '',
  fecha_solicitud DATE NULL,
  fecha_programada DATE NULL,
  vez VARCHAR(10) NOT NULL DEFAULT '',
  nivel VARCHAR(12) NOT NULL DEFAULT '',
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  lote VARCHAR(80) NOT NULL DEFAULT '',
  observaciones VARCHAR(200) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_solh_huella (huella), KEY idx_tit_solh_cuenta (cuenta), KEY idx_tit_solh_fecha (fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_protocolos_recibidos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL,
  modalidad_origen VARCHAR(60) NOT NULL DEFAULT '',
  fecha_entrega DATE NULL,
  correo VARCHAR(120) NOT NULL DEFAULT '',
  telefono VARCHAR(20) NOT NULL DEFAULT '',
  incorporada VARCHAR(30) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_protr_huella (huella), KEY idx_tit_protr_cuenta (cuenta), KEY idx_tit_protr_fecha (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_oficios_control (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  serie CHAR(4) CHARACTER SET ascii NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NULL,
  dirigido VARCHAR(200) NOT NULL DEFAULT '',
  asunto VARCHAR(200) NOT NULL DEFAULT '',
  tipo VARCHAR(30) NOT NULL DEFAULT 'otro',
  persona VARCHAR(160) NOT NULL DEFAULT '',
  tomado_por VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  origen VARCHAR(80) NOT NULL DEFAULT '',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_ofc_num (serie, numero), KEY idx_tit_ofc_fecha (fecha), KEY idx_tit_ofc_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_articulo5 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  huella CHAR(40) CHARACTER SET ascii NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  plan VARCHAR(20) NOT NULL DEFAULT '',
  fecha_evaluacion DATE NULL,
  fecha_articulo5 DATE NULL,
  oficio VARCHAR(40) NOT NULL DEFAULT '',
  fecha_revisar TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_art5_huella (huella), KEY idx_tit_art5_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tit_actas_aplazamiento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  consecutivo INT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NULL,
  alumno VARCHAR(160) NOT NULL,
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  fecha DATE NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tit_aplaz_cons (consecutivo), KEY idx_tit_aplaz_cuenta (cuenta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"""


def insertar(tabla, cols, filas_, por_lote=200):
    s = []
    for k in range(0, len(filas_), por_lote):
        trozo = filas_[k:k + por_lote]
        s.append(f"INSERT IGNORE INTO {tabla} ({', '.join(cols)}) VALUES\n" + ',\n'.join('(' + ', '.join(sql(v) for v in f) + ')' for f in trozo) + ';')
    return '\n'.join(s)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('carpeta', nargs='?', default=str(CARPETA))
    ap.add_argument('--informe', default='')
    ap.add_argument('--fechas', default='', help='CSV con las fechas del libro que el algoritmo corrigió (para revisión humana)')
    a = ap.parse_args()
    carpeta = Path(a.carpeta)
    informe = {}
    libro = next(p for p in carpeta.iterdir() if p.name.upper().startswith('CONTROL DE LIBROS'))
    solic = next(p for p in carpeta.iterdir() if p.name.upper().startswith('SOLICITUD'))

    actas, aplaz = leer_libro(libro, informe)
    solicitudes = leer_solicitudes(solic, informe)
    protocolos = leer_protocolos(carpeta, informe)
    oficios = leer_oficios(carpeta, informe)
    art5 = leer_art5(carpeta, informe)

    # cuenta -> carrera conocida (para completar la licenciatura donde la hoja no la trae)
    cc = defaultdict(Counter)
    for x in actas:
        if x['cuenta']:
            cc[x['cuenta']][x['carrera']] += 1
    for x in solicitudes + art5 + aplaz:
        if x.get('cuenta') and x.get('carrera'):
            cc[x['cuenta']][x['carrera']] += 1
    carrera_de = {c: k.most_common(1)[0][0] for c, k in cc.items() if len(k) == 1}
    for x in solicitudes + protocolos + art5 + aplaz:
        if not x.get('carrera') and x.get('cuenta'):
            x['carrera'] = carrera_de.get(x['cuenta'])

    # ---- deduplicar solicitudes: misma persona, tipo y fecha de solicitud (las hojas de lote repiten filas de la hoja principal)
    por_clave = {}
    for x in solicitudes:
        k = huella(x['cuenta'] or clave_nombre(x['alumno']), x['tipo'], x['fecha_solicitud'] or x['fecha_programada'] or '')
        x['huella'] = k
        if k in por_clave:
            y = por_clave[k]
            for campo in ('fecha_programada', 'correo', 'telefono', 'vez', 'nivel', 'carrera', 'siglas', 'fecha_solicitud'):
                if not y.get(campo) and x.get(campo):
                    y[campo] = x[campo]
            if x['lote'] and x['lote'] not in y['lote']:
                y['lote'] = (y['lote'] + ' + ' + x['lote'])[:80]
            informe.setdefault('solicitudes_duplicadas', 0)
            informe['solicitudes_duplicadas'] += 1
        else:
            por_clave[k] = x
    solicitudes = list(por_clave.values())

    # ---- protocolos recibidos: misma persona y fecha (la hoja 2 y «Oficios 2026/Protocolos» repiten filas)
    pc = {}
    for x in protocolos:
        k = huella(x['cuenta'] or clave_nombre(x['alumno']), x['fecha_entrega'] or '', x['modalidad'] or x['modalidad_origen'][:6].lower())
        x['huella'] = k
        if k not in pc:
            pc[k] = x
        else:
            informe['protocolos_duplicados'] = informe.get('protocolos_duplicados', 0) + 1
    protocolos = list(pc.values())

    # ---- art. 5: misma persona y oficio
    ac = {}
    for x in art5:
        k = huella(x['cuenta'] or clave_nombre(x['alumno']), x['oficio'])
        x['huella'] = k
        if k not in ac:
            ac[k] = x
        else:
            informe['art5_duplicados'] = informe.get('art5_duplicados', 0) + 1
    art5 = list(ac.values())

    # ---- aplazamiento: consecutivo único (si se repite, el último gana)
    ap_ = {}
    for x in aplaz:
        ap_[x['consecutivo']] = x
    aplaz = list(ap_.values())

    # ---- oficios: serie + número únicos
    of_ = {}
    for x in oficios:
        of_[(x['serie'], x['numero'])] = x
    oficios = list(of_.values())

    out = ['-- Datos personales de estudiantes (nombres, cuentas, correos, teléfonos): NO versionar. Aplicar después de la migración 040.']
    out.append(insertar('tit_solicitudes_historicas',
                        ['huella', 'carrera', 'siglas', 'alumno', 'cuenta', 'tipo', 'tipo_origen', 'fecha_solicitud', 'fecha_programada', 'vez', 'nivel', 'correo',
                         'telefono', 'lote', 'observaciones', 'fecha_revisar', 'origen'],
                        [[x['huella'], x['carrera'], x['siglas'], x['alumno'][:160], x['cuenta'], x['tipo'], x['tipo_origen'], x['fecha_solicitud'], x['fecha_programada'],
                          x['vez'], x['nivel'], x['correo'], x['telefono'], x['lote'], x['observaciones'], x['fecha_revisar'], f"{x['hoja']} fila {x['fila']}"[:80]]
                         for x in solicitudes]))
    out.append(insertar('tit_protocolos_recibidos',
                        ['huella', 'carrera', 'alumno', 'cuenta', 'modalidad', 'modalidad_origen', 'fecha_entrega', 'correo', 'telefono', 'incorporada', 'fecha_revisar', 'origen'],
                        [[x['huella'], x['carrera'], x['alumno'][:160], x['cuenta'], x['modalidad'], x['modalidad_origen'], x['fecha_entrega'], x['correo'], x['telefono'],
                          x['incorporada'], x['fecha_revisar'], f"{x['hoja']} fila {x['fila']}"[:80]] for x in protocolos]))
    out.append(insertar('tit_oficios_control',
                        ['serie', 'numero', 'fecha', 'dirigido', 'asunto', 'tipo', 'persona', 'tomado_por', 'fecha_revisar', 'origen'],
                        [[x['serie'], x['numero'], x['fecha'], x['dirigido'], x['asunto'], x['tipo'], x['persona'], x['tomado_por'], x['fecha_revisar'],
                          f"{x['hoja']} fila {x['fila']}"[:80]] for x in oficios]))
    out.append(insertar('tit_articulo5',
                        ['huella', 'carrera', 'alumno', 'cuenta', 'plan', 'fecha_evaluacion', 'fecha_articulo5', 'oficio', 'fecha_revisar'],
                        [[x['huella'], x['carrera'], x['alumno'][:160], x['cuenta'], x['plan'], x['fecha_evaluacion'], x['fecha_articulo5'], x['oficio'], x['fecha_revisar']] for x in art5]))
    out.append(insertar('tit_actas_aplazamiento',
                        ['consecutivo', 'carrera', 'alumno', 'cuenta', 'fecha'],
                        [[x['consecutivo'], x['carrera'], x['alumno'][:160], x['cuenta'], x['fecha']] for x in aplaz]))

    # ---- libro de actas: los vacíos de tit_historico y las actas que faltan (por licenciatura y número de acta)
    out.append("DROP TEMPORARY TABLE IF EXISTS tmp_libro;\nCREATE TEMPORARY TABLE tmp_libro (hoja VARCHAR(60) NOT NULL, fila INT UNSIGNED NOT NULL, carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, "
               "acta INT UNSIGNED NOT NULL, alumno VARCHAR(160) NOT NULL, cuenta VARCHAR(12) NOT NULL, modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL, "
               "modalidad_origen VARCHAR(60) NOT NULL, vez VARCHAR(10) NOT NULL, testimonio VARCHAR(20) NOT NULL, fecha_aut_egel DATE NULL, fecha_examen DATE NULL) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;")
    out.append(insertar('tmp_libro', ['hoja', 'fila', 'carrera', 'acta', 'alumno', 'cuenta', 'modalidad', 'modalidad_origen', 'vez', 'testimonio', 'fecha_aut_egel', 'fecha_examen'],
                        [[x['hoja'], x['fila'], x['carrera'], x['acta'], x['alumno'][:160], x['cuenta'], x['modalidad'], x['modalidad_origen'], x['vez'], x['testimonio'], x['fecha_aut_egel'], x['fecha_examen']]
                         for x in actas]))
    out.append('-- Fechas de examen en el futuro o con año absurdo (autollenado de Excel que sumó años): solo cambia el AÑO y solo si el valor actual sigue siendo el original.')
    for x in actas:
        lit, nv = x.get('fecha_literal'), x['fecha_examen']
        if lit and nv and not x['fecha_revisar'] and (lit > HOY + dt.timedelta(days=7) or lit < dt.date(2000, 1, 1)) and (nv.month, nv.day) == (lit.month, lit.day):
            out.append(f"UPDATE tit_historico SET fecha_examen = {sql(nv)} WHERE hoja = {sql(x['hoja'])} AND fila = {x['fila']} AND acta = {x['acta']} AND fecha_examen = {sql(lit)};")
    out.append("""
-- Llenar solo vacíos de actas que ya estaban (misma licenciatura y número de acta; si el acta se repite, la misma persona).
UPDATE tit_historico h JOIN tmp_libro t ON t.carrera = h.carrera AND t.acta = h.acta AND t.cuenta = h.cuenta AND t.cuenta <> ''
   SET h.modalidad = IF(h.modalidad IS NULL, t.modalidad, h.modalidad),
       h.modalidad_origen = IF(h.modalidad_origen = '', t.modalidad_origen, h.modalidad_origen),
       h.fecha_examen = IF(h.fecha_examen IS NULL, t.fecha_examen, h.fecha_examen),
       h.fecha_aut_egel = IF(h.fecha_aut_egel IS NULL, t.fecha_aut_egel, h.fecha_aut_egel),
       h.vez = IF(h.vez = '', t.vez, h.vez),
       h.testimonio = IF(h.testimonio = '', t.testimonio, h.testimonio)
 WHERE h.hoja <> 'SISTEMA ANTERIOR';

-- Actas del libro que no están: se agregan (misma licenciatura, número de acta y persona = ya existe).
INSERT INTO tit_historico (hoja, fila, carrera, acta, alumno, cuenta, modalidad, modalidad_origen, vez, testimonio, fecha_aut_egel, fecha_examen)
SELECT t.hoja, t.fila, t.carrera, t.acta, t.alumno, t.cuenta, t.modalidad, t.modalidad_origen, t.vez, t.testimonio, t.fecha_aut_egel, t.fecha_examen
  FROM tmp_libro t
 WHERE NOT EXISTS (SELECT 1 FROM tit_historico h WHERE h.carrera = t.carrera AND h.acta = t.acta AND (h.cuenta = t.cuenta OR h.cuenta = '' OR t.cuenta = ''))
   AND NOT EXISTS (SELECT 1 FROM tit_historico h2 WHERE h2.hoja = t.hoja AND h2.fila = t.fila);
DROP TEMPORARY TABLE IF EXISTS tmp_libro;

-- Licenciatura de lo recibido sin ella: la del historial, si esa cuenta aparece en una sola.
UPDATE tit_protocolos_recibidos r JOIN (SELECT cuenta, MIN(carrera) AS carrera FROM tit_historico WHERE cuenta <> '' GROUP BY cuenta HAVING COUNT(DISTINCT carrera) = 1) c ON c.cuenta = r.cuenta
   SET r.carrera = c.carrera WHERE r.carrera IS NULL;
UPDATE tit_solicitudes_historicas r JOIN (SELECT cuenta, MIN(carrera) AS carrera FROM tit_historico WHERE cuenta <> '' GROUP BY cuenta HAVING COUNT(DISTINCT carrera) = 1) c ON c.cuenta = r.cuenta
   SET r.carrera = c.carrera WHERE r.carrera IS NULL;
""")
    SALIDA.write_text(DDL.lstrip('\n') + '\n', encoding='utf-8', newline='\n')
    SALIDA_DATOS.parent.mkdir(parents=True, exist_ok=True)
    SALIDA_DATOS.write_text('\n'.join(out) + '\n', encoding='utf-8', newline='\n')

    informe.update(dict(
        actas_leidas=len(actas), actas_a_revisar=[(x['hoja'], x['fila'], x['acta'], str(x['fecha_examen'])) for x in actas if x['fecha_revisar']],
        solicitudes=len(solicitudes), solicitudes_sin_cuenta=sum(1 for x in solicitudes if not x['cuenta']), solicitudes_sin_carrera=sum(1 for x in solicitudes if not x['carrera']),
        solicitudes_fecha_revisar=sum(1 for x in solicitudes if x['fecha_revisar']),
        protocolos=len(protocolos), protocolos_sin_carrera=sum(1 for x in protocolos if not x['carrera']), protocolos_fecha_revisar=[(x['fila'], x['alumno'], str(x['fecha_entrega'])) for x in protocolos if x['fecha_revisar']],
        oficios=len(oficios), oficios_fecha_revisar=[(x['serie'], x['numero'], str(x['fecha'])) for x in oficios if x['fecha_revisar']],
        oficios_tipos=Counter(x['tipo'] for x in oficios), art5=len(art5), aplazamiento=len(aplaz),
    ))
    if a.fechas:
        import csv
        with open(a.fechas, 'w', encoding='utf-8-sig', newline='') as fh:
            w = csv.writer(fh)
            w.writerow(['hoja', 'fila_excel', 'acta', 'alumno', 'fecha_en_excel', 'fecha_propuesta', 'seguridad'])
            for x in actas:
                lit, nv = x.get('fecha_literal'), x['fecha_examen']
                if lit and nv and lit != nv:
                    w.writerow([x['hoja'], x['fila'], x['acta'], x['alumno'], lit, nv, ''])
                elif x['fecha_revisar']:
                    w.writerow([x['hoja'], x['fila'], x['acta'], x['alumno'], lit, x.get('fecha_sugerida') or '', 'dudosa'])
    if a.informe:
        Path(a.informe).write_text(json.dumps(informe, ensure_ascii=False, indent=1, default=str), encoding='utf-8')
    print(json.dumps({k: (v if not isinstance(v, list) else f'{len(v)} filas') for k, v in informe.items()}, ensure_ascii=False, default=str, indent=1))
    print('escrito', SALIDA, 'y', SALIDA_DATOS)


if __name__ == '__main__':
    main()
