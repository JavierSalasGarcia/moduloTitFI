<?php
use App\Core\Auth;
use App\Modules\Eventos\Clasificacion;
$visitante = !empty($visitante);
$filtrosTipo = ['todo' => 'Todo', 'avisos' => 'Avisos oficiales', 'convocatorias' => 'Convocatorias', 'eventos' => 'Eventos', 'examenes' => 'Exámenes'];
$cuenta = ['todo' => count($items)]; $sinLeer = ['todo' => 0];
foreach ($items as $it) {
    $cuenta[$it['filtro']] = ($cuenta[$it['filtro']] ?? 0) + 1;
    if (!$it['leido'] && !$visitante && !$it['borrador']) { $sinLeer[$it['filtro']] = ($sinLeer[$it['filtro']] ?? 0) + 1; $sinLeer['todo']++; }
}
$meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
$etiquetaTipo = ['avisos' => 'Aviso oficial', 'convocatorias' => 'Convocatoria', 'eventos' => 'Evento', 'examenes' => 'Exámenes'];
if (!$visitante) {
    $siglasProg = array_column($programas, 'siglas', 'id');
    $hayFiltro = $filtros['programa'] || $filtros['semestre'] || $filtros['tema'] !== '' || $filtros['categoria'] !== '' || $filtros['modalidad'] !== '';
}
?>
<?php include __DIR__ . '/_cintillo.php'; ?>

<header class="avisos-cabecera">
  <div>
    <h1 style="margin:0;font-size:22px"><?= $avisosArchivo ? 'Archivo de avisos' : 'Avisos y eventos' ?></h1>
    <p class="texto-suave" style="margin:4px 0 0">
      <?php if ($visitante): ?>Avisos públicos de la Facultad de Ingeniería.
      <?php elseif ($sinLeer['todo'] > 0): ?><strong style="color:var(--verde-700)"><?= (int) $sinLeer['todo'] ?> sin leer</strong> · abre cada aviso para leerlo completo.
      <?php else: ?>Estás al día: no tienes avisos sin leer.<?php endif; ?>
    </p>
  </div>
  <?php if (!$visitante): ?>
  <div class="grupo-chips">
    <?php if ($sinLeer['todo'] > 0): ?>
      <form method="post" action="<?= url('eventos/leidos') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini" type="submit">✓ Marcar todo como leído</button></form>
    <?php endif; ?>
    <?php if ($puedeEmitir): ?><a class="btn btn-mini" href="<?= url('eventos/nuevo') ?>">+ Publicar evento</a><?php endif; ?>
  </div>
  <?php endif; ?>
</header>

<?php if (!$visitante && $puedeEmitirComunicados): ?>
  <details class="card" style="margin:0 0 16px">
    <summary style="cursor:pointer;font-weight:700;color:var(--verde-900)">+ Publicar aviso oficial, convocatoria o exámenes</summary>
    <form method="post" action="<?= url('comunicados') ?>" class="formulario" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Tipo<select name="clase"><option value="aviso">Aviso oficial</option><option value="convocatoria">Convocatoria</option><option value="examenes">Exámenes</option></select></label>
      <label>Título <span class="req">*</span><input type="text" name="titulo" required maxlength="180" placeholder="Ej. Exámenes definitivos 2026B"></label>
      <?php if (count($emisores) > 1): ?>
      <label>Emitir como<select name="depto_id"><?php foreach ($emisores as $em): ?><option value="<?= (int) $em['id'] ?>"><?= e($em['nombre']) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <label>Dirigido a <span class="req">*</span><select name="destinatarios"><?php foreach (\App\Modules\Comunicados\Avisos::DESTINATARIOS as $k => $n): ?><option value="<?= e($k) ?>"><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Alcance <span class="req">*</span>
        <select name="alcance" onchange="document.getElementById('carrera-select-wrapper').style.display = this.value === 'programa' ? 'block' : 'none'">
          <option value="todos">Todos los programas</option><option value="licenciatura">Solo Licenciatura</option><option value="posgrado">Solo Posgrado</option><option value="programa">Uno o varios programas específicos…</option>
        </select>
      </label>
      <fieldset id="carrera-select-wrapper" style="display:none;border:1px solid var(--gris-200);border-radius:6px;padding:8px 12px">
        <legend>Programas a los que aplica</legend>
        <?php foreach ($programas as $p): ?><label style="display:block;font-weight:400"><input type="checkbox" name="programas[]" value="<?= (int) $p['id'] ?>"> <?= e($p['siglas']) ?> · <?= e($p['nombre']) ?></label><?php endforeach; ?>
      </fieldset>
      <label>Contenido <span class="req">*</span><textarea name="contenido" rows="5" required></textarea></label>
      <label>Vigente hasta (opcional)<input type="date" name="vence_en"><small class="ayuda-form">Después de esta fecha pasa al archivo.</small></label>
      <label style="font-weight:400"><input type="checkbox" name="publico" value="1"> Se puede consultar sin iniciar sesión (página pública de avisos)</label>
      <label style="font-weight:400"><input type="checkbox" name="borrador" value="1"> Guardar como borrador para revisarlo y adjuntar archivos (PDF) antes de publicarlo</label>
      <div class="acciones-formulario"><button type="submit" class="btn btn-primario">Guardar</button></div>
    </form>
  </details>
