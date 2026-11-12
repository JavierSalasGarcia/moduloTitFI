"""Genera los archivos LaTeX modulares de los manuales de Titulación (docs/manuales/titulacion/latex).

Un archivo por actor y por opción de titulación (modalidades/<opción>/<actor>.tex), un archivo de generalidades por actor
(actores/<actor>.tex) y los manuales que los ensamblan (manual_<actor>.tex y manual_opcion_<opción>.tex). Los .tex se
editan a mano después; este generador solo da el arranque y puede volver a correrse con --forzar para regenerar lo que no
se haya editado (por defecto no sobrescribe archivos existentes).
"""
import glob
import json
import os
import re
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
RAIZ = os.path.dirname(os.path.dirname(AQUI))
SALIDA = os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'latex')
IMG = os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'img')
FORZAR = '--forzar' in sys.argv

CAT = json.load(open(os.path.join(AQUI, 'catalogo.json'), encoding='utf-8'))

ACTORES = {
    'pasante': 'Pasante (estudiante)',
    'responsable_titulacion': 'Responsable de Titulación',
    'subdirector': 'Subdirector Académico',
    'coordinador_carrera': 'Coordinador de Carrera (Docencia)',
    'comision_protocolos': 'Comisión de revisión de protocolos',
    'asesor': 'Asesor',
    'coasesor': 'Coasesor',
    'sinodal': 'Sinodal (revisor y jurado)',
    'servicios_escolares': 'Servicios Escolares (Administración Central)',
}
SIN_TRABAJO = ['pasante', 'responsable_titulacion', 'subdirector', 'servicios_escolares']
CON_TRABAJO = list(ACTORES)
# Opciones cuyo recorrido completo se ejecutó con capturas reales.
EJECUTADAS = {m for m in CAT if glob.glob(os.path.join(IMG, f'{m}_pasante_06_concluido.png'))}

ESC = {'\\': r'\textbackslash{}', '&': r'\&', '%': r'\%', '$': r'\$', '#': r'\#', '_': r'\_', '{': r'\{', '}': r'\}', '~': r'\textasciitilde{}', '^': r'\textasciicircum{}'}


def t(s):
    return ''.join(ESC.get(c, c) for c in str(s or ''))


def escribir(ruta, contenido):
    ruta = os.path.join(SALIDA, ruta)
    os.makedirs(os.path.dirname(ruta), exist_ok=True)
    if os.path.exists(ruta) and not FORZAR:
        return
    open(ruta, 'w', encoding='utf-8', newline='\n').write(contenido)


def figuras(mod, prefijo, etiqueta):
    """Capturas existentes de la opción y el actor (orden por nombre)."""
    out = []
    for f in sorted(glob.glob(os.path.join(IMG, f'{mod}_{prefijo}_*.png'))):
        n = os.path.splitext(os.path.basename(f))[0]
        if re.search(r'ronda(?!0$)', n) and not n.endswith('ronda1'):
            continue
        out.append(n)
    return out


def captura(n, pie):
    return f'\\captura{{{n}}}{{{t(pie)}}}\n'


def pendiente(desc):
    return f'\\capturapendiente{{{t(desc)}}}\n'


def lista(items):
    if not items:
        return ''
    return '\\begin{itemize}\n' + ''.join(f'  \\item {t(i)}\n' for i in items) + '\\end{itemize}\n'


# ----------------------------------------------------------------------------------------------- preámbulo y macros
PREAMBULO = r"""% Preámbulo común de los manuales de Titulación (módulo de Traza FI).
\usepackage[spanish,es-noshorthands]{babel}
\usepackage[T1]{fontenc}
\usepackage[utf8]{inputenc}
\usepackage{lmodern}
\usepackage[margin=2.3cm]{geometry}
\usepackage{graphicx}
\usepackage{xcolor}
\usepackage{longtable}
\usepackage{booktabs}
\usepackage{enumitem}
\usepackage{fancyhdr}
\usepackage[hidelinks]{hyperref}
\usepackage{float}
\definecolor{uaemex}{HTML}{1F5E3B}
\definecolor{aviso}{HTML}{8A6A00}
\graphicspath{{../img/}{../../img/}{img/}}
\setlength{\parindent}{0pt}
\setlength{\parskip}{0.6em}
\pagestyle{fancy}
\fancyhf{}
\fancyhead[L]{\small Manual de Titulación de Licenciatura}
\fancyhead[R]{\small \leftmark}
\fancyfoot[C]{\thepage}
\setlist{itemsep=2pt}

% Captura de pantalla real, con pie.
\newcommand{\captura}[2]{%
  \begin{figure}[H]\centering
    \includegraphics[width=\linewidth,height=0.42\textheight,keepaspectratio]{#1}
    \caption{#2}
  \end{figure}}
% Recuadro para una captura que aún no se ha tomado.
\newcommand{\capturapendiente}[1]{%
  \begin{center}\fbox{\parbox{0.9\linewidth}{\color{aviso}\small\textbf{Captura pendiente.} #1}}\end{center}}
% Aviso destacado.
\newcommand{\aviso}[1]{\begin{center}\fcolorbox{uaemex}{white}{\parbox{0.92\linewidth}{\small #1}}\end{center}}
"""


