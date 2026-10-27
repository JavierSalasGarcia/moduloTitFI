#!/usr/bin/env python3
"""
Genera database/migrations/025_historico_sistema_anterior.sql a partir del respaldo del sistema de Titulación anterior
(«Respaldo BD Titulacion AAAAMMDD.sql»): una fila de tit_historico por alumno y acta de evaluación con trabajo escrito
(1988-2020), con título, director, codirector y jurado.

La migración generada es repetible y no sobrescribe: los registros que ya están en tit_historico (misma licenciatura,
cuenta y acta o fecha) solo reciben los datos que tienen vacíos, y los demás se agregan con hoja «SISTEMA ANTERIOR».

Uso:  python tools/importar_titulacion_anterior.py "ruta/Respaldo BD Titulacion 20210324.sql" [--mysql ruta/mysql.exe]
Requiere un MySQL local; carga el respaldo en una base temporal y la elimina al terminar.
"""
import argparse
import csv
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / 'database' / 'migrations' / '025_historico_sistema_anterior.sql'
BD_TEMP = 'tmp_titulacion_anterior'
HOJA = 'SISTEMA ANTERIOR'

CARRERAS = {1: 'civil', 2: 'computacion', 3: 'mecanica', 4: 'electronica', 5: 'sistemas'}
MODALIDADES = {1: 'tesis', 2: 'tesina', 3: 'memoria', 6: 'ensayo', 7: 'articulo', 8: 'aplicacion', 9: 'residencia', 10: 'autoempleo'}


def profesor(alias: str) -> str:
    """Grado y nombre con apellidos, separados por CHAR(31): el grado se antepone al nombre en el libro («M. EN I. NOMBRE APELLIDOS»)."""
    return (f"CONCAT(TRIM(IFNULL({alias}.CCTitPrf, '')), CHAR(31), TRIM(CONCAT_WS(' ', TRIM({alias}.CCNomPrf), TRIM({alias}.CCApePrf))))")


def jurado(rol: str, campo: str) -> str:
    """Subconsulta del profesor que fungió como `rol` (PRE, SEC, V01) en la evaluación."""
    expr = profesor('p') if campo == 'nombre' else 'TRIM(p.CCCorPrf)'
    return (f"(SELECT {expr} FROM tidefprf j JOIN ticatprf p ON p.CNCvePrf = j.CNCvePrf WHERE j.CNCveLic = d.CNCveLic "
            f"AND j.CNCveTem = d.CNCveTem AND j.CNNumLib = d.CNNumLib AND j.CNNumAct = d.CNNumAct AND j.CCIdeJur = '{rol}' LIMIT 1)")


def asesor(pos: int, campo: str) -> str:
    expr = profesor('p') if campo == 'nombre' else 'TRIM(p.CCCorPrf)'
    return (f"(SELECT {expr} FROM titemprf a JOIN ticatprf p ON p.CNCvePrf = a.CNCvePrf WHERE a.CNCveLic = d.CNCveLic "
            f"AND a.CNCveTem = d.CNCveTem ORDER BY a.CDFecMov, a.CNCvePrf LIMIT 1 OFFSET {pos})")


