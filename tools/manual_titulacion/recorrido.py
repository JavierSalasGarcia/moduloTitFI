"""Recorre de punta a punta las opciones de titulación de licenciatura con un pasante distinto por opción y toma, en la
misma ejecución, las capturas de cada actor para los manuales (docs/manuales/titulacion/img + _capturas.json).

Uso (con la base local preparada por semilla_manuales.php):
    python3 tools/manual_titulacion/recorrido.py                 # las 11 opciones y el recorrido de borrar/pausar/retomar
    python3 tools/manual_titulacion/recorrido.py tesis egel      # solo esas opciones
    python3 tools/manual_titulacion/recorrido.py --dejar         # solo borrar, pausar, retomar y «una modalidad activa»

Nombres de las capturas: <opción>_<actor>_<NN>_<tema>.jpg. Cada una lleva en el manifiesto su pie, actor, opción,
etapa y una clave de tarea (`tarea`) con la que el generador de LaTeX la coloca en su sección.
"""
import datetime
import os
import re
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from lib import BASE, LOG, RAIZ, Sesion, correos, crear_pdf, sync_playwright, tam  # noqa: E402
from flujo import CTX_TRABAJO, VALORES, formularios, llenar_y_enviar  # noqa: E402

OPCIONES = ['aprovechamiento', 'egel', 'creditos', 'articulo', 'ensayo', 'memoria', 'aplicacion', 'autoempleo', 'residencia', 'tesina', 'tesis']
SIN_TRABAJO = {'aprovechamiento', 'egel', 'creditos'}
MOVIL = {'tesis', 'egel'}   # opciones con capturas de 390 px para «Desde el celular»
PASANTES = {
    'aprovechamiento': ('Alicia Morales Fuentes', '2011001'), 'egel': ('Eduardo Rangel Silva', '2011002'),
    'creditos': ('Carla Benítez Ochoa', '2011003'), 'articulo': ('Arturo Delgado Cruz', '2011004'),
    'ensayo': ('Elisa Navarro Pineda', '2011005'), 'memoria': ('Mateo Guerrero León', '2011006'),
    'aplicacion': ('Andrea Campos Rivas', '2011007'), 'autoempleo': ('Aurelio Sandoval Mejía', '2011008'),
    'residencia': ('Regina Montiel Ávila', '2011010'), 'tesina': ('Tadeo Estrada Lara', '2011011'),
    'tesis': ('Teresa Hinojosa Bernal', '2011009'),
}
TITULOS = {
    'articulo': 'Detección temprana de fallas en motores de inducción con aprendizaje profundo',
    'ensayo': 'La ética del dato en los sistemas de recomendación educativos',
    'memoria': 'Implantación de una plataforma de monitoreo de redes en una empresa de logística',
    'aplicacion': 'Automatización del inventario de un almacén regional con lectores RFID',
    'autoempleo': 'Desarrollo y operación de un despacho de software para comercios locales',
    'residencia': 'Modelado de la demanda eléctrica de un campus universitario',
    'tesina': 'Revisión de técnicas de compresión de modelos de lenguaje para dispositivos embebidos',
    'tesis': 'Sistema de visión por computadora para clasificar residuos reciclables',
}
SINODALES = [('Sergio Cárdenas Bravo', 'Dr.', 'masculino', 'prueba.sinodal1@uaemex.mx'),
             ('Laura Inés Quintero Gil', 'Dra.', 'femenino', 'prueba.sinodal2@uaemex.mx'),
             ('Tomás Rendón Aguilar', 'M. en I.', 'masculino', 'prueba.sinodal3@uaemex.mx'),
             ('Pablo Andrade Soto', 'Dr.', 'masculino', 'prueba.evaluador1@uaemex.mx'),
             ('Ana Lucía Ferrer Montes', 'M. en C.', 'femenino', 'prueba.evaluador2@uaemex.mx')]
CTX_BASE = dict(CTX_TRABAJO, asesor='Raúl Medina Ortega', coasesor='Julia Paredes Luna', ocupacion='Desarrollador de software',
                lugar='Sala de exámenes profesionales, edificio A')
JEFA = 'prueba.jefatura.titulacion@uaemex.mx'
SUB = 'prueba.subdirector.academico@uaemex.mx'
DIR = 'prueba.director@uaemex.mx'
ENC = 'prueba.encargada.comision@uaemex.mx'
OBS_DEVUELTO = 'El PDF está incompleto: falta la segunda página. Escanéalo completo, a color, y vuelve a subirlo.'


def actor_de_correo(correo, tipo):
    c = correo.split('@')[0].replace('prueba.', '')
    if c.startswith('asesor'):
        return 'asesor'
    if c.startswith('coasesor'):
        return 'coasesor'
    if c.startswith('coordinador'):
        return 'coordinador_carrera'
    if c.startswith('sinodal') or c.startswith('evaluador'):
        return 'sinodal'
    return 'otro'


class Elenco:
    def __init__(self, pw):
        self.pw, self.s = pw, {}

    def __call__(self, email):
        if email not in self.s:
            self.s[email] = Sesion(self.pw, email)
        return self.s[email]

    def cerrar(self):
        for x in self.s.values():
            x.cerrar()