def portada(titulo, subtitulo):
    return (r'\title{\textcolor{uaemex}{%s}\\[4pt]\large %s}' % (t(titulo), t(subtitulo))
            + '\n\\author{Facultad de Ingeniería, UAEMéx\\\\Plataforma Traza FI}\n\\date{Noviembre de 2026}\n')


def documento(titulo, subtitulo, cuerpo):
    return ('\\documentclass[11pt,a4paper]{article}\n\\input{preambulo}\n' + portada(titulo, subtitulo)
            + '\\begin{document}\n\\maketitle\n\\tableofcontents\n\\newpage\n' + cuerpo + '\\end{document}\n')


# ----------------------------------------------------------------------------------------------- generalidades por actor
GENERAL = {
    'pasante': ('Qué hace el pasante en la plataforma',
                ['Inicias sesión con tu correo institucional en la plataforma y entras a \\emph{Titulación $\\rightarrow$ Licenciatura}.',
                 'Eliges tu opción de titulación, aceptas las condiciones e inicias el trámite; el sistema te guía etapa por etapa.',
                 'Capturas tus datos una sola vez; la plataforma llena y firma electrónicamente tus formatos.',
                 'Subes cada documento en PDF (legible, a color, máximo 5 MB). Si algo se devuelve con observaciones, lo corriges y lo vuelves a subir.',
                 'Consultas en \\emph{Estado} y en la \\emph{Ruta del trámite} qué falta y de quién es el turno.',
                 'Al concluir, el Departamento de Titulación integra tu expediente y lo envía a Servicios Escolares para la expedición del título.']),
    'responsable_titulacion': ('Qué hace el responsable de Titulación',
                               ['Entra a \\emph{Titulación $\\rightarrow$ Validar trámites}: la bandeja muestra los documentos por validar.',
                                'Valida o devuelve cada documento con observaciones; coteja los originales cuando el documento lo exige.',
                                'Registra los hitos del departamento (fechas, oficios, resultado y número de acta).',
                                'Al concluir la evaluación, entra a \\emph{Expedientes a Servicios Escolares}, verifica el expediente con el formato de integración y exporta la carpeta comprimida para Servicios Escolares.']),
    'subdirector': ('Qué hace el Subdirector Académico',
                    ['Entra a \\emph{Titulación $\\rightarrow$ Firmas pendientes}.',
                     'Revisa los formatos y oficios que llevan su nombre y los firma con sello electrónico (uno por uno o todos los pendientes).',
                     'Puede registrar un asistente que firme en su nombre; la bitácora conserva quién firmó realmente.']),
    'coordinador_carrera': ('Qué hace el Coordinador de Carrera (Docencia)',
                            ['Recibe por correo un enlace personal cuando un pasante de su licenciatura solicita sínodo o jurado.',
                             'Ratifica o modifica las personas propuestas, y firma la asignación desde el enlace.',
                             'Dictamina la afinidad en la opción de créditos en estudios avanzados.']),
    'comision_protocolos': ('Qué hace la comisión de revisión de protocolos',
                            ['El encargado de comisión (\\emph{Mi comité de revisión}) recibe el protocolo que turnó Titulación y elige a los evaluadores.',
                             'Cada evaluador recibe un enlace personal con fecha límite, redacta recomendaciones de forma y fondo y firma.',
                             'Con todas las firmas, el encargado consolida y firma el dictamen; el pasante solo ve el dictamen consolidado.']),
    'asesor': ('Qué hace el asesor',
               ['Recibe por correo un enlace personal para dar el Vo.\\,Bo. a las solicitudes del pasante (protocolo, revisión, sustentación).',
                'Firma el voto aprobatorio junto con los revisores.',
                'Sus constancias de director se generan solas al avanzar el trámite.']),
    'coasesor': ('Qué hace el coasesor',
                 ['Si el protocolo registra coasesoría, recibe el mismo tipo de enlaces que el asesor para los formatos que le corresponden.',
                  'Sus constancias de codirector se generan solas al avanzar el trámite.']),
    'sinodal': ('Qué hace el sinodal',
                ['Recibe por correo el oficio de designación y confirma su recibo (acuse) desde su enlace.',
                 'Como revisor, lee el trabajo escrito y firma el voto aprobatorio.',
                 'Como jurado, participa en el examen definitivo; sus constancias se generan en \\emph{Mis constancias}.']),
    'servicios_escolares': ('Qué recibe Servicios Escolares',
                            ['No usa la plataforma: recibe del Departamento de Titulación una carpeta comprimida por lote.',
                             'Cada expediente trae los documentos en el orden del formato de integración, el formato llenado y un índice con la huella SHA-256 de cada archivo.',
                             'Con ella se inicia la expedición del título profesional.']),
}