<?php endif; ?>

<?php if (!$visitante && $perfil['grupo'] === 'alumno' && !$preferencias['vigente'] && !$avisosArchivo): ?>
  <form method="post" action="<?= url('eventos/preferencias') ?>" class="card ux-toolbar" style="margin-bottom:14px;background:#FFFBEB;flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="volver" value="eventos">
    <?php foreach ($preferencias['temas'] as $t): ?><input type="hidden" name="temas[]" value="<?= e($t) ?>"><?php endforeach; ?>
    <?php if ($preferencias['notificar']): ?><input type="hidden" name="notificar" value="1"><?php endif; ?>
    <span><strong>¿En qué semestre estás este periodo (<?= e(Clasificacion::periodo()) ?>)?</strong> Así te mostramos los eventos que te tocan.</span>
    <select name="semestre" required><option value="">Elige…</option><?php for ($i = 1; $i <= Clasificacion::SEMESTRE_MAX; $i++): ?><option value="<?= $i ?>" <?= $preferencias['semestre'] === $i ? 'selected' : '' ?>><?= $i ?>.°</option><?php endfor; ?></select>
    <button class="btn btn-primario btn-mini" type="submit">Guardar</button>
  </form>
<?php endif; ?>

<div class="avisos-filtros" role="toolbar" aria-label="Filtrar por tipo">
  <?php foreach ($filtrosTipo as $k => $n): if ($k !== 'todo' && empty($cuenta[$k])) continue; ?>
    <button type="button" class="chip-filtro<?= $k === 'todo' ? ' activo' : '' ?>" data-filtro="<?= e($k) ?>" aria-pressed="<?= $k === 'todo' ? 'true' : 'false' ?>">
      <?= e($n) ?> <span class="chip-num"><?= (int) ($cuenta[$k] ?? 0) ?></span><?php if (!empty($sinLeer[$k])): ?><span class="chip-nuevos" title="sin leer"><?= (int) $sinLeer[$k] ?> nuevo<?= $sinLeer[$k] > 1 ? 's' : '' ?></span><?php endif; ?>
    </button>
  <?php endforeach; ?>
  <div class="buscador" style="margin:0 0 0 auto;max-width:300px;flex:1 1 220px"><?= icono('buscar', 16) ?><input type="search" id="buscar-avisos" placeholder="Buscar…" autocomplete="off" aria-label="Buscar avisos y eventos"></div>
</div>