class Fotos:
    """Numera las capturas por opción y actor y evita repetir la misma pantalla."""

    def __init__(self, op, solo=None):
        """solo: si se da, únicamente se toman las capturas de esas tareas (para reanudar un recorrido interrumpido)."""
        self.op, self.n, self.vistos, self.por_etapa, self.solo = op, {}, set(), {}, solo
        from lib import manifiesto
        for k in manifiesto():
            mm = re.match(re.escape(op) + r'_(.+)_(\d\d)_', k)
            if mm:
                self.n[mm.group(1)] = max(self.n.get(mm.group(1), 0), int(mm.group(2)))

    def nombre(self, actor, tema):
        self.n[actor] = self.n.get(actor, 0) + 1
        return f'{self.op}_{actor}_{self.n[actor]:02d}_{tema}'

    def una_vez(self, clave):
        if clave in self.vistos:
            return False
        self.vistos.add(clave)
        return True

    def tomar(self, s, actor, tema, pie, tarea, etapa='', **kw):
        if self.solo is not None and tarea not in self.solo:
            return None
        return s.foto(self.nombre(actor, tema), pie, actor=actor, opcion=self.op, etapa=etapa, extra={'tarea': tarea}, **kw)


# ------------------------------------------------------------------------------------------------ pasante

def siguiente(s):
    """(etapa, título) de la tarjeta «Tu siguiente paso»."""
    loc = s.pg.locator('.tit-siguiente')
    if not loc.count():
        return ('', '')
    t = loc.first.inner_text()
    m = re.search(r'ETAPA\s+\d+\s+DE\s+\d+', t, re.I)
    lineas = [x.strip() for x in t.split('\n') if x.strip()]
    titulo = lineas[1] if len(lineas) > 1 else ''
    etapa = ''
    for e in s.pg.locator('.tit-etapa[open] .tit-et-t, .tit-etapa.actual .tit-et-t').all()[:1]:
        etapa = e.inner_text().strip()
    return (etapa or (m.group(0) if m else ''), titulo)


def etapa_actual(s):
    """Título de la etapa en curso (el bloque «Etapa n de m» abierto)."""
    t = s.pg.evaluate("""() => { const h = [...document.querySelectorAll('main p, main span, main div')].find(x => /^Etapa \\d+ de \\d+$/.test((x.innerText||'').trim()));
        if (!h) return ''; let n = h.nextElementSibling; return n ? n.innerText.trim() : ''; }""")
    return t or ''


def pasante_paso(a, tid, ctx, f, op):
    """Hace UNA acción del pasante (la primera disponible) y toma capturas de los momentos clave. Devuelve True si actuó."""
    a.ir(f'titulacion/licenciatura/tramite/{tid}')
    et = etapa_actual(a)
    sig = siguiente(a)
    # «Tu siguiente paso»: una captura por cada cambio, hasta tres por etapa.
    clave = (et, sig[1])
    if sig[1] and f.una_vez(('sig',) + clave) and f.por_etapa.get(et, 0) < 3:
        f.por_etapa[et] = f.por_etapa.get(et, 0) + 1
        f.tomar(a, 'pasante', 'siguiente', f'Tu siguiente paso: {sig[1]}', 'siguiente', etapa=et, resaltar='.tit-siguiente')
    # Documento devuelto: aviso, observación y nueva carga.
    if a.pg.locator('.tit-devuelto').count() and f.una_vez('devuelto_aviso'):
        f.tomar(a, 'pasante', 'devuelto', 'Un documento devuelto: arriba se muestra qué corregir y la observación de Titulación.', 'devuelto', etapa=et, resaltar='.tit-devuelto')
    for fo in formularios(a.pg):
        acc = fo['action']
        if any(x in acc for x in ('/adicional/', '/cancelar', '/estatus', '/reenviar/', '/noaplica/', '/art5', '/dejar', '/retomar', '/sinodal/')):
            continue
        form = a.pg.locator('form').nth(fo['i'])
        if '/captura/' in acc:
            if acc in ctx['capturadas']:
                continue
            ctx['capturadas'].add(acc)
            etapa_c = acc.rsplit('/', 1)[-1]
            if f.una_vez('captura_' + etapa_c) and form.is_visible():
                # Se llena primero para que la captura muestre un ejemplo.
                form.evaluate("f => f.scrollIntoView({block:'start'})")
                f.tomar(a, 'pasante', 'captura_' + etapa_c, 'Formulario de la etapa: capturas tus datos una sola vez.' if etapa_c == 'datos' else 'Formulario de captura de la etapa.', 'captura_' + etapa_c, etapa=et)
            llenar_y_enviar(a, fo, ctx)
            return True
        if '/generar/' in acc or '/confirmar/' in acc:
            boton = form.locator('button[type=submit]').first
            if f.una_vez('gen_' + acc.rsplit('/', 2)[-2] + acc.rsplit('/', 1)[-1]) and boton.is_visible():
                boton.evaluate("b => b.id = 'manual-boton'")
                tarea = 'confirmar' if '/confirmar/' in acc else 'generar'
                pie = 'Confirmas que terminaste la etapa.' if tarea == 'confirmar' else 'La plataforma llena el formato con tus datos y lo firma por ti con un botón.'
                if f.n.get('pasante', 0) < 40:
                    f.tomar(a, 'pasante', tarea, pie, tarea, etapa=et, resaltar='#manual-boton')
            boton.click()
            a.pg.wait_for_load_state('networkidle')
            return True
        if '/documento/' in acc:
            dc = acc.rsplit('/', 1)[-1]
            devuelto = a.pg.locator(f'[id="doc-{dc}"] .tit-obs').count() > 0
            if (tid, acc) in ctx['subidos'] and not devuelto:
                continue
            inp = form.locator('input[type=file]')
            if not inp.count():
                continue
            ctx['subidos'].add((tid, acc))
            inp.set_input_files(crear_pdf())
            form.evaluate("f => f.id = 'manual-form'")
            if devuelto:
                f.tomar(a, 'pasante', 'resubir', 'Corriges el documento devuelto: lees la observación, eliges el PDF corregido y lo vuelves a enviar.', 'resubir', etapa=et, resaltar=f'[id="doc-{dc}"]')
            elif f.una_vez('subir_primero'):
                f.tomar(a, 'pasante', 'subir', 'Subir un documento: eliges el PDF y lo envías; queda «En validación».', 'subir', etapa=et, resaltar='#manual-form')
            form.locator('button[type=submit]').first.click()
            a.pg.wait_for_load_state('networkidle')
            return True
    for fo in formularios(a.pg):
        acc = fo['action']
        if '/noaplica/' in acc and re.search(r'dictamen_consejos|const_ingles|registro_dgp|carta_presentacion|prorroga|autenticacion', acc) and (tid, acc) not in ctx['subidos']:
            ctx['subidos'].add((tid, acc))
            a.pg.locator('form').nth(fo['i']).locator('button[type=submit]').first.click()
            a.pg.wait_for_load_state('networkidle')
            return True
    return False


