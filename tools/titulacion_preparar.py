#!/usr/bin/env python3
"""Prepara las plantillas del módulo Titulación a partir de docs/licenciatura.

Uso (desde la raíz del proyecto, cuando el departamento publique formatos nuevos):

    python3 tools/titulacion_preparar.py

Hace dos cosas:
  1. Reescribe cada PDF con tabla de referencias clásica para que FPDI gratuito
     pueda importarlo (los PDF originales usan xref comprimido) y copia los DOCX
     de protocolo a app/Modules/Titulacion/plantillas/.
  2. Detecta, con pdfplumber, las posiciones de líneas, casillas "( )" y celdas
     de cada formato y escribe app/Modules/Titulacion/formatos/pdf_specs.json,
     que usa Services/PdfFormato.php para sobreimprimir los datos del pasante.

Solo se ejecuta en el equipo de desarrollo; el servidor no necesita Python.
"""
import json
import re
import shutil
import sys
from pathlib import Path

import pdfplumber
import pypdf

RAIZ = Path(__file__).resolve().parent.parent
ORIGEN = RAIZ / 'docs' / 'licenciatura'
MODULO = RAIZ / 'app' / 'Modules' / 'Titulacion'
DESTINO_PDF = MODULO / 'plantillas' / 'pdf'
DESTINO_DOCX = MODULO / 'plantillas' / 'docx'
SPECS = MODULO / 'formatos' / 'pdf_specs.json'

CARRERAS = [('Civil', 'civil'), ('Mecánica', 'mecanica'), ('en Computación', 'computacion'),
            ('en Electrónica', 'electronica'), ('en Sistemas', 'sistemas')]
SISTEMAS = [('FI-UAEMéx', 'fi'), ('UI-CUI', 'ui'), ('CESU', 'cesu'), ('CUTV', 'cutv'), ('UIF', 'uif')]
MODALIDADES = [('Artículo', 'articulo'), ('Ensayo', 'ensayo'), ('Memoria', 'memoria'),
               ('Reporte de aplicación', 'aplicacion'), ('Reporte de autoempleo', 'autoempleo'),
               ('Reporte de residencia', 'residencia'), ('Tesina', 'tesina'), ('Tesis', 'tesis')]

# clave de formato -> (PDF de origen, archivo normalizado)
PDFS = {
    'aprovechamiento': 'SolicitudAprovechamiento.pdf',
    'egel_nacional': 'SolicitudEGEL.pdf',
    'egel_50': 'SolicitudEGEL_50.pdf',
    'creditos': 'SolicitudCreditos.pdf',
    'datos_titulo': '1 Formato de datos del interesado para expedici#U00f3n de titulo profesional.pdf',
    'trabajo_eval': 'SolicitudEvalTrabEscrito.pdf',
    'revision': 'SolicitudPrevio.pdf',
    'sustentacion': 'SolicitudDefinitivo.pdf',
    'art5': 'SolicitudArticulo5.pdf',
}
SIN_ACENTO = {'#U00f3': 'o'}


def normalizar(nombre: str) -> str:
    for k, v in SIN_ACENTO.items():
        nombre = nombre.replace(k, v)
    nombre = re.sub(r'[^A-Za-z0-9_.-]+', '_', nombre)
    return nombre


def reescribir_pdf(origen: Path, destino: Path) -> None:
    lector = pypdf.PdfReader(str(origen))
    escritor = pypdf.PdfWriter(clone_from=lector)
    destino.parent.mkdir(parents=True, exist_ok=True)
    with open(destino, 'wb') as fh:
        escritor.write(fh)


def r(v):
    return round(float(v), 1)


def corridas_guion_bajo(pagina):
    """Líneas de subrayado "____" como (x0, x1, top, bottom) agrupadas por renglón."""
    renglones = []
    for c in pagina.chars:
        for g in renglones:
            if abs(g['top'] - c['top']) <= 2:
                g['chars'].append(c)
                break
        else:
            renglones.append({'top': c['top'], 'chars': [c]})
    salida = []
    for g in sorted(renglones, key=lambda g: g['top']):
        cs = sorted(g['chars'], key=lambda c: c['x0'])
        corrida = None
        lista = []
        for c in cs:
            if c['text'] == '_':
                if corrida and c['x0'] - corrida[1] < 1.5:
                    corrida[1] = c['x1']
                else:
                    corrida = [c['x0'], c['x1']]
                    lista.append(corrida)
        lista = [x for x in lista if x[1] - x[0] > 14]
        if lista:
            salida.append({'top': g['top'], 'bottom': max(c['bottom'] for c in cs), 'runs': lista})
    return salida


