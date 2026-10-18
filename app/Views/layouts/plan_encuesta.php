<?php $titulo = $titulo ?? 'Cuestionario FI 360'; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Facultad de Ingeniería</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/plan.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/plan.css'))) ?>">
</head>
<body class="plan-encuesta-aislada">
  <main id="contenido"><?= $contenido ?></main>
</body>
</html>