<div id="lista-avisos">
  <?php foreach ($items as $it): $ev = $it['tipo'] === 'evento' ? $it['dato'] : null; ?>
    <article class="card aviso-item<?= (!$it['leido'] && !$visitante) ? ' no-leido' : '' ?>" data-filtro="<?= e($it['filtro']) ?>" data-texto="<?= e(mb_strtolower($it['titulo'] . ' ' . $it['texto'] . ' ' . $it['origen'])) ?>">
      <a class="aviso-enlace" href="<?= e(url($it['url'])) ?>">
        <?php if ($ev): $t = strtotime($ev['inicio']); ?>
          <span class="evento-fecha" aria-hidden="true"><span class="evento-dia"><?= (int) date('d', $t) ?></span><span class="evento-mes"><?= $meses[(int) date('n', $t) - 1] ?></span></span>
        <?php else: ?>
          <span class="aviso-icono" aria-hidden="true"><?= ['avisos' => '📢', 'convocatorias' => '📣', 'examenes' => '📝'][$it['filtro']] ?? '📢' ?></span>
        <?php endif; ?>
        <span class="aviso-cuerpo">
          <span class="grupo-chips" style="gap:6px">
            <span class="badge tipo-<?= e($it['filtro']) ?>"><?= e($etiquetaTipo[$it['filtro']] ?? 'Aviso') ?></span>
            <?php if (!$it['leido'] && !$visitante && !$it['borrador']): ?><span class="badge badge-nuevo">● Nuevo</span><?php endif; ?>
            <?php if ($it['importante']): ?><span class="badge" style="background:#fef3c7;color:#b45309;border-color:#fde68a">⭐ Importante</span><?php endif; ?>
            <?php if ($it['borrador']): ?><span class="badge" style="background:#f1f5f9;color:#475569">Borrador · solo tu área lo ve</span><?php endif; ?>
            <?php if (!$visitante && !empty($it['publico'])): ?><span class="badge" title="Se consulta sin iniciar sesión">🌐 Público</span><?php endif; ?>
          </span>
          <strong class="aviso-titulo"><?= e($it['titulo']) ?></strong>
          <span class="aviso-resumen"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $it['texto']), 0, 190, '…')) ?></span>
          <small class="texto-suave">
            <?php if ($ev): ?><?= e(fecha_corta($ev['inicio'])) ?><?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?> · <?= e($it['origen']) ?>
            <?php else: ?><?= e($it['origen']) ?> · <?= e(fecha_corta($it['fecha'])) ?><?= !empty($it['destino']) ? ' · ' . e($it['destino']) : '' ?><?= !empty($it['adjuntos']) ? ' · 📎 ' . (int) $it['adjuntos'] . ' archivo' . ($it['adjuntos'] > 1 ? 's' : '') : '' ?><?php endif; ?>
          </small>
        </span>
      </a>
      <?php if ($ev): ?>
        <form method="post" action="<?= url('eventos/' . $ev['id'] . '/suscribir') ?>" class="aviso-accion">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="volver" value="eventos">
          <button type="submit" class="btn <?= $ev['suscrito'] ? '' : 'btn-primario' ?> btn-mini"><?= $ev['suscrito'] ? '✓ En mi calendario' : '+ A mi calendario' ?></button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <div class="card" id="sin-resultados" <?= $items ? 'hidden' : '' ?>><p class="texto-suave" style="margin:0"><?= $items ? 'Nada coincide con tu búsqueda.' : ($visitante ? 'Por ahora no hay avisos públicos.' : 'No hay avisos ni eventos por ahora.') ?></p></div>
</div>

