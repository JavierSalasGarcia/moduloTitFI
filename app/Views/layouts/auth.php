<?php $titulo = $titulo ?? 'Acceso'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?> · Plataforma FI</title>
  <link rel="stylesheet" href="<?= url('assets/css/app.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= url('assets/css/ux.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/ux.css')) ?>">
</head>
<body class="cuerpo-auth">
  <?= $contenido ?>
  <script src="<?= url('assets/js/ux.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/ux.js')) ?>"></script>
</body>
</html>