def generalidades():
    for k, (titulo, pasos) in GENERAL.items():
        cuerpo = f'\\section{{{t(ACTORES[k])}}}\n\\subsection{{{t(titulo)}}}\n' + '\\begin{enumerate}\n' + ''.join(f'  \\item {p}\n' for p in pasos) + '\\end{enumerate}\n'
        cuerpo += '\\aviso{Cuentas, correos y datos de los ejemplos son ficticios y provienen de una base de pruebas.}\n'
        escribir(f'actores/{k}.tex', cuerpo)


# ----------------------------------------------------------------------------------------------- contenido por opción y actor
def etapas_con_docs(m):
    return [e for e in m['etapas'] if e['clave'] != 'datos']


def tabla_docs(docs):
    if not docs:
        return ''
    s = '\\begin{longtable}{p{0.55\\linewidth}p{0.35\\linewidth}}\n\\toprule\n\\textbf{Documento} & \\textbf{Observaciones}\\\\\n\\midrule\n\\endhead\n'
    for d in docs:
        obs = d['nota'] or ''
        if d['condicion']:
            obs = (obs + ' ' if obs else '') + d['condicion']
        if d['generado']:
            obs = ('La plataforma lo genera. ' + obs).strip()
        s += f"{t(d['nombre'])} & {t(obs)}\\\\\n"
    return s + '\\bottomrule\n\\end{longtable}\n'


def para_pasante(mod, m):
    p = f"\\subsection{{{t(m['nombre'])}}}\n{t(m['resumen'])} ({t(m['art'])}).\n\n\\subsubsection*{{Requisitos}}\n" + lista(m['requisitos'])
    fig = {os.path.basename(f)[len(mod) + 9:-4]: os.path.splitext(os.path.basename(f))[0] for f in glob.glob(os.path.join(IMG, f'{mod}_pasante_*.png'))}
    real = mod in EJECUTADAS

    def f(clave, pie, desc):
        k = next((x for x in fig if x.startswith(clave)), None)
        return captura(fig[k], pie) if k else pendiente(desc)

    p += f('01', 'Opciones de titulación en «Licenciatura».', 'Pantalla de opciones de titulación.')
    p += f('02', 'Requisitos de la opción y aceptación para iniciar.', 'Requisitos de la opción.')
    for n, e in enumerate(m['etapas'], 1):
        p += f"\\subsubsection*{{Etapa {n}. {t(e['titulo'])}}}\n{t(e['descripcion'])}\n"
        if e['campos']:
            p += '\nDatos que se capturan:\n' + lista(e['campos'])
        if e['formatos']:
            p += '\nLa plataforma llena los formatos: ' + ', '.join(t(x) for x in e['formatos']) + ' y los firma por ti.\n'
        if e['docs']:
            p += '\nDocumentos (PDF):\n' + tabla_docs(e['docs'])
        if e['confirmar']:
            p += f"\nAl terminar, confirma: «{t(e['confirmar'])}».\n"
        if e['agenda']:
            p += '\nElige fecha y hora, propone a tu sínodo o jurado y envía la solicitud al Coordinador de Docencia.\n'
        if e['personal']:
            p += f"\n\\emph{{Turno del departamento:}} {t(e['personal']['titulo'])}. {t(e['personal']['descripcion'])}\n"
        if n == 1:
            p += f('04', 'Captura de datos del pasante y apertura del trámite.', 'Captura de datos del pasante.')
        elif n == 2:
            p += f('05', 'Etapa en curso: formatos firmados y documentos por subir.', 'Etapa en curso del trámite.')
    p += '\n\\subsubsection*{Seguimiento y conclusión}\n' + f('06', 'Trámite concluido.', 'Trámite concluido.') + f('07', 'Ruta del trámite con los pasos hechos.', 'Ruta del trámite.')
    if not real:
        p += '\\aviso{Esta opción aún no se ha recorrido en la prueba de extremo a extremo con capturas; el texto sale del catálogo del sistema.}\n'
    return p


