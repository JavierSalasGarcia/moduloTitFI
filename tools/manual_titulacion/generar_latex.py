"""Genera las fuentes LaTeX de los manuales de Titulación de licenciatura (docs/manuales/titulacion/latex).

Entradas: catalogo.json (etapas, documentos y renglones del expediente por opción; lo regenera exportar_catalogo.php) y
las capturas del recorrido (img/*.jpg con img/_capturas.json; las toma recorrido.py).

Salidas (se REGENERAN completas en cada corrida; los textos se corrigen aquí, en los diccionarios de este archivo):
  preambulo.tex                        nivel + \\input del preámbulo común (docs/manuales/comun/preambulo.tex)
  actores/<actor>.tex                  lo que hace el actor, cómo entra, recuadros «Ten en cuenta» y «Si algo sale mal», contacto
  modalidades/<opción>/<actor>.tex     el proceso de esa opción para ese actor, por etapas, con sus capturas
  consolidados/<actor>.tex             lo común una vez (con las capturas de Tesis o de EGEL) y lo propio de cada opción
  manuales/<actor>/<opción>.tex        manual por actor y opción (portada + piezas)
  manuales/<actor>/consolidado.tex     manual consolidado por actor

Uso: python3 tools/manual_titulacion/generar_latex.py
"""
import glob
import json
import os
import re
import shutil
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, AQUI)
from latex_comun import captura, capturas_movil, de, documento, escribir, lista, lista_t, manifiesto, que_vas_a_hacer, recuadro, sin_conteo_pasos, t  # noqa: E402

RAIZ = os.path.dirname(os.path.dirname(AQUI))
SALIDA = os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'latex')
IMG = os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'img')
CAT = json.load(open(os.path.join(AQUI, 'catalogo.json'), encoding='utf-8'))

ACTORES = {
    'pasante': ('Manual del pasante', 'Pasante (estudiante que se titula)'),
    'asesor': ('Manual del asesor', 'Asesor(a) del trabajo escrito'),
    'coasesor': ('Manual del coasesor', 'Coasesor(a) del trabajo escrito'),
    'comision_protocolos': ('Manual del comité de revisión de protocolos', 'Encargado(a) y evaluadores del comité de revisión de protocolos'),
    'coordinador_carrera': ('Manual de la Coordinación de carrera', 'Coordinador(a) de Docencia de la licenciatura'),
    'sinodal': ('Manual del sinodal', 'Sinodal (revisor del trabajo y jurado)'),
    'responsable_titulacion': ('Manual del Departamento de Titulación', 'Jefatura y personal de Apoyo a Titulación'),
    'subdirector': ('Manual de la Subdirección Académica', 'Subdirector(a) Académico(a), su asistente y la Dirección'),
    'servicios_escolares': ('Guía de Servicios Escolares', 'Servicios Escolares (Administración Central)'),
}
ORDEN = list(ACTORES)
SIN_TRABAJO = ['pasante', 'responsable_titulacion', 'subdirector', 'servicios_escolares']
CONTACTO = ('\\section{Contacto}\nApoyo a Titulación, Departamento de Evaluación Profesional y Grado de la Facultad de Ingeniería: '
            'de lunes a viernes, de 9:00 a 15:00 y de 16:00 a 17:30 horas; correo \\texttt{titulacion\\_fi@uaemex.mx}. '
            'Los datos vigentes aparecen siempre en la plataforma, en el recuadro \\emph{¿Dudas?} de cada trámite.\n')


def actores_de(op):
    return SIN_TRABAJO if CAT[op]['tipo'] == 'sin_trabajo' else ORDEN


def combinaciones():
    out = []
    for a in ORDEN:
        for op in CAT:
            if a in actores_de(op):
                out.append((a, op))
        out.append((a, 'consolidado'))
    return out


M = manifiesto(IMG)


def caps(op, actor):
    return de(M, f'{op}_{actor}_')


