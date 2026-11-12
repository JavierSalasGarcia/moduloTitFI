#!/usr/bin/env bash
# Compila los manuales LaTeX de Titulación a docs/manuales/titulacion/pdf/<actor>/<opción>.pdf
# Uso: bash tools/manual_titulacion/compilar.sh [actor] [opción]     (sin argumentos: todos)
set -euo pipefail
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LATEX="$RAIZ/docs/manuales/titulacion/latex"
PDFS="$RAIZ/docs/manuales/titulacion/pdf"
export PATH="$PATH:/c/Users/edigi/AppData/Local/Programs/MiKTeX/miktex/bin/x64"
cd "$LATEX"
for f in manuales/${1:-*}/${2:-*}.tex; do
  actor="$(basename "$(dirname "$f")")"; nombre="$(basename "$f" .tex)"
  mkdir -p "$PDFS/$actor" "$LATEX/.build/$actor"
  # Dos pasadas: la tabla de contenido necesita la segunda.
  for i in 1 2; do
    pdflatex -interaction=nonstopmode -halt-on-error -output-directory "$LATEX/.build/$actor" "$f" > "$LATEX/.build/$actor/$nombre.log" 2>&1 || { echo "FALLÓ $f (ver .build/$actor/$nombre.log)"; exit 1; }
  done
  cp "$LATEX/.build/$actor/$nombre.pdf" "$PDFS/$actor/$nombre.pdf"
  echo "OK $actor/$nombre"
done
