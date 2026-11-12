"""Verifica el expediente final de cada trámite concluido (por opción) y exporta el ZIP, con capturas. Uso: python capturas_verificacion.py aprovechamiento:12 egel:13"""
import sys
from flujo import *

with sync_playwright() as p:
    j = Sesion(p, 'prueba.jefatura.titulacion@uaemex.mx')
    for par in sys.argv[1:]:
        mod, tid = par.split(':')
        j.ir('titulacion/expedientes'); j.foto(f'{mod}_titulacion_03_expedientes_bandeja')
        j.ir(f'titulacion/expedientes/{tid}'); j.foto(f'{mod}_titulacion_verificacion_01', completa=True)
        for sel in j.pg.locator('select[name^="m["]').all():
            opts = sel.locator('option').all_inner_texts()
            sel.select_option('no_aplica' if 'No aplica' in opts else 'ok')
        j.pg.click('button:has-text("Guardar lista")'); j.pg.wait_for_load_state('networkidle')
        j.pg.click('button:has-text("Hago constar")'); j.pg.wait_for_load_state('networkidle')
        j.foto(f'{mod}_titulacion_verificacion_02', completa=True)
        with j.pg.expect_download() as d:
            j.pg.click('button:has-text("Exportar este expediente")')
        d.value.save_as(os.path.join(RAIZ, 'tmp', f'exp_{mod}.zip'))
        j.ir('titulacion/expedientes'); j.foto(f'{mod}_titulacion_04_enviado')
        print(mod, 'verificado y exportado', flush=True)
    j.cerrar()
