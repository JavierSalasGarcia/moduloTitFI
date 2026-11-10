#!/usr/bin/env bash
# Despliegue de Traza FI a producción por SSH: un solo comando, sin manifiestos ni archivos .bak dentro del sitio.
# Documentación: docs/DESPLIEGUE.md
#
#   tools/desplegar.sh                    Sube lo que cambió entre el último commit desplegado y HEAD.
#   tools/desplegar.sh --simular          Muestra qué haría, sin tocar el servidor.
#   tools/desplegar.sh --modulo Nombre    Sube completa la carpeta app/Modules/Nombre (módulo nuevo o reinstalación).
#   tools/desplegar.sh --migrar 043_x.sql Aplica en producción una migración de database/migrations (repetible).
#   tools/desplegar.sh --desde <commit>   Usa ese commit como base (primera vez o marcador perdido).
#   tools/desplegar.sh --forzar           Sube aunque el servidor tenga archivos distintos a la base (se respaldan igual).
#   tools/desplegar.sh --restaurar        Restaura el último respaldo (deshace el último despliegue).
#
# Solo se despliega lo que está en un commit: el contenido sale de git (HEAD), no del directorio de trabajo.
set -euo pipefail
cd "$(dirname "$0")/.."

CONF=tools/despliegue.local.conf
[ -f "$CONF" ] || { echo "Falta $CONF (copia tools/despliegue.example.conf y complétalo)." >&2; exit 1; }
# shellcheck disable=SC1090
. "$CONF"
: "${SSH_HOST:?}" "${SSH_PUERTO:?}" "${SSH_LLAVE:?}" "${RUTA_SITIO:?}"
ESTADO="${RUTA_ESTADO:-trazafi-despliegue}"       # en el home del servidor, fuera del sitio
CONSERVAR="${CONSERVAR_RESPALDOS:-10}"
# Lo único que se publica. Documentación, herramientas, pruebas, Plan y temporales se quedan en el repositorio.
DESPLEGABLE=(app public lib fila legacy index.php .htaccess database/migrations database/actualizar.sql)

SIMULAR=0; FORZAR=0; MODULO=""; DESDE=""; MIGRAR=""; RESTAURAR=0
while [ $# -gt 0 ]; do
  case "$1" in
    --simular) SIMULAR=1 ;;
    --forzar) FORZAR=1 ;;
    --restaurar) RESTAURAR=1 ;;
    --modulo) MODULO="${2:?}"; shift ;;
    --desde) DESDE="${2:?}"; shift ;;
    --migrar) MIGRAR="${2:?}"; shift ;;
    *) echo "Opción desconocida: $1" >&2; exit 1 ;;
  esac
  shift
done

remoto() { ssh -i "$SSH_LLAVE" -p "$SSH_PUERTO" -o BatchMode=yes "$SSH_HOST" "$@"; }

if [ -n "$MIGRAR" ]; then
  f="database/migrations/$(basename "$MIGRAR")"
  git cat-file -e "HEAD:$f" 2>/dev/null || { echo "$f no está en HEAD." >&2; exit 1; }
  [ $SIMULAR -eq 1 ] && { echo "Aplicaría $f en producción."; exit 0; }
  remoto "cd $RUTA_SITIO && test -f $f" || { echo "Primero despliega el archivo $f." >&2; exit 1; }
  # La conexión sale de app/config.php del servidor; las credenciales nunca viajan ni se imprimen.
  remoto "cd $RUTA_SITIO && php -r '\$c=(require \"app/config.php\")[\"db\"];\$p=new PDO(\"mysql:host={\$c[\"host\"]};dbname={\$c[\"nombre\"]};charset=utf8mb4\",\$c[\"usuario\"],\$c[\"clave\"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>true]);\$s=\$p->query(file_get_contents(\$argv[1]));do{}while(\$s->nextRowset());echo \"Migración aplicada: \",\$argv[1],\"\\n\";' $f"
  exit 0
fi

if [ $RESTAURAR -eq 1 ]; then
  ultimo=$(remoto "ls -1t $ESTADO/respaldos/*.tgz 2>/dev/null | head -1")
  [ -n "$ultimo" ] || { echo "No hay respaldos en el servidor." >&2; exit 1; }
  [ $SIMULAR -eq 1 ] && { echo "Restauraría $ultimo"; exit 0; }
  remoto "cd $RUTA_SITIO && tar -xzf ~/$ultimo && if [ -f ~/${ultimo%.tgz}.nuevos ]; then xargs -r rm -f < ~/${ultimo%.tgz}.nuevos; fi && cp ~/${ultimo%.tgz}.base ~/$ESTADO/ultimo_commit"
  echo "Restaurado $ultimo."; exit 0
fi

HEAD_C=$(git rev-parse HEAD)
if [ -n "$(git status --porcelain -- "${DESPLEGABLE[@]}" | grep -v '^??' || true)" ]; then
  echo "Hay cambios sin commit en archivos desplegables. Haz commit antes de desplegar." >&2; exit 1
fi

BASE="$DESDE"
[ -n "$BASE" ] || BASE=$(remoto "cat $ESTADO/ultimo_commit 2>/dev/null" || true)
if [ -z "$BASE" ] && [ -z "$MODULO" ]; then
  echo "El servidor no tiene marcador de despliegue. Indica la base con --desde <commit>." >&2; exit 1
fi
[ -z "$BASE" ] || git cat-file -e "$BASE^{commit}" 2>/dev/null || { echo "La base $BASE no existe en este repositorio (¿falta git pull?)." >&2; exit 1; }

