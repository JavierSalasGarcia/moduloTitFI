<?php
$titulo = $titulo ?? 'Plan de Desarrollo FI';
$usuarioPlan = \App\Core\Auth::usuario();
$menuPlan = $usuarioPlan
  ? \App\Core\InterfaceUi::menu(\App\Core\ModuleManager::menu(array_merge([$usuarioPlan['rol']], array_column($usuarioPlan['roles_extra'] ?? [], 'rol'))))
  : [];
$basePlan = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$rutaPlanActual = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', strlen($basePlan)), '/');
$esItemPlanActivo = static function (array $item) use ($rutaPlanActual): bool {
  if (!empty($item['dinamico'])) {
    return $item['ruta'] === $rutaPlanActual;
  }
  if ($item['ruta'] === '') {
    return $rutaPlanActual === '' || $rutaPlanActual === 'consulta';
  }
  return $rutaPlanActual === $item['ruta'] || str_starts_with($rutaPlanActual, $item['ruta'] . '/');
};
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · Facultad de Ingeniería</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/plan.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/plan.css')) ) ?>">
  <script src="<?= e(url('assets/js/plan-chart-editor.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/plan-chart-editor.js')) ) ?>" defer></script>
</head>
<body>
  <a class="plan-saltar" href="#contenido">Saltar al contenido</a>
  <header class="plan-barra">
    <?php if ($usuarioPlan): ?>
      <button type="button" class="plan-menu-movil" id="plan-menu-movil" aria-label="Abrir menú" aria-controls="plan-menu-lateral" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    <?php endif; ?>
    <a class="plan-marca" href="<?= e(url('plan')) ?>"><span class="plan-simbolo">FI</span><span>Facultad de Ingeniería <small>Plan de Desarrollo 2026–2030</small></span></a>
    <nav aria-label="Menú principal">
      <a href="<?= e(url('')) ?>">Inicio</a>
      <a href="<?= e(url('fila/publico')) ?>">Fila pública</a>
      <a href="<?= e(url('plan')) ?>" aria-current="page">Plan de Desarrollo</a>
      <?php if ($usuarioPlan): ?>
        <form method="post" action="<?= e(url('logout')) ?>" style="display:inline;margin:0">
          <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
          <button type="submit" class="enlace" style="background:none;border:0;cursor:pointer;padding:0">Salir</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('login')) ?>">Entrar a Traza FI</a>
      <?php endif; ?>
    </nav>
  </header>
  <div class="plan-aplicacion<?= $usuarioPlan ? ' plan-aplicacion-con-menu' : '' ?>">
    <?php if ($usuarioPlan): ?>
      <button class="plan-menu-scrim" type="button" aria-label="Cerrar menú" hidden></button>
      <nav class="plan-menu-lateral" id="plan-menu-lateral" aria-label="Módulos de la plataforma">
        <?php foreach ($menuPlan as $nombreSeccion => $seccion): ?>
          <?php if ($nombreSeccion === 'Fila'): ?>
            <div class="plan-menu-seccion">
              <?php foreach ($seccion['items'] as $item): ?>
                <?php $itemActivo = $esItemPlanActivo($item); ?>
                <a class="plan-menu-item plan-menu-item-raiz<?= $itemActivo ? ' activo' : '' ?>" <?= $itemActivo ? 'aria-current="page"' : '' ?> href="<?= e(url($item['ruta'])) ?>"><?= e($item['titulo']) ?></a>
              <?php endforeach; ?>
            </div>
            <?php continue; ?>
          <?php endif; ?>
          <?php
            $seccionActiva = false;
            foreach ($seccion['items'] as $itemSeccion) {
              if ($esItemPlanActivo($itemSeccion)) {
                $seccionActiva = true;
                break;
              }
            }
          ?>
          <div class="plan-menu-seccion<?= $seccionActiva ? '' : ' colapsada' ?>">
            <button type="button" class="plan-menu-seccion-titulo" aria-expanded="<?= $seccionActiva ? 'true' : 'false' ?>">
              <span class="plan-menu-flecha" aria-hidden="true"></span>
              <span><?= e($nombreSeccion) ?></span>
            </button>
            <?php foreach ($seccion['items'] as $item): ?>
              <?php $itemActivo = $esItemPlanActivo($item); ?>
              <a class="plan-menu-item<?= $itemActivo ? ' activo' : '' ?>" <?= $itemActivo ? 'aria-current="page"' : '' ?> href="<?= e(url($item['ruta'])) ?>"><?= e($item['titulo']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <div class="plan-menu-pie"><span><?= e($usuarioPlan['departamento'] ?? '') ?></span></div>
      </nav>
    <?php endif; ?>
    <main class="plan-contenido" id="contenido" tabindex="-1"><?= $contenido ?></main>
  </div>
  <footer class="plan-pie">Facultad de Ingeniería · UAEMéx · Fuentes y métodos visibles junto a cada indicador.</footer>
  <script src="<?= e(url('assets/js/plan.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/plan.js')) ) ?>" defer></script>
</body>
</html>
