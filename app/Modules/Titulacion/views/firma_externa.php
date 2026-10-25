<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
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
  <?php $rol = mb_strtolower(\App\Modules\Titulacion\Services\FirmasExternas::etiquetaRol((string) $f['rol'])); ?>
  <h2><?= e($asunto) ?> · <?= e($folio) ?></h2>
  <p>Se te pide firmar, como <?= e(\App\Core\Persona::f(\App\Core\Persona::completar(['nombre' => $f['nombre'], 'genero' => $f['genero']], (string) $f['email']), $rol, $rol . 'a')) ?>, <?= e($que) ?> de <strong><?= e($alumno) ?></strong>:</p>
  <p style="font-style:italic">«<?= e($tema) ?>»</p>
  <p><a class="btn btn-primario" href="<?= url(str_contains($base, '/t/') ? $base . '/documento' : \App\Modules\Titulacion\Services\Archivos::url('firma_mia', (int) $f['id'])) ?>" target="_blank" rel="noopener">Leer el documento (PDF)</a>
    <?php if (\App\Core\Auth::usuario()): ?><a class="btn" href="<?= url('titulacion/ruta/' . (int) $tramiteId) ?>">Ver la ruta del trámite</a><?php endif; ?></p>
  <?php if ($f['estado'] === 'firmada'): ?>
    <p class="tit-ok">Firmaste este documento el <?= e(fecha_corta((string) $f['firmada_en'])) ?>. No hay nada más que hacer.</p>
  <?php elseif ($puedeFirmar): ?>
    <form method="post" action="<?= url($base . '/firmar') ?>">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <p class="texto-secundario">Al firmar se estampa tu sello electrónico en el documento del expediente.</p>
      <button class="btn btn-primario" type="submit">Firmar</button>
    </form>
  <?php else: ?>
    <p class="texto-secundario">Cuando tengas sesión con <b><?= e($f['email']) ?></b> aparecerá aquí el botón para firmar.</p>
  <?php endif; ?>
</section>
