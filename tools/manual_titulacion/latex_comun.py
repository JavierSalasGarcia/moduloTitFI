"""Piezas comunes de los generadores LaTeX de los manuales (licenciatura y posgrado)."""
import json
import os
import re

ESC = {'\\': r'\textbackslash{}', '&': r'\&', '%': r'\%', '$': r'\$', '#': r'\#', '_': r'\_', '{': r'\{', '}': r'\}',
       '~': r'\textasciitilde{}', '^': r'\textasciicircum{}'}


def t(s):
    """Escapa texto para LaTeX y convierte «…» de botones en negritas solo si se pide con b()."""
    return ''.join(ESC.get(c, c) for c in str(s or ''))


def lista(items, enum=False):
    items = [i for i in items if i]
    if not items:
        return ''
    env = 'enumerate' if enum else 'itemize'
    return f'\\begin{{{env}}}\n' + ''.join(f'  \\item {i}\n' for i in items) + f'\\end{{{env}}}\n'


def lista_t(items, enum=False):
    return lista([t(i) for i in items], enum)


def recuadro(tipo, items):
    """tipo: tencuenta | algosalemal. items: frases ya en LaTeX."""
    items = [i for i in items if i]
    if not items:
        return ''
    cuerpo = lista(items) if len(items) > 1 else items[0] + '\n'
    return f'\\begin{{{tipo}}}\n{cuerpo}\\end{{{tipo}}}\n'


def que_vas_a_hacer(texto):
    return f'\\begin{{quevasahacer}}\n{texto}\n\\end{{quevasahacer}}\n'


def captura(nombre, info):
    """Una captura del manifiesto: de celular (angosta) o de escritorio."""
    pie = t(info.get('pie', ''))
    if info.get('movil'):
        return f'\\capturamovil{{{nombre}}}{{{pie}}}\n'
    return f'\\captura{{{nombre}}}{{{pie}}}\n'


def capturas_movil(pares):
    """Capturas de celular de dos en dos."""
    s = ''
    for i in range(0, len(pares), 2):
        if i + 1 < len(pares):
            (n1, a), (n2, b) = pares[i], pares[i + 1]
            s += f'\\capturasmovil{{{n1}}}{{{t(a["pie"])}}}{{{n2}}}{{{t(b["pie"])}}}\n'
        else:
            s += captura(*pares[i])
    return s


def documento(titulo, actor, que, cuerpo):
    """Manual completo: portada (título, actor, modalidad), índice y cuerpo."""
    return ('\\documentclass[11pt,a4paper]{article}\n\\input{preambulo}\n\\begin{document}\n'
            f'\\portada{{{t(titulo)}}}{{{t(actor)}}}{{{t(que)}}}\n'
            '{\\small\\tableofcontents}\n\\newpage\n' + cuerpo + '\\end{document}\n')


def escribir(base, ruta, contenido):
    ruta = os.path.join(base, ruta)
    os.makedirs(os.path.dirname(ruta), exist_ok=True)
    with open(ruta, 'w', encoding='utf-8', newline='\n') as fh:
        fh.write(contenido)


def manifiesto(img):
    try:
        m = json.load(open(os.path.join(img, '_capturas.json'), encoding='utf-8'))
    except Exception:
        return {}
    # Solo las que existen en disco.
    return {k: v for k, v in m.items() if os.path.exists(os.path.join(img, k + '.jpg'))}


def de(m, prefijo):
    """Capturas cuyo nombre empieza con el prefijo, en el orden en que se tomaron."""
    return sorted([(k, v) for k, v in m.items() if k.startswith(prefijo)], key=lambda x: x[1].get('orden', 0))


def sin_conteo_pasos(texto):
    """Regla del Subdirector: nunca «paso 3 de 41». Si un pie trae un conteo de pasos, se quita."""
    return re.sub(r'\b[Pp]aso \d+ de \d+\b', 'paso', texto)