def agenda(a, tid, fecha, f, n):
    a.ir(f'titulacion/licenciatura/tramite/{tid}')
    if not a.pg.locator('#form-agenda').count():
        return False
    fo = a.pg.locator('#form-agenda')
    d = datetime.date.fromisoformat(fecha)
    horas = []
    for _ in range(40):
        if d.weekday() < 5:
            fo.locator('input[name=fecha]').fill(d.isoformat())
            fo.locator('input[name=fecha]').dispatch_event('change')
            fo.locator('input[name=fecha]').dispatch_event('input')
            a.pg.wait_for_timeout(1000)
            horas = fo.locator('select[name=hora] option').evaluate_all('o => o.map(x => x.value).filter(v => v)')
            if horas:
                break
        d += datetime.timedelta(days=1)
    if not horas:
        raise RuntimeError('Sin horarios desde ' + fecha)
    fo.locator('select[name=hora]').select_option(horas[0])
    for i, (nombre, grado, genero, correo) in enumerate(SINODALES):
        if fo.locator(f'input[name="s[{i}][nombre]"]').count():
            fo.locator(f'input[name="s[{i}][nombre]"]').fill(nombre)
            fo.locator(f'input[name="s[{i}][grado]"]').fill(grado)
            fo.locator(f'select[name="s[{i}][genero]"]').select_option(genero)
            fo.locator(f'input[name="s[{i}][email]"]').fill(correo)
    examen = 'examen previo' if n == 1 and f.op not in SIN_TRABAJO else 'examen definitivo'
    f.tomar(a, 'pasante', f'agenda{n}', f'Eliges fecha y hora del {examen} y propones a tu sínodo; la solicitud llega al Coordinador de Docencia.', f'agenda{n}', etapa=etapa_actual(a), resaltar='#form-agenda')
    fo.locator('button[type=submit]').first.click()
    a.pg.wait_for_load_state('networkidle')
    return True


# ------------------------------------------------------------------------------------------------ personal