CONSULTA = f"""
SELECT d.CNCveLic, d.CNNumLib, d.CNNumAct, d.CNCveTem, TRIM(a.CCCveAlu), d.CDFecDef, t.CNCveMod,
       REPLACE(REPLACE(TRIM(t.CCDesTem), CHAR(9), ' '), CHAR(10), ' '),
       TRIM(CONCAT_WS(' ', TRIM(al.CCApeAlu), TRIM(al.CCNomAlu))), TRIM(m.CCAbrMod),
       (SELECT COUNT(*) FROM titemprf x WHERE x.CNCveLic = d.CNCveLic AND x.CNCveTem = d.CNCveTem),
       {asesor(0, 'nombre')}, {asesor(0, 'correo')}, {asesor(1, 'nombre')}, {asesor(1, 'correo')},
       {jurado('PRE', 'nombre')}, {jurado('PRE', 'correo')}, {jurado('SEC', 'nombre')}, {jurado('SEC', 'correo')},
       {jurado('V01', 'nombre')}, {jurado('V01', 'correo')}
FROM timaedef d
JOIN tidefalu a ON a.CNCveLic = d.CNCveLic AND a.CNCveTem = d.CNCveTem AND a.CNNumLib = d.CNNumLib AND a.CNNumAct = d.CNNumAct
JOIN timaetem t ON t.CNCveLic = d.CNCveLic AND t.CNCveTem = d.CNCveTem
JOIN ticatmod m ON m.CNCveMod = t.CNCveMod
LEFT JOIN ticatalu al ON al.CCCveAlu = a.CCCveAlu
WHERE d.CDFecDef > '1980-01-01' AND d.CCResDef IN ('1', '2', '3')
ORDER BY d.CDFecDef, d.CNCveLic, d.CNNumAct, a.CCCveAlu
"""


def mysql(exe: str, *args: str, entrada: bytes | None = None) -> bytes:
    r = subprocess.run([exe, '-uroot', *args], input=entrada, capture_output=True)
    if r.returncode:
        sys.exit(f"mysql falló: {r.stderr.decode('utf-8', 'replace')}")
    return r.stdout


def correo(v: str) -> str:
    v = (v or '').strip().lower()
    if ' ' in v or v.count('@') != 1 or '.' not in v.split('@')[1] or v.split('@')[0] == 'sin':   # «sin@correo.com…» es un marcador
        return ''
    return v


SEP = chr(31)


def persona(v):
    """(nombre con grado, nombre sin grado) de un profesor que viene como «grado SEP nombre»."""
    grado, _, nombre = (v or '').partition(SEP)
    return (f'{grado} {nombre}'.strip(), nombre.strip())


