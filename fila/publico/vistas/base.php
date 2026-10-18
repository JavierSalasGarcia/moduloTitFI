<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= u_escapar($titulo) ?><?= $dep ? ' · ' . u_escapar($dep['nombre']) : '' ?></title>
<link rel="stylesheet" href="<?= ruta('estatico/estilo.css') ?>?v=<?= filemtime(__DIR__.'/../estatico/estilo.css') ?>">
<?php if ($desnudo): ?><link rel="stylesheet" href="<?= ruta('estatico/kiosco.css') ?>?v=<?= filemtime(__DIR__.'/../estatico/kiosco.css') ?>"><?php endif; ?>
</head>
<body class="<?= $desnudo ? 'kiosco' : '' ?>">
<?php if (!$desnudo): ?>
<header class="barra">
  <a class="marca" href="<?= $dep ? ruta($dep['clave']) : ruta() ?>">
    <?= $dep ? u_escapar($dep['nombre']) : 'Fila' ?>
  </a>
  <?php if (!empty(config()['integrado'])): ?>
  <?php if (!$ocultarPlan): ?>
  <a href="<?= u_escapar(url('fila/plan')) ?>">Plan de Desarrollo FI</a>
  <?php endif; ?>
  <a href="<?= u_escapar(url(\App\Core\Auth::usuario() ? 'fila' : 'login')) ?>"><?= \App\Core\Auth::usuario() ? 'Mis turnos · Traza FI' : 'Entrar a Traza FI (opcional)' ?></a>
  <?php endif; ?>
  <?php if ($dep): ?>
  <nav>
    <a href="<?= ruta($dep['clave'].'/turno') ?>">Tomar turno</a>
    <a href="<?= ruta($dep['clave'] . '/fila') ?>">Ver la fila</a>
  </nav>
  <?php endif; ?>
</header>
<?php endif; ?>
<main class="<?= $desnudo ? 'pantalla' : '' ?>"><?= $contenido ?></main>
<?php if (!$desnudo && $dep): ?>
<footer><?= u_escapar($dep['titular']) ?> · Facultad de Ingeniería</footer>
<?php endif; ?>
</body>
</html>