def personal_paso(j, tid, ctx, f):
    """Una acción del Departamento de Titulación (validar, devolver una vez, cotejar o registrar un hito)."""
    j.ir(f'titulacion/gestion/{tid}')
    if f.una_vez('expediente'):
        f.tomar(j, 'responsable_titulacion', 'expediente', 'Expediente del trámite: cada documento con su PDF y los botones «Validar» y «Devolver».', 'expediente', completa=False)
    for fo in formularios(j.pg):
        acc = fo['action']
        form = j.pg.locator('form').nth(fo['i'])
        if re.search(r'/gestion/\d+/documento/\d+$', acc) and 'Validar' in fo['btns'] and acc not in ctx['validados']:
            ctx['validados'].add(acc)
            fila = form.evaluate("f => (f.closest('li, tr, .tit-doc, section, div') || f).innerText")
            if not ctx.get('devuelto') and 'muestra.pdf' in fila and ctx.get('docs_vistos', 0) >= 1:
                # Una devolución por opción, para mostrar el ida y vuelta.
                ctx['devuelto'] = True
                form.locator('input[name=comentario]').fill(OBS_DEVUELTO)
                form.evaluate("f => f.id = 'manual-form'")
                f.tomar(j, 'responsable_titulacion', 'devolver', 'Devolver un documento: escribes la observación y pulsas «Devolver»; el pasante la ve al entrar a su trámite.', 'devolver', resaltar='#manual-form')
                form.locator('button:has-text("Devolver")').first.click()
                j.pg.wait_for_load_state('networkidle')
                return True
            ctx['docs_vistos'] = ctx.get('docs_vistos', 0) + 1
            if f.una_vez('validar'):
                form.evaluate("f => f.id = 'manual-form'")
                f.tomar(j, 'responsable_titulacion', 'validar', 'Validar un documento: revisas el PDF y pulsas «Validar».', 'validar', resaltar='#manual-form')
            form.locator('button:has-text("Validar")').first.click()
            j.pg.wait_for_load_state('networkidle')
            return True
        if '/cotejo/' in acc and 'quitar' not in str(fo['els']) and acc not in ctx['cotejos']:
            ctx['cotejos'].add(acc)
            for r in form.locator('input[type=radio]').all()[:1]:
                r.check()
            if f.una_vez('cotejo'):
                form.evaluate("f => f.id = 'manual-form'")
                f.tomar(j, 'responsable_titulacion', 'cotejo', 'Cotejo: con el original a la vista, marcas el documento como cotejado; la plataforma lo sella.', 'cotejo', resaltar='#manual-form')
            form.locator('button[type=submit]').first.click()
            j.pg.wait_for_load_state('networkidle')
            return True
        if '/hito/' in acc and ctx['hitos_n'].get(acc, 0) < 3 and fo['els'] and form.is_visible():
            ctx['hitos_n'][acc] = ctx['hitos_n'].get(acc, 0) + 1
            clave = acc.rsplit('/', 1)[-1]
            llenar_y_enviar_sin_enviar(j, fo, ctx)
            form.evaluate("f => f.id = 'manual-form'")
            pies = {'resultado': 'Registrar el resultado de la evaluación y el número de acta.'}
            f.tomar(j, 'responsable_titulacion', 'hito_' + clave, pies.get(clave, 'Registrar lo que le toca al departamento en esta etapa y completarla.'), 'hito_' + clave, resaltar='#manual-form')
            form.locator('button[type=submit]').first.click()
            j.pg.wait_for_load_state('networkidle')
            return True
    return False


def llenar_y_enviar_sin_enviar(s, fo, ctx):
    """Como flujo.llenar_y_enviar, pero sin pulsar el botón (para capturar el formulario lleno)."""
    form = s.pg.locator('form').nth(fo['i'])
    from flujo import valor_para
    for el in fo['els']:
        if el['dis'] or el['type'] in ('hidden', 'submit', 'button', 'file'):
            continue
        k0 = re.search(r'\[(\w+)\]', el['name'])
        k0 = k0.group(1) if k0 else el['name']
        if not el['req'] and el['type'] != 'checkbox' and ctx.get(k0) is None and VALORES.get(k0) is None and el['tag'] != 'SELECT':
            continue
        loc = form.locator(f'[name="{el["name"]}"]')
        if el['type'] == 'checkbox':
            if el['req']:
                loc.first.check()
            continue
        if el['type'] == 'radio':
            loc.first.check()
            continue
        v = valor_para(el, ctx)
        if el['tag'] == 'SELECT':
            if v:
                loc.first.select_option(v)
        else:
            loc.first.fill(str(v))


def firmas_bandeja(s, f, actor, tarea, pie):
    s.ir('titulacion/firmas')
    b = s.pg.locator('button:has-text("Firmar todos los pendientes")')
    if b.count() and b.first.is_enabled() and s.pg.locator('input[name="ids[]"]').count():
        if f.una_vez('firmas_' + tarea):
            f.tomar(s, actor, tarea, pie, tarea, resaltar='button:has-text("Firmar todos los pendientes")')
        b.first.click()
        s.pg.wait_for_load_state('networkidle')
        return True
    return False


def enlaces(e, desde, f, vistos):
    """Abre cada enlace personal nuevo de la bitácora con la cuenta del destinatario y firma."""
    hechos = 0
    for para, bloque in correos(LOG, desde):
        asunto = bloque.split('\n', 2)[1].split(' · ')[0].strip() if '\n' in bloque else ''
        for ruta in re.findall(r'/titulacion/(?:firma|jurado|sinodal)/t/[\w-]+', bloque):
            if ruta in vistos or not para.startswith('prueba.'):
                continue
            vistos.add(ruta)
            tipo = ruta.split('/')[2]
            actor = actor_de_correo(para, tipo)
            s = e(para)
            s.ir(ruta)
            if f.una_vez(('enlace', actor, asunto)):
                tema = re.sub(r'[^a-z]+', '_', asunto.lower().translate(str.maketrans('áéíóúñ', 'aeioun'))).strip('_')[:30] or tipo
                pies = {'firma': f'Enlace personal del correo «{asunto}»: lees el documento y pulsas «Firmar».',
                        'jurado': f'Enlace personal «{asunto}»: ratificas o cambias a las personas propuestas y firmas la asignación.',
                        'sinodal': f'Enlace personal «{asunto}»: consultas tu oficio y firmas de recibido.'}
                boton = 'form[action$="/firmar"] button[type=submit]'
                f.tomar(s, actor, tema, pies[tipo], 'enlace_' + tema, resaltar=boton if s.pg.locator(boton).count() else None)
            for _ in range(3):
                b = s.pg.locator('form[action$="/firmar"] button[type=submit]')
                if not b.count():
                    break
                b.first.click()
                s.pg.wait_for_load_state('networkidle')
                hechos += 1
    return hechos


