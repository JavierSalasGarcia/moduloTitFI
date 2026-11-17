#!/usr/bin/env bash
# Pruebas de las rutas de descarga de manuales contra el servidor LOCAL (usa /__dev_login, que solo existe en el router
# de pruebas). Uso: bash tools/manual_titulacion/probar_descargas.sh [URL base]
set -uo pipefail
BASE="${1:-${MANUAL_BASE:-http://127.0.0.1:8090}}"
BASE="${BASE%/}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fallas=0

sesion() { curl -s -c "$TMP/$1" "$BASE/__dev_login?email=$1" >/dev/null; }
prueba() {   # prueba <cookie|-> <ruta> <código esperado> [tipo esperado]
  local ck="$1" ruta="$2" esperado="$3" tipo="${4:-}" args=(-s -o "$TMP/cuerpo" -D "$TMP/cab" -w '%{http_code} %{content_type}')
  [ "$ck" != "-" ] && args+=(-b "$TMP/$ck")
  local r; r="$(curl "${args[@]}" --path-as-is "$BASE/$ruta")"
  local cod="${r%% *}" ct="${r#* }" ok=1
  [ "$cod" = "$esperado" ] || ok=0
  [ -z "$tipo" ] || [[ "$ct" == "$tipo"* ]] || ok=0
  local extra=""
  if [ "$cod" = 200 ] && [ "$tipo" = application/pdf ]; then
    extra=" $(grep -i '^content-disposition' "$TMP/cab" | tr -d '\r' | cut -c1-90) | $(grep -i '^cache-control' "$TMP/cab" | tr -d '\r')"
    head -c 5 "$TMP/cuerpo" | grep -q '%PDF-' || ok=0
  fi
  if [ "$cod" = 302 ]; then extra=" → $(grep -i '^location' "$TMP/cab" | tr -d '\r' | cut -d' ' -f2)"; fi
  printf '%-4s %-38s %-62s %s %s%s\n' "$([ $ok = 1 ] && echo OK || echo MAL)" "${ck}" "$ruta" "$cod" "$ct" "$extra"
  [ $ok = 1 ] || fallas=$((fallas + 1))
}

PAS=prueba.pasante.tesis@alumno.uaemex.mx
LIC=licenciatura@alumno.uaemex.mx
PROF=profesor.prueba@uaemex.mx
JEFA=prueba.jefatura.titulacion@uaemex.mx
SUB=prueba.subdirector.academico@uaemex.mx
EGR=maestria@alumno.uaemex.mx
APO=apoyo.grado@uaemex.mx
COO=coord.avanzados@uaemex.mx
SIN=prueba.sinodal1@uaemex.mx
OTRO=prueba.coordinador.computacion@uaemex.mx
for c in $PAS $LIC $PROF $JEFA $SUB $EGR $APO $COO $SIN $OTRO; do sesion "$c"; done

echo "== Licenciatura: descargas válidas (200 application/pdf)"
for par in pasante/tesis pasante/egel pasante/aprovechamiento pasante/consolidado asesor/tesis coasesor/memoria comision_protocolos/articulo \
           coordinador_carrera/tesina sinodal/ensayo responsable_titulacion/creditos subdirector/egel servicios_escolares/residencia asesor/consolidado; do
  prueba $LIC "titulacion/manual/$par" 200 application/pdf
done
prueba $PROF "titulacion/manual/sinodal/tesis" 200 application/pdf
prueba $JEFA "titulacion/manual/responsable_titulacion/consolidado" 200 application/pdf
prueba $EGR "titulacion/manual/pasante/tesis" 200 application/pdf
echo "== Licenciatura: inválidos (404)"
for r in titulacion/manual/rector/tesis titulacion/manual/pasante/doctorado titulacion/manual/asesor/egel \
         titulacion/manual/pasante/..%2f..%2fconfig titulacion/manual/../manual/pasante/tesis titulacion/manual/pasante/tesis.pdf \
         titulacion/manual/%2e%2e/tesis; do
  prueba $LIC "$r" 404
done
echo "== Byte nulo: lo rechaza el Router (400)"
prueba $LIC "titulacion/manual/pasante/consolidado%00" 400
echo "== Sin sesión (302 a login)"
prueba - titulacion/manual/pasante/tesis 302
prueba - titulacion/manuales 302
prueba - posgrado/manual/egresado/tesis 302
prueba - posgrado/modalidad/tesis 302

echo "== Posgrado: descargas válidas"
for par in egresado/tesis egresado/trabajo_terminal egresado/proyecto_terminal egresado/articulos egresado/consolidado director/tesis sinodal/articulos coordinacion/consolidado apoyo_grado/tesis supervision/proyecto_terminal; do
  prueba $EGR "posgrado/manual/$par" 200 application/pdf
done
prueba $APO posgrado/manual/apoyo_grado/consolidado 200 application/pdf
prueba $COO posgrado/manual/coordinacion/tesis 200 application/pdf
prueba $SUB posgrado/manual/supervision/consolidado 200 application/pdf
prueba $SIN posgrado/manual/sinodal/tesis 200 application/pdf
echo "== Posgrado: inválidos (404)"
for r in posgrado/manual/pasante/tesis posgrado/manual/egresado/egel posgrado/manual/egresado/..%2fsecretos posgrado/modalidad/egel posgrado/manual/egresado/tesis.pdf; do
  prueba $EGR "$r" 404
done
echo "== Posgrado no visible para un alumno de licenciatura ni para un profesor sin participación (404)"
prueba $LIC posgrado/manual/egresado/tesis 404
prueba $LIC posgrado/modalidad/tesis 404
prueba $LIC posgrado/manuales 404
prueba $OTRO posgrado/manual/director/tesis 404
prueba $OTRO posgrado/manuales 404
echo "== Director de tesis con cuenta (participa en Posgrado: sí ve)"
prueba $PROF posgrado/manual/director/tesis 200 application/pdf
echo "== Páginas (200 text/html)"
for c in $LIC $PROF; do prueba $c titulacion/manuales 200 text/html; prueba $c titulacion/licenciatura/modalidad/tesis 200 text/html; done
for c in $EGR $APO $SUB; do prueba $c posgrado/manuales 200 text/html; prueba $c posgrado/modalidad/articulos 200 text/html; done
echo "Fallas: $fallas"
exit $fallas
