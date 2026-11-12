"""Recorre de punta a punta una opción CON trabajo escrito (pasante, Titulación, comisión, asesor, coasesor, Coordinador,
sinodales, Subdirección) y toma las capturas en la misma ejecución. Uso: python capturas_trabajo.py tesis [id_trámite]"""
import sys
from trabajo import *

mod = sys.argv[1]
tid = int(sys.argv[2]) if len(sys.argv) > 2 else None
PASANTES = {'articulo': ('Arturo Artículo Prueba', '2011004'), 'ensayo': ('Elisa Ensayo Prueba', '2011005'), 'memoria': ('Mateo Memoria Prueba', '2011006'),
            'aplicacion': ('Andrea Aplicación Prueba', '2011007'), 'autoempleo': ('Aurelio Autoempleo Prueba', '2011008'),
            'residencia': ('Regina Residencia Prueba', '2011010'), 'tesina': ('Tadeo Tesina Prueba', '2011011'), 'tesis': ('Teresa Tesis Prueba', '2011009')}
import datetime
IDX = list(PASANTES).index(mod) if mod in PASANTES else 0
FECHAS = [(datetime.date(2026, 12, 3) + datetime.timedelta(days=9 * IDX)).isoformat(), (datetime.date(2027, 1, 14) + datetime.timedelta(days=9 * IDX)).isoformat()]


def pie_de(s):
    t = s.pg.inner_text('main')
    m = re.search(r'(Te toca a ti:[^\n]*|Espera[^\n]*|Solicitud enviada[^\n]*)', t)
    return (m.group(1) if m else 'Estado del trámite.')[:160]


with sync_playwright() as p:
    e = Elenco(p)
    nombre, cuenta = PASANTES[mod]
    email = f'prueba.pasante.{mod}@alumno.uaemex.mx'
    a = e(email); j = e('prueba.jefatura.titulacion@uaemex.mx'); sd = e('prueba.subdirector.academico@uaemex.mx'); ap = e('prueba.apoyo.titulacion@uaemex.mx'); dr = e('prueba.director@uaemex.mx')
    ctx = dict(CTX_TRABAJO, nombre=nombre, cuenta=cuenta, correo_inst=email, correo_pers='pasante.pruebas@example.com', capturadas=set())
    desde = tam_log(); vistos = set()
    if tid is None:
        a.ir('titulacion/licenciatura'); a.foto(f'{mod}_pasante_01_opciones', pie='Opciones de titulación en «Licenciatura».')
        a.ir(f'titulacion/licenciatura/modalidad/{mod}'); a.foto(f'{mod}_pasante_02_requisitos', completa=True, pie='Requisitos de la opción y aceptación para iniciar.')
        a.pg.check('input[name=acepto]')
        a.pg.click('button:has-text("Iniciar mi trámite")'); a.pg.wait_for_load_state('networkidle')
        tid = int(re.search(r'tramite/(\d+)', a.pg.url).group(1))
        a.foto(f'{mod}_pasante_04_datos', completa=True, pie='Captura de datos del pasante y apertura del trámite.')
    print(mod, 'tramite', tid, flush=True)
    R0 = int(os.environ.get('RONDA0', '0')); ag = int(os.environ.get('AG0', '0')); hechos_com = set(['asig']) if R0 else set()
    for r in range(R0, R0 + 60):
        x = pasante_avanza(a, tid, ctx)
        a.ir(f'titulacion/licenciatura/tramite/{tid}')
        pie = pie_de(a)
        a.foto(f'{mod}_pasante_05_ronda{r:02d}', completa=True, pie=pie)
        if ag < len(FECHAS) and a.pg.locator('#form-agenda').count():
            agenda_pasante(a, tid, FECHAS[ag], foto=f'{mod}_pasante_06_agenda{ag + 1}'); ag += 1; x += 1
        y = personal_avanza(j, tid, ctx)
        j.ir(f'titulacion/gestion/{tid}'); j.foto(f'{mod}_titulacion_01_expediente_ronda{r:02d}', completa=True, pie=pie_de(j) if False else 'Expediente del trámite en la bandeja de Titulación.')
        f1 = firmas_bandeja(j, foto=f'{mod}_titulacion_03_firmas_jefatura_r{r:02d}'); f2 = firmas_bandeja(sd, foto=f'{mod}_subdirector_01_firmas_ronda{r:02d}')
        f3 = firmas_bandeja(dr, foto=f'{mod}_director_01_firmas_ronda{r:02d}')
        c = firmar_correos(e, desde, prefijo=mod, vistos=vistos)
        k = 0
        if 'asig' not in hechos_com and comision_asignar(e, foto=f'{mod}_comision_01'):
            hechos_com.add('asig'); k += 1
        for i, m in enumerate(('evaluador1', 'evaluador2', 'encargada.comision')):
            if evaluar(e, f'prueba.{m}@uaemex.mx', foto=f'{mod}_comision_03_revision_{i + 1}'):
                k += 1
        if dictaminar(e, foto=f'{mod}_comision_04_dictamen'):
            k += 1
        print(mod, 'ronda', r, x, y, f1, f2, f3, c, k, flush=True)
        if not (x or y or f1 or f2 or f3 or c or k):
            break
    j.ir(f'titulacion/gestion/{tid}')
    print(mod, 'concluido' if 'Concluido' in j.pg.inner_text('main') else 'ABIERTO', pie_de(a), flush=True)
    a.ir(f'titulacion/licenciatura/tramite/{tid}'); a.foto(f'{mod}_pasante_07_concluido', completa=True, pie='Trámite concluido.')
    a.ir(f'titulacion/ruta/{tid}'); a.foto(f'{mod}_pasante_08_ruta', completa=True, pie='Ruta del trámite con los pasos hechos.')
    a.ir('titulacion/estado'); a.foto(f'{mod}_pasante_09_estado', pie='Estado del trámite.')
    e.cerrar()
