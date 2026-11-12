"""Recorrido genérico del trámite: llena formularios, sube PDF y registra hitos, según lo que muestre cada pantalla."""
import re
import os
from lib import *

TEXTO = 'Texto de prueba para el manual de usuario de Titulación.'
VALORES = {
    'cuenta': None, 'carrera': 'computacion', 'sistema': 'fi', 'plan': 'ICO-F2', 'anio_ingreso': '2019', 'anio_egreso': '2024',
    'telefono': '7221234567', 'correo_inst': None, 'correo_pers': None, 'acentos': 'Sí', 'ocupacion': 'Ingeniero de software',
    'fecha_nacimiento': '2000-05-17', 'fecha': None, 'hora': '11:00', 'lugar': 'Sala de Titulación, edificio B', 'vez': 'primera',
    'titulo': 'Sistema de prueba para documentar el proceso de titulación', 'resultado': None, 'acta': None,
}

FORMS_JS = """() => [...document.querySelectorAll('form')].map((f,i) => ({f,i})).filter(({f})=>(f.getAttribute('action')||'').includes('titulacion')).map(({f,i}) => ({
  i, action: f.getAttribute('action'), method: f.method,
  btns: [...f.querySelectorAll('button, input[type=submit]')].map(b=>(b.innerText||b.value||'').trim()),
  visible: !!(f.offsetWidth||f.offsetHeight||f.getClientRects().length),
  els: [...f.elements].filter(e=>e.name&&e.name!='_csrf').map(e=>({tag:e.tagName, type:e.type, name:e.name, req:e.required, dis:e.disabled, val:e.value, max:e.maxLength, pat:e.pattern||'', opts: e.tagName=='SELECT'?[...e.options].map(o=>o.value):[], checked:e.checked}))
}))"""


def formularios(pg):
    return pg.evaluate(FORMS_JS)


def valor_para(el, ctx):
    n = el['name']
    k = re.search(r'\[(\w+)\]', n)
    k = k.group(1) if k else n
    v = ctx.get(k)
    if v is None:
        v = VALORES.get(k)
    if v:
        return v
    if el['tag'] == 'SELECT':
        return next((o for o in el['opts'] if o), '')
    t = el['type']
    if t == 'email':
        return 'prueba@uaemex.mx'
    if t == 'date':
        return '2026-11-12'
    if t == 'time':
        return '11:00'
    if t == 'tel':
        return '7221234567'
    if t == 'number':
        return '1'
    pat = el['pat']
    if pat and re.match(r'^\^?\[0-9\]', pat):
        return '12345678'
    txt = TEXTO
    if el['max'] and el['max'] > 0:
        txt = (txt + ' ') * 40
        txt = txt[: min(el['max'], 400)]
    return txt


def llenar_y_enviar(s, f, ctx, boton=None):
    """Llena el formulario f (de formularios()) con valores de prueba y lo envía."""
    pg = s.pg
    sel = f'form:nth-of-type({f["i"] + 1})'
    forms = pg.locator('form').filter(has=pg.locator('xpath=.'))  # todos
    form = pg.locator('form').nth(f['i'])
    for el in f['els']:
        if el['dis'] or el['type'] in ('hidden', 'submit', 'button', 'file'):
            continue
        loc = form.locator(f'[name="{el["name"]}"]')
        if el['type'] == 'checkbox':
            if el['req'] or el['name'] in ctx.get('marcar', []):
                loc.first.check()
            continue
        if el['type'] == 'radio':
            continue
        v = valor_para(el, ctx)
        if el['tag'] == 'SELECT':
            if not el['req'] and not v:
                continue
            loc.first.select_option(v)
        else:
            loc.first.fill(str(v))
    b = form.locator('button[type=submit], input[type=submit]')
    if boton:
        b = form.locator(f'button:has-text("{boton}")')
    b.first.click()
    pg.wait_for_load_state('networkidle')


def crear_pdf():
    """PDF mínimo de muestra (una página) para las cargas de documentos."""
    if os.path.exists(PDF):
        return
    objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>']
    cont = 'BT /F1 22 Tf 72 700 Td (Documento de muestra - prueba del manual de Titulacion) Tj ET'
    objs.append('<< /Length %d >>\nstream\n%s\nendstream' % (len(cont), cont))
    objs.append('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>')
    out = b'%PDF-1.4\n'
    offs = []
    for i, o in enumerate(objs, 1):
        offs.append(len(out))
        out += ('%d 0 obj\n%s\nendobj\n' % (i, o)).encode('latin-1')
    x = len(out)
    out += ('xref\n0 %d\n0000000000 65535 f \n' % (len(objs) + 1)).encode()
    for o in offs:
        out += ('%010d 00000 n \n' % o).encode()
    out += ('trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF' % (len(objs) + 1, x)).encode()
    open(PDF, 'wb').write(out)