def para_responsable(mod, m):
    real = mod in EJECUTADAS
    p = f"\\subsection{{{t(m['nombre'])}}}\n"
    p += 'Bandeja y expediente del trámite.\n' + (captura(f'{mod}_titulacion_02_bandeja', 'Bandeja de validación de trámites.') if real else pendiente('Bandeja de validación.'))
    p += (captura(f'{mod}_titulacion_01_expediente_ronda0', 'Expediente del trámite con documentos por validar.') if real else pendiente('Expediente del trámite.'))
    for n, e in enumerate(etapas_con_docs(m), 1):
        p += f"\\subsubsection*{{{t(e['titulo'])}}}\n"
        if e['docs']:
            p += 'Valida o devuelve con observaciones cada documento; coteja el original cuando se indique.\n' + tabla_docs(e['docs'])
        if e['personal']:
            p += f"\n{t(e['personal']['titulo'])}. {t(e['personal']['descripcion'])}\n"
            if e['personal']['campos']:
                p += 'Datos que registras:\n' + lista(e['personal']['campos'])
            if e['personal']['entrega']:
                p += f"Se genera: {t(e['personal']['entrega'])}, que firma la Subdirección.\n"
    p += '\\subsubsection*{Verificación final del expediente}\n' + t(m['titulo_exp']).title() + '.\n\n'
    p += 'Documentos que verifica el responsable, en el orden del formato oficial:\n\\begin{enumerate}\n'
    for r in m['renglones']:
        origen = 'plataforma' if r['doc'] or r['generado'] else 'físico'
        extra = ' (sólo si aplica)' if r['cond'] else ''
        p += f"  \\item {t(r['texto'])} --- \\emph{{{origen}{extra}}}\n"
    p += '\\end{enumerate}\n'
    for n, pie in [('verificacion_01', 'Lista de verificación final con estado de cada renglón.'), ('verificacion_02', 'Expediente verificado y exportación para Servicios Escolares.')]:
        p += captura(f'{mod}_titulacion_{n}', pie) if os.path.exists(os.path.join(IMG, f'{mod}_titulacion_{n}.png')) else pendiente(pie)
    p += ('Marca con «Entregado y revisado» lo que se entrega en físico y con «No aplica» lo condicional; con todo cubierto, pulsa '
          '«Hago constar que el expediente está completo y verificado». Después, en \\emph{Expedientes a Servicios Escolares}, '
          'elige los verificados y pulsa «Exportar carpeta comprimida para Servicios Escolares».\n')
    return p


def para_subdirector(mod, m):
    real = mod in EJECUTADAS
    docs = [d['nombre'] for e in m['etapas'] for d in e['docs'] if d['generado']]
    ofi = [e['personal']['entrega'] for e in m['etapas'] if e['personal'] and e['personal']['entrega']]
    p = f"\\subsection{{{t(m['nombre'])}}}\nDocumentos de esta opción que esperan tu firma electrónica:\n" + lista(docs + ofi)
    p += (captura(f'{mod}_subdirector_01_firmas_ronda0', 'Bandeja de firmas del Subdirector.') if real else pendiente('Bandeja «Firmas pendientes».'))
    return p


def para_servicios(mod, m):
    p = f"\\subsection{{{t(m['nombre'])}}}\nEl expediente de esta opción llega como carpeta comprimida con este contenido, en el orden del formato «{t(m['titulo_exp']).title()}»:\n\\begin{{enumerate}}\n"
    for r in m['renglones']:
        p += f"  \\item {t(r['texto'])}{' (sólo si aplica)' if r['cond'] else ''}\n"
    return p + '\\end{enumerate}\nIncluye además el índice con la huella SHA-256 de cada archivo y la relación de envío del lote.\n'


