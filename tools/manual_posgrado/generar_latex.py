"""Genera las fuentes LaTeX de los manuales de Posgrado (evaluación de grado) en docs/manuales/posgrado/latex.

Misma estructura y preámbulo que los de licenciatura (docs/manuales/comun/preambulo.tex):
  preambulo.tex, actores/<actor>.tex, comun/<actor>_proceso.tex (el recorrido, igual en todas las modalidades),
  modalidades/<modalidad>/<actor>.tex (solo lo que cambia), consolidados/<actor>.tex,
  manuales/<actor>/<modalidad>.tex y manuales/<actor>/consolidado.tex.

Entradas: catalogo.json (lo exporta exportar_catalogo.php) y las capturas de recorrido.py (docs/manuales/posgrado/img).
Uso: python3 tools/manual_posgrado/generar_latex.py        (regenera todo; los textos se corrigen en este archivo)
"""
import glob
import json
import os
import shutil
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(AQUI), 'manual_titulacion'))
from latex_comun import captura, capturas_movil, de, documento, escribir, lista, lista_t, manifiesto, que_vas_a_hacer, recuadro, t  # noqa: E402

RAIZ = os.path.dirname(os.path.dirname(AQUI))
SALIDA = os.path.join(RAIZ, 'docs', 'manuales', 'posgrado', 'latex')
IMG = os.path.join(RAIZ, 'docs', 'manuales', 'posgrado', 'img')
CAT = json.load(open(os.path.join(AQUI, 'catalogo.json'), encoding='utf-8'))
MODS = CAT['modalidades']
M = manifiesto(IMG)

ACTORES = {
    'egresado': ('Manual del egresado', 'Egresado(a) de maestría, doctorado o especialidad'),
    'director': ('Manual del director de tesis', 'Director(a) de tesis (firma por enlace, sin cuenta)'),
    'sinodal': ('Manual del sinodal', 'Integrantes del sínodo, con o sin cuenta, internos o externos'),
    'coordinacion': ('Manual de la Coordinación de Estudios Avanzados', 'Titular de la Coordinación de Estudios Avanzados (firma los oficios)'),
    'apoyo_grado': ('Manual de Apoyo a Grado', 'Personal de Apoyo a Grado de la Coordinación de Estudios Avanzados'),
    'supervision': ('Manual de supervisión', 'Administración de la plataforma y Subdirección Académica (consulta)'),
}
ORDEN = list(ACTORES)
CONTACTO = ('\\section{Contacto}\nApoyo a Grado, Coordinación de Estudios Avanzados de la Facultad de Ingeniería. '
            'Los correos y el teléfono vigentes aparecen en cada trámite y en los correos de la plataforma '
            '(se configuran en \\emph{Posgrado} → \\emph{Configuración}).\n')

