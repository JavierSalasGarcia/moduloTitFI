"""Recorre de punta a punta la evaluación de grado (módulo Posgrado) y toma las capturas de los manuales
(docs/manuales/posgrado/img + _capturas.json). Usa el arnés de tools/manual_titulacion/lib.py.

Recorridos:
  completo   maestría, modalidad Tesis: los cuatro momentos con todos los actores (egresado, director y sinodales por
             enlace, un sinodal con cuenta que firma desde «Posgrado: firmas», un externo cuya firma UAEMéx no alcanza
             y la actualiza, Coordinación, Apoyo a Grado y supervisión), «Mi perfil» con la vigencia y su aviso.
  modalidad  trabajo terminal, proyecto terminal y tesis por artículos (doctorado): datos con la modalidad elegida,
             voto aprobatorio firmado por el director y lo que cambia en cada una.
  dejar      borrar un trámite sin intervención, pausar uno con intervención, «un solo trámite activo» y retomar.

Uso: python3 tools/manual_posgrado/recorrido.py [completo] [modalidad] [dejar]      (sin argumentos: los tres)
Requiere la base local preparada con tools/manual_titulacion/semilla_manuales.php.
"""
import datetime
import os
import re
import sys

AQUI = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(AQUI), 'manual_titulacion'))
from lib import LOG_POS, RAIZ, Sesion, correos, crear_pdf, sync_playwright, tam  # noqa: E402

IMG = os.path.join(RAIZ, 'docs', 'manuales', 'posgrado', 'img')
APOYO = 'apoyo.grado@uaemex.mx'
COORD = 'coord.avanzados@uaemex.mx'
ADMIN = 'admin.local@uaemex.mx'
SUBD = 'prueba.subdirector.academico@uaemex.mx'
DIRECTOR = ('Dr.', 'Juan Carlos Ponce Ríos', 'profesor.prueba@uaemex.mx')
CON_CUENTA = 'prueba.sinodal1@uaemex.mx'
EXTERNO = 'h.zamora@ejemplo.edu.mx'
SINODO = [
    ('Dr. Juan Carlos Ponce Ríos', 'director', 'profesor.prueba@uaemex.mx', False),
    ('Dr. Sergio Cárdenas Bravo', 'nab', CON_CUENTA, False),
    ('Dra. Laura Inés Quintero Gil', 'profesor', 'prueba.sinodal2@uaemex.mx', False),
    ('Dr. Héctor Zamora Lugo', 'externo', EXTERNO, True),
    ('M. en I. Tomás Rendón Aguilar', 'profesor', 'prueba.sinodal3@uaemex.mx', False),
    ('Dr. Pablo Andrade Soto', 'nab', 'prueba.evaluador1@uaemex.mx', False),
    ('M. en C. Ana Lucía Ferrer Montes', 'profesor', 'prueba.evaluador2@uaemex.mx', False),
]
EGRESADOS = {
    'tesis': ('maestria@alumno.uaemex.mx', 'Marco Antonio Juárez Téllez', '1820456', 'maestria', 'Maestría en Movilidad y Transporte',
              'Modelo de asignación de rutas para el transporte público del Valle de Toluca'),
    'trabajo_terminal': ('prueba.egresado.tt@alumno.uaemex.mx', 'Iván Ledesma Corona', '1820511', 'maestria', 'Maestría en Movilidad y Transporte',
                         'Plan de movilidad peatonal para el centro histórico de una ciudad media'),
    'proyecto_terminal': ('prueba.egresado.pt@alumno.uaemex.mx', 'Paula Sierra Mondragón', '1820533', 'maestria', 'Maestría en Movilidad y Transporte',
                          'Sistema de información para el mantenimiento de una flota de autobuses'),
    'articulos': ('doctorado@alumno.uaemex.mx', 'Dora Valdés Castañeda', '1620117', 'doctorado', 'Doctorado en Ciencias de la Ingeniería',
                  'Materiales compuestos de bajo costo para sensores de presión flexibles'),
}
MOD_NOMBRE = {'tesis': 'Tesis', 'trabajo_terminal': 'Trabajo terminal de grado', 'proyecto_terminal': 'Proyecto terminal',
              'articulos': 'Tesis por artículos especializados o capítulo para libro'}


class Fotos:
    def __init__(self, mod):
        self.mod, self.n, self.vistos = mod, {}, set()

    def una_vez(self, k):
        if k in self.vistos:
            return False
        self.vistos.add(k)
        return True

    def tomar(self, s, actor, tema, pie, tarea, momento='', **kw):
        self.n[actor] = self.n.get(actor, 0) + 1
        nombre = f'{self.mod}_{actor}_{self.n[actor]:02d}_{tema}'
        return s.foto(nombre, pie, actor=actor, opcion=self.mod, etapa=str(momento), extra={'tarea': tarea}, **kw)


def ses(p, email=None, **kw):
    return Sesion(p, email, img=IMG, **kw)


