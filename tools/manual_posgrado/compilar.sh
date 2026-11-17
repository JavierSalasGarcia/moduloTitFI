#!/usr/bin/env bash
# Compila los manuales de Posgrado. Uso: bash tools/manual_posgrado/compilar.sh [actor] [modalidad]
exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../manual_titulacion/compilar.sh" posgrado "$@"