GENERAL = {
    'egresado': {
        'que': 'Capturas tus datos, subes tus requisitos en PDF, propones a tu sínodo y la fecha de tu examen, y sigues el avance en cuatro momentos. Tu director, tu sínodo y la Coordinación firman en línea; no imprimes ni recabas firmas. Siempre ves un solo siguiente paso.',
        'entrar': ['Entra a Traza FI con tu correo institucional de alumno de posgrado.',
                   'En el menú abre \\emph{Titulación} → \\emph{Posgrado: obtener mi grado}.',
                   'Antes de comenzar puedes abrir la página de cada modalidad y descargar su manual; la modalidad la eliges al capturar tus datos.'],
        'tencuenta': [f"La fecha del examen debe quedar al menos a {CAT['dias_minimos']} días hábiles; se sugieren {CAT['dias_sugeridos']}.",
                      'La firma electrónica UAEMéx (la del SICDE) de cada sinodal debe estar vigente al menos un mes después del examen; la plataforma avisa si alguna no alcanza.',
                      'Las fotografías, las tres memorias USB y el resumen impreso se entregan en persona el día del cotejo.',
                      'Solo puede haber un trámite activo. Si inicias otro, antes se te explica qué pasará con el que tienes.',
                      'Aprobado el examen, tienes 48 horas para registrar tu grado en el SICDE y capturar el folio.'],
        'algomal': ['\\textbf{Te devolvieron un documento.} Tu siguiente paso te lleva a él; lee la observación, corrige el PDF y pulsa \\boton{Reemplazar}.',
                    '\\textbf{Tu director o un sinodal no encuentra el correo.} En la lista de firmas puedes reenviar el enlace o corregir su correo.',
                    '\\textbf{Una firma UAEMéx no alcanza.} Pide a esa persona que la renueve en \\texttt{https://fise.uaemex.mx/} y actualice la fecha desde su enlace.',
                    '\\textbf{Elegiste mal o quieres dejar el trámite.} Al final de tu trámite abre \\emph{¿Elegiste otra modalidad o quieres dejar este trámite?}: si nadie más intervino se borra; si no, queda en pausa en \\emph{Abandonadas} y lo retomas cuando quieras.',
                    '\\textbf{Tu examen se aplazó.} El segundo momento se reabre: propones una nueva fecha y tu director y tu sínodo vuelven a firmar.'],
    },
    'director': {
        'que': 'Firmas en línea el voto aprobatorio y la solicitud de evaluación de grado de tu egresado, y recibes la notificación de la fecha del examen. Todo llega a tu correo con un enlace personal: no necesitas cuenta en Traza FI ni imprimir nada.',
        'entrar': ['Abre el enlace del correo de Traza FI (por ejemplo, «Voto aprobatorio · nombre del egresado»).',
                   'Si tienes cuenta con ese mismo correo, también encuentras tus firmas en \\emph{Posgrado: firmas}.'],
        'tencuenta': ['El enlace es personal y tiene fecha de vencimiento (se indica en el correo).', 'Antes de firmar puedes leer el documento completo.',
                      'Eres responsable de verificar que las firmas electrónicas del sínodo estén vigentes a la fecha del examen.',
                      'Después del examen, sube la tesis al Repositorio Institucional en los tres días siguientes.'],
        'algomal': ['Si el enlace venció, pide al egresado que te lo reenvíe desde su trámite.',
                    'Si el trámite está en pausa, el enlace lo indica y no permite firmar.',
                    'Si un dato del documento está mal, no firmes: avisa al egresado para que lo corrija; el documento se genera de nuevo.'],
    },
    'sinodal': {
        'que': 'Das tu visto bueno a la fecha del examen contestando tres preguntas y declarando la vigencia de tu firma electrónica UAEMéx, y después acusas de recibido tu oficio de invitación. Si eres externo y no tienes cuenta, todo se hace desde los enlaces de tu correo.',
        'entrar': ['Abre el enlace del correo de Traza FI (visto bueno y, después, invitación).',
                   'Con cuenta en Traza FI: \\emph{Titulación} → \\emph{Posgrado: firmas}. La vigencia de tu firma UAEMéx se guarda en \\emph{Mi perfil}.'],
        'tencuenta': ['La vigencia de tu firma electrónica UAEMéx debe cubrir al menos un mes después del examen; puedes verificarla en \\texttt{https://fise.uaemex.mx/}.',
                      'Con cuenta, la plataforma te avisa cuando a tu firma le falta un mes o menos para vencer.',
                      'Si eres externo a la UAEMéx, Apoyo a Grado verifica tu alta en Control Escolar antes de programar el examen.'],
        'algomal': ['Si contestas «No» a una pregunta del visto bueno, Apoyo a Grado revisa la propuesta con el egresado.',
                    'Si tu firma no alcanza, renuévala y actualiza la fecha desde el mismo enlace; no necesitas volver a firmar el visto bueno.',
                    'Si el enlace venció, pide al egresado que te lo reenvíe.'],
    },
    'coordinacion': {
        'que': 'Firmas en línea los oficios y constancias que emite Apoyo a Grado: no adeudo, cotejo, invitaciones al sínodo, acuse, notificación al director, constancias de revisores y de dirección, y el formato de integración de expediente.',
        'entrar': ['\\emph{Titulación} → \\emph{Posgrado: firmas}, sección \\emph{Por firmar como Coordinación de Estudios Avanzados}.',
                   'Firma la cuenta del correo configurado en \\emph{Posgrado} → \\emph{Configuración} o quien tenga rol titular en la Coordinación.'],
        'tencuenta': ['Revisa cada PDF con \\boton{Ver PDF} antes de firmar.', 'Puedes elegir varios documentos y firmarlos juntos; debes marcar que los revisaste.',
                      'Al firmar las invitaciones, cada integrante del sínodo recibe la suya por correo.'],
        'algomal': ['Si un oficio tiene un error, no lo firmes: pide a Apoyo a Grado que lo emita de nuevo.'],
    },
    'apoyo_grado': {
        'que': 'Validas o devuelves los requisitos en PDF, marcas las entregas en físico, registras el cotejo, verificas a los sinodales externos, programas el examen, emites los oficios y constancias (que firma la Coordinación), registras el resultado e integras el expediente.',
        'entrar': ['\\emph{Titulación} → \\emph{Posgrado: validar trámites} (bandeja).', 'Los datos que salen en los oficios (quién firma, contacto) se editan en \\emph{Posgrado} → \\emph{Configuración}.'],
        'tencuenta': ['El cotejo solo se registra con todos los requisitos validados o recibidos.',
                      'Programar con firmas faltantes, respuestas «No» o vigencias que no alcanzan exige escribir una justificación, que queda en la bitácora.',
                      'Un resultado «Aplazado» reabre el segundo momento para reprogramar.'],
        'algomal': ['Si el egresado necesita corregir sus datos antes del cotejo, anula el documento firmado con una justificación.',
                    'Si un sinodal contestó «No» en el visto bueno, puedes devolver la propuesta al egresado; se cancelan las firmas.',
                    'Si faltan acuses, puedes cerrar las invitaciones con una justificación.'],
    },
    'supervision': {
        'que': 'La administración de la plataforma y la Subdirección Académica consultan todo el módulo: la bandeja, cada expediente y las firmas pendientes de la Coordinación. La Subdirección solo consulta: no valida ni firma por la Coordinación.',
        'entrar': ['\\emph{Titulación} → \\emph{Posgrado: validar trámites} y \\emph{Posgrado: firmas}.'],
        'tencuenta': ['Lo que se ve en modo consulta es lo mismo que ve Apoyo a Grado.', 'Cada acción queda en la bitácora del trámite con quién y cuándo.'],
        'algomal': ['Si un trámite se detuvo, revisa en su expediente el siguiente paso y a quién le toca.'],
    },
}