def abrir_todo(s, sel):
    s.pg.locator(sel).evaluate_all("""es => es.forEach(e => { let d = e.closest('details'); while (d) { d.open = true; d = d.parentElement && d.parentElement.closest('details'); } })""")


def clic(s, sel):
    abrir_todo(s, sel)
    s.pg.locator(sel).first.click()
    s.pg.wait_for_load_state('networkidle')


def tid_de(s):
    return int(re.search(r'tramite/(\d+)', s.pg.url).group(1))


# ------------------------------------------------------------------------------------------------ egresado

def iniciar(e, f=None, movil=None):
    e.ir('posgrado')
    if f:
        f.tomar(e, 'egresado', 'entrada', '«Posgrado: obtener mi grado»: los cuatro momentos, las modalidades y sus manuales.', 'entrada', 1, resaltar='.pos-modalidades', con_menu=True)
        e.ir('posgrado/modalidad/' + f.mod)
        f.tomar(e, 'egresado', 'informativa', f'Página de la modalidad «{MOD_NOMBRE[f.mod]}», antes de iniciar: en qué consiste, requisitos en resumen y manuales.', 'informativa', 1, resaltar='#manual')
        if movil:
            movil.ir('posgrado/modalidad/' + f.mod)
            f.tomar(movil, 'egresado', 'movil_modalidad', 'Desde el celular: la página de la modalidad y su manual.', 'movil', 1, resaltar='#manual')
        e.ir('posgrado')
    e.pg.check('#comenzar input[name=acepto]') if e.pg.locator('#comenzar input[name=acepto]').count() else e.pg.locator('input[name=acepto]').first.check()
    if f:
        f.tomar(e, 'egresado', 'comenzar', 'Aceptas el aviso de privacidad y pulsas «Comenzar mi trámite de grado».', 'comenzar', 1, resaltar='#comenzar')
    clic(e, 'form[action$="/posgrado/iniciar"] button[type=submit]')
    if e.pg.locator('input[name=entiendo]').count():
        return None
    return tid_de(e)


def datos(e, tid, mod, f=None):
    email, nombre, cuenta, nivel, programa, titulo = EGRESADOS[mod]
    e.ir(f'posgrado/tramite/{tid}')
    if f:
        f.tomar(e, 'egresado', 'siguiente_datos', 'Tu siguiente paso: siempre una sola acción, aquí «Capturar mis datos».', 'siguiente', 1, resaltar='.pos-paso')
    pg = e.pg
    e.abrir_detalles('form[action$="/datos"]')
    val = {'nombre': nombre, 'cuenta': cuenta, 'programa': programa, 'plan': 'Plan 2019', 'periodo_inicio': '2024A', 'telefono': '7221234567',
           'correo': email, 'ocupacion': 'Analista de movilidad urbana', 'titulo': titulo, 'fecha_previo': '2026-10-20',
           'director_grado': DIRECTOR[0], 'director_nombre': DIRECTOR[1], 'director_correo': DIRECTOR[2]}
    for k, v in val.items():
        loc = pg.locator(f'form[action$="/datos"] [name="c[{k}]"]')
        if loc.count():
            loc.first.fill(v)
    pg.locator('form[action$="/datos"] [name="c[nivel]"]').select_option(nivel)
    pg.locator('form[action$="/datos"] [name="c[modalidad]"]').select_option(mod if mod in ('tesis', 'trabajo_terminal', 'proyecto_terminal', 'articulos') else 'tesis')
    pg.locator('form[action$="/datos"] input[name="c[acentos]"][value=si]').check()
    pg.locator('form[action$="/datos"] input[name="c[art53]"][value=no]').check()
    if f:
        pg.locator('form[action$="/datos"] [name="c[modalidad]"]').scroll_into_view_if_needed()
        f.tomar(e, 'egresado', 'datos', f'Tus datos: aquí eliges la modalidad («{MOD_NOMBRE[mod]}») y capturas los datos de tu director con su correo.', 'datos', 1, resaltar='form[action$="/datos"] [name="c[modalidad]"]')
    clic(e, 'form[action$="/datos"] button[type=submit]')


def pedir_voto(e, tid, f=None):
    e.ir(f'posgrado/tramite/{tid}')
    sel = 'form[action$="/voto"] button[type=submit]'
    if not e.pg.locator(sel).count():
        return False
    if f:
        f.tomar(e, 'egresado', 'voto', 'Pides el voto aprobatorio: tu director recibe por correo su enlace para firmarlo en línea.', 'voto', 1, resaltar='form[action$="/voto"]')
    clic(e, sel)
    return True


def firmar_datos(e, tid, f=None):
    e.ir(f'posgrado/tramite/{tid}')
    if not e.pg.locator('form[action$="/firmar-datos"]').count():
        return False
    e.pg.locator('form[action$="/firmar-datos"] input[name=leido]').check()
    if f:
        f.tomar(e, 'egresado', 'firmar_datos', 'Revisas tu formato de datos del interesado y lo firmas en línea con un botón.', 'firmar_datos', 1, resaltar='form[action$="/firmar-datos"]')
    clic(e, 'form[action$="/firmar-datos"] button[type=submit]')
    return True