# ============================================================================================ textos por actor
GENERAL = {
    'pasante': {
        'que': 'Eliges tu opción de titulación, capturas tus datos una sola vez y avanzas por etapas. En cada momento la plataforma te muestra una sola tarjeta, \\emph{Tu siguiente paso}, con lo que te toca. Subes tus documentos en PDF, firmas tus formatos con un botón y ves cuándo le toca a otra persona (tu asesor, el comité, Titulación o la Subdirección).',
        'entrar': [
            'Entra a Traza FI con tu correo institucional (\\texttt{@alumno.uaemex.mx}).',
            'En el menú, abre \\emph{Titulación} y luego \\emph{Mi trámite de licenciatura}.',
            'En \\emph{¿Cómo quieres titularte?} abre \\emph{Sin trabajo escrito} o \\emph{Con trabajo escrito} y elige tu opción. Antes de iniciar puedes leer su página y descargar este manual; también puedes comparar las opciones en \\emph{Compara las opciones: descarga sus manuales}.',
        ],
        'tencuenta': [
            'El avance se mide por \\textbf{etapas} («Etapa 2 de 8»); dentro de cada etapa, la tarjeta \\emph{Tu siguiente paso} te dice qué hacer ahora.',
            'Cada documento va en PDF escaneado, legible y a color (no fotografías); el tamaño máximo lo indica la pantalla.',
            'Solo puede haber \\textbf{una modalidad activa}. Si cambias de opción, antes se te explica qué pasará con la que tienes y se te pide confirmarlo.',
            'Los formatos se llenan con tus datos y se firman electrónicamente; no necesitas imprimirlos.',
        ],
        'algomal': [
            '\\textbf{Te devolvieron un documento.} Arriba de tu trámite aparece \\emph{Falta corregir un documento} con la observación. Corrige el PDF y vuelve a subirlo desde el mismo renglón.',
            '\\textbf{Tu asesor no encuentra el correo.} Desde tu trámite puedes reenviarle el enlace; revisa que su correo esté bien escrito.',
            '\\textbf{Elegiste la opción equivocada.} Al final de tu trámite abre \\emph{¿Elegiste otra modalidad o quieres dejar este trámite?}. Si nadie más ha intervenido, el trámite \\textbf{se borra}; si ya intervino alguien (asesor, comité, Titulación), \\textbf{queda en pausa} en \\emph{Abandonadas} y conservas todo.',
            '\\textbf{Quieres volver a un trámite en pausa.} En \\emph{Mi trámite de licenciatura}, sección \\emph{Abandonadas}, pulsa \\boton{Retomar}: sigue justo donde se quedó.',
            'Si una pantalla muestra un error que no entiendes, toma nota del folio de tu trámite y escribe a Apoyo a Titulación.',
        ],
    },
    'asesor': {
        'que': 'Acompañas al pasante en su trabajo escrito y das tu visto bueno en línea a sus solicitudes: el protocolo, la solicitud de revisión, el voto aprobatorio y la solicitud de sustentación. Cada firma te llega por correo con un enlace personal; con tu sesión de Traza FI también la encuentras en \\emph{Mis pendientes}.',
        'entrar': ['Abre el enlace del correo que te envía Traza FI (asunto como «Firma de protocolo · TIT-…»).',
                   'Para firmar necesitas sesión con el mismo correo al que llegó el enlace; si no tienes cuenta, el enlace te lleva a crearla.',
                   'Con sesión, todo lo que te toca está en \\emph{Titulación} → \\emph{Mis pendientes}.'],
        'tencuenta': ['El enlace es personal; no lo compartas.', 'Antes de firmar puedes leer el documento completo con \\boton{Leer el documento (PDF)}.',
                      'Tus constancias de asesoría se generan solas y aparecen en \\emph{Mis constancias}.'],
        'algomal': ['Si el enlace venció o no lo encuentras, pide al pasante que te lo reenvíe desde su trámite.',
                    'Si el trámite está en pausa, el enlace lo indica y no permite firmar hasta que el pasante lo retome.'],
    },
    'coasesor': {
        'que': 'Si el protocolo registra coasesoría, firmas en línea los mismos formatos que el asesor te pida (el protocolo y, según el caso, las solicitudes). Cada firma llega por correo con un enlace personal y también aparece en \\emph{Mis pendientes}.',
        'entrar': ['Abre el enlace del correo de Traza FI.', 'Firma con sesión del mismo correo al que llegó el enlace.'],
        'tencuenta': ['El enlace es personal.', 'Tus constancias de coasesoría se generan solas.'],
        'algomal': ['Si el enlace venció, pide al pasante que lo reenvíe.'],
    },
    'comision_protocolos': {
        'que': 'El comité revisa los protocolos de los trabajos escritos. El encargado recibe el protocolo que turnó Titulación y lo asigna a los evaluadores; cada evaluador escribe sus recomendaciones de forma y de fondo y firma; con todas las firmas, el encargado consolida el dictamen. El pasante solo ve el dictamen consolidado.',
        'entrar': ['Encargado: \\emph{Titulación} → \\emph{Mi comité de revisión}.', 'Evaluadores: \\emph{Titulación} → \\emph{Protocolos por revisar} (también en \\emph{Mis pendientes}).'],
        'tencuenta': ['Cada revisión tiene fecha límite (siete días hábiles por omisión); el semáforo indica cuáles van tarde.',
                      'El dictamen solo se envía cuando todos los evaluadores firmaron.'],
        'algomal': ['Si un evaluador no puede revisar, el encargado cancela su asignación y asigna a otra persona.',
                    'Si el pasante dejó su trámite en pausa, la revisión queda detenida hasta que lo retome.'],
    },
    'coordinador_carrera': {
        'que': 'Cuando un pasante de tu licenciatura elige fecha y propone a su sínodo (examen previo) o a su jurado (examen definitivo), recibes un enlace para ratificar o cambiar a las personas propuestas y firmar la asignación. Con tu firma se generan los oficios de cada sinodal.',
        'entrar': ['Abre el enlace del correo («Asignar sinodales…») o entra a \\emph{Titulación} → \\emph{Mis pendientes}.'],
        'tencuenta': ['El orden de los propietarios define su función en el jurado (presidente, secretario y vocal).',
                      'Si buscas a un profesor por nombre o correo y está registrado, sus datos se completan solos.'],
        'algomal': ['Si cambias a una persona, revisa su correo: a esa dirección le llega su oficio.'],
    },
    'sinodal': {
        'que': 'Como revisor lees el trabajo escrito y firmas el voto aprobatorio; como integrante del sínodo o del jurado recibes tu oficio y firmas de recibido. Todo llega por correo con enlaces personales y está también en \\emph{Mis pendientes}.',
        'entrar': ['Abre el enlace del correo (por ejemplo, «Firma del voto aprobatorio» u «Oficio de citación»).', 'Con sesión: \\emph{Titulación} → \\emph{Mis pendientes}.'],
        'tencuenta': ['Desde el enlace del oficio puedes descargar el trabajo escrito.', 'Al firmar de recibido, el examen aparece en tu calendario de Traza FI.',
                      'Tus constancias de sinodal aparecen en \\emph{Mis constancias}.'],
        'algomal': ['Si no puedes asistir, avisa cuanto antes a Apoyo a Titulación para que se convoque al suplente.'],
    },
    'responsable_titulacion': {
        'que': 'Validas o devuelves los documentos de cada pasante, cotejas los originales cuando se pide, registras lo que le toca al departamento (fechas, lugar, resultado y acta), das tu visto bueno a los oficios que lo requieren y, al final, verificas el expediente y lo exportas para Servicios Escolares.',
        'entrar': ['\\emph{Titulación} → \\emph{Validar trámites} (bandeja) y, al final, \\emph{Expedientes a Servicios Escolares}.',
                   'La Jefatura firma su visto bueno en \\emph{Firmas pendientes}.'],
        'tencuenta': ['Al devolver un documento la observación es obligatoria; el pasante la ve arriba de su trámite.',
                      'Los documentos generados por la plataforma ya vienen firmados; revísalos y valídalos igual que los demás.',
                      'Cada renglón de la verificación final se marca como entregado o \\emph{No aplica}.'],
        'algomal': ['Si validaste por error, puedes devolver el documento con una observación mientras la etapa siga abierta.',
                    'Si una etapa se cerró antes de tiempo, usa \\boton{Reabrir etapa} en el expediente.',
                    'Un trámite en pausa no se puede validar: aparece en la bandeja como \\emph{Abandonado} hasta que el pasante lo retome.'],
    },
    'subdirector': {
        'que': 'Firmas electrónicamente los formatos y oficios que llevan tu nombre (solicitudes autorizadas, oficios de aprobación de tema, de impresión y de jurado). La Dirección firma en la misma bandeja el oficio de estructura capitular. Puedes registrar un asistente que firme en tu nombre; la bitácora conserva quién firmó.',
        'entrar': ['\\emph{Titulación} → \\emph{Firmas pendientes}.'],
        'tencuenta': ['Antes de firmar puedes abrir la vista previa de cada documento.', 'Puedes firmar los seleccionados o todos los pendientes con un botón.',
                      'La firma estampa un sello con nombre, cargo, folio y huella del documento.'],
        'algomal': ['Si un documento tiene un error, no lo firmes: avisa a Titulación para que se corrija y se genere de nuevo.'],
    },
    'servicios_escolares': {
        'que': 'No usas la plataforma: recibes del Departamento de Titulación una carpeta comprimida por lote, con un expediente por pasante, para iniciar la expedición del título.',
        'entrar': ['Recibes la carpeta comprimida (ZIP) que exporta el Departamento de Titulación.'],
        'tencuenta': ['Cada expediente trae los documentos numerados en el orden del formato de integración, el formato llenado, el formato de revisión y un índice con la huella SHA-256 de cada archivo.',
                      'La huella permite comprobar que ningún archivo cambió después de exportarse.'],
        'algomal': ['Si falta un documento o una huella no coincide, comunícalo al Departamento de Titulación indicando el folio.'],
    },
}

