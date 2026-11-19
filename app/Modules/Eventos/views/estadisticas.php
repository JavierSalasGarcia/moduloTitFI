<?php use App\Core\Auth; ?>
<p style="margin:0 0 10px"><a href="<?= e(url('eventos')) ?>">← Avisos y eventos</a></p>
<header style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <div><h1 style="margin:0;font-size:22px">Estadísticas de avisos y eventos</h1>
    <p class="texto-suave" style="margin:4px 0 0">Últimos 6 meses. «Personas» cuenta cada cuenta una sola vez; «visitantes» son aperturas sin sesión (un visitante por día, sin guardar quién es).</p></div>
  <a class="btn" href="<?= e(url('eventos/estadisticas/excel')) ?>">⬇ Descargar para Excel</a>
</header>
<div class="buscador" style="max-width:360px;margin-bottom:12px"><?= icono('buscar', 16) ?><input type="search" id="buscar-estad" placeholder="Buscar por título o área…" aria-label="Buscar"></div>
<div class="card" style="padding:0;overflow-x:auto">
  <table class="estad-resumen" id="tabla-estad">
    <thead><tr><th data-c="0">Tipo</th><th data-c="1">Título</th><th data-c="2">Fecha</th><th data-c="3" class="n">Personas</th><th data-c="4" class="n">Visitantes</th><th data-c="5" class="n">Aperturas</th><th data-c="6" class="n">Descargas</th></tr></thead>
    <tbody>
      <?php foreach ($filas as $f): ?>
        <tr data-t="<?= e(mb_strtolower($f['titulo'] . ' ' . $f['depto'])) ?>">
          <td><?= e($f['tipo']) ?></td>
          <td><a href="<?= e(url($f['url'])) ?>"><?= e($f['titulo']) ?></a><br><small class="texto-suave"><?= e($f['depto']) ?><?= $f['borrador'] ? ' · borrador' : '' ?></small></td>
          <td data-v="<?= e(substr((string) $f['fecha'], 0, 10)) ?>"><?= e(date('d/m/Y', strtotime((string) $f['fecha']))) ?></td>
          <td class="n"><?= (int) $f['personas'] ?></td><td class="n"><?= (int) $f['visitantes'] ?></td><td class="n"><?= (int) $f['aperturas'] ?></td><td class="n"><?= (int) $f['descargas'] ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$filas): ?><tr><td colspan="7" class="texto-suave">Aún no hay avisos ni eventos en este periodo.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($gestor): ?>
  <section class="card" style="margin-top:18px;padding:18px">
    <h2 style="font-size:16px;margin:0 0 6px">Quién puede ver las estadísticas</h2>
    <p class="texto-suave" style="margin:0 0 10px">Siempre: administración de la plataforma, Subdirección Académica y coordinadores. Además, estas personas:</p>
    <?php if (!$personas): ?><p class="texto-suave">Nadie más por ahora.</p><?php endif; ?>
    <ul style="list-style:none;padding:0;margin:0 0 10px">
      <?php foreach ($personas as $p): ?>
        <li style="display:flex;gap:10px;align-items:center;margin:4px 0"><span><?= e($p['nombre']) ?> · <span class="texto-suave"><?= e($p['email']) ?></span></span>
          <form method="post" action="<?= e(url('eventos/estadisticas/personas/' . (int) $p['id'] . '/quitar')) ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini btn-peligro" type="submit">Quitar</button></form></li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('eventos/estadisticas/personas')) ?>" class="ux-toolbar" style="flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label>Correo de la cuenta <input type="email" name="email" required maxlength="120" placeholder="usuario@uaemex.mx"></label>
      <button class="btn btn-mini btn-primario" type="submit">Dar acceso a estadísticas</button>
    </form>
  </section>
<?php endif; ?>
<style>
.estad-resumen{width:100%;border-collapse:collapse;font-size:14px}
.estad-resumen th,.estad-resumen td{padding:9px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
.estad-resumen th{background:#f6fbf8;cursor:pointer;white-space:nowrap}.estad-resumen .n{text-align:right;font-variant-numeric:tabular-nums}
</style>
<script>
(() => {
  const t = document.getElementById('tabla-estad'), b = t.tBodies[0];
  document.getElementById('buscar-estad').addEventListener('input', e => { const q = e.target.value.trim().toLowerCase(); [...b.rows].forEach(r => r.hidden = !!q && !(r.dataset.t || '').includes(q)); });
  t.querySelectorAll('th').forEach(th => { let asc = false; th.addEventListener('click', () => {
    const c = +th.dataset.c; asc = !asc; const val = r => { const td = r.cells[c]; return td.dataset.v || (c >= 3 ? +td.textContent : td.textContent.trim().toLowerCase()); };
    [...b.rows].sort((x, y) => (val(x) > val(y) ? 1 : val(x) < val(y) ? -1 : 0) * (asc ? 1 : -1)).forEach(r => b.appendChild(r)); }); });
})();
</script>