def sql(v) -> str:
    if v is None:
        return 'NULL'
    return "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('respaldo')
    ap.add_argument('--mysql', default=r'C:\xampp\mysql\bin\mysql.exe')
    a = ap.parse_args()

    dump = Path(a.respaldo).read_bytes().replace(b'`titulacion`', f'`{BD_TEMP}`'.encode())
    mysql(a.mysql, f'--default-character-set=latin1', entrada=dump)
    try:
        crudo = mysql(a.mysql, '--default-character-set=utf8mb4', '-B', '-N', '--raw', BD_TEMP, '-e', CONSULTA).decode('utf-8')
    finally:
        mysql(a.mysql, '-e', f'DROP DATABASE IF EXISTS {BD_TEMP}')

    filas = []
    for r in csv.reader(crudo.splitlines(), delimiter='\t', quoting=csv.QUOTE_NONE):
        (lic, lib, act, tem, cuenta, fecha, mod, titulo, alumno, abr, n_ases,
         dir_n, dir_c, cod_n, cod_c, pre_n, pre_c, sec_n, sec_c, voc_n, voc_c) = [None if x == 'NULL' else x for x in r]
        if int(mod) not in MODALIDADES or int(lic) not in CARRERAS or not cuenta:
            continue
        ambiguo = int(n_ases) >= 2
        f = dict(
            lic=int(lic), lib=int(lib), act=int(act), tem=int(tem), carrera=CARRERAS[int(lic)], cuenta=cuenta, fecha=fecha,
            modalidad=MODALIDADES[int(mod)], origen=(abr or '')[:60], titulo=(titulo or '')[:500], alumno=(alumno or '')[:160],
            ambiguo=int(ambiguo),
        )
        for rol, nombre, mail in (('director', dir_n, dir_c), ('codirector', cod_n, cod_c), ('presidente', pre_n, pre_c),
                                  ('secretario', sec_n, sec_c), ('vocal', voc_n, voc_c)):
            f[rol], f[rol + '_sg'] = persona(nombre)
            f[rol + '_email'] = correo(mail)
        # Con dos o más asesores el sistema anterior no distingue director de codirector: se dejan los nombres pero sin
        # correo, para que el visor los marque como pendientes y el personal confirme el rol antes de emitir constancias.
        filas.append(f)

    # El número de acta se repite entre libros antiguos: solo se conserva si identifica una única evaluación de la licenciatura.
    evaluaciones = {}
    for f in filas:
        evaluaciones.setdefault((f['lic'], f['act']), set()).add((f['lib'], f['tem']))
    for n, f in enumerate(filas, 1):
        f['fila'] = n
        f['acta_dudosa'] = int(len(evaluaciones[(f['lic'], f['act'])]) > 1)

    roles = ['director', 'codirector', 'presidente', 'secretario', 'vocal']
    cols = ['fila', 'carrera', 'acta', 'acta_dudosa', 'ambiguo', 'alumno', 'cuenta', 'modalidad', 'modalidad_origen', 'fecha_examen', 'titulo',
            'director', 'director_email', 'codirector', 'codirector_email', 'presidente', 'presidente_email',
            'secretario', 'secretario_email', 'vocal', 'vocal_email'] + [r + '_sg' for r in roles]
    claves = ['fila', 'carrera', 'act', 'acta_dudosa', 'ambiguo', 'alumno', 'cuenta', 'modalidad', 'origen', 'fecha', 'titulo',
              'director', 'director_email', 'codirector', 'codirector_email', 'presidente', 'presidente_email',
              'secretario', 'secretario_email', 'vocal', 'vocal_email'] + [r + '_sg' for r in roles]
    cuerpo = []
    for i in range(0, len(filas), 150):
        lote = ',\n'.join('(' + ', '.join(sql(f[k]) for k in claves) + ')' for f in filas[i:i + 150])
        cuerpo.append(f"INSERT INTO tmp_hist_ant ({', '.join(cols)}) VALUES\n{lote};\n")

    # Completa solo lo vacío; el correo se toma únicamente si el profesor es el mismo (o el campo estaba sin nombre).
    sets = [
        "h.acta = IF(h.acta IS NULL AND t.acta_dudosa = 0, t.acta, h.acta)",
        "h.fecha_examen = IFNULL(h.fecha_examen, t.fecha_examen)",
        "h.modalidad = IFNULL(NULLIF(h.modalidad, ''), t.modalidad)",
        "h.titulo = IF(h.titulo = '', t.titulo, h.titulo)",
    ]
    for p in roles:
        sets.append(f"h.{p}_email = IF(h.{p}_email = '' AND (h.{p} = '' OR h.{p} = t.{p}), t.{p}_email, h.{p}_email)")
        sets.append(f"h.{p} = IF(h.{p} = '', t.{p}, h.{p})")

    mismo = ("h.carrera = t.carrera AND h.cuenta = t.cuenta AND (h.acta = t.acta OR h.fecha_examen = t.fecha_examen) "
             "AND (h.modalidad IS NULL OR h.modalidad = '' OR h.modalidad = t.modalidad)")
    cuerpo_sql = f"""-- Historial del libro de actas: evaluaciones con trabajo escrito del sistema de Titulación anterior (respaldo del 24-03-2021),
-- {len(filas)} registros (alumno por acta) de {min(f['fecha'] for f in filas)} a {max(f['fecha'] for f in filas)}. Generado por
-- tools/importar_titulacion_anterior.py. Repetible y sin sobrescribir: los registros que ya existen en tit_historico (misma
-- licenciatura y cuenta, y misma acta o fecha) solo reciben lo que tienen vacío —nunca los que ya tienen constancias—, y los demás
-- se agregan con hoja «{HOJA}». Con dos o más asesores el sistema anterior no distingue director de codirector: esos registros
-- llevan los nombres pero no los correos, y quedan pendientes en el visor hasta que el personal confirme el rol.
-- El número de acta se omite cuando se repite en otra evaluación de la misma licenciatura (libros antiguos).

DROP TEMPORARY TABLE IF EXISTS tmp_hist_ant;
CREATE TEMPORARY TABLE tmp_hist_ant (
  fila INT UNSIGNED NOT NULL,
  carrera VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  acta INT UNSIGNED NULL,
  acta_dudosa TINYINT NOT NULL DEFAULT 0,
  ambiguo TINYINT NOT NULL DEFAULT 0,
  alumno VARCHAR(160) NOT NULL DEFAULT '',
  cuenta VARCHAR(12) NOT NULL DEFAULT '',
  modalidad VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  modalidad_origen VARCHAR(60) NOT NULL DEFAULT '',
  fecha_examen DATE NULL,
  titulo VARCHAR(500) NOT NULL DEFAULT '',
  director VARCHAR(160) NOT NULL DEFAULT '', director_email VARCHAR(120) NOT NULL DEFAULT '',
  codirector VARCHAR(160) NOT NULL DEFAULT '', codirector_email VARCHAR(120) NOT NULL DEFAULT '',
  presidente VARCHAR(160) NOT NULL DEFAULT '', presidente_email VARCHAR(120) NOT NULL DEFAULT '',
  secretario VARCHAR(160) NOT NULL DEFAULT '', secretario_email VARCHAR(120) NOT NULL DEFAULT '',
  vocal VARCHAR(160) NOT NULL DEFAULT '', vocal_email VARCHAR(120) NOT NULL DEFAULT '',
  director_sg VARCHAR(160) NOT NULL DEFAULT '', codirector_sg VARCHAR(160) NOT NULL DEFAULT '', presidente_sg VARCHAR(160) NOT NULL DEFAULT '',
  secretario_sg VARCHAR(160) NOT NULL DEFAULT '', vocal_sg VARCHAR(160) NOT NULL DEFAULT '',
  PRIMARY KEY (fila)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

{''.join(cuerpo)}
-- 0) Los correos del sistema anterior son marcadores («sin@correo.com.mx»): se toman de las cuentas de la plataforma cuando el
--    nombre (sin grado) coincide con una sola cuenta activa. Con dos o más asesores no se asigna ninguno.
{chr(10).join(f"UPDATE tmp_hist_ant t SET t.{r}_email = IFNULL((SELECT MIN(u.email) FROM usuarios u WHERE u.activo = 1 AND u.nombre COLLATE utf8mb4_unicode_ci = t.{r}_sg HAVING COUNT(*) = 1), '') WHERE t.ambiguo = 0 AND t.{r}_sg <> '' AND t.{r}_email = '';" for r in roles)}

-- 1) Completar lo vacío de los registros que ya existen y no tienen constancias.
UPDATE tit_historico h JOIN tmp_hist_ant t ON {mismo}
SET {(',' + chr(10) + '    ').join(sets)}
WHERE h.tramite_id IS NULL;

-- 2) Agregar los que no existen.
INSERT IGNORE INTO tit_historico (hoja, fila, carrera, acta, alumno, cuenta, modalidad, modalidad_origen, fecha_examen, titulo,
  director, director_email, codirector, codirector_email, presidente, presidente_email, secretario, secretario_email, vocal, vocal_email)
SELECT '{HOJA}', t.fila, t.carrera,
  IF(t.acta_dudosa = 0 AND NOT EXISTS (SELECT 1 FROM tit_historico x WHERE x.carrera = t.carrera AND x.acta = t.acta AND x.cuenta <> t.cuenta), t.acta, NULL),
  t.alumno, t.cuenta, t.modalidad, t.modalidad_origen, t.fecha_examen, t.titulo,
  t.director, t.director_email, t.codirector, t.codirector_email, t.presidente, t.presidente_email, t.secretario, t.secretario_email, t.vocal, t.vocal_email
FROM tmp_hist_ant t
WHERE NOT EXISTS (SELECT 1 FROM tit_historico h WHERE {mismo});

DROP TEMPORARY TABLE tmp_hist_ant;
"""
    SALIDA.write_text(cuerpo_sql, encoding='utf-8')
    revisar = [f for f in filas if f['ambiguo']]
    print(f"{len(filas)} registros; {sum(f['acta_dudosa'] for f in filas)} con acta repetida; {len(revisar)} con 2+ asesores")
    print(f"Escrito {SALIDA}")


if __name__ == '__main__':
    main()
