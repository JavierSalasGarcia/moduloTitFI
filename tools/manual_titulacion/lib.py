"""Arnés de la prueba completa de Titulación (Playwright): inicia sesión como cada actor, recorre el flujo y toma capturas.

Solo contra una base local o de pruebas (http://localhost:8090). Las contraseñas salen de cred_titulacion_pruebas.txt,
que genera tools/titulacion_pruebas_semilla.php y no se versiona.
"""
import re
import os
import json
from playwright.sync_api import sync_playwright

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
BASE = os.environ.get('TIT_BASE', 'http://localhost:8090/')
CHROME = os.environ.get('TIT_CHROME', r'C:/Users/edigi/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe')
IMG = os.path.join(RAIZ, 'docs', 'manuales', 'titulacion', 'img')
LOG = os.path.join(RAIZ, 'storage', 'logs', 'titulacion-correos.log')
PDF = os.path.join(RAIZ, 'tools', 'manual_titulacion', 'muestra.pdf')


def credenciales():
    c = {}
    for l in open(os.path.join(RAIZ, 'cred_titulacion_pruebas.txt'), encoding='utf-8'):
        m = re.search(r'(\S+@\S+)\s+(\S+)\s*$', l)
        if m and not l.lstrip().startswith('→'):
            c[m.group(1)] = m.group(2)
    return c


CREDS = credenciales()


class Sesion:
    """Un navegador con la sesión de un actor."""

    def __init__(self, pw, email, ancho=1280, alto=860):
        self.email = email
        self.nav = pw.chromium.launch(executable_path=CHROME)
        self.ctx = self.nav.new_context(viewport={'width': ancho, 'height': alto}, accept_downloads=True, locale='es-MX')
        self.pg = self.ctx.new_page()
        self.pg.set_default_timeout(20000)
        self.entrar()

    def entrar(self):
        pg = self.pg
        pg.goto(BASE + 'login')
        pg.fill('#email-login', self.email)
        pg.click('#btn-correo')
        pg.fill('#clave-login', CREDS[self.email])
        pg.click('#btn-clave')
        pg.wait_for_timeout(1500)
        if pg.is_visible('#paso-mfa'):
            raise RuntimeError('La cuenta pide segundo factor: ' + pg.inner_text('#paso-mfa')[:200])
        pg.wait_for_load_state('networkidle')

    def ir(self, ruta):
        self.pg.goto(BASE + ruta.lstrip('/'))
        self.pg.wait_for_load_state('networkidle')
        return self.pg

    def foto(self, nombre, completa=False, localizador=None):
        os.makedirs(IMG, exist_ok=True)
        ruta = os.path.join(IMG, nombre + '.png')
        if localizador:
            self.pg.locator(localizador).first.screenshot(path=ruta)
        else:
            self.pg.screenshot(path=ruta, full_page=completa)
        return ruta

    def cerrar(self):
        self.nav.close()
