"""Arnés de capturas de los manuales (Playwright): sesión por actor, navegación y capturas con resaltado y pie.

SOLO contra una base local o de pruebas. Variables de entorno (sin rutas personales en el código):
  MANUAL_BASE     URL del sitio local                     (por omisión http://127.0.0.1:8090/)
  MANUAL_CHROME   ejecutable de Chrome/Chromium            (por omisión /usr/bin/google-chrome si existe;
                                                            si no, el Chromium que trae Playwright)
  MANUAL_LOGIN    «dev» (por omisión): usa /__dev_login?email=..., que solo existe en el router local de pruebas;
                  «clave»: inicio de sesión real con las contraseñas de cred_titulacion_pruebas.txt (no se versiona).
  MANUAL_IMG      carpeta de las capturas                  (por omisión docs/manuales/titulacion/img)

Cada captura se guarda en JPEG (calidad 80, máximo 1600 px de ancho) y se registra en <img>/_capturas.json con su pie
de foto, actor, opción, etapa y orden; de ahí las toma el generador de LaTeX.
"""
import io
import json
import os
import re

from playwright.sync_api import sync_playwright  # noqa: F401  (lo reexportan los scripts)

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BASE = os.environ.get('MANUAL_BASE', os.environ.get('TIT_BASE', 'http://127.0.0.1:8090/')).rstrip('/') + '/'
_CH = os.environ.get('MANUAL_CHROME', os.environ.get('TIT_CHROME', '/usr/bin/google-chrome'))
CHROME = _CH if _CH and os.path.exists(_CH) else None
LOGIN = os.environ.get('MANUAL_LOGIN', 'dev')
IMG = os.environ.get('MANUAL_IMG', os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'img'))
LOG = os.path.join(RAIZ, 'storage', 'logs', 'titulacion-correos.log')
LOG_POS = os.path.join(RAIZ, 'storage', 'logs', 'posgrado-correos.log')
PDF = os.path.join(RAIZ, 'tools', 'manual_titulacion', 'muestra.pdf')

RESALTE = '#E0A800'
_ORDEN = [0]


def credenciales():
    c = {}
    ruta = os.path.join(RAIZ, 'cred_titulacion_pruebas.txt')
    if not os.path.exists(ruta):
        return c
    for linea in open(ruta, encoding='utf-8'):
        m = re.search(r'(prueba\.[\w.-]+@[\w.-]+)\s+(\S+)\s*$', linea)
        if m and not linea.lstrip().startswith('→'):
            c[m.group(1)] = m.group(2)
    return c


def _manifiesto_ruta(img):
    return os.path.join(img, '_capturas.json')


def manifiesto(img=None):
    try:
        return json.load(open(_manifiesto_ruta(img or IMG), encoding='utf-8'))
    except Exception:
        return {}


def registrar(nombre, datos, img=None):
    img = img or IMG
    m = manifiesto(img)
    if not _ORDEN[0]:
        _ORDEN[0] = max([v.get('orden', 0) for v in m.values()] + [0])
    _ORDEN[0] += 1
    datos['orden'] = _ORDEN[0]
    m[nombre] = datos
    tmp = _manifiesto_ruta(img) + '.tmp'
    json.dump(m, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False, indent=1, sort_keys=True)
    os.replace(tmp, _manifiesto_ruta(img))


def recortar_fondo(im, margen=14):
    """Quita los bordes de color uniforme (el fondo de las páginas públicas angostas) dejando un margen."""
    from PIL import Image, ImageChops
    fondo = Image.new('RGB', im.size, im.getpixel((im.width - 1, im.height - 1)))
    dif = ImageChops.difference(im, fondo).convert('L').point(lambda v: 255 if v > 10 else 0)
    caja = dif.getbbox()
    if not caja:
        return im
    x0, y0, x1, y1 = caja
    # Páginas públicas: la franja superior ocupa todo el ancho, pero el contenido es angosto. El ancho se toma de lo
    # que hay debajo de esa franja.
    if im.height > 120:
        abajo = dif.crop((0, 70, im.width, im.height)).getbbox()
        if abajo and (abajo[2] - abajo[0]) < 0.8 * im.width:
            x0, x1 = abajo[0], abajo[2]
    caja = (max(0, x0 - margen), max(0, y0 - margen), min(im.width, x1 + margen), min(im.height, y1 + margen))
    if (caja[2] - caja[0]) * (caja[3] - caja[1]) > 0.92 * im.width * im.height:
        return im
    return im.crop(caja)