def subir_todo(e, tid, f=None, solo_devueltos=False):
    hechos = 0
    for _ in range(40):
        e.ir(f'posgrado/tramite/{tid}')
        forms = e.pg.locator('#requisitos form[enctype="multipart/form-data"]')
        objetivo = None
        for i in range(forms.count()):
            fo = forms.nth(i)
            boton = fo.locator('button[type=submit]').inner_text().strip()
            fila = fo.evaluate("f => (f.closest('li') || f).innerText")
            devuelto = 'Corregir' in fila
            if (boton == 'Subir PDF' and not solo_devueltos) or devuelto:
                objetivo = (fo, devuelto)
                break
        if not objetivo:
            break
        fo, devuelto = objetivo
        e.abrir_detalles('#requisitos form[enctype="multipart/form-data"]')
        fo.evaluate("f => (f.closest('li') || f).id = 'manual-fila'")
        fo.locator('input[type=file]').set_input_files(crear_pdf())
        if f and devuelto:
            f.tomar(e, 'egresado', 'corregir', 'Documento devuelto: lees la observación de Apoyo a Grado, eliges el PDF corregido y pulsas «Reemplazar».', 'corregir', 1, resaltar='#manual-fila')
        elif f and f.una_vez('subir'):
            f.tomar(e, 'egresado', 'subir', 'Subes cada requisito en PDF desde su renglón; queda «En revisión».', 'subir', 1, resaltar='#manual-fila')
        fo.locator('button[type=submit]').click()
        e.pg.wait_for_load_state('networkidle')
        hechos += 1
    return hechos


# ------------------------------------------------------------------------------------------------ enlaces y firmas

def enlaces_de(desde, para=None):
    out = []
    for dest, bloque in correos(LOG_POS, desde):
        if para and dest != para:
            continue
        asunto = bloque.split('\n', 2)[1].strip() if '\n' in bloque else ''
        for r in re.findall(r'/posgrado/firma/t/[\w-]{43}', bloque):
            out.append((dest, asunto, r))
    return out


def firmar_enlace(p, ruta, f=None, actor='', tema='', pie='', vigencia=None, respuestas='si', foto_despues=False, momento=''):
    s = ses(p)
    s.ir(ruta)
    pg = s.pg
    if not pg.locator('form[action$="/firmar"]').count():
        s.cerrar()
        return False
    for g in pg.locator('form[action$="/firmar"] .pos-pregunta').all():
        g.locator(f'input[value={respuestas}]').check()
    if pg.locator('form[action$="/firmar"] input[name=vigencia]').count():
        pg.locator('form[action$="/firmar"] input[name=vigencia]').fill(vigencia or '2028-06-30')
    pg.locator('form[action$="/firmar"] input[name=leido]').check()
    if f:
        f.tomar(s, actor, tema, pie, tema, momento, resaltar='form[action$="/firmar"]')
    clic(s, 'form[action$="/firmar"] button[type=submit]')
    if f and foto_despues:
        f.tomar(s, actor, tema + '_hecho', 'Después de firmar: la confirmación y el documento con tu sello para descargar.', tema + '_hecho', momento, resaltar='.pos-ok, .pos-alerta')
    s.cerrar()
    return ruta


def coordinacion_firma(c, f=None, tema='', pie='', momento=''):
    c.ir('posgrado/firmas')
    if not c.pg.locator('input[name="ids[]"]').count():
        return False
    if c.pg.locator('#pos-todos').count():
        c.pg.locator('#pos-todos').check()
    c.pg.locator('input[name=leido]').check()
    if f and tema:
        f.tomar(c, 'coordinacion', tema, pie, tema, momento, resaltar='form[action$="/firmas/coordinacion"]')
    clic(c, 'form[action$="/firmas/coordinacion"] button[type=submit]')
    return True


