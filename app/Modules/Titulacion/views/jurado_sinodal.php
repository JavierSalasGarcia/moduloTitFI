<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$base ??= 'titulacion/sinodal/t/' . $token;
include __DIR__ . '/_banner_enlace.php';
?>
<section class="tarjeta" style="max-width:720px;margin:20px auto">
  <h2>Oficio de citación · <?= e($a['tipo'] === 'previo' ? 'examen previo' : ($a['tipo'] === 'definitivo' ? 'examen definitivo' : 'toma de protesta')) ?></h2>
  <p>Fuiste <?= e(\App\Core\Persona::f(\App\Core\Persona::completar(['nombre' => $s['nombre'], 'genero' => $s['genero']], (string) $s['email']), 'designado', 'designada')) ?> sinodal <strong><?= e($s['calidad'] === 'suplente' ? 'suplente' : \App\Core\Persona::f($s, 'propietario', 'propietaria')) ?></strong> para el <?= e($examen) ?> de <strong><?= e($alumno) ?></strong>.</p>
  <p><strong><?= e(\App\Modules\Titulacion\Services\Oficios::fecha($a['fecha'])) ?></strong>, <?= e($a['hora']) ?> h · <?= e($a['lugar']) ?></p>
  <p style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primario" href="<?= url($base . '/oficio') ?>" target="_blank" rel="noopener">Ver mi oficio (PDF)</a>
    <?php if ($trabajo): ?><a class="btn" href="<?= url($base . '/trabajo') ?>" target="_blank" rel="noopener">Descargar el trabajo escrito (PDF)</a><?php endif; ?>
  </p>
  <?php if ($s['estado'] === 'acuse'): ?>
    <p class="tit-ok">Firmaste de recibido el <?= e(fecha_corta((string) $s['acuse_en'])) ?>. El examen aparece en tu calendario de Traza FI.</p>
    <p><a href="<?= url('calendario') ?>">Abrir mi calendario</a></p>
  <?php elseif ($puedeFirmar): ?>
    <form method="post" action="<?= url($base . '/firmar') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <button class="btn btn-primario" type="submit">Firmar de recibido</button>
    </form>
  <?php else: ?>
    <p class="texto-secundario">Cuando tengas sesión con <b><?= e($s['email']) ?></b> aparecerá aquí el botón para firmar de recibido.</p>
  <?php endif; ?>
</section>
