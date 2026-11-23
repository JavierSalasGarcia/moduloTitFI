<?php
/** @var array $filas */
$n = count($filas);
?>
<header class="avisos-cabecera">
  <div>
    <h1 style="margin:0;font-size:22px">Avisos y eventos</h1>
    <p class="texto-suave" style="margin:4px 0 0">Oficios de la Subdirección Académica dirigidos a ti, firmados electrónicamente.</p>
  </div>
</header>
<nav class="grupo-chips" aria-label="Secciones" style="margin:0 0 14px">
  <a class="chip" href="<?= e(url('eventos')) ?>">Avisos y eventos</a>
  <a class="chip estado-en_atencion" href="<?= e(url('eventos/oficios')) ?>" aria-current="page">Oficios<?= $n ? ' (' . $n . ')' : '' ?></a>
</nav>

<?php if (!$filas): ?>
  <div class="card"><p class="texto-suave" style="margin:0">Todavía no hay oficios dirigidos a ti. Cuando la Subdirección Académica firme uno para ti o para un grupo al que perteneces (por ejemplo, «Todos los profesores»), aparecerá aquí.</p></div>
<?php else: ?>
  <label class="sr-only" for="of-filtro">Buscar un oficio</label>
  <input id="of-filtro" type="search" placeholder="Buscar por número o asunto…" style="width:100%;max-width:420px;margin:0 0 10px" autocomplete="off">
  <div class="card sin-relleno tabla-contenedor">
    <table class="tabla" data-no-cards id="of-tabla">
      <thead><tr><th>Fecha</th><th>Número de oficio</th><th>Asunto</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr data-texto="<?= e(mb_strtolower($f['numero_texto'] . ' ' . $f['asunto'])) ?>">
          <td style="white-space:nowrap"><?= $f['fecha'] ? e(date('d/m/Y', strtotime((string) $f['fecha']))) : '—' ?></td>
          <td class="monoespacio" style="white-space:nowrap"><a href="<?= e(url('eventos/oficios/' . (int) $f['id'] . '/pdf')) ?>" target="_blank" rel="noopener"><?= e((string) $f['numero_texto']) ?></a></td>
          <td><?= e((string) $f['asunto']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <script>
    document.getElementById('of-filtro').addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      document.querySelectorAll('#of-tabla tbody tr').forEach(function (tr) { tr.hidden = q !== '' && tr.getAttribute('data-texto').indexOf(q) === -1; });
    });
  </script>
<?php endif; ?>