# Textos de las tareas de cada actor (clave de tarea del manifiesto → (título de la sección, explicación)).
TAREAS = {
    'responsable_titulacion': [
        ('bandeja', 'La bandeja «Validar trámites»', 'La bandeja reúne los trámites que esperan algo del departamento. Abre el folio para ver su expediente.'),
        ('expediente', 'El expediente del trámite', 'Cada documento aparece con su estado, el enlace a su PDF y los botones \\boton{Validar} y \\boton{Devolver}.'),
        ('validar', 'Validar documentos', 'Abre el PDF, revisa que sea el documento correcto y legible, y pulsa \\boton{Validar}.'),
        ('devolver', 'Devolver un documento', 'Escribe en \\emph{Observaciones} qué debe corregir el pasante y pulsa \\boton{Devolver}. El pasante lo ve en su trámite y vuelve a subirlo.'),
        ('cotejo', 'Cotejar con el original', 'Cuando el documento lo pide, coteja el original que entrega el pasante y márcalo; la plataforma lo sella.'),
        ('hito', 'Registrar lo que le toca al departamento', 'Fechas, lugar, oficios y, al final, el resultado y el número de acta. Al registrarlo se completa la etapa.'),
        ('firmas_jefatura', 'Visto bueno de la Jefatura', 'Los oficios que llevan el visto bueno de la Jefatura esperan en \\emph{Firmas pendientes}.'),
        ('expedientes', 'Expedientes a Servicios Escolares', 'Al concluir, el trámite pasa a esta bandeja para la verificación final.'),
        ('verificacion', 'Verificación final', 'Revisa cada renglón del formato de integración y marca \\emph{Entregado y revisado} o \\emph{No aplica}. Después pulsa \\boton{Guardar lista} y \\boton{Hago constar que el expediente está completo y verificado}.'),
        ('exportar', 'Exportar para Servicios Escolares', 'Con el expediente verificado, exporta la carpeta comprimida.'),
    ],
    'subdirector': [
        ('firmas', 'Firmar los documentos pendientes', 'Revisa cada documento con \\boton{Ver} y firma los seleccionados o todos los pendientes.'),
        ('firmas_director', 'Firma de la Dirección', 'El Director o la Directora firma en su propia bandeja el oficio de estructura capitular de las opciones con trabajo escrito.'),
    ],
    'comision_protocolos': [
        ('com_bandeja', 'Protocolos recibidos (encargado)', 'Titulación turna el protocolo al comité de la licenciatura; el encargado lo ve en su bandeja.'),
        ('com_asignar', 'Asignar evaluadores (encargado)', 'Marca a los integrantes que revisarán y pulsa \\boton{Enviar a revisión}; cada uno recibe la asignación con fecha límite.'),
        ('com_revisiones', 'Protocolos por revisar (evaluador)', 'Cada evaluador ve aquí los protocolos que le asignaron.'),
        ('com_revision', 'Revisar y firmar (evaluador)', 'Elige el dictamen, escribe recomendaciones de forma y de fondo, y pulsa \\boton{Firmar y enviar}.'),
        ('com_dictamen', 'Consolidar el dictamen (encargado)', 'Con todas las revisiones firmadas, elige el dictamen del comité y envíalo; el trámite avanza solo.'),
    ],
    'servicios_escolares': [('zip', 'Cómo se genera la carpeta', 'El Departamento de Titulación verifica el expediente y lo exporta como carpeta comprimida.')],
}
ENLACE_TEXTO = {
    'asesor': 'Abre el enlace, lee el documento con \\boton{Leer el documento (PDF)} y pulsa \\boton{Firmar}. El trámite avanza en cuanto firman todos los que deben hacerlo.',
    'coasesor': 'Abre el enlace, lee el documento y pulsa \\boton{Firmar}.',
    'sinodal': 'Abre el enlace, consulta el documento u oficio y firma (\\boton{Firmar} o \\boton{Firmar de recibido}).',
    'coordinador_carrera': 'Revisa a las personas propuestas, ratifícalas o cámbialas, y pulsa \\boton{Firmar asignación}.',
}