def ag_validar(a, tid, f=None, devolver=False):
    hechos = 0
    saltar = set()
    for _ in range(60):
        a.ir(f'posgrado/gestion/{tid}')
        forms = [x for x in a.pg.locator('form:has(input[name=decision][value=validado])').all() if x.get_attribute('action') not in saltar]
        if not forms:
            break
        val = forms[0]
        accion = val.get_attribute('action')
        if devolver and hechos == 2:
            dev = a.pg.locator(f'form[action="{accion}"]:has(input[name=decision][value=devuelto])').first
            abrir_todo(a, f'form[action="{accion}"]')
            dev.locator('textarea[name=comentario]').fill('El acta está incompleta: falta el reverso. Escanéala por ambos lados en un solo PDF.')
            dev.evaluate("f => (f.closest('li') || f.parentElement).id = 'manual-fila'")
            if f:
                f.tomar(a, 'apoyo_grado', 'devolver', 'Devolver un documento: escribes qué debe corregir el egresado y pulsas «Devolver».', 'devolver', 1, resaltar='#manual-fila')
            dev.locator('button[type=submit]').click()
            a.pg.wait_for_load_state('networkidle')
            saltar.add(accion)
            devolver = False
            hechos += 1
            continue
        if f and f.una_vez('validar'):
            val.evaluate("f => (f.closest('li') || f.parentElement).id = 'manual-fila'")
            f.tomar(a, 'apoyo_grado', 'validar', 'Validar un documento: abres el PDF y pulsas «Validar».', 'validar', 1, resaltar='#manual-fila')
        abrir_todo(a, f'form[action="{accion}"]')
        val.locator('button[type=submit]').click()
        a.pg.wait_for_load_state('networkidle')
        hechos += 1
    return hechos


def ag_fisicos(a, tid, f=None):
    for _ in range(10):
        a.ir(f'posgrado/gestion/{tid}')
        b = a.pg.locator('form[action*="/fisico/"]:has(input[name=accion][value=marcar])')
        if not b.count():
            break
        if f and f.una_vez('fisico'):
            b.first.evaluate("f => (f.closest('li') || f.parentElement).id = 'manual-fila'")
            f.tomar(a, 'apoyo_grado', 'fisico', 'Entregas en físico (fotografías, USB, resumen): las marcas como recibidas el día del cotejo.', 'fisico', 1, resaltar='#manual-fila')
        b.first.locator('button[type=submit]').click()
        a.pg.wait_for_load_state('networkidle')


def ag_emitir(a, tid, clave, f=None, pie='', momento=''):
    a.ir(f'posgrado/gestion/{tid}')
    sel = f'form[action$="/emitir/{clave}"]'
    if not a.pg.locator(sel).count():
        return False
    if f:
        f.tomar(a, 'apoyo_grado', 'emitir_' + clave, pie, 'emitir_' + clave, momento, resaltar=sel)
    clic(a, sel + ' button[type=submit]')
    return True


# ------------------------------------------------------------------------------------------------ recorrido completo