def para_otro(actor):
    textos = {
        'coordinador_carrera': 'Recibe el correo con el enlace personal, revisa a las personas propuestas (sínodo para el examen previo; jurado para el examen definitivo), las ratifica o las cambia y firma la asignación. En créditos en estudios avanzados dictamina la afinidad de los estudios.',
        'comision_protocolos': 'Recibe el protocolo turnado por Titulación, asigna evaluadores con fecha límite (siete días hábiles por defecto), da seguimiento con el semáforo, y consolida y firma el dictamen cuando todos firmaron.',
        'asesor': 'Firma desde su enlace personal el protocolo, la solicitud de revisión, el voto aprobatorio y la solicitud de sustentación.',
        'coasesor': 'Si el protocolo registra coasesoría, firma desde su enlace los formatos que le corresponden.',
        'sinodal': 'Confirma el recibo del oficio de designación, revisa el trabajo escrito, firma el voto aprobatorio y participa en el examen.',
    }
    return textos[actor]


def modalidades():
    for mod, m in CAT.items():
        actores = CON_TRABAJO if m['tipo'] == 'con_trabajo' else SIN_TRABAJO
        for a in actores:
            if a == 'pasante':
                c = para_pasante(mod, m)
            elif a == 'responsable_titulacion':
                c = para_responsable(mod, m)
            elif a == 'subdirector':
                c = para_subdirector(mod, m)
            elif a == 'servicios_escolares':
                c = para_servicios(mod, m)
            else:
                c = f"\\subsection{{{t(m['nombre'])}}}\n{t(para_otro(a))}\n" + pendiente(f'Pantalla de {ACTORES[a]} para esta opción.')
            escribir(f'modalidades/{mod}/{a}.tex', c)


def participa(a, m):
    return a in (CON_TRABAJO if m['tipo'] == 'con_trabajo' else SIN_TRABAJO)


def docs_de(m):
    """Nombres de los documentos (pasante) y de los renglones del expediente final de una opción."""
    d = [x['nombre'] for e in m['etapas'] for x in e['docs']]
    return d, [r['texto'] for r in m['renglones']]