QUE = {
    'director': 'firmar el voto aprobatorio y la solicitud de evaluación de grado',
    'sinodal': 'dar tu visto bueno a la fecha y acusar de recibido tu invitación',
    'coordinacion': 'firmar los oficios y constancias del trámite',
    'apoyo_grado': 'validar, cotejar, programar, emitir y cerrar el expediente',
    'supervision': 'consultar los trámites y las firmas',
}

# Secciones del recorrido por actor: (momento o '', título, explicación, tareas del manifiesto).
SECCIONES = {
    'egresado': [
        ('1', 'Momento 1. Reúne tus requisitos', 'Capturas tus datos (incluida la modalidad y los datos de tu director), pides el voto aprobatorio, firmas tu formato de datos y subes cada requisito en PDF. Apoyo a Grado los valida y, con los originales, hace el cotejo.', ['siguiente_1', 'datos', 'voto', 'firmar_datos', 'subir', 'devuelto', 'corregir']),
        ('2', 'Momento 2. Propón sínodo y fecha', 'Capturas a los cinco propietarios y los dos suplentes con su correo, la fecha y la hora. Al enviar, tu director firma la solicitud y cada integrante su visto bueno, desde su correo.', ['siguiente_2', 'sinodo', 'fecha', 'esperando', 'vigencia']),
        ('3', 'Momento 3. Invitaciones al sínodo', 'Apoyo a Grado emite las invitaciones, la Coordinación las firma y cada integrante acusa de recibido en línea.', ['acuses']),
        ('4', 'Momento 4. Examen y cierre', 'Presentas tu examen; aprobado, registras tu grado en el SICDE y capturas el folio. Apoyo a Grado emite las constancias e integra tu expediente.', ['sicde', 'concluido']),
    ],
    'director': [
        ('1', 'Firmar el voto aprobatorio', 'Recibes el enlace cuando el egresado lo pide. Lee el documento y pulsa \\boton{Firmar}; marca antes que lo leíste.', ['voto', 'voto_hecho']),
        ('2', 'Firmar la solicitud de evaluación de grado', 'Cuando el egresado envía su propuesta de sínodo y fecha, recibes la solicitud para firmarla.', ['solicitud']),
    ],
    'sinodal': [
        ('2', 'Dar el visto bueno', 'Contesta las tres preguntas (tesis concluida, entregada a usted, revisión en el periodo), escribe la vigencia de tu firma electrónica UAEMéx, marca que leíste el documento y firma.', ['visto_bueno', 'visto_bueno_externo']),
        ('2', 'Si tu firma UAEMéx no alcanza', 'El enlace te avisa y te deja actualizar la fecha; el egresado ve el aviso en su siguiente paso.', ['vigencia']),
        ('2', 'Con cuenta: «Posgrado: firmas»', 'Si tienes cuenta con el mismo correo, tus firmas pendientes y hechas están en \\emph{Posgrado: firmas}.', ['mis_firmas']),
        ('3', 'Acusar de recibido tu invitación', 'Con la invitación firmada por la Coordinación recibes un enlace: consulta tu oficio y pulsa \\boton{Acusar de recibido}.', ['acuse']),
        ('4', 'Después del examen', 'Tus firmas hechas quedan en \\emph{Posgrado: firmas}; tus constancias las emite Apoyo a Grado y las firma la Coordinación.', ['firmadas']),
        ('', 'La vigencia en «Mi perfil»', 'Con cuenta, declara la vigencia de tu firma electrónica UAEMéx en \\emph{Mi perfil}; la plataforma te avisa cuando le falte un mes o menos.', ['perfil']),
    ],
    'coordinacion': [
        ('1', 'Constancia de no adeudo y oficio de cotejo', 'En el primer momento firmas la constancia de no adeudo de mobiliario y equipo y el oficio de cotejo.', ['firmas_no_adeudo', 'firmas_cotejo']),
        ('3', 'Invitaciones, acuse y notificación', 'Con el examen programado firmas las invitaciones, el acuse y la notificación al director.', ['firmas_invitaciones']),
        ('4', 'Constancias e integración', 'Al cierre firmas las constancias de revisores y de dirección y el formato de integración de expediente.', ['firmas_cierre']),
    ],
    'apoyo_grado': [
        ('1', 'Bandeja y expediente', 'La bandeja cuenta los trámites por momento y los documentos por validar; cada folio abre su expediente.', ['bandeja', 'expediente']),
        ('1', 'Validar o devolver requisitos', 'Abre cada PDF y pulsa \\boton{Validar}, o escribe la observación y pulsa \\boton{Devolver}.', ['validar', 'devolver']),
        ('1', 'Entregas en físico, no adeudo y cotejo', 'Marca lo que recibes en persona, emite la constancia de no adeudo y registra el cotejo; el oficio de cotejo queda en firma de la Coordinación.', ['fisico', 'emitir_no_adeudo', 'cotejo']),
        ('2', 'Externos y programación', 'Con sinodales externos confirma su alta en Control Escolar. Con las firmas completas, programa fecha, hora, lugar y modalidad.', ['externos', 'programar']),
        ('3', 'Invitaciones', 'Emite las invitaciones, el acuse y la notificación al director; quedan en firma de la Coordinación.', ['emitir_invitaciones']),
        ('4', 'Resultado y cierre', 'Registra el resultado y el jurado, emite las constancias y el formato de integración y, con todo firmado, marca el expediente como integrado.', ['resultado', 'emitir_constancias_revisores', 'expediente_cierre']),
    ],
    'supervision': [
        ('', 'Bandeja en modo consulta', 'La administración y la Subdirección ven la bandeja completa.', ['bandeja']),
        ('', 'Expediente de un trámite', 'Cada expediente muestra requisitos, firmas, oficios y bitácora.', ['tramite']),
        ('', 'Firmas de la Coordinación', 'En \\emph{Posgrado: firmas} se ve lo que espera la firma de la Coordinación y lo firmado recientemente.', ['firmas']),
    ],
}


