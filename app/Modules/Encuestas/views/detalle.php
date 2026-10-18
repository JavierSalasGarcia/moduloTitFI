<?php
use App\Core\Auth;
$e = $encuesta;
$tokenNuevo = $_SESSION['token_encuesta_' . $e['id']] ?? null;
unset($_SESSION['token_encuesta_' . $e['id']]);
$totalVotos = array_sum(array_column($opciones, 'votos'));
?>
<p><a class="enlace" href="<?= url('encuestas') ?>"><?= icono('flecha-izq', 14) ?> Volver a encuestas</a></p>

<?php if ($tokenNuevo): ?>
  <div class="flash flash-exito" data-encuesta="<?= (int) $e['id'] ?>">
    Tu voto quedó registrado de forma anónima. Guarda tu token de verificación:
    <strong class="monoespacio"><?= e($tokenNuevo) ?></strong> <button type="button" class="btn" data-copy="<?= e($tokenNuevo) ?>">Copiar comprobante</button> — con él puedes ubicar tu voto en el
    <a href="<?= url('encuestas/' . $e['id'] . '/tablero') ?>">tablero público</a> sin revelar tu identidad.
  </div>
  <script>localStorage.setItem('fi_voto_<?= (int) $e['id'] ?>', <?= json_encode($tokenNuevo) ?>);</script>
<?php endif; ?>

<div class="rejilla-detalle">
  <section class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($e['titulo']) ?></h2>
        <small class="texto-suave">Organiza: <?= e($e['creador']) ?> · <?= e(fecha_corta($e['creado_en'])) ?></small>
      </div>
      <span class="chip estado-<?= e($e['estado']) ?>"><?= e($e['estado']) ?></span>
    </header>

    <?php if ($e['descripcion']): ?>
      <p class="descripcion-solicitud"><?= nl2br(e($e['descripcion'])) ?></p>
    <?php endif; ?>

    <?php if ($e['estado'] === 'abierta' && !$yaVoto): ?>
      <h3 class="subtitulo">Emite tu voto</h3>
      <form method="post" action="<?= url('encuestas/' . $e['id'] . '/votar') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <?php foreach ($opciones as $o): ?>
          <label class="opcion-voto">
            <input type="radio" name="opcion" value="<?= (int) $o['id'] ?>" required style="width:auto;margin:0">
            <span><?= e($o['texto']) ?></span>
          </label>
        <?php endforeach; ?>
        <div class="acciones-formulario">
          <button type="submit" class="btn btn-primario">Votar de forma anónima</button>
        </div>
      </form>
    <?php else: ?>
      <h3 class="subtitulo">Resultados (<?= $totalVotos ?> votos)</h3>
      <?php foreach ($opciones as $o): $pct = $totalVotos ? round($o['votos'] * 100 / $totalVotos) : 0; ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;gap:10px">
            <span><?= e($o['texto']) ?></span>
            <strong><?= (int) $o['votos'] ?> · <?= $pct ?>%</strong>
          </div>
          <div class="progreso"><div class="progreso-barra" style="width:<?= $pct ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <?php if ($yaVoto && $e['estado'] === 'abierta'): ?>
        <p class="texto-suave">Ya emitiste tu voto en esta encuesta.</p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <aside class="columna-lateral">
    <section class="card">
      <header class="tarjeta-cabecera"><h2>Transparencia</h2></header>
      <p class="texto-suave">Todos los votos, identificados solo por su token, se publican en un tablero
      accesible <strong>sin iniciar sesión</strong>. Nadie puede vincular un token con una persona.</p>
      <a class="btn btn-oro btn-bloque" href="<?= url('encuestas/' . $e['id'] . '/tablero') ?>">Ver tablero público</a>
    </section>
    <?php if ($esDueno && $e['estado'] === 'abierta'): ?>
      <section class="card">
        <header class="tarjeta-cabecera"><h2>Administrar</h2></header>
        <form method="post" action="<?= url('encuestas/' . $e['id'] . '/cerrar') ?>" data-confirm="¿Cerrar la votación? Ya no se admitirán votos.">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button type="submit" class="btn btn-peligro btn-bloque">Cerrar votación</button>
        </form>
      </section>
    <?php endif; ?>
  </aside>
</div>