def casillas(pagina):
    """Pares "( )" con su etiqueta: devuelve dicts con centro (cx, cy) y texto que sigue."""
    palabras = pagina.extract_words()
    renglones = {}
    for w in palabras:
        renglones.setdefault(round(w['top'] / 3), []).append(w)
    out = []
    for k in sorted(renglones):
        fila = sorted(renglones[k], key=lambda w: w['x0'])
        i = 0
        while i < len(fila):
            if fila[i]['text'] == '(' and i + 1 < len(fila) and fila[i + 1]['text'] == ')':
                a, b = fila[i], fila[i + 1]
                j = i + 2
                etiqueta = []
                while j < len(fila) and fila[j]['text'] != '(':
                    etiqueta.append(fila[j]['text'])
                    j += 1
                # La etiqueta previa (p. ej. "Primera vez ( )") se resuelve por orden en vez de texto.
                previo = []
                m = i - 1
                while m >= 0 and fila[m]['text'] != ')':
                    previo.insert(0, fila[m]['text'])
                    m -= 1
                out.append({'cx': (a['x0'] + b['x1']) / 2, 'cy': (a['top'] + a['bottom']) / 2,
                            'despues': ' '.join(etiqueta), 'antes': ' '.join(previo)})
                i += 2
                continue
            i += 1
    return out


def buscar_palabra(pagina, patron, desde_top=0, hasta_top=10000):
    for w in pagina.extract_words():
        if re.match(patron, w['text']) and desde_top <= w['top'] <= hasta_top:
            return w
    return None


def tablas(pagina):
    return pagina.find_tables()


def celdas_tabla(t):
    out = []
    for fila in t.rows:
        out.append([c for c in fila.cells if c])
    return out


def texto(k, p, x, y, w, h=12, s=10, a='L'):
    return {'k': k, 'tipo': 'texto', 'p': p, 'x': r(x), 'y': r(y), 'w': r(w), 'h': r(h), 's': s, 'a': a}


def sobre_linea(k, p, run, bottom, s=10, a='L', alto=12):
    """Texto apoyado sobre una línea subrayada."""
    return texto(k, p, run[0] + 1, bottom - alto - 0.5, run[1] - run[0] - 2, alto, s, a)


def en_celda(k, p, celda, s=10, a='L', pad=3):
    x0, t, x1, b = celda
    return texto(k, p, x0 + pad, t, x1 - x0 - 2 * pad, b - t, s, a)


def marcas_por_etiqueta(boxes, tabla_etiquetas, p, k, lado='despues'):
    marcas = {}
    for c in boxes:
        txt = c[lado]
        for prefijo, valor in tabla_etiquetas:
            if txt.startswith(prefijo) and valor not in marcas:
                marcas[valor] = [r(c['cx']), r(c['cy'])]
                break
    return {'k': k, 'tipo': 'marca', 'p': p, 'm': marcas} if marcas else None


def fecha_campos(pagina, p, prefijo='fecha'):
    """Detecta el renglón "Ciudad Universitaria … ___ de ____ de 20___"."""
    campos = []
    for ren in corridas_guion_bajo(pagina):
        if len(ren['runs']) == 3:
            textos = ''.join(c['text'] for c in pagina.chars if abs(c['top'] - ren['top']) <= 2)
            if 'Ciudad' in textos or 'Toluca' in textos:
                dia, mes, anio = ren['runs']
                campos += [sobre_linea(prefijo + '_dia', p, dia, ren['bottom'], a='C'),
                           sobre_linea(prefijo + '_mes', p, mes, ren['bottom'], a='C'),
                           sobre_linea(prefijo + '_anio', p, anio, ren['bottom'], s=9, a='C')]
                break
    return campos


