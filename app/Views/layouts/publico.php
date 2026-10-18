<?php
$titulo = $titulo ?? 'Titulación';
$flash = flash_leer();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title><?= e($titulo) ?> · Traza FI</title>
  <link rel="stylesheet" href="<?= url('assets/css/app.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= url('assets/css/ux.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/ux.css')) ?>">
</head>
<body class="ux-app" style="display:block">
  <header class="barra-superior" style="position:static">
    <span class="logo-barra" style="background:url('<?= url('assets/img/logo.png') ?>') no-repeat center center;background-size:cover;border-radius:50%;font-size:0;width:44px;height:44px;display:inline-block">Traza FI</span>
    <span class="marca"><strong>Traza FI</strong> <span class="marca-desc">Facultad de Ingeniería · UAEMEX · Titulación</span></span>
  </header>
  <main id="contenido-principal" class="contenido" style="max-width:1040px;margin:0 auto;padding:16px">
    <?php if ($flash): ?><p role="status" class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></p><?php endif; ?>
    <?= $contenido ?>
  </main>
</body>
</html>