def comision(e, f):
    """Encargada asigna; evaluadores revisan y firman; encargada dictamina."""
    k = 0
    en = e(ENC)
    en.ir('titulacion/comision')
    href = en.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /comision\\/\\d+$/.test(h))")
    if href:
        en.ir(href[0])
        if en.pg.locator('input[name="miembros[]"]').count() and en.pg.locator('button:has-text("Enviar a revisión")').count():
            en.ir('titulacion/comision')
            if f.una_vez('com_bandeja'):
                f.tomar(en, 'comision_protocolos', 'bandeja', 'Bandeja del encargado del comité: protocolos recibidos de Titulación.', 'com_bandeja')
            en.ir(href[0])
            for c in en.pg.locator('input[name="miembros[]"]').all():
                c.check()
            f.tomar(en, 'comision_protocolos', 'asignar', 'Asignar evaluadores: marcas a los integrantes y pulsas «Enviar a revisión».', 'com_asignar', resaltar='button:has-text("Enviar a revisión")')
            en.pg.click('button:has-text("Enviar a revisión")')
            en.pg.wait_for_load_state('networkidle')
            k += 1
    for i, m in enumerate(('prueba.evaluador1@uaemex.mx', 'prueba.evaluador2@uaemex.mx', ENC)):
        ev = e(m)
        ev.ir('titulacion/revision')
        hr = ev.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /revision\\/\\d+$/.test(h))")
        if not hr:
            continue
        if f.una_vez('rev_lista'):
            f.tomar(ev, 'comision_protocolos', 'revisiones', 'Protocolos asignados a ti para revisar, con su fecha límite.', 'com_revisiones')
        ev.ir(hr[0])
        if not ev.pg.locator('button:has-text("Firmar y enviar")').count():
            continue
        ev.pg.get_by_label('Se aprueba', exact=True).check()
        ev.pg.get_by_label('No', exact=True).first.check()
        ev.pg.locator('textarea[name=forma]').fill('Redacción clara; cuidar el formato de las referencias y la numeración de las secciones.')
        ev.pg.locator('textarea[name=fondo]').fill('El planteamiento, los objetivos y la metodología son congruentes con el alcance propuesto.')
        if f.una_vez('rev_form'):
            f.tomar(ev, 'comision_protocolos', 'revision', 'Revisión de un evaluador: dictamen, recomendaciones de forma y de fondo, y «Firmar y enviar».', 'com_revision', resaltar='button:has-text("Firmar y enviar")')
        ev.pg.click('button:has-text("Firmar y enviar")')
        ev.pg.wait_for_load_state('networkidle')
        k += 1
    en.ir('titulacion/comision')
    href = en.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /comision\\/\\d+$/.test(h))")
    if href:
        en.ir(href[0])
        b = en.pg.locator('form[action$="/dictaminar"] button:has-text("Enviar")')
        if b.count():
            radios = en.pg.locator('form[action$="/dictaminar"] input[name=dictamen]').all()
            ok = [r for r in radios if 'aprob' in (r.get_attribute('value') or '') and not (r.get_attribute('value') or '').startswith('no')]
            (ok or radios)[0].check()
            if f.una_vez('dictamen'):
                f.tomar(en, 'comision_protocolos', 'dictamen', 'Con todas las revisiones firmadas, el encargado consolida el dictamen y lo envía.', 'com_dictamen', resaltar='form[action$="/dictaminar"]')
            b.first.click()
            en.pg.wait_for_load_state('networkidle')
            k += 1
    return k


# ------------------------------------------------------------------------------------------------ recorrido de una opción

