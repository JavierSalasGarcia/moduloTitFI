<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$base = 'titulacion/firma/t/' . $token;
?>
<?php if ($banner): ?>
  <div class="tit-banner" role="status">
    <?php if ($banner['tipo'] === 'registro'): ?>
      <strong>Estás usando tu enlace personal. Para firmar necesitas registrarte.</strong>
      Puedes leer el protocolo ahora. Para firmar, crea tu cuenta con <b><?= e($banner['email']) ?></b>.
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('registro') ?>">Crear mi cuenta</a> <a class="btn btn-mini" href="<?= url('login') ?>">Ya tengo cuenta</a></div>
    <?php elseif ($banner['tipo'] === 'login'): ?>
      <strong>Ya existe una cuenta con tu correo. Inicia sesión para firmar.</strong>
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('login') ?>">Iniciar sesión</a></div>
    <?php elseif ($banner['tipo'] === 'otra'): ?>
      <strong>Tu sesión es de otra cuenta.</strong> Esta firma se pidió a <b><?= e($banner['email']) ?></b>; inicia sesión con ese correo para firmar.
    <?php else: ?>
      <strong>Verifica tu correo para firmar.</strong> <a href="<?= url('cuenta/verificar') ?>">Verificar ahora</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
<section class="tarjeta" style="max-width:720px;margin:20px auto">
  <h2>Firma de protocolo · <?= e($folio) ?></h2>
  <p><strong><?= e($alumno) ?></strong> te pide firmar, como <?= e(\App\Core\Persona::f(\App\Core\Persona::completar(['nombre' => $f['nombre'], 'genero' => $f['genero']], (string) $f['email']), $f['rol'], $f['rol'] . 'a')) ?>, su protocolo:</p>
  <p style="font-style:italic">«<?= e($tema) ?>»</p>
  <p><a class="btn btn-primario" href="<?= url($base . '/documento') ?>" target="_blank" rel="noopener">Leer el protocolo (PDF)</a></p>
  <?php if ($f['estado'] === 'firmada'): ?>
    <p class="tit-ok">Firmaste este protocolo el <?= e(fecha_corta((string) $f['firmada_en'])) ?>. No hay nada más que hacer.</p>
  <?php elseif ($puedeFirmar): ?>
    <form method="post" action="<?= url($base . '/firmar') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <p class="texto-secundario">Al firmar das tu visto bueno al protocolo; se estampa tu sello electrónico en el documento del expediente.</p>
      <button class="btn btn-primario" type="submit">Firmar protocolo</button>
    </form>
  <?php else: ?>
    <p class="texto-secundario">Cuando tengas sesión con <b><?= e($f['email']) ?></b> aparecerá aquí el botón para firmar.</p>
  <?php endif; ?>
</section>