def caps(mod, actor):
    return de(M, f'{mod}_{actor}_')


def clave_tarea(v):
    ta = v.get('tarea', '')
    return f"{ta}_{v.get('etapa', '')}" if ta == 'siguiente' else ta


def proceso(actor, base='tesis'):
    """El recorrido del actor, igual en todas las modalidades (capturas de la modalidad Tesis)."""
    lst = caps(base, actor)
    s = '\\section{El recorrido, momento por momento}\nEl trámite avanza en cuatro momentos: ' + ', '.join(f"{n}. {t(m['titulo'])}" for n, m in CAT['momentos'].items()) + '. Es igual en todas las modalidades; las pantallas son de la modalidad \\emph{Tesis}.\n'
    usadas = set()
    for mom, titulo, texto, tareas in SECCIONES[actor]:
        sel = [(n, v) for n, v in lst if clave_tarea(v) in tareas and not v.get('movil') and n not in usadas]
        s += f'\\subsection{{{t(titulo)}}}\n{texto}\n'
        if actor == 'egresado' and mom == '1':
            s += requisitos_tex()
        for n, v in sel:
            s += captura(n, v)
            usadas.add(n)
    if actor == 'egresado':
        mov = [(n, v) for n, v in lst if v.get('movil')]
        if mov:
            s += '\\section{Desde el celular}\nEl trámite funciona igual en el teléfono: el siguiente paso arriba y los momentos plegados.\n' + capturas_movil(mov)
    return s