def bloque_pasante(pagina, p, alumno_en_tabla=True):
    """Nombre/cuenta, carrera, sistema, años, vez, contacto, que se repiten en las solicitudes."""
    campos = []
    for t in tablas(pagina):
        if t.bbox[0] < 50 or t.bbox[2] - t.bbox[0] > 500:
            continue  # cuadrícula de maquetación de toda la hoja
        filas = celdas_tabla(t)
        if any(len(f) < 2 for f in filas):
            continue
        contenido = [[(pagina.crop(c).extract_text() or '').strip() for c in f] for f in filas]
        plano = ' '.join(' '.join(f) for f in contenido)
        if 'Nombre del pasante' in plano and len(filas) == 2 and len(filas[1]) == 2:
            campos.append(en_celda('nombre', p, filas[1][0]))
            campos.append(en_celda('cuenta', p, filas[1][1]))
        elif 'Nombre:' in plano and 'Número de cuenta' in plano and len(filas) == 2:
            campos.append(en_celda('nombre', p, filas[0][1]))
            campos.append(en_celda('cuenta', p, filas[1][1]))
        elif 'Teléfono' in plano and 'Correo' in plano:
            campos.append(en_celda('telefono', p, filas[0][1], s=9))
            campos.append(en_celda('correo_inst', p, filas[1][1], s=9))
            campos.append(en_celda('correo_pers', p, filas[2][1], s=9))
    campos += respaldo_por_palabras(pagina, p, {c['k'] for c in campos})
    cajas = casillas(pagina)
    for k, tabla in (('carrera', CARRERAS), ('sistema', SISTEMAS), ('modalidad', MODALIDADES)):
        m = marcas_por_etiqueta(cajas, tabla, p, k)
        if m:
            campos.append(m)
    vez = [c for c in cajas if 'Segunda' in c['antes'] or 'Primera' in c['antes']]
    if len(vez) == 2:
        campos.append({'k': 'vez', 'tipo': 'marca', 'p': p,
                       'm': {'primera': [r(vez[0]['cx']), r(vez[0]['cy'])],
                             'segunda': [r(vez[1]['cx']), r(vez[1]['cy'])]}})
    for ren in corridas_guion_bajo(pagina):
        textos = ''.join(c['text'] for c in pagina.chars if abs(c['top'] - ren['top']) <= 2)
        if 'ingreso' in textos and len(ren['runs']) == 2:
            campos.append(sobre_linea('anio_ingreso', p, ren['runs'][0], ren['bottom'], a='C'))
            campos.append(sobre_linea('anio_egreso', p, ren['runs'][1], ren['bottom'], a='C'))
    return campos


def respaldo_por_palabras(pagina, p, ya):
    """Cuando find_tables no resuelve una celda, se ubica por las etiquetas impresas."""
    ws = pagina.extract_words()
    campos = []

    def sig(i, *textos):
        return all(i + 1 + n < len(ws) and ws[i + 1 + n]['text'] == t for n, t in enumerate(textos))

    for i, w in enumerate(ws):
        alto = w['bottom'] - w['top'] + 2
        if w['text'] == 'Teléfono' and 'telefono' not in ya and w['x0'] < 100:
            campos.append(texto('telefono', p, 172, w['top'] - 1, 350, alto, 9)); ya.add('telefono')
        if w['text'] == 'Correo' and sig(i, 'institucional') and 'correo_inst' not in ya:
            campos.append(texto('correo_inst', p, 172, w['top'] - 1, 350, alto, 9)); ya.add('correo_inst')
        if w['text'] == 'Correo' and sig(i, 'personal') and 'correo_pers' not in ya:
            campos.append(texto('correo_pers', p, 172, w['top'] - 1, 350, alto, 9)); ya.add('correo_pers')
        if w['text'] == 'Nombre' and sig(i, 'del', 'pasante') and 'nombre' not in ya:
            campos.append(texto('nombre', p, 88, w['bottom'] + 1, 335, 13, 10))
            campos.append(texto('cuenta', p, 428, w['bottom'] + 1, 94, 13, 10)); ya |= {'nombre', 'cuenta'}
        if w['text'] == 'Nombre:' and 'nombre' not in ya and w['x0'] < 120:
            c = next((x for x in ws if x['text'] == 'cuenta:' and x['top'] > w['top']), None)
            if c:
                x0 = c['x1'] + 10
                campos.append(texto('nombre', p, x0, w['top'] - 1, 520 - x0, alto + 1, 10))
                campos.append(texto('cuenta', p, x0, c['top'] - 1, 520 - x0, alto + 1, 10)); ya |= {'nombre', 'cuenta'}
    return campos


def lineas_parrafo(k, p, renglones, s=10):
    cajas = [[r(run[0] + 1), r(ren['bottom'] - 12.5), r(run[1] - run[0] - 2), 12] for ren, run in renglones]
    return {'k': k, 'tipo': 'parrafo', 'p': p, 'cajas': cajas, 's': s}


NOMBRE_SUBDIRECTOR = 'DR. JAVIER SALAS GARCÍA'


