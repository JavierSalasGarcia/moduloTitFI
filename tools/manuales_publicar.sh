#!/usr/bin/env bash
# Regenera, compila y publica los manuales en PDF de Titulación (licenciatura) y Posgrado.
#
#   bash tools/manuales_publicar.sh                 # todo
#   bash tools/manuales_publicar.sh licenciatura    # solo licenciatura
#   bash tools/manuales_publicar.sh posgrado        # solo posgrado
#   MANUAL_SIN_CATALOGO=1 bash tools/manuales_publicar.sh   # no vuelve a exportar los catálogos (sin PHP/MySQL)
#
# Pasos: exporta los catálogos (PHP), genera los .tex (Python), compila con pdflatex (dos pasadas) y copia los PDF a
#   storage/manuales/licenciatura/<actor>/<opción>.pdf      y .../<actor>/consolidado.pdf
#   storage/manuales/posgrado/<actor>/<modalidad>.pdf       y .../<actor>/consolidado.pdf
# storage/ no se versiona: esa carpeta se sube aparte al servidor. Las capturas deben existir antes (recorrido.py).
set -euo pipefail
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="${PHP:-php}"
QUE="${1:-todo}"
LIMITE=$((4 * 1024 * 1024))

publicar() {   # $1 = carpeta en docs/manuales (titulacion|posgrado), $2 = carpeta en storage/manuales
  local origen="$RAIZ/docs/manuales/$1/pdf" destino="$RAIZ/storage/manuales/$2" n=0 grandes=0
  mkdir -p "$destino"
  # Se reemplaza todo: un manual que ya no existe no debe seguir descargándose.
  find "$destino" -name '*.pdf' -delete
  while IFS= read -r f; do
    rel="${f#$origen/}"
    mkdir -p "$destino/$(dirname "$rel")"
    cp "$f" "$destino/$rel"
    n=$((n + 1))
    if [ "$(stat -c %s "$f")" -gt "$LIMITE" ]; then echo "AVISO: $rel pesa más de 4 MB"; grandes=$((grandes + 1)); fi
  done < <(find "$origen" -name '*.pdf' | sort)
  echo "Publicados $n PDF en storage/manuales/$2 ($grandes de más de 4 MB)"
}

if [ "$QUE" = todo ] || [ "$QUE" = licenciatura ]; then
  [ -n "${MANUAL_SIN_CATALOGO:-}" ] || "$PHP" "$RAIZ/tools/manual_titulacion/exportar_catalogo.php" >/dev/null
  python3 "$RAIZ/tools/manual_titulacion/generar_latex.py"
  rm -rf "$RAIZ/docs/manuales/titulacion/pdf"
  bash "$RAIZ/tools/manual_titulacion/compilar.sh" titulacion | grep -v '^OK' || true
  publicar titulacion licenciatura
fi
if [ "$QUE" = todo ] || [ "$QUE" = posgrado ]; then
  [ -n "${MANUAL_SIN_CATALOGO:-}" ] || "$PHP" "$RAIZ/tools/manual_posgrado/exportar_catalogo.php" >/dev/null
  python3 "$RAIZ/tools/manual_posgrado/generar_latex.py"
  rm -rf "$RAIZ/docs/manuales/posgrado/pdf"
  bash "$RAIZ/tools/manual_titulacion/compilar.sh" posgrado | grep -v '^OK' || true
  publicar posgrado posgrado
fi