<?php if (!$visitante): ?>
  <details class="card" style="margin-top:8px" <?= $hayFiltro ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-weight:600">Filtrar eventos por carrera, semestre, tema o modalidad</summary>
    <form method="get" action="<?= url('eventos') ?>" class="ux-toolbar" style="margin-top:10px;flex-wrap:wrap">
      <label>Ver<select name="ver"><option value="para_mi" <?= $filtros['ver'] === 'para_mi' ? 'selected' : '' ?>>Para mí</option><option value="todos" <?= $filtros['ver'] === 'todos' ? 'selected' : '' ?>>Todos</option></select></label>
      <label>Carrera<select name="programa"><option value="">Todas</option><?php foreach ($programas as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $filtros['programa'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['siglas']) ?></option><?php endforeach; ?></select></label>
      <label>Semestre<select name="semestre"><option value="">Todos</option><?php for ($i = 1; $i <= Clasificacion::SEMESTRE_MAX; $i++): ?><option value="<?= $i ?>" <?= $filtros['semestre'] === $i ? 'selected' : '' ?>><?= $i ?>.°</option><?php endfor; ?></select></label>
      <label>Tema<select name="tema"><option value="">Todos</option><?php foreach (Clasificacion::TEMAS as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['tema'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Formato<select name="categoria"><option value="">Todos</option><?php foreach ($categorias as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['categoria'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <label>Modalidad<select name="modalidad"><option value="">Todas</option><?php foreach (Clasificacion::MODALIDADES as $k => $n): ?><option value="<?= e($k) ?>" <?= $filtros['modalidad'] === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-mini btn-primario" type="submit">Filtrar</button>
      <?php if ($hayFiltro): ?><a class="btn btn-mini" href="<?= url('eventos') ?>">Quitar filtros</a><?php endif; ?>
    </form>
  </details>
  <p class="texto-suave" style="margin-top:14px;font-size:13px">
    <?php if ($avisosArchivo): ?><a href="<?= url('eventos') ?>">← Volver a los avisos vigentes</a><?php else: ?><a href="<?= url('eventos?avisos=archivo') ?>">Archivo de avisos vencidos</a><?php endif; ?>
    · <a href="<?= url('eventos/biblioteca') ?>">Biblioteca de eventos concluidos</a> · <a href="<?= url('eventos/expediente') ?>">Mi expediente de formación integral</a>
  </p>
<?php endif; ?>

<style>
.avisos-cabecera{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.avisos-filtros{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
.chip-filtro{border:1px solid var(--gris-200,#d6dbe1);background:#fff;border-radius:999px;padding:7px 14px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;gap:6px;align-items:center;color:var(--texto)}
.chip-filtro.activo{background:var(--verde-700,#2c6b4a);border-color:var(--verde-700,#2c6b4a);color:#fff}
.chip-num{font-weight:400;opacity:.75;font-size:12px}
.chip-nuevos{background:#d92d20;color:#fff;border-radius:999px;font-size:11px;padding:1px 7px;font-weight:700}
.aviso-item{display:flex;gap:12px;align-items:center;margin-bottom:12px;padding:0;overflow:hidden}
.aviso-item.no-leido{border-left:5px solid var(--verde-700,#2c6b4a);background:#f6fbf8}
.aviso-enlace{display:flex;gap:14px;align-items:flex-start;flex:1;min-width:0;padding:14px 16px;color:inherit;text-decoration:none}
.aviso-enlace:hover .aviso-titulo{text-decoration:underline}
.aviso-icono{font-size:26px;line-height:1;width:48px;text-align:center;flex:0 0 48px}
.aviso-cuerpo{display:flex;flex-direction:column;gap:4px;min-width:0}
.aviso-titulo{font-size:16px;color:var(--verde-900,#1f4d36)}
.aviso-item.no-leido .aviso-titulo{font-weight:800}
.aviso-resumen{font-size:13.5px;color:var(--gris-700,#334155);line-height:1.45}
.aviso-accion{padding:0 16px 0 0}
.badge-nuevo{background:#fee4e2;color:#b42318;border-color:#fecdca}
.tipo-examenes{background:#e0ecff;color:#1d4ed8;border-color:#bfd5ff}
.tipo-convocatorias{background:#fdf2e9;color:#b54708;border-color:#f9dbaf}
.tipo-eventos{background:#ecfdf3;color:#067647;border-color:#abefc6}
.card .badge{text-transform:none}
.aviso-item[hidden],#sin-resultados[hidden]{display:none!important}
@media (max-width:640px){.aviso-item{flex-wrap:wrap}.aviso-accion{padding:0 16px 14px;flex-basis:100%}.aviso-accion .btn{width:100%}}
</style>
<script>
(() => {
  const chips = document.querySelectorAll('.chip-filtro'), items = document.querySelectorAll('.aviso-item');
  const buscador = document.getElementById('buscar-avisos'), vacio = document.getElementById('sin-resultados');
  let filtro = 'todo';
  const aplicar = () => {
    const q = (buscador.value || '').trim().toLowerCase(); let n = 0;
    items.forEach(it => { const ok = (filtro === 'todo' || it.dataset.filtro === filtro) && (!q || it.dataset.texto.includes(q)); it.hidden = !ok; if (ok) n++; });
    if (items.length) vacio.hidden = n > 0;
  };
  chips.forEach(c => c.addEventListener('click', () => {
    filtro = c.dataset.filtro;
    chips.forEach(x => { x.classList.toggle('activo', x === c); x.setAttribute('aria-pressed', x === c ? 'true' : 'false'); });
    history.replaceState(null, '', filtro === 'todo' ? location.pathname + location.search : '#' + filtro);
    aplicar();
  }));
  buscador.addEventListener('input', aplicar);
  const inicial = location.hash.slice(1);
  const chip = document.querySelector('.chip-filtro[data-filtro="' + inicial + '"]');
  if (chip) chip.click();
})();
</script>
