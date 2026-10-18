<?php
use App\Core\Auth;
$totalVotos = array_sum(array_column($opciones, 'votos'));
$urlBase = 'encuestas/' . (int) $encuesta['id'] . '/tablero';
$qs = $buscar !== '' ? '&token=' . urlencode($buscar) : '';
?>
<header class="barra-superior no-imprimir">
  <span class="logo-barra" aria-hidden="true">FI</span>
  <div class="marca"><strong>Plataforma FI</strong> <span class="marca-desc">Tablero público de votación</span></div>
  <div class="sesion">
    <?php if (Auth::usuario()): ?>
      <a href="<?= url('encuestas') ?>">Encuestas</a>
      <a href="<?= url('') ?>">Ir a la plataforma</a>
    <?php else: ?>
      <a href="<?= url('login') ?>">Iniciar sesión</a>
    <?php endif; ?>
  </div>
</header>

<div class="contenido" style="max-width:960px;margin:0 auto" data-encuesta="<?= (int) $encuesta['id'] ?>">
  <div class="card" style="border-top:4px solid var(--oro)">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($encuesta['titulo']) ?></h2>
        <small class="texto-suave">Tablero de verificación pública · estado: <?= e($encuesta['estado']) ?> · <?= $totalVotos ?> votos emitidos</small>
      </div>
      <div class="grupo-chips no-imprimir">
        <a class="btn btn-mini btn-primario" href="<?= url($urlBase . '/csv') ?>">⬇ Descargar CSV</a>
        <button type="button" class="btn btn-mini" onclick="window.print()">🖨 Imprimir / PDF</button>
      </div>
    </header>

    <p class="texto-suave">Cada fila es un voto anónimo identificado por su token. Si votaste, busca tu token para
    verificar que tu voto se contó tal como lo emitiste. Los tokens no están vinculados a ninguna identidad.</p>

    <h3 class="subtitulo">Resultados</h3>
    <?php foreach ($opciones as $o): $pct = $totalVotos ? round($o['votos'] * 100 / $totalVotos) : 0; ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;gap:10px">
          <span><?= e($o['texto']) ?></span>
          <strong><?= (int) $o['votos'] ?> · <?= $pct ?>%</strong>
        </div>
        <div class="progreso"><div class="progreso-barra" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card sin-relleno">
    <div class="barra-acciones no-imprimir" style="padding:14px 16px 0;margin-bottom:10px">
      <h3 class="subtitulo" style="margin:0">Registro de votos<?= $buscar !== '' ? ' (filtro: ' . e($buscar) . ' · ' . $total . ' coincidencias)' : ' (' . $total . ')' ?></h3>
      <form method="get" action="<?= url($urlBase) ?>" class="buscador">
        <?= icono('buscar', 15) ?>
        <input type="search" name="token" value="<?= e($buscar) ?>" placeholder="Buscar mi token (V-…)" style="min-width:150px">
        <button type="submit" class="btn btn-mini">Buscar</button>
        <?php if ($buscar !== ''): ?><a class="enlace" href="<?= url($urlBase) ?>">limpiar</a><?php endif; ?>
      </form>
    </div>

    <div class="tabla-contenedor">
      <table class="tabla">
        <thead>
          <tr><th style="width:70px">#</th><th style="width:160px">Token de voto</th><th>Opción votada</th></tr>
        </thead>
        <tbody>
          <?php if (!$votos): ?>
            <tr><td colspan="3" class="texto-suave">Sin votos<?= $buscar !== '' ? ' que coincidan con el filtro' : ' todavía' ?>.</td></tr>
          <?php endif; ?>
          <?php $base = ($pagina - 1) * $porPagina; foreach ($votos as $i => $v): ?>
            <tr class="token-voto" data-token="<?= e($v['token']) ?>">
              <td class="texto-suave"><?= $base + $i + 1 ?></td>
              <td class="monoespacio"><strong><?= e($v['token']) ?></strong></td>
              <td><?= e($v['texto']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($paginas > 1): ?>
      <div class="barra-acciones no-imprimir" style="padding:12px 16px">
        <span class="texto-suave">Página <?= $pagina ?> de <?= $paginas ?> · <?= $porPagina ?> votos por página</span>
        <div class="grupo-chips">
          <?php if ($pagina > 1): ?>
            <a class="btn btn-mini" href="<?= url($urlBase . '?p=' . ($pagina - 1) . $qs) ?>">← Anterior</a>
          <?php endif; ?>
          <?php if ($pagina < $paginas): ?>
            <a class="btn btn-mini" href="<?= url($urlBase . '?p=' . ($pagina + 1) . $qs) ?>">Siguiente →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <p class="auth-pie no-imprimir">Plataforma FI · Facultad de Ingeniería UAEMEX · La exportación CSV contiene el registro íntegro para auditoría.</p>
</div>

<style>
  /* Resaltar el voto propio (token guardado en este navegador) */
  tr.token-voto.mio td { background: var(--verde-050); box-shadow: inset 3px 0 0 var(--verde-700); }
  @media print {
    .no-imprimir { display: none !important; }
    .contenido { padding: 0; max-width: none; }
    .card { border: none; }
    .progreso { border: 1px solid #999; }
  }
</style>
<script src="<?= url('assets/js/app.js') ?>"></script>
