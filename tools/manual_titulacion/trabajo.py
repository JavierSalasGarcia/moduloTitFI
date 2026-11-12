"""Pasos propios de las opciones con trabajo escrito: firmas por enlace de correo, comisión y jurado."""
import re
from flujo import *


class Elenco:
    """Sesiones de los actores de prueba, abiertas bajo demanda."""

    def __init__(self, pw):
        self.pw = pw
        self.s = {}

    def __call__(self, email):
        if email not in self.s:
            self.s[email] = Sesion(self.pw, email)
        return self.s[email]

    def cerrar(self):
        for x in self.s.values():
            x.cerrar()


PIES = {'firma': 'Enlace personal del correo: revisar el documento y firmar.',
        'jurado': 'Enlace personal del Coordinador de Docencia para ratificar o modificar el sínodo o jurado y firmar.',
        'sinodal': 'Enlace personal del sinodal para consultar su oficio y confirmar el acuse de recibo.'}


def tipo_de(ruta):
    return ruta.split('/')[2]


def firmar_correos(elenco, desde, prefijo=None, tipos=('firma', 'jurado', 'sinodal'), vistos=None):
    """Abre cada enlace nuevo de la bitácora de correos con la cuenta del destinatario y pulsa el botón de firmar."""
    vistos = vistos if vistos is not None else set()
    txt = open(LOG, 'rb').read()[desde:].decode('utf-8', 'replace') if os.path.exists(LOG) else ''
    hechos = 0
    for bloque in txt.split('=== ')[1:]:
        cab = bloque.split('\n', 1)[0]
        m = re.search(r' a (\S+@\S+) ===', cab)
        if not m:
            continue
        para = m.group(1)
        for ruta in re.findall(r'/titulacion/(?:' + '|'.join(tipos) + r')/t/[\w-]+', bloque):
            if ruta in vistos or not para.startswith('prueba.'):
                continue
            vistos.add(ruta)
            s = elenco(para)
            s.ir(ruta)
            if prefijo:
                actor = re.sub(r'[\d.].*$', '', para.split('@')[0].replace('prueba.', ''))
                actor = {'evaluador': 'comision', 'encargada': 'comision'}.get(actor, actor)
                s.foto(f'{prefijo}_{actor}_{len(vistos):02d}_enlace_{tipo_de(ruta)}', completa=True, pie=PIES.get(tipo_de(ruta), 'Enlace personal recibido por correo.'))
            b = s.pg.locator('button:has-text("Firmar"), button:has-text("Confirmar"), button:has-text("Ratificar")')
            if b.count():
                b.first.click()
                s.pg.wait_for_load_state('networkidle')
                hechos += 1
    return hechos


def comision_asignar(elenco, foto=None):
    en = elenco('prueba.encargada.comision@uaemex.mx')
    en.ir('titulacion/comision')
    if foto:
        en.foto(foto + '_01_bandeja')
    href = en.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /comision\/\d+$/.test(h))")
    if not href:
        return False
    en.ir(href[0])
    if not en.pg.locator('input[name="miembros[]"]').count():
        return False
    for c in en.pg.locator('input[name="miembros[]"]').all():
        c.check()
    if foto:
        en.foto(foto + '_02_asignar', completa=True)
    en.pg.click('button:has-text("Enviar a revisión")')
    en.pg.wait_for_load_state('networkidle')
    return True


def evaluar(elenco, email, foto=None):
    """Un integrante del comité revisa el protocolo asignado, lo aprueba y firma."""
    ev = elenco(email)
    ev.ir('titulacion/revision')
    href = ev.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /revision\/\d+$/.test(h))")
    if not href:
        return False
    ev.ir(href[0])
    if not ev.pg.locator('button:has-text("Firmar y enviar")').count():
        return False
    ev.pg.get_by_label('Se aprueba', exact=True).check()
    ev.pg.get_by_label('No', exact=True).first.check()
    ev.pg.locator('textarea[name=forma]').fill('Redacción clara; cuidar el formato de las referencias y la numeración de las secciones.')
    ev.pg.locator('textarea[name=fondo]').fill('El planteamiento, los objetivos y la metodología son congruentes con el alcance propuesto.')
    if foto:
        ev.foto(foto, completa=True)
    ev.pg.once('dialog', lambda d: d.accept())
    ev.pg.click('button:has-text("Firmar y enviar")')
    ev.pg.wait_for_load_state('networkidle')
    return True