def a_jpeg(png_bytes, ruta, calidad=80, ancho_max=1600, alto_max=None):
    from PIL import Image
    im = recortar_fondo(Image.open(io.BytesIO(png_bytes)).convert('RGB'))
    if im.width > ancho_max:
        im = im.resize((ancho_max, int(im.height * ancho_max / im.width)), Image.LANCZOS)
    if alto_max and im.height > alto_max:
        im = im.crop((0, 0, im.width, alto_max))
    im.save(ruta, 'JPEG', quality=calidad, optimize=True, progressive=True)
    return im.size


class Sesion:
    """Un navegador con la sesión de un actor (o sin sesión, con email=None, para los enlaces de quien no tiene cuenta)."""

    def __init__(self, pw, email=None, ancho=1280, alto=860, movil=False, img=None):
        self.email = email
        self.img = img or IMG
        self.movil = movil
        self.nav = pw.chromium.launch(executable_path=CHROME, args=['--no-sandbox']) if CHROME else pw.chromium.launch()
        opciones = dict(viewport={'width': ancho, 'height': alto}, accept_downloads=True, locale='es-MX', timezone_id='America/Mexico_City')
        if movil:
            opciones.update(device_scale_factor=2, is_mobile=True, has_touch=True)
        self.ctx = self.nav.new_context(**opciones)
        self.pg = self.ctx.new_page()
        self.pg.set_default_timeout(25000)
        self.pg.on('dialog', lambda d: d.accept())
        if email:
            self.entrar()

    def entrar(self):
        pg = self.pg
        if LOGIN == 'dev':
            r = pg.goto(BASE + '__dev_login?email=' + self.email)
            if not r or r.status != 200:
                raise RuntimeError('Sin cuenta local para ' + self.email)
            return
        pg.goto(BASE + 'login')
        pg.fill('#email-login', self.email)
        pg.click('#btn-correo')
        pg.fill('#clave-login', credenciales()[self.email])
        pg.click('#btn-clave')
        pg.wait_for_timeout(1500)
        pg.wait_for_load_state('networkidle')

    def ir(self, ruta):
        ruta = re.sub(r'^https?://[^/]+/', '', ruta)
        self.pg.goto(BASE + ruta.lstrip('/'))
        self.pg.wait_for_load_state('networkidle')
        return self.pg

    def texto(self, sel='main'):
        loc = self.pg.locator(sel)
        return loc.first.inner_text() if loc.count() else self.pg.inner_text('body')

    def abrir_detalles(self, sel):
        """Abre los <details> que contengan el selector (para que se vea en la captura)."""
        loc = self.pg.locator(sel)
        if loc.count():
            loc.first.evaluate("""e => { let d = e.closest('details'); while (d) { d.open = true; d = d.parentElement && d.parentElement.closest('details'); } }""")

    def foto(self, nombre, pie, actor='', opcion='', etapa='', resaltar=None, recorte=None, completa=False, alto_max=None, extra=None, con_menu=False):
        """Toma la captura. resaltar: selector a enmarcar; recorte: selector de la región útil (se captura solo esa
        región, con margen); completa: página entera (recortada a alto_max px)."""
        os.makedirs(self.img, exist_ok=True)
        pg = self.pg
        # Avisos de PHP del modo de depuración (texto suelto antes de la página): no son parte de la interfaz.
        pg.evaluate("""() => { const b = document.body; if (!b) return; while (b.firstChild && !(b.firstChild.nodeType === 1 && (b.firstChild.matches('a.skip-link, header, main, div, section')))) b.removeChild(b.firstChild); }""")
        # Sin el aviso de segundo factor de las cuentas de prueba ni animaciones a medias.
        pg.add_style_tag(content='.seguridad-resumen{display:none!important}.pos-visor{display:none!important}*{transition:none!important;animation:none!important;caret-color:transparent!important}')
        if resaltar and pg.locator(resaltar).count():
            self.abrir_detalles(resaltar)
            pg.locator(resaltar).first.evaluate("""(e, c) => { e.setAttribute('data-manual-resalte', '1'); e.style.outline = '4px solid ' + c;
                e.style.outlineOffset = '3px'; e.scrollIntoView({block: 'center'}); }""", RESALTE)
            pg.wait_for_timeout(250)
        try:
            if recorte and pg.locator(recorte).count():
                loc = pg.locator(recorte).first
                loc.scroll_into_view_if_needed()
                caja = loc.bounding_box()
                vp = pg.viewport_size
                margen = 12
                x = max(0, caja['x'] - margen)
                y = max(0, caja['y'] - margen)
                w = min(vp['width'] - x, caja['width'] + 2 * margen)
                h = caja['height'] + 2 * margen
                if h > vp['height']:
                    png = loc.screenshot()
                else:
                    png = pg.screenshot(clip={'x': x, 'y': y, 'width': w, 'height': h})
            else:
                png = pg.screenshot(full_page=completa)
        finally:
            if resaltar:
                pg.evaluate("""() => document.querySelectorAll('[data-manual-resalte]').forEach(e => { e.style.outline = ''; e.style.outlineOffset = ''; e.removeAttribute('data-manual-resalte'); })""")
        if not self.movil and not recorte and not con_menu:
            # Sin el menú lateral: la captura empieza donde empieza el contenido.
            x0 = pg.evaluate("() => { const m = document.querySelector('main') || document.querySelector('.contenido'); return m ? Math.max(0, Math.floor(m.getBoundingClientRect().left)) : 0; }")
            if x0 and x0 < 400:
                from PIL import Image
                im = Image.open(io.BytesIO(png))
                buf = io.BytesIO()
                im.crop((x0, 0, im.width, im.height)).save(buf, 'PNG')
                png = buf.getvalue()
        ruta = os.path.join(self.img, nombre + '.jpg')
        limite = alto_max or (3200 if self.movil else 2400)
        w, h = a_jpeg(png, ruta, alto_max=limite)
        datos = {'pie': pie, 'actor': actor, 'opcion': opcion, 'etapa': etapa, 'ancho': w, 'alto': h, 'movil': self.movil}
        if extra:
            datos.update(extra)
        registrar(nombre, datos, self.img)
        return ruta

    def cerrar(self):
        self.nav.close()


def crear_pdf():
    """PDF mínimo de muestra (una página) para las cargas de documentos."""
    if os.path.exists(PDF):
        return PDF
    objs = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>']
    cont = 'BT /F1 22 Tf 72 700 Td (Documento de muestra - prueba de los manuales) Tj ET'
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
    return PDF


def tam(ruta):
    return os.path.getsize(ruta) if os.path.exists(ruta) else 0


def correos(ruta_log, desde=0):
    """Bloques de la bitácora de correos de pruebas: [(destinatario, texto)]. Se lee en binario (desplazamiento en bytes)."""
    if not os.path.exists(ruta_log):
        return []
    txt = open(ruta_log, 'rb').read()[desde:].decode('utf-8', 'replace')
    out = []
    for bloque in txt.split('=== ')[1:]:
        cab = bloque.split('\n', 1)[0]
        m = re.search(r' a (\S+@\S+?) ===', cab)
        if m:
            out.append((m.group(1).lower(), bloque))
    return out