# ============================================================================================ piezas
def pieza_actor(a):
    g = GENERAL[a]
    s = f'\\section{{Antes de empezar}}\n\\subsection*{{Cómo entras}}\n' + lista(g['entrar'], enum=True)
    s += recuadro('tencuenta', g['tencuenta'])
    s += recuadro('algosalemal', g['algomal'])
    return s


def tabla_docs(docs):
    if not docs:
        return ''
    s = '{\\small\\begin{longtable}{>{\\raggedright\\arraybackslash}p{0.47\\linewidth}>{\\raggedright\\arraybackslash}p{0.45\\linewidth}}\n\\toprule\n\\textbf{Documento} & \\textbf{Observaciones}\\\\\n\\midrule\n\\endhead\n'
    for d in docs:
        obs = d.get('nota') or ''
        if d.get('condicion'):
            obs = (obs + ' ' if obs else '') + d['condicion']
        if d.get('generado'):
            obs = ('La plataforma lo genera. ' + obs).strip()
        s += f"{t(d['nombre'])} & {t(obs)}\\\\\n"
    return s + '\\bottomrule\n\\end{longtable}}\n'


def elegir_pasante(lst):
    """De todas las capturas del pasante en una opción, las que van al manual (sin repetir pantallas parecidas)."""
    out, vistos = [], set()
    for n, v in lst:
        tarea, etapa = v.get('tarea', ''), v.get('etapa', '')
        if tarea in ('siguiente', 'generar', 'confirmar'):
            k = (tarea, etapa)
            if k in vistos:
                continue
            vistos.add(k)
        out.append((n, v))
    return out