def dictaminar(elenco, foto=None):
    en = elenco('prueba.encargada.comision@uaemex.mx')
    en.ir('titulacion/comision')
    href = en.pg.evaluate("() => [...document.querySelectorAll('a')].map(a=>a.getAttribute('href')).filter(h=>h && /comision\/\d+$/.test(h))")
    if not href:
        return False
    en.ir(href[0])
    b = en.pg.locator('form[action$="/dictaminar"] button:has-text("Enviar")')
    if not b.count():
        return False
    radios = en.pg.locator('form[action$="/dictaminar"] input[name=dictamen]').all()
    ok = [r for r in radios if 'aprob' in (r.get_attribute('value') or '') and 'no' not in (r.get_attribute('value') or '')[:2]]
    (ok or radios)[0].check()
    if foto:
        en.foto(foto, completa=True)
    en.pg.once('dialog', lambda d: d.accept())
    b.first.click()
    en.pg.wait_for_load_state('networkidle')
    return True


SINODALES = [('Sergio Sinodal Uno Prueba', 'Dr.', 'masculino', 'prueba.sinodal1@uaemex.mx'),
             ('Laura Sinodal Dos Prueba', 'Dra.', 'femenino', 'prueba.sinodal2@uaemex.mx'),
             ('Tomás Sinodal Tres Prueba', 'M. en I.', 'masculino', 'prueba.sinodal3@uaemex.mx'),
             ('Pablo Evaluador Uno Prueba', 'Dr.', 'masculino', 'prueba.evaluador1@uaemex.mx'),
             ('Ana Evaluadora Dos Prueba', 'M. en C.', 'femenino', 'prueba.evaluador2@uaemex.mx')]


def agenda_pasante(s, tid, fecha, foto=None):
    """Elige fecha y hora del examen y propone al sínodo o jurado; envía la solicitud al Coordinador."""
    s.ir(f'titulacion/licenciatura/tramite/{tid}')
    if not s.pg.locator('#form-agenda').count():
        return False
    import datetime
    f = s.pg.locator('#form-agenda')
    d = datetime.date.fromisoformat(fecha)
    horas = []
    for _ in range(30):
        if d.weekday() < 5:
            f.locator('input[name=fecha]').fill(d.isoformat())
            f.locator('input[name=fecha]').dispatch_event('change')
            f.locator('input[name=fecha]').dispatch_event('input')
            s.pg.wait_for_timeout(1200)
            horas = f.locator('select[name=hora] option').evaluate_all("o => o.map(x => x.value).filter(v => v)")
            if horas:
                break
        d += datetime.timedelta(days=1)
    if not horas:
        raise RuntimeError('Sin horarios disponibles desde el ' + fecha)
    f.locator('select[name=hora]').select_option(horas[0])
    for i, (nombre, grado, genero, correo) in enumerate(SINODALES):
        f.locator(f'input[name="s[{i}][nombre]"]').fill(nombre)
        f.locator(f'input[name="s[{i}][grado]"]').fill(grado)
        f.locator(f'select[name="s[{i}][genero]"]').select_option(genero)
        f.locator(f'input[name="s[{i}][email]"]').fill(correo)
    if foto:
        s.pg.locator('#agenda').scroll_into_view_if_needed()
        s.foto(foto, completa=True)
    s.pg.once('dialog', lambda d: d.accept())
    f.locator('button[type=submit]').first.click()
    s.pg.wait_for_load_state('networkidle')
    return True
