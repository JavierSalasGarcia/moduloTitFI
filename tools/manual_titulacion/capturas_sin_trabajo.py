"""Recorre y captura las opciones sin trabajo escrito (aprovechamiento, EGEL, créditos) en el entorno local."""
import sys
from flujo import *

PASANTES = {'aprovechamiento': ('Alicia Aprovechamiento Prueba', '2011001'), 'egel': ('Eduardo Egel Prueba', '2011002'), 'creditos': ('Carla Créditos Prueba', '2011003')}
mods = sys.argv[1:] or list(PASANTES)

with sync_playwright() as p:
    jefa = Sesion(p, 'prueba.jefatura.titulacion@uaemex.mx')
    sub = Sesion(p, 'prueba.subdirector.academico@uaemex.mx')
    for mod in mods:
        nombre, cuenta = PASANTES[mod]
        email = f'prueba.pasante.{mod}@alumno.uaemex.mx'
        a = Sesion(p, email)
        ctx = {'nombre': nombre, 'cuenta': cuenta, 'correo_inst': email, 'correo_pers': 'pasante.pruebas@example.com'}
        a.ir('titulacion/licenciatura'); a.foto(f'{mod}_pasante_01_opciones')
        a.ir(f'titulacion/licenciatura/modalidad/{mod}'); a.foto(f'{mod}_pasante_02_requisitos', completa=True)
        if a.pg.locator('input[name=variante]').count():
            a.pg.check('input[name=variante][value=pagando_menos]')
        a.pg.check('input[name=acepto]')
        a.foto(f'{mod}_pasante_03_iniciar')
        a.pg.click('button:has-text("Iniciar mi trámite")'); a.pg.wait_for_load_state('networkidle')
        tid = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
        a.foto(f'{mod}_pasante_04_datos', completa=True)
        print(mod, 'tramite', tid, flush=True)
        for r in range(14):
            x = pasante_avanza(a, tid, ctx)
            a.ir(f'titulacion/licenciatura/tramite/{tid}'); a.foto(f'{mod}_pasante_05_ronda{r}', completa=True)
            jefa.ir(f'titulacion/gestion/{tid}'); jefa.foto(f'{mod}_titulacion_01_expediente_ronda{r}', completa=True)
            y = personal_avanza(jefa, tid, ctx)
            z = subdirector_firma(sub, foto=f'{mod}_subdirector_01_firmas_ronda{r}')
            print(mod, 'ronda', r, x, y, z, flush=True)
            if not (x or y or z):
                break
        a.ir(f'titulacion/licenciatura/tramite/{tid}'); a.foto(f'{mod}_pasante_06_concluido', completa=True)
        a.ir(f'titulacion/ruta/{tid}'); a.foto(f'{mod}_pasante_07_ruta', completa=True)
        a.ir('titulacion/estado'); a.foto(f'{mod}_pasante_08_estado')
        jefa.ir('titulacion/gestion'); jefa.foto(f'{mod}_titulacion_02_bandeja')
        jefa.ir(f'titulacion/gestion/{tid}'); print(mod, jefa.pg.inner_text('main')[:200].replace('\n', ' | '), flush=True)
        a.cerrar()
    jefa.cerrar(); sub.cerrar()