def pasante_opcion(op, con_capturas=True):
    """Proceso del pasante en una opción, por etapas."""
    m = CAT[op]
    todas = elegir_pasante(caps(op, 'pasante')) if con_capturas else []
    por = {}
    for n, v in todas:
        por.setdefault(v.get('tarea', ''), []).append((n, v))
    porEtapa = {}
    for n, v in todas:
        if v.get('etapa'):
            porEtapa.setdefault(v['etapa'], []).append((n, v))

    def cap(*tareas):
        r = ''
        for ta in tareas:
            for n, v in por.get(ta, []):
                if not v.get('movil'):
                    r += captura(n, dict(v, pie=sin_conteo_pasos(v['pie'])))
        return r

    s = f"\\section{{{t(m['nombre'])}}}\n{t(m['resumen'])} ({t(m['art'])}).\n\n\\subsection{{Requisitos de la opción}}\n" + lista_t(m['requisitos'])
    s += '\\subsection{Elegir la opción e iniciar}\n'
    s += ('En \\emph{Mi trámite de licenciatura}, bajo \\emph{¿Cómo quieres titularte?}, abre \\emph{' + ('Sin trabajo escrito' if m['tipo'] == 'sin_trabajo' else 'Con trabajo escrito')
          + '} y elige \\emph{' + t(m['nombre']) + '}. La página de la opción explica qué necesitas, cómo será tu trámite y ofrece este manual para descargar, sin iniciar nada. '
          'Para empezar, acepta el aviso de privacidad y pulsa \\boton{Iniciar mi trámite}.' + (' En EGEL, antes eliges la convocatoria.' if op == 'egel' else '') + '\n')
    s += cap('entrada', 'informativa', 'iniciar')
    s += f"\\subsection{{Tu trámite, etapa por etapa}}\nTu trámite tiene {len(m['etapas'])} etapas. Arriba siempre ves la tarjeta \\emph{{Tu siguiente paso}} con lo que te toca ahora y una barra con las etapas: las terminadas, la que está en curso y las que vienen después.\n"
    usadas = set()
    for i, e in enumerate(m['etapas'], 1):
        s += f"\\subsubsection*{{Etapa {i}. {t(e['titulo'])}}}\n{t(e['descripcion'])}\n"
        if e['campos']:
            s += '\nDatos que capturas:\n\\begin{multicols}{2}\\small\n' + lista_t(e['campos']) + '\\end{multicols}\n'
        if e['formatos']:
            s += '\nLa plataforma llena con tus datos los formatos de esta etapa; los firmas electrónicamente con un botón.\n'
        if e['docs']:
            s += '\nDocumentos (PDF):\n' + tabla_docs(e['docs'])
        if e.get('confirmar'):
            s += f"\nAl terminar confirmas: «{t(e['confirmar'])}».\n"
        if e.get('agenda'):
            s += '\nEliges fecha y hora en los horarios disponibles y propones a tu sínodo o jurado; la solicitud llega al Coordinador de Docencia de tu licenciatura, que lo ratifica o modifica.\n'
        if e.get('personal'):
            s += f"\n\\emph{{Le toca al departamento:}} {t(e['personal']['titulo'])}. {t(e['personal']['descripcion'])}\n"
        for n, v in porEtapa.get(e['titulo'], []):
            if v.get('movil') or v.get('tarea') in ('devuelto', 'resubir'):
                continue
            s += captura(n, dict(v, pie=sin_conteo_pasos(v['pie'])))
            usadas.add(n)
    s += '\\subsection{Si te devuelven un documento}\nCuando Titulación devuelve un documento, arriba de tu trámite aparece \\emph{Falta corregir un documento} con la observación, y tu siguiente paso te lleva a corregirlo. Sube el PDF corregido desde el mismo renglón.\n'
    s += cap('devuelto', 'resubir')
    s += '\\subsection{Al terminar}\nCuando se registra el resultado, tu trámite queda concluido. Titulación verifica tu expediente y lo envía a Servicios Escolares para la expedición del título. En \\emph{Estado de mi trámite} ves en qué va.\n'
    s += cap('concluido', 'estado')
    movil = [(n, v) for n, v in todas if v.get('movil')]
    if movil:
        s += '\\subsection{Desde el celular}\nLa plataforma funciona igual en el teléfono: la tarjeta \\emph{Tu siguiente paso} queda arriba y las etapas futuras se pliegan.\n' + capturas_movil(movil)
    return s