def completo(p):
    mod = 'tesis'
    f = Fotos(mod)
    email = EGRESADOS[mod][0]
    e = ses(p, email)
    m = ses(p, email, ancho=390, alto=844, movil=True)
    a = ses(p, APOYO)
    c = ses(p, COORD)
    desde = tam(LOG_POS)
    tid = iniciar(e, f, movil=m)
    print('posgrado completo: trámite', tid, flush=True)
    datos(e, tid, mod, f)
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'siguiente_voto', 'Con tus datos guardados, el siguiente paso cambia: pedir el voto aprobatorio a tu director.', 'siguiente', 1, resaltar='.pos-paso')
    pedir_voto(e, tid, f)
    firmar_datos(e, tid, f)
    subir_todo(e, tid, f)
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'siguiente_revision', 'Con todo subido, el siguiente paso es de Apoyo a Grado: revisar tus documentos.', 'siguiente', 1, resaltar='.pos-paso')
    m.ir(f'posgrado/tramite/{tid}')
    f.tomar(m, 'egresado', 'movil_tramite', 'Desde el celular: tu siguiente paso y los cuatro momentos.', 'movil', 1)
    # Director: voto aprobatorio por enlace.
    for dest, asunto, ruta in enlaces_de(desde, DIRECTOR[2]):
        firmar_enlace(p, ruta, f, 'director', 'voto', 'El director abre el enlace de su correo, lee el voto aprobatorio y lo firma; no necesita cuenta.', foto_despues=True, momento=1)
    # Apoyo a Grado: bandeja, validación, devolución y corrección.
    a.ir('posgrado/gestion')
    f.tomar(a, 'apoyo_grado', 'bandeja', 'Bandeja de Apoyo a Grado: trámites por momento y documentos por validar.', 'bandeja', 1)
    a.ir(f'posgrado/gestion/{tid}')
    f.tomar(a, 'apoyo_grado', 'expediente', 'Expediente del egresado: requisitos con su estado, PDF y acciones.', 'expediente', 1)
    ag_validar(a, tid, f, devolver=True)
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'devuelto', 'Si Apoyo a Grado te devuelve un documento, tu siguiente paso te lleva a corregirlo.', 'devuelto', 1, resaltar='.pos-paso')
    subir_todo(e, tid, f, solo_devueltos=True)
    ag_validar(a, tid, f)
    ag_fisicos(a, tid, f)
    ag_emitir(a, tid, 'no_adeudo', f, 'Emites la constancia de no adeudo de mobiliario y equipo; queda en firma de la Coordinación.', 1)
    coordinacion_firma(c, f, 'firmas_no_adeudo', '«Posgrado: firmas» de la Coordinación: eliges los documentos, confirmas que los revisaste y firmas.', 1)
    a.ir(f'posgrado/gestion/{tid}')
    a.abrir_detalles('form[action$="/cotejo"]')
    f.tomar(a, 'apoyo_grado', 'cotejo', 'Cotejo: con los originales a la vista registras fecha y hora; se emite el oficio de cotejo.', 'cotejo', 1, resaltar='form[action$="/cotejo"]')
    clic(a, 'form[action$="/cotejo"] button[type=submit]')
    coordinacion_firma(c, f, 'firmas_cotejo', 'La Coordinación firma el oficio de cotejo.', 1)
    # Momento 2: sínodo y fecha.
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'siguiente_sinodo', 'Momento 2: propones a tu sínodo y la fecha de tu examen.', 'siguiente', 2, resaltar='.pos-paso')
    e.abrir_detalles('form[action$="/sinodo"]')
    pg = e.pg
    for n, (nom, tipo, correo, ext) in enumerate(SINODO):
        pg.locator(f'input[name="m[{n}][nombre]"]').fill(nom)
        pg.locator(f'select[name="m[{n}][tipo]"]').select_option(tipo)
        pg.locator(f'input[name="m[{n}][correo]"]').fill(correo)
        if ext:
            pg.locator(f'input[name="m[{n}][externo]"]').check()
    fmin = pg.locator('form[action$="/sinodo"] input[name=fecha]').get_attribute('min')
    fecha = datetime.date.fromisoformat(fmin) + datetime.timedelta(days=7)
    while fecha.weekday() >= 5:
        fecha += datetime.timedelta(days=1)
    pg.locator('form[action$="/sinodo"] input[name=fecha]').fill(fecha.isoformat())
    pg.locator('form[action$="/sinodo"] input[name=hora]').fill('11:00')
    pg.locator('input[name="m[3][correo]"]').scroll_into_view_if_needed()
    f.tomar(e, 'egresado', 'sinodo', 'Propuesta de sínodo: cinco propietarios y dos suplentes, cada uno con su correo; marca a los externos.', 'sinodo', 2, resaltar='form[action$="/sinodo"]')
    pg.locator('form[action$="/sinodo"] input[name=fecha]').scroll_into_view_if_needed()
    f.tomar(e, 'egresado', 'fecha', 'Fecha y hora propuestas: la plataforma solo acepta fechas con al menos 10 días hábiles.', 'fecha', 2, resaltar='form[action$="/sinodo"] button[name=enviar]')
    clic(e, 'form[action$="/sinodo"] button[name=enviar]')
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'esperando_firmas', 'Mientras firman, ves quién falta; puedes reenviar el enlace o corregir un correo.', 'esperando', 2, resaltar='#firmas-m2, .pos-paso')
    # Firmas del momento 2.
    corta = (fecha + datetime.timedelta(days=10)).isoformat()   # no alcanza: debe durar un mes más que el examen
    for dest, asunto, ruta in enlaces_de(desde):
        if 'solicitud' in asunto.lower() and dest == DIRECTOR[2]:
            firmar_enlace(p, ruta, f, 'director', 'solicitud', 'El director firma la solicitud de evaluación de grado desde su enlace.', momento=2)
    vistos = set()
    for dest, asunto, ruta in enlaces_de(desde):
        if 'visto bueno' not in asunto.lower() or ruta in vistos:
            continue
        vistos.add(ruta)
        if dest == CON_CUENTA:
            continue
        if dest == EXTERNO:
            firmar_enlace(p, ruta, f, 'sinodal', 'visto_bueno_externo', 'Visto bueno del sinodal externo (sin cuenta): contesta tres preguntas, declara la vigencia de su firma electrónica UAEMéx y firma.', vigencia=corta, momento=2)
            s = ses(p)
            s.ir(ruta)
            f.tomar(s, 'sinodal', 'vigencia_no_alcanza', 'Si la vigencia declarada no cubre un mes después del examen, el enlace lo avisa y permite actualizar la fecha.', 'vigencia', 2, resaltar='#vigencia')
            e.ir(f'posgrado/tramite/{tid}')
            f.tomar(e, 'egresado', 'vigencia_aviso', 'Tu siguiente paso te avisa qué sinodal debe renovar su firma electrónica UAEMéx.', 'vigencia', 2, resaltar='.pos-paso')
            s.abrir_detalles('form[action$="/vigencia"]')
            s.pg.locator('form[action$="/vigencia"] input[name=vigencia]').fill('2028-03-31')
            f.tomar(s, 'sinodal', 'vigencia_actualizar', 'El sinodal actualiza la vigencia desde el mismo enlace.', 'vigencia', 2, resaltar='form[action$="/vigencia"]')
            clic(s, 'form[action$="/vigencia"] button[type=submit]')
            s.cerrar()
        else:
            firmar_enlace(p, ruta, f if f.una_vez('vb_normal') else None, 'sinodal', 'visto_bueno', 'Visto bueno de un integrante del sínodo desde su enlace.', momento=2)
    # El sinodal con cuenta firma desde «Posgrado: firmas».
    sc = ses(p, CON_CUENTA)
    sc.ir('posgrado/firmas')
    f.tomar(sc, 'sinodal', 'mis_firmas', 'Con cuenta en Traza FI, el sinodal encuentra sus firmas en «Posgrado: firmas».', 'mis_firmas', 2, resaltar='.pos-firmantes')
    href = sc.pg.locator('a[href*="/posgrado/firma/mia/"]').first.get_attribute('href')
    sc.ir(href)
    for g in sc.pg.locator('.pos-pregunta').all():
        g.locator('input[value=si]').check()
    sc.pg.locator('form[action$="/firmar"] input[name=vigencia]').fill('2027-12-31')
    sc.pg.locator('form[action$="/firmar"] input[name=leido]').check()
    f.tomar(sc, 'sinodal', 'visto_bueno_cuenta', 'El visto bueno con sesión: el mismo formulario, con la vigencia de su perfil ya escrita.', 'visto_bueno', 2, resaltar='form[action$="/firmar"]')
    clic(sc, 'form[action$="/firmar"] button[type=submit]')
    # Apoyo a Grado: externos y programación.
    a.ir(f'posgrado/gestion/{tid}')
    if a.pg.locator('form[action$="/externos"] input[value=marcar]').count():
        f.tomar(a, 'apoyo_grado', 'externos', 'Con sinodales externos, confirmas que están dados de alta en Control Escolar.', 'externos', 2, resaltar='form[action$="/externos"]')
        clic(a, 'form[action$="/externos"] button[type=submit]')
    a.ir(f'posgrado/gestion/{tid}')
    fp = a.pg.locator('form[action$="/programacion"]')
    fp.locator('input[name=fecha]').fill(fecha.isoformat())
    fp.locator('input[name=hora]').fill('11:00')
    fp.locator('input[name=lugar]').fill('el Auditorio 120 de la Facultad de Ingeniería')
    f.tomar(a, 'apoyo_grado', 'programar', 'Programación: confirmas fecha, hora, lugar y modalidad del examen.', 'programar', 2, resaltar='form[action$="/programacion"]')
    clic(a, 'form[action$="/programacion"] button[type=submit]')
    # Momento 3: invitaciones.
    for k, pie in (('invitaciones', 'Emites los oficios de invitación al sínodo.'), ('acuse', 'Emites el acuse de recibido de oficios.'), ('notificacion', 'Emites la notificación al director.')):
        ag_emitir(a, tid, k, f if k == 'invitaciones' else None, pie, 3)
    coordinacion_firma(c, f, 'firmas_invitaciones', 'La Coordinación firma las invitaciones, el acuse y la notificación; al firmar, cada integrante recibe la suya por correo.', 3)
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'acuses', 'Momento 3: ves quién ya acusó de recibido su invitación.', 'acuses', 3, resaltar='.pos-paso')
    for dest, asunto, ruta in enlaces_de(desde):
        if 'invitaci' in asunto.lower() or 'acuse' in asunto.lower():
            pie = 'Invitación al sínodo: el integrante consulta su oficio y acusa de recibido en línea.'
            firmar_enlace(p, ruta, f if (dest == EXTERNO and f.una_vez('acuse')) else None, 'sinodal', 'acuse', pie, momento=3)
    if sc.pg:
        sc.ir('posgrado/firmas')
        h = sc.pg.locator('a[href*="/posgrado/firma/mia/"]')
        if h.count():
            sc.ir(h.first.get_attribute('href'))
            if sc.pg.locator('form[action$="/firmar"]').count():
                sc.pg.locator('form[action$="/firmar"] input[name=leido]').check()
                clic(sc, 'form[action$="/firmar"] button[type=submit]')
    # Momento 4: resultado, folio SICDE, constancias e integración.
    a.ir(f'posgrado/gestion/{tid}')
    if a.pg.locator('form[action$="/acuse"]').count() and not a.pg.locator('form[action$="/resultado"]').count():
        a.pg.locator('form[action$="/acuse"] textarea').fill('Los acuses faltantes se recibieron por escrito en la Coordinación.')
        clic(a, 'form[action$="/acuse"] button[type=submit]')
        a.ir(f'posgrado/gestion/{tid}')
    fr = a.pg.locator('form[action$="/resultado"]')
    fr.locator('input[name=resultado][value=unanimidad]').check()
    f.tomar(a, 'apoyo_grado', 'resultado', 'Después del examen registras el resultado y quiénes integraron el jurado.', 'resultado', 4, resaltar='form[action$="/resultado"]')
    clic(a, 'form[action$="/resultado"] button[type=submit]')
    e.ir(f'posgrado/tramite/{tid}')
    e.pg.locator('form[action$="/sicde"] input[name=folio_sicde]').fill('SICDE-2026-004517')
    f.tomar(e, 'egresado', 'sicde', 'Aprobado el examen, registras tu grado en el SICDE (48 horas) y capturas aquí el folio.', 'sicde', 4, resaltar='form[action$="/sicde"]')
    clic(e, 'form[action$="/sicde"] button[type=submit]')
    for k in ('constancias_revisores', 'constancias_direccion', 'integracion'):
        ag_emitir(a, tid, k, f if k == 'constancias_revisores' else None, 'Emites las constancias del sínodo y de dirección, y el formato de integración de expediente.', 4)
    c.ir('posgrado/firmas')
    f.tomar(c, 'coordinacion', 'bandeja_cierre', 'Constancias y formato de integración esperando la firma de la Coordinación.', 'firmas_cierre', 4, resaltar='.pos-tabla-firmas')
    coordinacion_firma(c)
    a.ir(f'posgrado/gestion/{tid}')
    sel = 'form[action$="/expediente"]'
    if a.pg.locator(sel).count():
        if a.pg.locator(sel + ' input[type=checkbox]').count():
            for x in a.pg.locator(sel + ' input[type=checkbox]').all():
                x.check()
        f.tomar(a, 'apoyo_grado', 'expediente_cierre', 'Con todo firmado, marcas el expediente como integrado y el trámite concluye.', 'expediente_cierre', 4, resaltar=sel)
        clic(a, sel + ' button[type=submit]')
    e.ir(f'posgrado/tramite/{tid}')
    f.tomar(e, 'egresado', 'concluido', 'Trámite concluido: los oficios y constancias quedan en tu expediente.', 'concluido', 4, resaltar='.pos-paso')
    sc.ir('posgrado/firmas')
    f.tomar(sc, 'sinodal', 'firmadas', 'En «Posgrado: firmas» quedan las firmas hechas, para consultar el documento con tu sello.', 'firmadas', 4)
    # Supervisión.
    for correo, tema in ((ADMIN, 'admin'), (SUBD, 'subdireccion')):
        s = ses(p, correo)
        s.ir('posgrado/gestion')
        f.tomar(s, 'supervision', 'bandeja_' + tema, 'Supervisión: la misma bandeja de Apoyo a Grado, para consultar.' if tema == 'admin' else 'La Subdirección Académica consulta los trámites; no opera la bandeja ni firma por la Coordinación.', 'bandeja', '')
        s.ir(f'posgrado/gestion/{tid}')
        f.tomar(s, 'supervision', 'tramite_' + tema, 'Expediente de un trámite visto por supervisión.', 'tramite', '')
        s.ir('posgrado/firmas')
        f.tomar(s, 'supervision', 'firmas_' + tema, '«Posgrado: firmas» en modo consulta: lo que espera la firma de la Coordinación.', 'firmas', '')
        s.cerrar()
    # Mi perfil: vigencia y aviso de vencimiento.
    sc.ir('perfil')
    pronto = (datetime.date.today() + datetime.timedelta(days=20)).isoformat()
    sc.pg.locator('input[name=fise_vigencia]').fill(pronto)
    clic(sc, 'form[action$="/perfil/firma-uaemex"] button[value=guardar]')
    sc.ir('perfil')
    f.tomar(sc, 'sinodal', 'perfil', '«Mi perfil»: la vigencia de tu firma electrónica UAEMéx y el aviso cuando le falta un mes o menos.', 'perfil', '', resaltar='form[action$="/perfil/firma-uaemex"]')
    sc.ir('notificaciones')
    f.tomar(sc, 'sinodal', 'aviso_vencimiento', 'El aviso de vencimiento también llega a tus notificaciones.', 'perfil', '')
    for x in (e, m, a, c, sc):
        x.cerrar()
    return tid