def correr(p, op, idx):
    e = Elenco(p)
    f = Fotos(op)
    nombre, cuenta = PASANTES[op]
    email = f'prueba.pasante.{op}@alumno.uaemex.mx'
    a = e(email)
    j, sd, dr = e(JEFA), e(SUB), e(DIR)
    ctx = dict(CTX_BASE, nombre=nombre, cuenta=cuenta, correo_inst=email, correo_pers=email.split('@')[0].replace('prueba.pasante.', '') + '.pasante@ejemplo.com',
               titulo=TITULOS.get(op, VALORES['titulo']), capturadas=set(), subidos=set(), validados=set(), cotejos=set(), hitos=set(), hitos_n={}, acta=str(51200 + idx))
    desde = tam(LOG)
    vistos = set()
    fechas = [(datetime.date(2026, 12, 2) + datetime.timedelta(days=3 * idx)).isoformat(), (datetime.date(2027, 1, 13) + datetime.timedelta(days=3 * idx)).isoformat()]

    # Elegir la opción y la página informativa.
    a.ir('titulacion/licenciatura')
    via = 'Sin trabajo escrito' if op in SIN_TRABAJO else 'Con trabajo escrito'
    a.pg.locator('details.tit-via', has_text=via).first.evaluate('d => d.open = true')
    f.tomar(a, 'pasante', 'entrada', f'«¿Cómo quieres titularte?»: abres «{via}» y eliges la opción.', 'entrada', resaltar=f'a.tit-opc[href$="/modalidad/{op}"]', con_menu=True)
    a.ir(f'titulacion/licenciatura/modalidad/{op}')
    f.tomar(a, 'pasante', 'informativa', 'Página de la opción, antes de iniciar: qué necesitas, cómo será el trámite y el manual para descargar.', 'informativa', resaltar='#manual')
    if a.pg.locator('input[name=variante]').count():
        a.pg.check('input[name=variante][value=pagando_menos]')
    a.pg.check('input[name=acepto]')
    f.tomar(a, 'pasante', 'iniciar', 'Aceptas el aviso de privacidad y pulsas «Iniciar mi trámite».', 'iniciar', resaltar='form[action$="/modalidad/' + op + '"]')
    if op in MOVIL:
        m = Sesion(p, email, ancho=390, alto=844, movil=True)
        m.ir('titulacion/licenciatura')
        m.pg.locator('details.tit-via', has_text=via).first.evaluate('d => d.open = true')
        f.tomar(m, 'pasante', 'movil_entrada', 'Desde el celular: las opciones se abren con un toque.', 'movil', resaltar=f'a.tit-opc[href$="/modalidad/{op}"]')
        m.ir(f'titulacion/licenciatura/modalidad/{op}')
        f.tomar(m, 'pasante', 'movil_manual', 'Desde el celular: el manual de la opción se descarga desde su página.', 'movil', resaltar='#manual')
    a.pg.click('form[action$="/modalidad/' + op + '"] button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    tid = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
    print(op, 'trámite', tid, flush=True)
    j.ir('titulacion/gestion')
    if f.una_vez('bandeja'):
        f.tomar(j, 'responsable_titulacion', 'bandeja', 'Bandeja «Validar trámites»: los trámites con documentos por validar.', 'bandeja')

    ag = 0
    for r in range(400):
        x = 0
        for _ in range(60):
            if not pasante_paso(a, tid, ctx, f, op):
                break
            x += 1
        if ag < len(fechas) and a.pg.locator('#form-agenda').count():
            agenda(a, tid, fechas[ag], f, ag + 1)
            ag += 1
            x += 1
        y = 0
        for _ in range(80):
            if not personal_paso(j, tid, ctx, f):
                break
            y += 1
        z = firmas_bandeja(j, f, 'responsable_titulacion', 'firmas_jefatura', 'La Jefatura de Titulación da su visto bueno en «Firmas pendientes».')
        z += firmas_bandeja(sd, f, 'subdirector', 'firmas', 'Bandeja «Firmas pendientes» de la Subdirección: revisas y firmas los formatos y oficios.')
        z += firmas_bandeja(dr, f, 'subdirector', 'firmas_director', 'La Dirección firma en su bandeja el oficio de estructura capitular.')
        c = enlaces(e, desde, f, vistos)
        k = 0 if op in SIN_TRABAJO else comision(e, f)
        print(op, 'ronda', r, x, y, z, c, k, flush=True)
        if not (x or y or z or c or k):
            break
    a.ir(f'titulacion/licenciatura/tramite/{tid}')
    concluido = 'Concluido' in a.texto() or 'conclu' in a.texto().lower()
    f.tomar(a, 'pasante', 'concluido', 'Trámite concluido: el resultado queda registrado.', 'concluido')
    a.ir('titulacion/estado')
    f.tomar(a, 'pasante', 'estado', '«Estado de mi trámite»: en qué va y qué sigue, por etapas.', 'estado')
    if op in MOVIL:
        m.ir(f'titulacion/licenciatura/tramite/{tid}')
        f.tomar(m, 'pasante', 'movil_tramite', 'Desde el celular: tu trámite, con la etapa en curso.', 'movil')
        m.cerrar()
    verificar(j, tid, f)
    e.cerrar()
    return tid, concluido


def continuar(p, op, tid):
    """Reanuda un trámite interrumpido hasta concluirlo; solo toma las capturas finales que falten."""
    from lib import manifiesto
    hechas = {v.get('tarea') for k, v in manifiesto().items() if k.startswith(op + '_')}
    finales = {'concluido', 'estado', 'expedientes', 'verificacion', 'exportar', 'zip', 'hito_resultado', 'agenda2', 'movil'} - hechas
    e = Elenco(p)
    f = Fotos(op, solo=finales)
    nombre, cuenta = PASANTES[op]
    email = f'prueba.pasante.{op}@alumno.uaemex.mx'
    a, j, sd, dr = e(email), e(JEFA), e(SUB), e(DIR)
    ctx = dict(CTX_BASE, nombre=nombre, cuenta=cuenta, correo_inst=email, correo_pers='pasante.' + op + '@ejemplo.com', titulo=TITULOS.get(op, VALORES['titulo']),
               capturadas=set(), subidos=set(), validados=set(), cotejos=set(), hitos=set(), hitos_n={}, devuelto=True, acta=str(51200 + OPCIONES.index(op)))
    # Lo ya subido no se vuelve a subir: se marcan como hechos los formularios de documentos con archivo.
    a.ir(f'titulacion/licenciatura/tramite/{tid}')
    for fo in formularios(a.pg):
        if '/documento/' in fo['action'] or '/captura/' in fo['action']:
            ctx['subidos'].add((tid, fo['action']))
            ctx['capturadas'].add(fo['action'])
    vistos = set()
    desde = tam(LOG)
    idx = OPCIONES.index(op)
    fechas = [(datetime.date(2027, 1, 13) + datetime.timedelta(days=3 * idx)).isoformat()]
    ag = 0
    for r in range(60):
        x = sum(1 for _ in iter(lambda: pasante_paso(a, tid, ctx, f, op), False))
        if ag < len(fechas) and a.pg.locator('#form-agenda').count():
            agenda(a, tid, fechas[ag], f, 2)
            ag += 1
            x += 1
        y = sum(1 for _ in iter(lambda: personal_paso(j, tid, ctx, f), False))
        z = firmas_bandeja(j, f, 'responsable_titulacion', 'firmas_jefatura', '') + firmas_bandeja(sd, f, 'subdirector', 'firmas', '') + firmas_bandeja(dr, f, 'subdirector', 'firmas_director', '')
        c = enlaces(e, desde, f, vistos)
        k = 0 if op in SIN_TRABAJO else comision(e, f)
        print(op, 'continúa', r, x, y, z, c, k, flush=True)
        if not (x or y or z or c or k):
            break
    a.ir(f'titulacion/licenciatura/tramite/{tid}')
    f.tomar(a, 'pasante', 'concluido', 'Trámite concluido: el resultado queda registrado.', 'concluido')
    a.ir('titulacion/estado')
    f.tomar(a, 'pasante', 'estado', '«Estado de mi trámite»: en qué va y qué sigue, por etapas.', 'estado')
    verificar(j, tid, f)
    e.cerrar()


def verificar(j, tid, f):
    """Verificación final del expediente y exportación para Servicios Escolares."""
    j.ir('titulacion/expedientes')
    f.tomar(j, 'responsable_titulacion', 'expedientes', '«Expedientes a Servicios Escolares»: los trámites concluidos por verificar y exportar.', 'expedientes')
    j.ir(f'titulacion/expedientes/{tid}')
    if not j.pg.locator('select[name^="m["]').count():
        return
    for sel in j.pg.locator('select[name^="m["]').all():
        opts = sel.locator('option').all_inner_texts()
        sel.select_option('no_aplica' if 'No aplica' in opts else 'ok')
    f.tomar(j, 'responsable_titulacion', 'verificacion', 'Verificación final: cada renglón del formato de integración se marca como entregado o «No aplica».', 'verificacion')
    j.pg.click('button:has-text("Guardar lista")')
    j.pg.wait_for_load_state('networkidle')
    j.pg.click('button:has-text("Hago constar")')
    j.pg.wait_for_load_state('networkidle')
    b = 'button:has-text("Exportar este expediente")'
    f.tomar(j, 'responsable_titulacion', 'exportar', 'Expediente verificado: se exporta la carpeta comprimida para Servicios Escolares.', 'exportar', resaltar=b if j.pg.locator(b).count() else None)
    if j.pg.locator(b).count():
        destino = os.path.join('/tmp', f'expediente_{tid}.zip')
        with j.pg.expect_download() as d:
            j.pg.click(b)
        d.value.save_as(destino)
        f.tomar(j, 'servicios_escolares', 'zip', 'El Departamento de Titulación exporta la carpeta comprimida que recibe Servicios Escolares.', 'zip', resaltar=None)


# ------------------------------------------------------------------------------------------------ borrar, pausar y retomar

def dejar(p):
    """Con un pasante aparte: borrar un trámite sin intervención, «una sola modalidad activa», pausar uno con
    intervención, ver «Abandonadas» y retomar."""
    f = Fotos('comun')
    e = Elenco(p)
    email = 'licenciatura@alumno.uaemex.mx'
    a = e(email)
    ctx = dict(CTX_BASE, nombre='Lucía Herrera Pacheco', cuenta='2011020', correo_inst=email, correo_pers='lucia.herrera@ejemplo.com',
               titulo='Tablero de indicadores para el seguimiento de egresados', capturadas=set(), subidos=set())
    # 1. Inicia aprovechamiento y lo borra (nadie intervino).
    a.ir('titulacion/licenciatura/modalidad/aprovechamiento')
    a.pg.check('input[name=acepto]')
    a.pg.click('form[action$="/modalidad/aprovechamiento"] button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    tid = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
    a.abrir_detalles('details.tit-dejar summary')
    a.pg.locator('details.tit-dejar').first.evaluate('d => d.open = true')
    f.tomar(a, 'pasante', 'dejar_pliegue', 'Al final de tu trámite: «¿Elegiste otra modalidad o quieres dejar este trámite?».', 'dejar_pliegue', resaltar='details.tit-dejar')
    a.ir(f'titulacion/licenciatura/tramite/{tid}/dejar')
    a.pg.check('input[name=entiendo]')
    f.tomar(a, 'pasante', 'borrar', 'Si nadie más intervino, el trámite se borra: la pantalla dice qué se borrará y pide confirmarlo.', 'borrar', resaltar='.tit-confirma')
    a.pg.click('button:has-text("Borrar definitivamente")')
    a.pg.wait_for_load_state('networkidle')
    f.tomar(a, 'pasante', 'borrado', 'Trámite borrado: vuelves a «¿Cómo quieres titularte?» para elegir otra opción.', 'borrado', resaltar='.alerta, .flash, [role=status]')
    # 2. Inicia tesis y lo manda al asesor (interviene el asesor: ya no se puede borrar).
    a.ir('titulacion/licenciatura/modalidad/tesis')
    a.pg.check('input[name=acepto]')
    a.pg.click('form[action$="/modalidad/tesis"] button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    tid = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
    desde = tam(LOG)
    for _ in range(8):
        if not pasante_paso(a, tid, ctx, Fotos('nulo'), 'tesis'):
            break
    enlaces(e, desde, Fotos('nulo'), set())
    # 3. «Solo una modalidad activa»: quiere iniciar EGEL.
    a.ir('titulacion/licenciatura/modalidad/egel')
    f.tomar(a, 'pasante', 'otra_activa', 'Si ya tienes una opción en proceso, la página de otra opción te lo recuerda antes de iniciar.', 'otra_activa', resaltar='.tit-necesitas')
    a.pg.check('input[name=variante][value=pagando_menos]')
    a.pg.check('input[name=acepto]')
    a.pg.click('form[action$="/modalidad/egel"] button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    a.pg.check('input[name=entiendo]')
    f.tomar(a, 'pasante', 'una_activa', 'Solo puede haber una modalidad activa: antes de iniciar otra, la plataforma explica qué pasará con la que tienes y pide confirmarlo.', 'una_activa', resaltar='.tit-confirma')
    a.pg.click('.tit-confirma button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    tid_egel = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
    # 4. Abandonadas y retomar.
    a.ir('titulacion/licenciatura')
    a.pg.locator('#abandonadas').evaluate('d => d.open = true')
    f.tomar(a, 'pasante', 'abandonadas', '«Abandonadas»: el trámite en pausa conserva todo; desde aquí lo retomas.', 'abandonadas', resaltar='#abandonadas')
    a.pg.locator('#abandonadas form button[type=submit]').first.click()
    a.pg.wait_for_load_state('networkidle')
    if a.pg.locator('input[name=entiendo]').count():
        a.pg.check('input[name=entiendo]')
    f.tomar(a, 'pasante', 'retomar', 'Retomar: confirmas, y lo que tengas activo se borra o queda en pausa según el caso.', 'retomar', resaltar='.tit-confirma')
    a.pg.click('.tit-confirma button[type=submit]')
    a.pg.wait_for_load_state('networkidle')
    f.tomar(a, 'pasante', 'retomado', 'El trámite retomado sigue justo donde se quedó.', 'retomado', resaltar='.tit-siguiente')
    # 5. Pausa directa desde el trámite (con intervención).
    a.ir(f'titulacion/licenciatura/tramite/{tid}/dejar')
    if a.pg.locator('input[name=entiendo]').count():
        a.pg.check('input[name=entiendo]')
        f.tomar(a, 'pasante', 'pausa', 'Si otras personas ya intervinieron, el trámite no se borra: queda en pausa y la pantalla explica por qué y qué se conserva.', 'pausa', resaltar='.tit-confirma')
    print('dejar: tesis', tid, 'egel', tid_egel, flush=True)
    e.cerrar()


def marcadores():
    """Si aún no hay PDF publicados, deja marcadores en storage/manuales para que las páginas muestren el bloque de
    descarga en las capturas (tools/manuales_publicar.sh los reemplaza por los manuales reales)."""
    import shutil
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    from generar_latex import combinaciones
    for actor, op in combinaciones():
        destino = os.path.join(RAIZ, 'storage', 'manuales', 'licenciatura', actor, op + '.pdf')
        if not os.path.exists(destino):
            os.makedirs(os.path.dirname(destino), exist_ok=True)
            shutil.copy(crear_pdf(), destino)


if __name__ == '__main__':
    marcadores()
    if '--continuar' in sys.argv:
        with sync_playwright() as p:
            for par in [x for x in sys.argv[1:] if ':' in x]:
                op, tid = par.split(':')
                continuar(p, op, int(tid))
        sys.exit(0)
    args = [x for x in sys.argv[1:] if not x.startswith('--')]
    solo_dejar = '--dejar' in sys.argv
    ops = args or ([] if solo_dejar else OPCIONES)
    t0 = time.time()
    with sync_playwright() as p:
        for op in ops:
            tid, ok = correr(p, op, OPCIONES.index(op))
            print(op, tid, 'CONCLUIDO' if ok else 'ABIERTO', round(time.time() - t0), 's', flush=True)
        if solo_dejar or not args:
            dejar(p)