SUBIR=$(mktemp); BORRAR=$(mktemp); trap 'rm -f "$SUBIR" "$BORRAR"' EXIT
if [ -n "$MODULO" ]; then
  [ -f "app/Modules/$MODULO/module.json" ] || [ -f "app/Modules/$MODULO/module.php" ] || { echo "app/Modules/$MODULO no tiene manifiesto." >&2; exit 1; }
  php tools/validar_modulos.php >/dev/null 2>&1 || echo "Aviso: tools/validar_modulos.php reportó problemas; revísalo." >&2
  git ls-files -- "app/Modules/$MODULO" > "$SUBIR"
else
  git diff --name-only --diff-filter=ACMRT "$BASE" HEAD -- "${DESPLEGABLE[@]}" > "$SUBIR"
  git diff --name-only --diff-filter=D "$BASE" HEAD -- "${DESPLEGABLE[@]}" > "$BORRAR"
fi
# Nunca se publican respaldos, configuración local ni pruebas.
grep -vE '(\.bak|\.orig|config\.local\.php|/tests?/)' "$SUBIR" > "$SUBIR.f" || true; mv "$SUBIR.f" "$SUBIR"

NS=$(wc -l < "$SUBIR"); NB=$(wc -l < "$BORRAR")
echo "Base: ${BASE:-ninguna (módulo)} → HEAD: $HEAD_C"
echo "Subir: $NS archivo(s); eliminar: $NB."
[ "$NS" -eq 0 ] && [ "$NB" -eq 0 ] && { echo "Nada que desplegar."; exit 0; }
sed 's/^/  + /' "$SUBIR"; sed 's/^/  - /' "$BORRAR"

# Deriva: ¿alguien cambió en el servidor un archivo que vamos a reemplazar? (se compara sin saltos de línea CR)
if [ -n "$BASE" ]; then
  DERIVA=0
  while IFS= read -r f; do
    git cat-file -e "$BASE:$f" 2>/dev/null || continue           # archivo nuevo: no hay con qué comparar
    local_h=$(git show "$BASE:$f" | tr -d '\r' | sha256sum | cut -d' ' -f1)
    rem_h=$(remoto "cd $RUTA_SITIO && [ -f '$f' ] && tr -d '\r' < '$f' | sha256sum | cut -d' ' -f1 || echo ausente")
    if [ "$local_h" != "$rem_h" ]; then echo "  ! distinto en el servidor: $f"; DERIVA=1; fi
  done < <(cat "$SUBIR" "$BORRAR")
  if [ $DERIVA -eq 1 ] && [ $FORZAR -eq 0 ]; then
    echo "El servidor tiene cambios que no están en la base. Revísalos o usa --forzar (se respaldan)." >&2; exit 1
  fi
fi

MIGR=$(grep -E '^database/migrations/' "$SUBIR" || true)
[ $SIMULAR -eq 1 ] && { echo "Simulación: no se tocó el servidor."; [ -n "$MIGR" ] && echo "Migraciones por aplicar después: $MIGR"; exit 0; }

SELLO="$(date +%Y%m%d-%H%M%S)-${HEAD_C:0:7}"
# Respaldo único, fuera del sitio, de lo que se reemplaza o elimina; lista de archivos nuevos para poder deshacer.
cat "$SUBIR" "$BORRAR" | remoto "mkdir -p $ESTADO/respaldos && cd $RUTA_SITIO && T=\$(mktemp) && N=~/$ESTADO/respaldos/$SELLO.nuevos && : > \$N && while IFS= read -r f; do if [ -f \"\$f\" ]; then echo \"\$f\" >> \$T; else echo \"\$f\" >> \$N; fi; done && tar -czf ~/$ESTADO/respaldos/$SELLO.tgz -T \$T && rm -f \$T && echo '${BASE:-}' > ~/$ESTADO/respaldos/$SELLO.base"
# Subida en un solo flujo, con el contenido exacto del commit.
git archive --format=tar HEAD $(tr '\n' ' ' < "$SUBIR") | remoto "cd $RUTA_SITIO && tar -xf -"
[ "$NB" -gt 0 ] && remoto "cd $RUTA_SITIO && xargs -r rm -f" < "$BORRAR"

# Sintaxis: si algo falla, se restaura el respaldo completo.
ERR=$(grep -E '\.php$' "$SUBIR" | remoto "cd $RUTA_SITIO && while IFS= read -r f; do php -l \"\$f\" 2>&1 | grep -v '^No syntax errors' ; done" || true)
if [ -n "$ERR" ]; then
  echo "$ERR" >&2
  remoto "cd $RUTA_SITIO && tar -xzf ~/$ESTADO/respaldos/$SELLO.tgz && xargs -r rm -f < ~/$ESTADO/respaldos/$SELLO.nuevos"
  echo "Error de sintaxis: se restauró la versión anterior." >&2; exit 1
fi

[ -z "$MODULO" ] && remoto "echo $HEAD_C > $ESTADO/ultimo_commit"
# Retención: solo los últimos $CONSERVAR respaldos.
remoto "cd $ESTADO/respaldos && ls -1t *.tgz 2>/dev/null | tail -n +$((CONSERVAR + 1)) | while read -r t; do rm -f \"\$t\" \"\${t%.tgz}.nuevos\" \"\${t%.tgz}.base\"; done"
echo "Desplegado $SELLO. Respaldo en ~/$ESTADO/respaldos/$SELLO.tgz (se conservan $CONSERVAR)."
[ -n "$MIGR" ] && { echo "Migraciones incluidas (aplícalas con --migrar):"; echo "$MIGR" | sed 's/^/  /'; }
exit 0
