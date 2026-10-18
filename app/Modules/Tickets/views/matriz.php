<?php
use App\Core\Auth;

/** Tarjeta de ticket con mini-formulario para moverlo de cuadrante. */
$tarjeta = function (array $t, array $cuadrantes): void { ?>
  <article class="tarjeta-ticket">
    <p>
      <a href="<?= url('tickets/' . $t['id']) ?>"><strong><?= e($t['asunto']) ?></strong></a><br>
      <small class="texto-suave monoespacio"><?= e($t['folio']) ?></small>
      <small class="texto-suave">· <?= $t['es_mio'] ? 'genero · atiende ' . e($t['destino']) : 'atiendo · de ' . e($t['solicitante']) ?></small>
    </p>
    <div class="grupo-chips">
      <span class="chip estado-<?= e($t['estado']) ?>"><?= e(str_replace('_', ' ', $t['estado'])) ?></span>
      <span class="chip prioridad-<?= e($t['prioridad']) ?>"><?= e($t['prioridad']) ?></span>
    </div>
    <form method="post" action="<?= url('tickets/' . $t['id'] . '/clasificar') ?>" class="mover-cuadrante">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <select name="cuadrante" aria-label="Mover a cuadrante">
        <option value="">— sin clasificar —</option>
        <?php foreach ($cuadrantes as $c): ?>
          <option value="<?= e($c) ?>" <?= ($t['mi_cuadrante'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn">Mover</button>
    </form>
  </article>
<?php };

$titulos = [
    'hacer'      => ['Hacer', 'Urgente e importante: atiéndelo ya'],
    'planificar' => ['Planificar', 'Importante, no urgente: agéndalo'],
    'delegar'    => ['Delegar', 'Urgente, no importante: pásalo'],
    'eliminar'   => ['Eliminar', 'Ni urgente ni importante'],
];
?>
<div class="barra-acciones">
  <nav class="pestanas" aria-label="Bandejas">
    <a class="pestana" href="<?= url('tickets') ?>">Los que genero</a>
    <?php if ($esStaff): ?>
      <a class="pestana" href="<?= url('tickets?bandeja=recibidos') ?>">Los que atiendo</a>
    <?php endif; ?>
    <a class="pestana activa" href="<?= url('tickets?vista=matriz') ?>">Matriz de Eisenhower</a>
  </nav>
  <a class="btn btn-primario" href="<?= url('tickets/nuevo') ?>">Nuevo ticket</a>
</div>

<p class="texto-suave">Tu clasificación es personal: ordena tus tickets activos sin afectar cómo los ven los demás.</p>

<?php if ($porCuadrante['']): ?>
  <section class="card cuadrante cuadrante-sin">
    <h3 class="subtitulo">Sin clasificar (<?= count($porCuadrante['']) ?>)</h3>
    <div class="pila-tickets">
      <?php foreach ($porCuadrante[''] as $t) { $tarjeta($t, $cuadrantes); } ?>
    </div>
  </section>
<?php endif; ?>

<div class="matriz-eisenhower">
  <?php foreach (['hacer', 'planificar', 'delegar', 'eliminar'] as $c): ?>
    <section class="card cuadrante cuadrante-<?= $c ?>">
      <h3 class="subtitulo"><?= $titulos[$c][0] ?> <small class="texto-suave">· <?= $titulos[$c][1] ?></small></h3>
      <?php if (!$porCuadrante[$c]): ?>
        <p class="texto-suave">Vacío.</p>
      <?php else: ?>
        <div class="pila-tickets">
          <?php foreach ($porCuadrante[$c] as $t) { $tarjeta($t, $cuadrantes); } ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
