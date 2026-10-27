#!/usr/bin/env python3
"""
Genera database/migrations/026_directorio_profesores.sql a partir del Excel «ProfesPorCarrera_AAB.xlsx» (una hoja por
licenciatura, propedéutico, inglés, maestría y doctorado): directorio de profesores sin cuenta, sin duplicados.

Un profesor es la persona identificada por su RFC sin homoclave (los 10 primeros caracteres de «Clave»); aparece una sola vez
aunque esté en varias hojas, y sus licenciaturas y posgrados quedan como adscripciones. Los nombres se guardan con mayúscula
inicial (sin acentos: el profesor los corrige desde su perfil); género H/M => masculino/femenino; correos institucionales.

Uso:  python tools/importar_profesores_xlsx.py "C:/ruta/ProfesPorCarrera_26B.xlsx" [--periodo 2026B] [--inicio 2026-08-01]
"""
import argparse
import collections
import re
import unicodedata
from pathlib import Path

import openpyxl

SALIDA = Path(__file__).resolve().parent.parent / 'database' / 'migrations' / '026_directorio_profesores.sql'

# Hoja del Excel => (clave de unidad, nombre, nivel, siglas del programa en `programas` o None)
UNIDADES = {
    'ICI': ('ICI', 'Ingeniería Civil', 'licenciatura', None),
    'IME': ('IME', 'Ingeniería Mecánica', 'licenciatura', 'IME'),
    'ICO': ('ICO', 'Ingeniería en Computación', 'licenciatura', 'ICO'),
    'IEL': ('IEL', 'Ingeniería en Electrónica', 'licenciatura', 'IEL'),
    'ISES': ('ISES', 'Ingeniería en Sistemas Energéticos Sustentables', 'licenciatura', 'ISES'),
    'IIA': ('IIA', 'Ingeniería en Inteligencia Artificial', 'licenciatura', 'IIA'),
    'PROPE': ('PROPE', 'Propedéutico', 'otro', None),
    'INGLES': ('INGLES', 'Inglés', 'otro', None),
    'MAESTRIA': ('MAESTRIA', 'Maestría', 'posgrado', None),
    'DOCTORADO': ('DOCTORADO', 'Doctorado', 'posgrado', None),
}
CORREOS_DE_ALUMNO = ('alumno.uaemex.mx', 'alumnouaemex.onmicrosoft.com')
PARTICULAS = {'de', 'del', 'la', 'las', 'los', 'y', 'e', 'van', 'von', 'da', 'di', 'dos'}


def sin_acentos(s: str) -> str:
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn' or c == '\u0303').lower()


def clave_hoja(titulo: str) -> str:
    t = unicodedata.normalize('NFD', titulo.strip().upper())
    return ''.join(c for c in t if unicodedata.category(c) != 'Mn')


def mayuscula_inicial(s: str, partículas: bool = False) -> str:
    """«MARIA DE LOURDES» => «Maria de Lourdes»: las partículas («de», «del», «la»…) van en minúscula, también al inicio de un apellido."""
    palabras = []
    for i, p in enumerate(re.split(r'\s+', s.strip().lower())):
        if p in PARTICULAS and (i or partículas):
            palabras.append(p)
        else:
            palabras.append(re.sub(r"(^|[-'])(\w)", lambda m: m.group(1) + m.group(2).upper(), p))
    return ' '.join(palabras)


def correo_valido(v) -> str:
    v = (str(v) if v else '').strip().lower()
    return v if re.fullmatch(r'[^@\s]+@[^@\s]+\.[^@\s]+', v) else ''


def sql(v) -> str:
    return 'NULL' if v is None else "'" + str(v).replace('\\', '\\\\').replace("'", "''") + "'"


