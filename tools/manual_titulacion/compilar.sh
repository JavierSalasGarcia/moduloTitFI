#!/usr/bin/env bash
# Compila los manuales LaTeX (dos pasadas de pdflatex) a docs/manuales/<nivel>/pdf/<actor>/<opción>.pdf
# Uso: bash tools/manual_titulacion/compilar.sh [titulacion|posgrado] [actor] [opción]   (sin argumentos: titulacion, todos)
# Requiere pdflatex en el PATH (TeX Live o MiKTeX). Variable opcional: MANUAL_JOBS (compilaciones en paralelo, por omisión 4).
set -euo pipefail
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
NIVEL="${1:-titulacion}"
LATEX="$RAIZ/docs/manuales/$NIVEL/latex"
PDFS="$RAIZ/docs/manuales/$NIVEL/pdf"
JOBS="${MANUAL_JOBS:-4}"
[ -d "$LATEX" ] || { echo "No existe $LATEX"; exit 1; }
cd "$LATEX"
uno() {
  f="$1"; actor="$(basename "$(dirname "$f")")"; nombre="$(basename "$f" .tex)"
  mkdir -p "$PDFS/$actor" ".build/$actor"
  for i in 1 2; do
    if ! pdflatex -interaction=nonstopmode -halt-on-error -output-directory ".build/$actor" "$f" > ".build/$actor/$nombre.salida" 2>&1; then
      echo "FALLÓ $actor/$nombre (ver $LATEX/.build/$actor/$nombre.log)"; return 1
    fi
  done
  cp ".build/$actor/$nombre.pdf" "$PDFS/$actor/$nombre.pdf"
  echo "OK $actor/$nombre"
}
export -f uno; export PDFS LATEX
ls manuales/${2:-*}/${3:-*}.tex | xargs -P "$JOBS" -I{} bash -c 'uno "$@"' _ {}