def requisitos_tex():
    por = {}
    for k, r in CAT['requisitos'].items():
        extra = ' (solo doctorado)' if r['aplica'] == 'doctorado' else (' (solo si aplica el art. 53 del REA)' if r['aplica'] == 'art53' else '')
        modo = {'fisico': ' — en persona', 'firma': ' — se firma en línea', 'generado': ' — lo genera la plataforma'}.get(r['tipo'], '')
        por.setdefault(r['grupo'], []).append(t(r['nombre'] + extra + modo))
    s = '\\subsubsection*{Tus requisitos}\n\\begin{multicols}{2}\\small\n'
    for g, nombre in CAT['grupos'].items():
        if por.get(g):
            s += f'\\textbf{{{t(nombre)}}}\n' + lista(por[g])
    return s + '\\end{multicols}\n'


def entrada_modalidad(mod):
    """Lo propio de la modalidad para el egresado: elegirla, su página y cómo cambian los documentos."""
    d = MODS[mod]
    s = f"\\section{{La modalidad: {t(d['nombre'])}}}\n{t(d['descripcion'])}\n\n"
    s += ('\\subsection{Conocer la modalidad y comenzar}\nEn \\emph{Posgrado: obtener mi grado} está la lista de modalidades; cada una abre su página con los requisitos en resumen y los manuales, sin iniciar nada. '
          'Para comenzar, acepta el aviso de privacidad y pulsa \\boton{Comenzar mi trámite de grado}. La modalidad se elige después, en tus datos.\n')
    for n, v in caps(mod, 'egresado'):
        if v.get('tarea') in ('entrada', 'informativa', 'comenzar', 'requisitos') or (v.get('tarea') == 'datos' and mod != 'tesis'):
            s += captura(n, v)
    return s


def diferencia(mod, actor):
    d = MODS[mod]
    s = f"\\section{{Lo que cambia en «{t(d['nombre'])}»}}\n"
    s += f"El recorrido es el mismo en todas las modalidades. En los oficios y formatos el trabajo se nombra «{t(d['trabajo'])}», y en el formato de integración de expediente se marca la casilla de esta modalidad.\n"
    if mod == 'articulos':
        s += 'En la prueba, esta modalidad se recorrió con una egresada de doctorado: en doctorado se agrega como requisito la evidencia de artículo aceptado o capítulo de libro, y el certificado y la cédula de maestría.\n'
    if actor in ('director', 'egresado') and mod != 'tesis':
        for n, v in caps(mod, 'director'):
            s += captura(n, v)
    return s