def cajas_subdirector(pdf):
    """Ubica cada vez que el formato imprime el nombre del Subdirector Académico, para poder
    sustituirlo desde Ajustes sin pedirlo en cada trámite."""
    import unicodedata
    objetivo = 'DR.JAVIERSALASGARCIA'

    def plano(t):
        return ''.join(c for c in unicodedata.normalize('NFD', t) if unicodedata.category(c) != 'Mn').upper()

    cajas = []
    for pi, pagina in enumerate(pdf.pages):
        ws = pagina.extract_words()
        i = 0
        while i < len(ws):
            acumulado = ''
            for n in range(i, min(i + 6, len(ws))):
                acumulado += plano(ws[n]['text'])
                if acumulado == objetivo:
                    a, d = ws[i], ws[n]
                    letras = [c for c in pagina.chars if a['x0'] - 1 <= c['x0'] <= d['x1'] + 1 and abs(c['top'] - a['top']) < 2]
                    cajas.append({'p': pi, 'x': r(a['x0']), 'y': r(a['top']), 'w': r(d['x1'] - a['x0']),
                                  'h': r(d['bottom'] - a['top']), 's': round(float(letras[0]['size']), 1) if letras else 11,
                                  'negrita': bool(letras) and 'bold' in letras[0]['fontname'].lower()})
                    i = n
                    break
                if not objetivo.startswith(acumulado):
                    break
            i += 1
    return cajas


def fondo_en(ruta, cajas):
    """Color de fondo junto a cada nombre (blanco o el gris del recuadro de autorización)."""
    import pypdfium2 as pdfium
    doc = pdfium.PdfDocument(str(ruta))
    paginas = {}
    for c in cajas:
        if c['p'] not in paginas:
            paginas[c['p']] = doc[c['p']].render(scale=1).to_pil().convert('RGB')
        img = paginas[c['p']]
        px = img.getpixel((max(int(c['x']) - 3, 0), min(int(c['y'] + c['h'] / 2), img.height - 1)))
        c['fondo'] = [int(v) for v in px]
    return cajas


def construir(clave, ruta):
    campos = []
    with pdfplumber.open(str(ruta)) as pdf:
        pagina = pdf.pages[0]
        ancho, alto = float(pagina.width), float(pagina.height)
        campos += fecha_campos(pagina, 0)
        campos += bloque_pasante(pagina, 0)
        renglones = corridas_guion_bajo(pagina)

        if clave == 'revision':
            titulo = [(ren, ren['runs'][0]) for ren in renglones if 385 < ren['top'] < 425][:3]
            campos.append(lineas_parrafo('titulo', 0, titulo))
            aprob = next(ren for ren in renglones if len(ren['runs']) == 2 and ren['top'] > 420 and ren['top'] < 440)
            campos += [sobre_linea('prot_dia', 0, aprob['runs'][0], aprob['bottom'], a='C'),
                       sobre_linea('prot_mes', 0, aprob['runs'][1], aprob['bottom'], a='C')]
            anio = next(ren for ren in renglones if ren['top'] > 440 and ren['top'] < 460)
            campos.append(sobre_linea('prot_anio', 0, anio['runs'][0], anio['bottom'], s=9, a='C'))
        if clave == 'sustentacion':
            voto = next(ren for ren in renglones if len(ren['runs']) == 3 and 340 < ren['top'] < 380)
            campos += [sobre_linea('voto_dia', 0, voto['runs'][0], voto['bottom'], a='C'),
                       sobre_linea('voto_mes', 0, voto['runs'][1], voto['bottom'], a='C'),
                       sobre_linea('voto_anio', 0, voto['runs'][2], voto['bottom'], s=9, a='C')]
            titulo = [(ren, ren['runs'][0]) for ren in renglones if 470 < ren['top'] < 540][:3]
            campos.append(lineas_parrafo('titulo', 0, titulo))
            # Segunda hoja: voto aprobatorio (los profesores completan su parte a mano).
            p2 = pdf.pages[1]
            campos += fecha_campos(p2, 1, 'voto2')
            cajas2 = casillas(p2)
            for k, tabla in (('carrera', CARRERAS), ('sistema', SISTEMAS), ('modalidad', MODALIDADES)):
                m = marcas_por_etiqueta(cajas2, tabla, 1, k)
                if m:
                    m['k'] = k + '_v'
                    campos.append(m)
            campos += [texto('nombre_v', 1, 88, 244, 335, 22, 10), texto('cuenta_v', 1, 430, 244, 94, 22, 10),
                       texto('titulo_v', 1, 88, 445, 436, 36, 9)]
        if clave == 'datos_titulo':
            campos = []
            ren = {c: next(x for x in renglones if abs(x['top'] - c) < 3) for c in (229, 279, 305, 330, 355)}
            campos += [texto('nombre', 0, 138, 227, 240, 13, 10),
                       sobre_linea('cuenta', 0, ren[229]['runs'][0], ren[229]['bottom'], a='C'),
                       sobre_linea('acentos', 0, ren[279]['runs'][0], ren[279]['bottom'], a='C'),
                       sobre_linea('licenciatura', 0, ren[305]['runs'][0], ren[305]['bottom']),
                       texto('telefono', 0, 140, 328, 135, 13, 10),
                       sobre_linea('correo_pers', 0, ren[330]['runs'][0], ren[330]['bottom'], s=9),
                       sobre_linea('ocupacion', 0, ren[355]['runs'][0], ren[355]['bottom'])]
        if clave == 'art5':
            campos = fecha_campos(pagina, 0)
            tabla = next(t for t in tablas(pagina) if 'Nombre' in (pagina.crop(t.cells[0]).extract_text() or ''))
            filas = celdas_tabla(tabla)
            campos += [en_celda('nombre', 0, filas[0][1]), en_celda('cuenta', 0, filas[1][1])]
            cajas = casillas(pagina)
            m = marcas_por_etiqueta(cajas, CARRERAS, 0, 'carrera')
            campos.append(m)
            m = marcas_por_etiqueta(cajas, SISTEMAS, 0, 'sistema')
            campos.append(m)
            campos.append({'k': 'motivos', 'tipo': 'parrafo', 'p': 0, 'cajas': [[75, 445 + i * 14, 455, 13] for i in range(7)], 's': 9})
        subdirector = fondo_en(ruta, cajas_subdirector(pdf))
    return {'plantilla': 'pdf/' + normalizar(PDFS[clave]), 'ancho': r(ancho), 'alto': r(alto),
            'campos': [c for c in campos if c], 'subdirector': subdirector, 'subdirector_impreso': NOMBRE_SUBDIRECTOR}


