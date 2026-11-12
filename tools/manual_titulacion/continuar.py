"""Continúa trámites abiertos hasta concluirlos y toma las capturas finales. Uso: python continuar.py egel:13:2011002 creditos:14:2011003"""
import sys
from flujo import *

with sync_playwright() as p:
    jefa = Sesion(p, 'prueba.jefatura.titulacion@uaemex.mx')
    sub = Sesion(p, 'prueba.subdirector.academico@uaemex.mx')
    for par in sys.argv[1:]:
        mod, tid, cuenta = par.split(':')
        email = f'prueba.pasante.{mod}@alumno.uaemex.mx'
        a = Sesion(p, email)
        ctx = {'cuenta': cuenta, 'correo_inst': email, 'correo_pers': 'pasante.pruebas@example.com', 'capturadas': set()}
        for r in range(20):
            x = pasante_avanza(a, tid, ctx)
            y = personal_avanza(jefa, tid, ctx)
            z = subdirector_firma(sub)
            print(mod, 'ronda', r, x, y, z, flush=True)
            if not (x or y or z):
                break
        a.ir(f'titulacion/licenciatura/tramite/{tid}'); a.foto(f'{mod}_pasante_06_concluido', completa=True)
        a.ir(f'titulacion/ruta/{tid}'); a.foto(f'{mod}_pasante_07_ruta', completa=True)
        a.ir('titulacion/estado'); a.foto(f'{mod}_pasante_08_estado')
        jefa.ir(f'titulacion/gestion/{tid}'); jefa.foto(f'{mod}_titulacion_01_expediente_final', completa=True)
        t = jefa.pg.inner_text('main')
        print(mod, 'concluido' if 'Concluido' in t else 'ABIERTO', flush=True)
        a.cerrar()
    jefa.cerrar(); sub.cerrar()