# ------------------------------------------------------------------------------------------------ modalidades

def modalidad(p, mod):
    f = Fotos(mod)
    email = EGRESADOS[mod][0]
    e = ses(p, email)
    desde = tam(LOG_POS)
    tid = iniciar(e, f)
    datos(e, tid, mod, f)
    pedir_voto(e, tid)
    firmar_datos(e, tid)
    for dest, asunto, ruta in enlaces_de(desde, DIRECTOR[2]):
        firmar_enlace(p, ruta, f, 'director', 'voto', f'Voto aprobatorio de la modalidad «{MOD_NOMBRE[mod]}»: el documento nombra el trabajo según la modalidad.', momento=1)
    e.ir(f'posgrado/tramite/{tid}')
    if mod == 'articulos':
        e.abrir_detalles('#req-articulo_aceptado')
        f.tomar(e, 'egresado', 'requisito_doctorado', 'En doctorado se agrega la evidencia de artículo aceptado o capítulo de libro.', 'requisitos', 1, resaltar='#req-articulo_aceptado')
    else:
        f.tomar(e, 'egresado', 'siguiente', 'Tu siguiente paso, igual que en las demás modalidades.', 'siguiente', 1, resaltar='.pos-paso')
    e.cerrar()
    print('posgrado', mod, 'trámite', tid, flush=True)