def main():
    DESTINO_PDF.mkdir(parents=True, exist_ok=True)
    DESTINO_DOCX.mkdir(parents=True, exist_ok=True)
    SPECS.parent.mkdir(parents=True, exist_ok=True)
    specs = {}
    for clave, archivo in PDFS.items():
        origen = ORIGEN / archivo
        if not origen.exists():
            alt = list(ORIGEN.glob(archivo.split('#')[0] + '*'))
            origen = alt[0] if alt else origen
        if not origen.exists():
            print('FALTA', archivo, file=sys.stderr)
            continue
        destino = DESTINO_PDF / normalizar(archivo)
        reescribir_pdf(origen, destino)
        specs[clave] = construir(clave, destino)
        print('ok', clave, len(specs[clave]['campos']), 'campos')
    for modalidad in ('Articulo', 'Ensayo', 'Memoria', 'Aplicacion', 'Autoempleo', 'Residencia', 'Tesina', 'Tesis'):
        shutil.copy(ORIGEN / ('Protocolo' + modalidad + '.docx'), DESTINO_DOCX / ('Protocolo' + modalidad + '.docx'))
    # Formatos en blanco que el pasante descarga tal cual (sin llenado automático).
    blancos = ['SolicitudPr#U00f3rrogaTrabEscrito.pdf', 'SolicitudCoasesoria.pdf', 'CartaPresentacion_v082025.docx',
               'CartaDeAutorizacionRI.pdf', 'CartaDeAutorizacionRI2Tesistas.pdf', 'CartaDeExcepcionRI.pdf',
               'CartaDeExcepcionRI2Tesistas.pdf', 'REP.pdf', 'avisoDePrivacidadFI.pdf',
               'ProtocoloCambioAsesor.docx', 'ProtocoloBajaTemaSustentante.docx', 'ProtocoloCambios.docx']
    destino_blanco = MODULO / 'plantillas' / 'blanco'
    destino_blanco.mkdir(parents=True, exist_ok=True)
    for nombre in blancos:
        origen = ORIGEN / nombre
        if not origen.exists():
            alt = list(ORIGEN.glob(nombre.split('#')[0] + '*'))
            origen = alt[0] if alt else origen
        if origen.exists():
            shutil.copy(origen, destino_blanco / normalizar(nombre))
        else:
            print('FALTA formato en blanco', nombre, file=sys.stderr)
    SPECS.write_text(json.dumps(specs, ensure_ascii=False, indent=1), encoding='utf-8')
    print('Escrito', SPECS)


if __name__ == '__main__':
    main()