def comun_pasante():
    """Borrar, pausar y retomar (capturas del recorrido aparte, prefijo comun_pasante_)."""
    c = dict(de(M, 'comun_pasante_'))
    g = lambda k: next(((n, v) for n, v in c.items() if v.get('tarea') == k), None)  # noqa: E731
    s = '\\section{Cambiar de opción: borrar, dejar en pausa y retomar}\n'
    s += ('Solo puede haber una modalidad activa. Al final de tu trámite está el pliegue \\emph{¿Elegiste otra modalidad o quieres dejar este trámite?}. '
          'Lo que pasa depende de si alguien más ya intervino:\n')
    s += lista(['\\textbf{Nadie más intervino:} el trámite \\textbf{se borra} definitivamente. La pantalla dice qué se borrará y pide confirmarlo.',
                '\\textbf{Ya intervino alguien} (tu asesor firmó, el comité revisó, Titulación validó…): el trámite \\textbf{queda en pausa} en \\emph{Abandonadas}. Se conserva todo; mientras tanto nadie puede firmar ni revisar nada en él.'])
    for k in ('dejar_pliegue', 'borrar', 'borrado'):
        if g(k):
            s += captura(*g(k))
    s += '\\subsection*{Iniciar otra opción teniendo una activa}\nSi ya tienes una opción en proceso y entras a otra, la página te lo recuerda. Al pulsar iniciar, la plataforma explica qué pasará con la que tienes (se borra o queda en pausa) y te pide confirmarlo.\n'
    for k in ('otra_activa', 'una_activa'):
        if g(k):
            s += captura(*g(k))
    s += '\\subsection*{Retomar un trámite en pausa}\nEn \\emph{Mi trámite de licenciatura}, abre \\emph{Abandonadas} y pulsa \\boton{Retomar}. Vuelve justo donde se quedó; si tienes otra opción activa, antes se te explica qué pasará con ella.\n'
    for k in ('abandonadas', 'retomar', 'retomado', 'pausa'):
        if g(k):
            s += captura(*g(k))
    return s


def tareas_actor(op, a, titulo_nivel='subsection'):
    """Secciones de las tareas de un actor en una opción, con sus capturas."""
    lst = caps(op, a)
    if a in ('asesor', 'coasesor', 'sinodal', 'coordinador_carrera'):
        s = ''
        for n, v in lst:
            asunto = re.sub(r'^Enlace personal del correo «|^Enlace personal «', '', v['pie']).split('»')[0]
            s += f'\\{titulo_nivel}{{{t(asunto)}}}\n{ENLACE_TEXTO[a]}\n' + captura(n, v)
        return s
    s = ''
    usadas = set()
    for clave, titulo, texto in TAREAS.get(a, []):
        sel = [(n, v) for n, v in lst if v.get('tarea', '') == clave or v.get('tarea', '').startswith(clave + '_')]
        if not sel:
            continue
        s += f'\\{titulo_nivel}{{{t(titulo)}}}\n{texto}\n'
        for n, v in sel[:3]:
            s += captura(n, v)
            usadas.add(n)
    return s