def pasante_avanza(s, tid, ctx, prefijo=None, maximo=60):
    """Hace todo lo que el pasante puede hacer ahora en el trámite. Devuelve cuántas acciones hizo."""
    crear_pdf()
    hechas = 0
    for _ in range(maximo):
        s.ir(f'titulacion/licenciatura/tramite/{tid}')
        acciones = 0
        for f in formularios(s.pg):
            a = f['action']
            if '/captura/' in a and f['els'] and not any(e['dis'] for e in f['els']):
                # solo si la etapa está pendiente: el botón existe
                if re.search(r'/captura/datos$', a) and ctx.get('datos_hechos'):
                    continue
        if prefijo:
            pass
        # 1) capturas de la etapa actual
        for f in formularios(s.pg):
            a = f['action']
            if '/adicional/' in a or '/cancelar' in a or '/estatus' in a or '/reenviar/' in a or '/noaplica/' in a or '/art5' in a:
                continue
            if '/captura/' in a:
                if ctx.setdefault('capturadas', set()) and a in ctx['capturadas']:
                    continue
                ctx['capturadas'].add(a)
                llenar_y_enviar(s, f, ctx)
                acciones += 1
                break
            if '/generar/' in a or '/confirmar/' in a:
                if prefijo:
                    s.foto(f'{prefijo}_acción_{acciones}')
                s.pg.locator('form').nth(f['i']).locator('button[type=submit]').first.click()
                s.pg.wait_for_load_state('networkidle')
                acciones += 1
                break
            if '/documento/' in a:
                subidos = ctx.setdefault('subidos', set())
                if (tid, a) in subidos:
                    continue
                subidos.add((tid, a))
                s.pg.locator('form').nth(f['i']).locator('input[type=file]').set_input_files(PDF)
                s.pg.locator('form').nth(f['i']).locator('button[type=submit]').first.click()
                s.pg.wait_for_load_state('networkidle')
                acciones += 1
                break
        if not acciones:
            # Documentos condicionales que no le aplican al pasante de prueba.
            for f in formularios(s.pg):
                a = f['action']
                if '/noaplica/' in a and re.search(r'dictamen_consejos|const_ingles|registro_dgp|carta_presentacion', a) and (tid, a) not in ctx.setdefault('subidos', set()):
                    ctx['subidos'].add((tid, a))
                    s.pg.locator('form').nth(f['i']).locator('button[type=submit]').first.click()
                    s.pg.wait_for_load_state('networkidle')
                    acciones += 1
                    break
        hechas += acciones
        if not acciones:
            break
    return hechas


def personal_avanza(s, tid, ctx, maximo=80, foto=None):
    """Valida documentos, coteja y registra los hitos del departamento. Devuelve cuántas acciones hizo."""
    hechas = 0
    for _ in range(maximo):
        s.ir(f'titulacion/gestion/{tid}')
        hecho = False
        for f in formularios(s.pg):
            a = f['action']
            form = s.pg.locator('form').nth(f['i'])
            if re.search(r'/gestion/\d+/documento/\d+$', a) and 'Validar' in f['btns'] and a not in ctx.setdefault('validados', set()):
                ctx['validados'].add(a)
                form.locator('button:has-text("Validar")').first.click()
                hecho = True
            elif '/cotejo/' in a and not ctx.get('cotejo_hecho', {}).get(a):
                ctx.setdefault('cotejo_hecho', {})[a] = 1
                for r in form.locator('input[type=radio]').all()[:1]:
                    r.check()
                form.locator('button[type=submit]').first.click()
                hecho = True
            elif '/hito/' in a and not ctx.setdefault('hitos_hechos', set()).__contains__(a) and f['els']:
                ctx['hitos_hechos'].add(a)
                llenar_y_enviar(s, f, ctx)
                hecho = True
            if hecho:
                s.pg.wait_for_load_state('networkidle')
                break
        if not hecho:
            break
        hechas += 1
    return hechas


def subdirector_firma(s, foto=None):
    """El Subdirector firma todo lo que espera su firma. Devuelve True si firmó algo."""
    s.ir('titulacion/firmas')
    b = s.pg.locator('button:has-text("Firmar todos los pendientes")')
    if b.count() and b.first.is_enabled() and s.pg.locator('input[name="ids[]"]').count():
        if foto:
            s.foto(foto, completa=True)
        b.first.click()
        s.pg.wait_for_load_state('networkidle')
        return True
    return False