def pieza_actor(a):
    g = GENERAL[a]
    return ('\\section{Antes de empezar}\n\\subsection*{Cómo entras}\n' + lista(g['entrar'], enum=True)
            + recuadro('tencuenta', g['tencuenta']) + recuadro('algosalemal', g['algomal']))


def dejar():
    c = de(M, 'comun_egresado_')
    s = ('\\section{Cambiar de modalidad: borrar, dejar en pausa y retomar}\nSolo puede haber un trámite activo. Al final de tu trámite está \\emph{¿Elegiste otra modalidad o quieres dejar este trámite?}. '
         'Si nadie más ha intervenido, el trámite \\textbf{se borra}; si ya intervino alguien (tu director firmó, Apoyo a Grado validó…), \\textbf{queda en pausa} en \\emph{Abandonadas}, conserva todo y lo retomas con \\boton{Retomar}.\n')
    for n, v in c:
        s += captura(n, v)
    return s


def que_hacer(a, mod=None):
    g = GENERAL[a]['que']
    if mod and a in QUE:
        g += f" En la modalidad \\emph{{{t(MODS[mod]['nombre'])}}} te toca {QUE[a]}."
    return que_vas_a_hacer(g)


def generar():
    for d in ('actores', 'modalidades', 'consolidados', 'manuales', 'comun'):
        shutil.rmtree(os.path.join(SALIDA, d), ignore_errors=True)
    escribir(SALIDA, 'preambulo.tex', '% Manuales de Posgrado. Generado por tools/manual_posgrado/generar_latex.py\n'
             '\\newcommand{\\nivelmanual}{Posgrado: evaluación de grado}\n\\input{../../comun/preambulo}\n\\graphicspath{{../img/}{../../comun/}}\n')
    escribir(SALIDA, 'comun/contacto.tex', CONTACTO)
    escribir(SALIDA, 'comun/egresado_dejar.tex', dejar())
    for a in ORDEN:
        escribir(SALIDA, f'actores/{a}.tex', pieza_actor(a))
        escribir(SALIDA, f'comun/{a}_proceso.tex', proceso(a))
        for mod in MODS:
            cuerpo = (entrada_modalidad(mod) if a == 'egresado' else '') + diferencia(mod, a)
            escribir(SALIDA, f'modalidades/{mod}/{a}.tex', cuerpo)
            titulo, para = ACTORES[a]
            partes = (que_hacer(a, mod) + f'\\input{{actores/{a}}}\n\\input{{modalidades/{mod}/{a}}}\n\\input{{comun/{a}_proceso}}\n'
                      + ('\\input{comun/egresado_dejar}\n' if a == 'egresado' else '') + '\\input{comun/contacto}\n')
            escribir(SALIDA, f'manuales/{a}/{mod}.tex', documento(titulo, para, MODS[mod]['nombre'], partes))
        cons = '\\section{Las modalidades}\n' + lista([f"\\textbf{{{t(d['nombre'])}}}: {t(d['descripcion'])}" for d in MODS.values()])
        cons += '\\input{comun/' + a + '_proceso}\n\\section{Lo que cambia según la modalidad}\n'
        for mod, d in MODS.items():
            cons += f"\\subsection{{{t(d['nombre'])}}}\nEn los oficios el trabajo se nombra «{t(d['trabajo'])}». " + ('Recorrida en la prueba con doctorado: se agrega la evidencia de artículo aceptado o capítulo de libro.' if mod == 'articulos' else '') + '\n'
        escribir(SALIDA, f'consolidados/{a}.tex', cons)
        titulo, para = ACTORES[a]
        partes = que_hacer(a) + f'\\input{{actores/{a}}}\n\\input{{consolidados/{a}}}\n' + ('\\input{comun/egresado_dejar}\n' if a == 'egresado' else '') + '\\input{comun/contacto}\n'
        escribir(SALIDA, f'manuales/{a}/consolidado.tex', documento(titulo, para, 'Todas las modalidades', partes))


if __name__ == '__main__':
    generar()
    print('Generado en', SALIDA, '·', len(glob.glob(os.path.join(SALIDA, 'manuales', '*', '*.tex'))), 'manuales ·', len(M), 'capturas')