def consolidado(a):
    """Pasos comunes unificados de un actor en todas las opciones, y particularidades de cada una."""
    mods = {k: m for k, m in CAT.items() if participa(a, m)}
    cuerpo = f"\\section{{{t(ACTORES[a])}: visión global}}\nEste manual reúne lo que el actor hace en \\textbf{{todas}} las opciones de titulación y señala, al final, lo particular de cada una. Para el detalle paso a paso de una opción, consulta el manual específico de esa opción.\n"
    nombres = [m['nombre'] for m in mods.values()]
    cuerpo += '\n\\subsection{Opciones en las que participa}\n' + lista(nombres)
    if a == 'responsable_titulacion':
        comunes = None
        for m in mods.values():
            ds = set(docs_de(m)[0])
            comunes = ds if comunes is None else comunes & ds
        cuerpo += '\n\\subsection{Pasos comunes a todas las opciones}\n\\begin{enumerate}\n'
        cuerpo += '  \\item Entrar a \\emph{Titulación $\\rightarrow$ Validar trámites} y abrir el expediente del pasante.\n'
        cuerpo += '  \\item Validar o devolver con observaciones cada documento en PDF; cotejar el original cuando se indique.\n'
        cuerpo += '  \\item Registrar los hitos del departamento (fecha y lugar de la toma de protesta o del examen, oficios y, al final, resultado y número de acta).\n'
        cuerpo += '  \\item Cuando la Subdirección firma los oficios y formatos que le corresponden, el trámite avanza solo.\n'
        cuerpo += '  \\item Al concluir con resultado favorable: \\emph{Expedientes a Servicios Escolares} $\\rightarrow$ verificar con el formato de integración $\\rightarrow$ exportar la carpeta comprimida.\n\\end{enumerate}\n'
        cuerpo += '\n\\subsection{Documentos que se validan en todas las opciones}\n' + lista(sorted(comunes or []))
        cuerpo += '\n\\subsection{Verificación final: renglones comunes}\n'
        rc = None
        for m in mods.values():
            r = set(docs_de(m)[1])
            rc = r if rc is None else rc & r
        cuerpo += lista(sorted(rc or []))
        cuerpo += '\n\\subsection{Particularidades por opción}\n'
        for k, m in mods.items():
            ds, rs = docs_de(m)
            extra = [x for x in ds if x not in (comunes or set())]
            extra_r = [x for x in rs if x not in (rc or set())]
            cuerpo += f"\\subsubsection*{{{t(m['nombre'])}}}\nDocumentos propios de la opción:\n" + lista(extra)
            if extra_r:
                cuerpo += 'Renglones propios en la verificación final:\n' + lista(extra_r)
        return cuerpo
    if a == 'subdirector':
        cuerpo += '\n\\subsection{Pasos comunes}\n\\begin{enumerate}\n  \\item Entrar a \\emph{Titulación $\\rightarrow$ Firmas pendientes}.\n  \\item Revisar cada formato u oficio (\\emph{Ver}) y firmarlo; pueden firmarse seleccionados o todos los pendientes.\n  \\item La firma estampa un sello electrónico con nombre, cargo, folio y huella; un asistente puede firmar en su nombre.\n\\end{enumerate}\n'
        cuerpo += '\n\\subsection{Particularidades por opción}\n'
        for k, m in mods.items():
            docs = [d['nombre'] for e in m['etapas'] for d in e['docs'] if d['generado']] + [e['personal']['entrega'] for e in m['etapas'] if e['personal'] and e['personal']['entrega']]
            cuerpo += f"\\subsubsection*{{{t(m['nombre'])}}}\n" + lista(docs)
        return cuerpo
    if a == 'servicios_escolares':
        cuerpo += '\n\\subsection{Qué se recibe siempre}\n' + lista(['Una carpeta comprimida por lote, con una carpeta por expediente.', 'Los documentos numerados en el orden del formato de integración de expediente.', 'El formato de integración llenado y el formato de revisión.', 'Un índice con la huella SHA-256 de cada archivo y la relación de envío.'])
        cuerpo += '\n\\subsection{Particularidades por opción}\n'
        for k, m in mods.items():
            cuerpo += f"\\subsubsection*{{{t(m['nombre'])}}}\nFormato: {t(m['titulo_exp']).title()}.\n"
        return cuerpo
    cuerpo += f"\n\\subsection{{Pasos comunes}}\n{t(para_otro(a))}\n"
    cuerpo += '\n\\subsection{Particularidades por opción}\nParticipa en las opciones con trabajo escrito; el flujo es el mismo en todas, con la diferencia del tipo de trabajo y de las evidencias propias de cada una:\n'
    for k, m in mods.items():
        ev = [d['nombre'] for e in m['etapas'] if e['clave'] == 'final' for d in e['docs'] if d['nombre'] not in ('Trabajo escrito final en PDF',)][:0]
        cuerpo += f"\\subsubsection*{{{t(m['nombre'])}}}\n{t(m['resumen'])}\n"
    return cuerpo


def manuales():
    escribir('preambulo.tex', PREAMBULO)
    # Pasante: un manual por opción, solo con esa opción.
    for mod, m in CAT.items():
        cuerpo = '\\input{actores/pasante}\n' + f'\\input{{modalidades/{mod}/pasante}}\n'
        escribir(f'manuales/pasante/{mod}.tex', documento(f"Guía del pasante: {m['nombre']}", 'Titulación de licenciatura', cuerpo))
    # Demás actores: un manual por opción (autocontenido) y uno global.
    for a, nombre in ACTORES.items():
        if a == 'pasante':
            continue
        titulo = f'Manual del {nombre}' if not nombre.startswith('Servicios') else f'Guía de {nombre}'
        for mod, m in CAT.items():
            if participa(a, m):
                escribir(f'manuales/{a}/{mod}.tex', documento(f"{titulo}: {m['nombre']}", 'Titulación de licenciatura', f'\\input{{actores/{a}}}\n\\input{{modalidades/{mod}/{a}}}\n'))
        escribir(f'consolidados/{a}.tex', consolidado(a))
        escribir(f'manuales/{a}/global.tex', documento(f'{titulo}: manual global', 'Todas las opciones de titulación', f'\\input{{actores/{a}}}\n\\input{{consolidados/{a}}}\n'))


if __name__ == '__main__':
    generalidades()
    modalidades()
    manuales()
    print('Generado en', SALIDA, '| opciones con capturas reales:', sorted(EJECUTADAS))