def pertenece(correo: str, nombre: str, ap1: str) -> int:
    """Puntúa qué tanto «inicial(es) del nombre + primer apellido» coincide con el usuario del correo."""
    usuario = sin_acentos(correo.split('@')[0])
    ap = sin_acentos(ap1).replace(' ', '')
    iniciales = ''.join(w[0] for w in sin_acentos(nombre).split())
    return 2 if usuario.startswith(iniciales) and ap in usuario else (1 if ap in usuario else 0)


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('excel')
    ap.add_argument('--periodo', default='2026B')
    ap.add_argument('--inicio', default='2026-08-01')
    a = ap.parse_args()

    personas = {}                     # rfc10 -> datos
    adscripciones = collections.defaultdict(set)
    veces_correo = collections.defaultdict(collections.Counter)    # rfc10 -> Counter(correo)
    generos = collections.defaultdict(list)                        # rfc10 -> [(hoja, 'H'|'M')]
    excluidos = collections.Counter()
    sin_clave = []

    wb = openpyxl.load_workbook(a.excel, read_only=True, data_only=True)
    for ws in wb:
        hoja = clave_hoja(ws.title)
        if hoja not in UNIDADES:
            print(f'Hoja omitida: {ws.title}')
            continue
        for r in list(ws.iter_rows(values_only=True))[1:]:
            if not r or not any(r):
                continue
            clave = str(r[0] or '').strip().upper()
            if len(clave) < 10:
                sin_clave.append((ws.title, r[1:4]))
                continue
            k = clave[:10]
            p = personas.setdefault(k, dict(rfc=clave, nombre=str(r[3] or '').strip(), ap1=str(r[1] or '').strip(), ap2=str(r[2] or '').strip()))
            if len(clave) > len(p['rfc']):
                p['rfc'] = clave          # conserva la forma con homoclave si alguna hoja la trae
            adscripciones[k].add(hoja)
            for v in r[4:]:
                v = str(v or '').strip()
                if v.upper() in ('H', 'M'):
                    generos[k].append((hoja, v.upper()))
                elif '@' in v:
                    c = correo_valido(v)
                    if not c:
                        continue
                    if c.split('@')[1] in CORREOS_DE_ALUMNO:
                        excluidos[c] += 1
                    else:
                        veces_correo[k][c] += 1

    # Género: el de la mayoría de las hojas, dando prioridad a las que no son PROPE (que viene desordenada); se avisan los conflictos.
    avisos = []
    for k, p in personas.items():
        g = generos.get(k, [])
        propio = [x for h, x in g if h != 'PROPE'] or [x for h, x in g]
        conteo = collections.Counter(propio)
        p['genero'] = None
        if conteo:
            p['genero'] = 'masculino' if conteo.most_common(1)[0][0] == 'H' else 'femenino'
        if len({x for _, x in g}) > 1:
            avisos.append(f"género distinto entre hojas ({'/'.join(f'{h}:{x}' for h, x in g)}): se tomó «{p['genero']}»")
        if not g:
            avisos.append('sin género en el Excel')
        if set(adscripciones[k]) == {'PROPE'} and g:
            avisos.append('solo aparece en PROPE (su género no se pudo contrastar)')
        p['obs'] = avisos[:]
        avisos.clear()

    # Un mismo correo no puede ser de dos personas: se queda con quien coincide mejor con su forma (inicial + apellido).
    dueños = collections.defaultdict(list)
    for k, c in veces_correo.items():
        for correo in c:
            dueños[correo].append(k)
    conflictos = []
    for correo, ks in dueños.items():
        if len(ks) > 1:
            puntos = sorted(((pertenece(correo, personas[k]['nombre'], personas[k]['ap1']), k) for k in ks), reverse=True)
            ganador = puntos[0][1] if puntos[0][0] > puntos[1][0] else None
            conflictos.append((correo, ks, ganador))
            for k in ks:
                if k != ganador:
                    del veces_correo[k][correo]
                    personas[k]['obs'].append(f'el correo {correo} del Excel pertenece a otra persona y no se asignó')

    correos = []
    for k, c in veces_correo.items():
        # Principal: @uaemex.mx antes que @profesor.uaemex.mx, y el que aparece en más hojas.
        orden = sorted(c, key=lambda m: (m.endswith('@uaemex.mx') is False, -c[m], m))
        for i, m in enumerate(orden):
            correos.append((k, m, int(i == 0)))
    sin_correo = [k for k in personas if not veces_correo.get(k)]

    # ------------------------------------------------------------------ SQL
    filas_p = ',\n'.join(
        '(' + ', '.join(sql(x) for x in (k, p['rfc'], mayuscula_inicial(p['nombre']), mayuscula_inicial(p['ap1'], True), mayuscula_inicial(p['ap2'], True) or None,
                                         p['genero'], '; '.join(p['obs']) or None)) + ')' for k, p in sorted(personas.items(), key=lambda kv: (kv[1]['ap1'], kv[1]['ap2'], kv[1]['nombre'])))
    filas_c = ',\n'.join('(' + ', '.join((sql(k), sql(m), str(pr))) + ')' for k, m, pr in correos)
    filas_a = ',\n'.join('(' + sql(k) + ', ' + sql(u) + ')' for k, us in sorted(adscripciones.items()) for u in sorted(us))
    unidades = ',\n'.join(
        "  (" + ', '.join((sql(c), sql(n), sql(nv), f"(SELECT id FROM programas WHERE siglas = {sql(s)})" if s else 'NULL')) + ")" for c, n, nv, s in UNIDADES.values())

    texto = f"""-- Directorio de profesores (sin cuenta en la plataforma), importado del Excel de profesores por carrera del periodo {a.periodo}:
-- {len(personas)} profesores distintos en {sum(len(v) for v in adscripciones.values())} adscripciones a licenciaturas, propedéutico, inglés y posgrados.
-- Generado por tools/importar_profesores_xlsx.py. Repetible y sin sobrescribir: un profesor se identifica por su RFC sin homoclave y
-- aparece una sola vez aunque imparta en varios programas; al volver a ejecutarla no se pisan los nombres que el profesor corrigió.
--
--  profesores            una fila por persona (nombre con mayúscula inicial, género para «Profesor»/«Profesora», cuenta enlazada).
--  profesor_correos      correos institucionales de la persona (un correo no puede ser de dos personas).
--  profesor_unidades     catálogo de lo que se imparte: licenciaturas (con su programa), propedéutico, inglés, maestría y doctorado.
--  profesor_adscripciones  qué imparte cada profesor, por periodo (llave primaria compuesta: sin repetidos).
-- Cuando el profesor se registra con uno de sus correos y lo verifica, su cuenta se enlaza a este registro (usuario_id) en lugar de
-- crear otra persona; sus programas pasan a programa_profesores y su género y nombre se toman de aquí.

CREATE TABLE IF NOT EXISTS profesores (
  id int unsigned NOT NULL AUTO_INCREMENT,
  rfc10 char(10) NOT NULL,
  rfc varchar(13) NOT NULL,
  nombre varchar(80) NOT NULL,
  apellido1 varchar(60) NOT NULL,
  apellido2 varchar(60) DEFAULT NULL,
  grado varchar(30) DEFAULT NULL,
  genero enum('masculino','femenino') DEFAULT NULL,
  usuario_id int unsigned DEFAULT NULL,
  observaciones varchar(500) DEFAULT NULL,
  creado_en timestamp NOT NULL DEFAULT current_timestamp(),
  actualizado_en timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_rfc (rfc10),
  UNIQUE KEY uq_profesor_usuario (usuario_id),
  CONSTRAINT fk_profesor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_correos (
  id int unsigned NOT NULL AUTO_INCREMENT,
  profesor_id int unsigned NOT NULL,
  email varchar(120) NOT NULL,
  principal tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_correo (email),
  KEY idx_profesor_correo (profesor_id),
  CONSTRAINT fk_profesor_correo FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_unidades (
  id smallint unsigned NOT NULL AUTO_INCREMENT,
  clave varchar(12) NOT NULL,
  nombre varchar(80) NOT NULL,
  nivel enum('licenciatura','posgrado','otro') NOT NULL,
  programa_id smallint unsigned DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profesor_unidad (clave),
  CONSTRAINT fk_profesor_unidad_programa FOREIGN KEY (programa_id) REFERENCES programas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

CREATE TABLE IF NOT EXISTS profesor_adscripciones (
  profesor_id int unsigned NOT NULL,
  unidad_id smallint unsigned NOT NULL,
  periodo char(5) NOT NULL,
  fecha_inicio date NOT NULL,
  PRIMARY KEY (profesor_id, unidad_id, periodo),
  KEY idx_profesor_adscripcion_unidad (unidad_id),
  CONSTRAINT fk_profesor_adscripcion_profesor FOREIGN KEY (profesor_id) REFERENCES profesores (id) ON DELETE CASCADE,
  CONSTRAINT fk_profesor_adscripcion_unidad FOREIGN KEY (unidad_id) REFERENCES profesor_unidades (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT IGNORE INTO profesor_unidades (clave, nombre, nivel, programa_id) VALUES
{unidades};

DROP TEMPORARY TABLE IF EXISTS tmp_prof;
DROP TEMPORARY TABLE IF EXISTS tmp_prof_correo;
DROP TEMPORARY TABLE IF EXISTS tmp_prof_unidad;
CREATE TEMPORARY TABLE tmp_prof (rfc10 char(10) NOT NULL, rfc varchar(13) NOT NULL, nombre varchar(80) NOT NULL, apellido1 varchar(60) NOT NULL,
  apellido2 varchar(60) DEFAULT NULL, genero varchar(10) DEFAULT NULL, observaciones varchar(500) DEFAULT NULL, PRIMARY KEY (rfc10)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
CREATE TEMPORARY TABLE tmp_prof_correo (rfc10 char(10) NOT NULL, email varchar(120) NOT NULL, principal tinyint NOT NULL, PRIMARY KEY (email)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
CREATE TEMPORARY TABLE tmp_prof_unidad (rfc10 char(10) NOT NULL, clave varchar(12) NOT NULL, PRIMARY KEY (rfc10, clave)) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

INSERT INTO tmp_prof (rfc10, rfc, nombre, apellido1, apellido2, genero, observaciones) VALUES
{filas_p};

INSERT INTO tmp_prof_correo (rfc10, email, principal) VALUES
{filas_c};

INSERT INTO tmp_prof_unidad (rfc10, clave) VALUES
{filas_a};

-- 1) Profesores nuevos; los que ya existen no se tocan (conservan lo que el profesor corrigió), salvo género y RFC completo si faltaban.
INSERT IGNORE INTO profesores (rfc10, rfc, nombre, apellido1, apellido2, genero, observaciones)
SELECT rfc10, rfc, nombre, apellido1, apellido2, genero, observaciones FROM tmp_prof;
UPDATE profesores p JOIN tmp_prof t ON t.rfc10 = p.rfc10
SET p.genero = IFNULL(p.genero, t.genero), p.rfc = IF(CHAR_LENGTH(p.rfc) < CHAR_LENGTH(t.rfc), t.rfc, p.rfc);

-- 2) Correos (uno por persona; si ya pertenece a alguien, se conserva) y adscripciones del periodo {a.periodo}.
INSERT IGNORE INTO profesor_correos (profesor_id, email, principal)
SELECT p.id, c.email, IF(EXISTS (SELECT 1 FROM profesor_correos x WHERE x.profesor_id = p.id AND x.principal = 1), 0, c.principal)
FROM tmp_prof_correo c JOIN profesores p ON p.rfc10 = c.rfc10;
INSERT IGNORE INTO profesor_adscripciones (profesor_id, unidad_id, periodo, fecha_inicio)
SELECT p.id, u.id, '{a.periodo}', '{a.inicio}' FROM tmp_prof_unidad t JOIN profesores p ON p.rfc10 = t.rfc10 JOIN profesor_unidades u ON u.clave = t.clave;

-- 3) Profesores que ya tienen cuenta (su correo coincide y no hay otra persona enlazada a esa cuenta): se enlazan, sin crear otro registro.
UPDATE profesores p
JOIN profesor_correos c ON c.profesor_id = p.id
JOIN usuarios u ON u.email = c.email
SET p.usuario_id = u.id
WHERE p.usuario_id IS NULL AND NOT EXISTS (SELECT 1 FROM (SELECT usuario_id FROM profesores WHERE usuario_id IS NOT NULL) y WHERE y.usuario_id = u.id);
UPDATE usuarios u JOIN profesores p ON p.usuario_id = u.id SET u.genero = p.genero WHERE u.genero IS NULL AND p.genero IS NOT NULL;

-- 4) Los programas de los profesores con cuenta pasan a programa_profesores (adscripción docente vigente, sin repetir).
INSERT IGNORE INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo, observaciones)
SELECT un.programa_id, p.usuario_id, 'docente', a.fecha_inicio, 1, 'Directorio de profesores {a.periodo}'
FROM profesor_adscripciones a JOIN profesores p ON p.id = a.profesor_id AND p.usuario_id IS NOT NULL
JOIN profesor_unidades un ON un.id = a.unidad_id AND un.programa_id IS NOT NULL
WHERE a.periodo = '{a.periodo}';

-- 5) Correos del historial de actas (tit_historico): se completan, sin pisar los que ya tienen, cuando el nombre (sin importar acentos
--    ni mayúsculas, con o sin grado) coincide con un solo profesor del directorio. El correo del director solo se asigna si no hay
--    codirector: con dos asesores el sistema anterior no distingue sus roles y deben confirmarse a mano.
{chr(10).join(f"UPDATE tit_historico h SET h.{r}_email = IFNULL((SELECT MIN(c.email) FROM profesores p JOIN profesor_correos c ON c.profesor_id = p.id AND c.principal = 1 WHERE h.{r} COLLATE utf8mb4_spanish_ci = CONCAT_WS(' ', p.nombre, p.apellido1, p.apellido2) OR h.{r} COLLATE utf8mb4_spanish_ci LIKE CONCAT('% ', CONCAT_WS(' ', p.nombre, p.apellido1, p.apellido2)) HAVING COUNT(DISTINCT p.id) = 1), '') WHERE h.{r}_email = '' AND h.{r} <> '' AND h.tramite_id IS NULL{extra};" for r, extra in (('director', " AND h.codirector = ''"), ('presidente', ''), ('secretario', ''), ('vocal', ''), ('codirector', ' AND 1 = 0')))}

DROP TEMPORARY TABLE tmp_prof;
DROP TEMPORARY TABLE tmp_prof_correo;
DROP TEMPORARY TABLE tmp_prof_unidad;
"""
    SALIDA.write_text(texto, encoding='utf-8')
    print(f'{len(personas)} profesores, {len(correos)} correos, {sum(len(v) for v in adscripciones.values())} adscripciones')
    print(f'sin correo: {len(sin_correo)}; correos de alumno excluidos: {sum(excluidos.values())}; con aviso: {sum(1 for p in personas.values() if p["obs"])}')
    for c, ks, g in conflictos:
        print(f'correo repetido {c}: {ks} -> {g}')
    for t, n in sin_clave:
        print('fila sin clave válida:', t, n)
    print(f'Escrito {SALIDA}')


if __name__ == '__main__':
    main()