def particular(op, a):
    """Lo propio de una opción para un actor que no es el pasante."""
    m = CAT[op]
    docs = [d for e in m['etapas'] for d in e['docs']]
    if a == 'responsable_titulacion':
        s = 'Documentos que valida el departamento en esta opción:\n' + lista_t(sorted({d['nombre'] for d in docs}))
        hitos = [e['personal']['titulo'] for e in m['etapas'] if e.get('personal')]
        if hitos:
            s += 'Lo que registra el departamento:\n' + lista_t(hitos)
        s += f"Renglones de la verificación final ({t(m['titulo_exp'])}):\n" + lista([t(r['texto']) + (' \\emph{(solo si aplica)}' if r['cond'] else '') for r in m['renglones']], enum=True)
        return s
    if a == 'subdirector':
        gen = [d['nombre'] for d in docs if d.get('generado')] + [e['personal']['entrega'] for e in m['etapas'] if e.get('personal') and e['personal'].get('entrega')]
        return 'Documentos de esta opción que pueden esperar firma de la Subdirección o de la Dirección:\n' + lista_t(gen)
    if a == 'servicios_escolares':
        return f"El expediente llega en el orden del formato «{t(m['titulo_exp'])}»:\n" + lista([t(r['texto']) + (' \\emph{(solo si aplica)}' if r['cond'] else '') for r in m['renglones']], enum=True)
    if a in ('asesor', 'coasesor'):
        return f"Trabajo escrito de esta opción: {t(m['resumen'])} " + ('Firmas: protocolo, solicitud de revisión, voto aprobatorio y solicitud de sustentación.' if a == 'asesor' else 'Firmas: el protocolo y los formatos en que el pasante registre coasesoría.') + '\n'
    if a == 'sinodal':
        return f"Trabajo escrito de esta opción: {t(m['resumen'])} Como revisor firmas el voto aprobatorio; como sinodal, el acuse de tu oficio para el examen previo y para el definitivo.\n"
    if a == 'coordinador_carrera':
        return 'En esta opción ratificas dos veces: el sínodo del examen previo y el jurado del examen definitivo.\n'
    if a == 'comision_protocolos':
        return f"Protocolo de esta opción: {t(m['resumen'])}\n"
    return ''


QUE = {
    'asesor': 'firmar en línea, desde los enlaces de tu correo, los formatos del trabajo escrito',
    'coasesor': 'firmar en línea los formatos en que el pasante te registró como coasesor',
    'comision_protocolos': 'revisar el protocolo y emitir el dictamen del comité',
    'coordinador_carrera': 'ratificar o cambiar el sínodo y el jurado, y firmar la asignación',
    'sinodal': 'firmar el voto aprobatorio y el acuse de tu oficio',
    'responsable_titulacion': 'validar documentos, registrar las fechas y el resultado, y verificar y exportar el expediente',
    'subdirector': 'firmar los formatos y oficios que llevan tu nombre',
    'servicios_escolares': 'recibir el expediente verificado para expedir el título',
}


def que_hacer(a, op=None):
    g = GENERAL[a]['que']
    if op and a != 'pasante':
        g += f" En la opción \\emph{{{t(CAT[op]['nombre'])}}} te toca {QUE[a]}."
    return que_vas_a_hacer(g)