# ------------------------------------------------------------------------------------------------ borrar, pausar, retomar

def dejar(p):
    f = Fotos('comun')
    email = 'prueba.egresado.pausa@alumno.uaemex.mx'
    EGRESADOS['pausa'] = (email, 'Hugo Barrera Olvera', '1820599', 'maestria', 'Maestría en Movilidad y Transporte', 'Indicadores de accesibilidad del transporte público')
    MOD_NOMBRE['pausa'] = 'Tesis'
    e = ses(p, email)
    desde = tam(LOG_POS)
    tid = iniciar(e)
    e.ir(f'posgrado/tramite/{tid}')
    e.pg.locator('details.pos-dejar').last.evaluate('d => d.open = true')
    f.tomar(e, 'egresado', 'dejar_pliegue', 'Al final de tu trámite: «¿Elegiste otra modalidad o quieres dejar este trámite?».', 'dejar_pliegue', '', resaltar='details.pos-dejar >> nth=-1')
    e.ir(f'posgrado/tramite/{tid}/dejar')
    e.pg.locator('input[name=entiendo]').check()
    f.tomar(e, 'egresado', 'borrar', 'Si nadie más intervino, el trámite se borra definitivamente; la pantalla lo explica y pide confirmarlo.', 'borrar', '', resaltar='form[action$="/dejar"]')
    clic(e, 'form[action$="/dejar"] button[type=submit]')
    tid = iniciar(e)
    datos_pausa(e, tid)
    pedir_voto(e, tid)
    for dest, asunto, ruta in enlaces_de(desde, DIRECTOR[2]):
        firmar_enlace(p, ruta)
    e.ir('posgrado')
    e.pg.locator('details.pos-dejar').first.evaluate('d => d.open = true')
    e.pg.locator('details.pos-dejar input[name=acepto]').check()
    clic(e, 'details.pos-dejar button[type=submit]')
    if e.pg.locator('input[name=entiendo]').count():
        e.pg.locator('input[name=entiendo]').check()
    f.tomar(e, 'egresado', 'una_activa', 'Solo un trámite activo: antes de comenzar otro, la plataforma explica qué pasará con el que tienes (aquí, quedará en pausa porque tu director ya firmó).', 'una_activa', '', resaltar='main form')
    clic(e, 'main form:has(input[name=confirmo]) button[type=submit]')
    tid2 = tid_de(e)
    e.ir('posgrado')
    e.pg.locator('#abandonadas').evaluate('d => d.open = true')
    f.tomar(e, 'egresado', 'abandonadas', '«Abandonadas»: el trámite en pausa conserva todo y se retoma desde aquí.', 'abandonadas', '', resaltar='#abandonadas')
    clic(e, '#abandonadas form button[type=submit]')
    if e.pg.locator('input[name=entiendo]').count():
        e.pg.locator('input[name=entiendo]').check()
    f.tomar(e, 'egresado', 'retomar', 'Retomar: confirmas; el trámite activo se borra o queda en pausa, según el caso.', 'retomar', '', resaltar='main form')
    clic(e, 'main form:has(input[name=confirmo]) button[type=submit]')
    e.ir(f'posgrado/tramite/{tid}/dejar')
    if e.pg.locator('input[name=entiendo]').count():
        e.pg.locator('input[name=entiendo]').check()
        f.tomar(e, 'egresado', 'pausa', 'Con intervención de otras personas, el trámite no se borra: queda en pausa y se explica por qué.', 'pausa', '', resaltar='form[action$="/dejar"]')
    e.cerrar()
    print('posgrado dejar', tid, tid2, flush=True)


def datos_pausa(e, tid):
    datos(e, tid, 'pausa')


def marcadores():
    """Marcadores en storage/manuales/posgrado para que las páginas muestren el bloque de descarga en las capturas
    (tools/manuales_publicar.sh los reemplaza por los manuales reales)."""
    import shutil
    for actor in ('egresado', 'director', 'sinodal', 'coordinacion', 'apoyo_grado', 'supervision'):
        for mod in ('tesis', 'trabajo_terminal', 'proyecto_terminal', 'articulos', 'consolidado'):
            d = os.path.join(RAIZ, 'storage', 'manuales', 'posgrado', actor, mod + '.pdf')
            if not os.path.exists(d):
                os.makedirs(os.path.dirname(d), exist_ok=True)
                shutil.copy(crear_pdf(), d)


if __name__ == '__main__':
    marcadores()
    que = sys.argv[1:] or ['completo', 'modalidad', 'dejar']
    with sync_playwright() as p:
        if 'completo' in que:
            completo(p)
        if 'modalidad' in que:
            for mod in ('trabajo_terminal', 'proyecto_terminal', 'articulos'):
                modalidad(p, mod)
        if 'dejar' in que:
            dejar(p)
