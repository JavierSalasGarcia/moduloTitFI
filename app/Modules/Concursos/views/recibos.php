<?php use App\Modules\Concursos\Concursos; $base = 'concursos/' . $c['slug']; ?>
<?php include __DIR__ . '/_estilos.php'; ?>
<section class="tarjeta" style="margin-bottom:16px">
  <h2>Verificación de votos</h2>
  <p class="texto-secundario"><a href="<?= url($base) ?>"><?= e($c['titulo']) ?></a>. Cada voto se guarda solo con su recibo y la obra elegida, sin la identidad de quien votó. Escribe tu recibo para comprobar que tu voto se contó por la obra que elegiste.</p>
  <form method="get" action="<?= url($base . '/recibos') ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
    <label>Recibo <input name="recibo" value="<?= e($buscar) ?>" placeholder="CV-XXXXX-XXXXX" maxlength="14" style="text-transform:uppercase"></label>
    <button class="btn btn-primario" type="submit">Verificar</button>
  </form>
  <?php if ($buscar !== ''): ?>
    <?php if ($encontrado): ?><p class="tit-ok">El recibo <strong><?= e($encontrado['recibo']) ?></strong> corresponde a un voto por la obra <strong>N.º <?= (int) $encontrado['numero'] ?> · <?= e($encontrado['titulo']) ?></strong>.</p>
    <?php else: ?><p class="tit-obs">No hay ningún voto con el recibo <?= e($buscar) ?> en este concurso.</p><?php endif; ?>
  <?php endif; ?>
</section>
<?php if ($lista): ?>
<section class="tarjeta">
  <h2>Todos los votos (<?= count($lista) ?>)</h2>
  <p class="texto-secundario">Lista pública al cerrar la votación, ordenada por recibo (no por la hora del voto).</p>
  <div class="tabla-contenedor"><table class="tabla"><thead><tr><th>Recibo</th><th>Obra votada</th></tr></thead><tbody>
    <?php foreach ($lista as $v): ?><tr><td><code><?= e($v['recibo']) ?></code></td><td>N.º <?= (int) $v['numero'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php elseif ($fase === 'votacion'): ?>
  <p class="texto-secundario">La lista completa de votos se publica al cerrar la votación (<?= e(Concursos::fechaHora($c['votacion_fin'])) ?>).</p>
<?php endif; ?>