# ============================================================================================ ensamblado
def generar():
    for d in ('actores', 'modalidades', 'consolidados', 'manuales', 'comun'):
        shutil.rmtree(os.path.join(SALIDA, d), ignore_errors=True)
    for f in glob.glob(os.path.join(SALIDA, '*.tex')):
        os.remove(f)
    escribir(SALIDA, 'preambulo.tex', '% Manuales de Titulación de licenciatura. Generado por tools/manual_titulacion/generar_latex.py\n'
             '\\newcommand{\\nivelmanual}{Titulación de licenciatura}\n\\input{../../comun/preambulo}\n\\graphicspath{{../img/}{../../comun/}}\n')
    escribir(SALIDA, 'comun/pasante_dejar.tex', comun_pasante())
    escribir(SALIDA, 'comun/contacto.tex', CONTACTO)
    for a in ORDEN:
        escribir(SALIDA, f'actores/{a}.tex', pieza_actor(a))
    for a, op in combinaciones():
        titulo, para = ACTORES[a]
        if op == 'consolidado':
            continue
        if a == 'pasante':
            cuerpo = pasante_opcion(op)
        else:
            cuerpo = f"\\section{{{t(CAT[op]['nombre'])}: lo que te toca}}\n" + particular(op, a)
            tareas = tareas_actor(op, a)
            if not tareas and a not in ('servicios_escolares',):
                tareas = tareas_actor('tesis' if a not in SIN_TRABAJO else 'egel', a)
                if tareas:
                    cuerpo += '\\subsection*{Pantallas}\nLas pantallas de esta sección se tomaron en la opción \\emph{Tesis}; en esta opción son iguales.\n'
            cuerpo += tareas
        escribir(SALIDA, f'modalidades/{op}/{a}.tex', cuerpo)
        partes = que_hacer(a, op) + f'\\input{{actores/{a}}}\n\\input{{modalidades/{op}/{a}}}\n'
        if a == 'pasante':
            partes += '\\input{comun/pasante_dejar}\n'
        partes += '\\input{comun/contacto}\n'
        escribir(SALIDA, f'manuales/{a}/{op}.tex', documento(titulo, para, CAT[op]['nombre'], partes))
    for a in ORDEN:
        titulo, para = ACTORES[a]
        escribir(SALIDA, f'consolidados/{a}.tex', consolidado(a))
        partes = que_hacer(a) + f'\\input{{actores/{a}}}\n\\input{{consolidados/{a}}}\n' + ('\\input{comun/pasante_dejar}\n' if a == 'pasante' else '') + '\\input{comun/contacto}\n'
        escribir(SALIDA, f'manuales/{a}/consolidado.tex', documento(titulo, para, 'Todas las opciones de titulación', partes))


def consolidado(a):
    ops = [op for op in CAT if a in actores_de(op)]
    s = '\\section{Las opciones en las que participas}\n' + lista_t([CAT[op]['nombre'] for op in ops])
    if a == 'pasante':
        s += ('\\section{Lo que es igual en todas las opciones}\nEn todas eliges la opción en \\emph{¿Cómo quieres titularte?}, capturas tus datos una vez, avanzas por etapas con la tarjeta \\emph{Tu siguiente paso}, subes tus documentos en PDF y firmas tus formatos con un botón. '
              'Las pantallas de esta sección son de la opción \\emph{Tesis}.\n')
        # Del recorrido de Tesis se toman solo las pantallas generales.
        sel = [(n, v) for n, v in elegir_pasante(caps('tesis', 'pasante')) if v.get('tarea') in ('entrada', 'informativa', 'iniciar', 'captura_datos', 'subir', 'devuelto', 'resubir', 'agenda1', 'estado')]
        for n, v in sel:
            s += captura(n, dict(v, pie=sin_conteo_pasos(v['pie'])))
        s += '\\section{Lo propio de cada opción}\nRequisitos y etapas de cada una, para compararlas. El detalle con pantallas está en el manual de cada opción.\n'
        for op in ops:
            m = CAT[op]
            s += f"\\subsection{{{t(m['nombre'])}}}\n{t(m['resumen'])} ({t(m['art'])}). " + ('Sin trabajo escrito.' if m['tipo'] == 'sin_trabajo' else 'Con trabajo escrito.') + '\n\n\\textbf{Requisitos:}\n' + lista_t(m['requisitos'])
            s += '\\textbf{Etapas:}\n' + lista_t([e['titulo'] for e in m['etapas']], enum=True)
        return s
    base = 'tesis' if 'tesis' in ops else ops[0]
    s += f"\\section{{Lo que es igual en todas las opciones}}\nLas pantallas de esta sección se tomaron en la opción \\emph{{{t(CAT[base]['nombre'])}}}; en las demás opciones son iguales.\n"
    s += tareas_actor(base, a)
    if a == 'responsable_titulacion' and 'egel' in ops:
        s += '\\subsection{Opciones sin trabajo escrito}\nEn aprovechamiento, EGEL y créditos el recorrido es más corto (sin comité ni sínodo): validas, registras fecha, hora y lugar de la toma de protesta y el resultado. Pantallas de EGEL:\n'
        s += tareas_actor('egel', a, 'subsubsection*')
    s += '\\section{Lo propio de cada opción}\n'
    for op in ops:
        s += f"\\subsection{{{t(CAT[op]['nombre'])}}}\n" + particular(op, a)
    return s


if __name__ == '__main__':
    generar()
    n = len(glob.glob(os.path.join(SALIDA, 'manuales', '*', '*.tex')))
    print('Generado en', SALIDA, '·', n, 'manuales ·', len(M), 'capturas en el manifiesto')
