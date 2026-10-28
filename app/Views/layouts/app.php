<?php
use App\Core\Auth;
use App\Core\ModuleManager;

$usuario = Auth::usuario();
// Recordatorios de los eventos del calendario personal (sin tareas programadas: se generan al usar la plataforma).
if (class_exists(\App\Modules\Eventos\Recordatorios::class)) {
    \App\Modules\Eventos\Recordatorios::generar();
}
$secciones = \App\Core\InterfaceUi::menu(ModuleManager::menu(array_merge([$usuario['rol']], array_column($usuario['roles_extra'] ?? [], 'rol'))));
$flash   = flash_leer();
$moduloActivo = $moduloActivo ?? '';

// Ruta actual (para resaltar entradas dinámicas como recursos/3)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$rutaActual = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', strlen($base)), '/');
$esItemActivo = function (array $item) use ($moduloActivo, $rutaActual): bool {
    if (!empty($item['dinamico'])) {
        return $item['ruta'] === $rutaActual;
    }
    if ($item['ruta'] === '') return $rutaActual === '' || $rutaActual === 'consulta';
    if ($item['ruta'] === 'ajustes') return $rutaActual === 'ajustes';
    if ($item['ruta'] === 'eventos' && str_starts_with($rutaActual, 'eventos/biblioteca')) return false;
    return $rutaActual === $item['ruta'] || str_starts_with($rutaActual, $item['ruta'] . '/');
};
$titulo  = $titulo ?? 'Traza FI';
$rolEtiqueta = ['admin' => 'Administración', 'titular' => 'Titular', 'operativo' => 'Operativo', 'coordinador' => 'Coordinador', 'profesor' => 'Profesor', 'alumno' => 'Alumno'][$usuario['rol']] ?? $usuario['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?> · Traza FI</title>
  <link rel="stylesheet" href="<?= url('assets/css/app.css?v=' . (string) filemtime(dirname(__DIR__, 3) . '/public/assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= url('assets/css/ux.css?v=' . (string) filemtime(dirname(__DIR__, 3) . '/public/assets/css/ux.css')) ?>">
</head>
<body class="ux-app <?= !empty($portalMinimal) ? 'portal-minimal' : '' ?>" data-user="<?= (int) $usuario['id'] ?>" data-base="<?= e(url('')) ?>" data-module="<?= e($moduloActivo) ?>">
  <a class="skip-link" href="#contenido-principal">Saltar al contenido</a>
  <header class="barra-superior">
    <button type="button" class="btn-menu-movil" id="btn-menu-movil" aria-label="Abrir menú" aria-controls="menu-lateral" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <a href="<?= url('') ?>" class="logo-barra" aria-label="Inicio" style="background: url('<?= url('assets/img/logo.png') ?>') no-repeat center center; background-size: cover; border-radius: 50%; font-size: 0; width: 44px; height: 44px; display: inline-block;">Traza FI</a>
    <a href="<?= url('') ?>" class="marca" aria-label="Traza FI · Inicio"><strong>Traza FI</strong> <span class="marca-desc">Facultad de Ingeniería · UAEMEX</span></a>
    <div class="sesion">
      <span class="sesion-nombre">
        <?= e($usuario['nombre']) ?> · <?= e($rolEtiqueta) ?><?= $usuario['programa_siglas'] ? ' (' . e($usuario['programa_siglas']) . ')' : '' ?>
      </span>
      <form method="post" action="<?= url('logout') ?>" style="margin:0">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <button class="sesion-salir" type="submit" aria-label="Salir" style="background:none;border:0;cursor:pointer">
          <?= !empty($portalMinimal) ? icono('salir', 18) : 'Salir' ?>
        </button>
      </form>
    </div>
  </header>
  <?php if (isset($_SESSION['impersonador'])): ?>
    <div class="cuenta-franja" role="status">Actúas como <?= e($usuario['nombre']) ?> · Administrador: <?= e($_SESSION['impersonador']['nombre']) ?>
      <form method="post" action="<?= url('cuenta/volver') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn btn-mini">Volver a mi cuenta</button></form>
    </div>
  <?php elseif (\App\Core\AccessControl::pending()): ?>
    <div class="cuenta-franja" role="status">Modo lectura · <a href="<?= url('cuenta/verificar') ?>">Verifica tu correo para participar</a></div>
  <?php endif; ?>

  <div class="contenedor-app">
    <button class="menu-scrim" type="button" aria-label="Cerrar menú" hidden></button>
    <nav class="menu-lateral" id="menu-lateral" aria-label="Menú principal">
      <?php foreach ($secciones as $nombreSeccion => $seccion): ?>
        <?php if ($nombreSeccion === 'Fila'): ?>
          <div class="menu-seccion">
            <?php foreach ($seccion['items'] as $item): ?>
              <a class="menu-item<?= $esItemActivo($item) ? ' activo' : '' ?>" <?= $esItemActivo($item) ? 'aria-current="page"' : '' ?> href="<?= url($item['ruta']) ?>"><?= e($item['titulo']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php continue; ?>
        <?php endif; ?>
        <?php
          $activa = false;
          foreach ($seccion['items'] as $it) {
              if ($esItemActivo($it)) { $activa = true; break; }
          }
        ?>
        <div class="menu-seccion<?= $activa ? '' : ' colapsada' ?>">
          <button type="button" class="menu-seccion-titulo" aria-expanded="<?= $activa ? 'true' : 'false' ?>">
            <span class="menu-flecha" aria-hidden="true"></span>
            <span><?= e($nombreSeccion) ?></span>
          </button>
          <?php foreach ($seccion['items'] as $item): ?>
            <?php
              $badgeContent = '';
              if ($item['clave'] === 'reuniones') {
                  $notifCount = (int) \App\Core\Database::valor(
                      'SELECT COUNT(*) FROM reuniones r
                         JOIN reuniones_participantes rp ON rp.reunion_id = r.id AND rp.usuario_id = ?
                        WHERE rp.estado_invitacion <> \'revocada\'
                          AND (rp.respondio_en IS NULL OR r.actualizado_en > rp.respondio_en)',
                      [$usuario['id']]
                  );
                  if ($notifCount > 0) {
                      $badgeContent = ' <span style="background: var(--error); color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold; margin-left: 6px; display: inline-block; min-width: 18px; text-align: center;">' . $notifCount . '</span>';
                  }
              } elseif ($item['clave'] === 'tickets') {
                  $notifCount = 0;
                  if ($usuario['rol'] === 'alumno' || $usuario['rol'] === 'profesor') {
                      $notifCount = (int) \App\Core\Database::valor("SELECT COUNT(*) FROM tickets WHERE usuario_id = ? AND estado IN ('abierto','en_atencion')", [$usuario['id']]);
                  } else {
                      $notifCount = (int) \App\Core\Database::valor("SELECT COUNT(*) FROM tickets WHERE depto_destino_id = ? AND estado IN ('abierto','en_atencion')", [\App\Core\Auth::departamentoId()]);
                  }
                  if ($notifCount > 0) {
                      $badgeContent = ' <span style="background: var(--error); color: #fff; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold; margin-left: 6px; display: inline-block; min-width: 18px; text-align: center;">' . $notifCount . '</span>';
                  }
              }
            ?>
            <a class="menu-item<?= $esItemActivo($item) ? ' activo' : '' ?>" <?= $esItemActivo($item) ? 'aria-current="page"' : '' ?> href="<?= url($item['ruta']) ?>">
              <?= e($item['titulo']) ?><?= $badgeContent ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="menu-pie" style="padding: 15px; display: flex; flex-direction: column; gap: 4px;">
        <span class="texto-secundario"><?= e($usuario['departamento']) ?></span>
        <a href="#" onclick="event.preventDefault(); document.getElementById('modal-privacidad').style.display = 'flex';" style="font-size: 11px; color: var(--gris-400); text-decoration: underline;">Aviso de Privacidad (ARCO)</a>
      </div>
    </nav>

    <main class="contenido" id="contenido-principal" tabindex="-1">
      <?php
        $tieneSegundoFactor = !empty($usuario['tiene_totp']) || !empty($usuario['totp_secret']) || (int) \App\Core\Database::valor(
            'SELECT COUNT(*) FROM usuarios_biometria WHERE usuario_id = ?', [(int) $usuario['id']]
        ) > 0;
      ?>
      <?php if (!\App\Core\AccessControl::pending() && !$tieneSegundoFactor): ?>
        <div class="seguridad-resumen" role="note">
          <span><?= (int)($usuario['permite_contrasena'] ?? 0) === 1 && (int)($usuario['logins_contrasena_restantes'] ?? 0) <= 5 ? 'Protege tu acceso: quedan ' . (int)$usuario['logins_contrasena_restantes'] . ' entradas con contraseña.' : 'Registra un segundo factor de autenticación para proteger tu cuenta.' ?></span>
          <a href="<?= url('seguridad') ?>">Registrar segundo factor →</a>
        </div>
      <?php endif; ?>
      <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['tipo']) ?>" role="<?= in_array($flash['tipo'], ['error','danger']) ? 'alert' : 'status' ?>" data-flash="<?= e($flash['tipo']) ?>" tabindex="-1"><?= e($flash['mensaje']) ?></div>
      <?php endif; ?>
      <?php if (empty($portalMinimal)): ?>
        <?php $raizRuta = explode('/', $rutaActual)[0]; $nombreRaiz = \App\Core\InterfaceUi::NAMES[$raizRuta] ?? $titulo; ?>
        <nav class="breadcrumbs" aria-label="Tu ubicación"><a href="<?= url('') ?>">Inicio</a><span aria-hidden="true">/</span><?php if ($rutaActual !== $raizRuta): ?><a href="<?= url($raizRuta) ?>"><?= e($nombreRaiz) ?></a><span aria-hidden="true">/</span><?php endif; ?><span aria-current="page"><?= e($rutaActual === $raizRuta ? $nombreRaiz : $titulo) ?></span></nav>
        <h1><?= e($rutaActual === $raizRuta ? $nombreRaiz : $titulo) ?></h1>
      <?php endif; ?>
      <?= $contenido ?>
    </main>
  </div>
  <div id="modal-privacidad" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: #fff; max-width: 600px; width: 100%; border-radius: var(--radio); box-shadow: var(--sombra-lg); display: flex; flex-direction: column; max-height: 80vh;">
      <div style="padding: 20px; border-bottom: 1px solid var(--gris-200); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: var(--gris-800);">Aviso de Privacidad Integral (Traza FI)</h3>
        <button type="button" onclick="document.getElementById('modal-privacidad').style.display = 'none'" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--gris-400);">&times;</button>
      </div>
      <div style="padding: 20px; overflow-y: auto; font-size: 13px; color: var(--gris-600); line-height: 1.6; text-align: justify;">
        <p><strong>Responsable del Tratamiento:</strong> La Facultad de Ingeniería de la Universidad Autónoma del Estado de México (UAEMEX) es responsable de proteger los datos personales de sus usuarios en cumplimiento con la normatividad vigente.</p>
        <p><strong>Origen y estado de la plataforma:</strong> Traza FI es una plataforma creada y operada por el Dr. Javier Salas García. Actualmente está en trámite para su uso oficial en la Facultad de Ingeniería de la UAEMEX.</p>
        <p><strong>Ausencia de Almacenamiento Biométrico:</strong> Traza FI <strong>NO almacena ni tiene acceso</strong> a huellas dactilares, rostros, imágenes o datos biométricos reales de los usuarios. Los "identificadores biométricos hash locales" consisten únicamente en llaves criptográficas públicas y firmas generadas por el sistema operativo de su propio dispositivo (Windows Hello, TouchID/FaceID) bajo el estándar WebAuthn, las cuales se quedan resguardadas localmente en su hardware personal y nunca viajan por la red.</p>
        <p><strong>Sobre la Gestión de Archivos e Integración con Teams:</strong> Traza FI <strong>NO almacena, copia ni aloja carpetas ni contenidos de archivos</strong> en sus servidores. El sistema funciona únicamente como un canalizador ordenado que gestiona accesos directos y peticiones en tiempo real conectándose directamente a la plataforma institucional oficial de <strong>Microsoft Teams</strong>. Traza FI no sustituye ni reemplaza a Teams como plataforma de colaboración oficial, sino que actúa como una interfaz o puente que permite su navegación y consulta estructurada de forma más ágil.</p>
        <p><strong>Concursos y formularios temporales:</strong> Cuando participas en un concurso o llenas un formulario temporal de la Facultad (por ejemplo, el registro de profesores para clasificaciones externas de programas académicos), Traza FI usa los datos que proporcionas (nombre, correo, la obra que envías y, en los formularios, los datos de contacto que se solicitan) únicamente para la finalidad de esa convocatoria: registrar y revisar tu participación, avisarte de su estado, publicar los nombres de quienes ganen y, en los registros para instituciones externas, entregarlos a la institución que realiza la clasificación. En las votaciones anónimas no se guarda tu identidad junto con tu voto: solo que ya votaste y tu recibo de voto. Estos datos se conservan mientras dura la actividad y el tiempo necesario para su comprobación; después se archivan o se eliminan.</p>
        <p><strong>Derechos ARCO:</strong> Usted tiene derecho a acceder, rectificar, cancelar u oponerse al tratamiento de sus datos personales. Para ejercer cualquiera de sus Derechos ARCO (Acceso, Rectificación, Cancelación y Oposición), deberá enviar una solicitud formal por escrito a la Unidad de Transparencia de la Facultad de Ingeniería o al correo oficial de soporte del administrador.</p>
      </div>
      <div style="padding: 15px 20px; border-top: 1px solid var(--gris-200); display: flex; justify-content: flex-end;">
        <button type="button" class="btn btn-primario" onclick="document.getElementById('modal-privacidad').style.display = 'none'">Entendido</button>
      </div>
    </div>
  </div>
  <script>window.fiUploadRules = <?= json_encode(['maxMb'=>(float)\App\Core\App::config('archivos.max_bytes',10485760)/1048576, 'extensions'=>\App\Core\App::config('archivos.ext_permitidas',[])], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
  <script src="<?= url('assets/js/app.js?v=' . (string) filemtime(dirname(__DIR__, 3) . '/public/assets/js/app.js')) ?>"></script>
  <script src="<?= url('assets/js/ux.js?v=' . (string) filemtime(dirname(__DIR__, 3) . '/public/assets/js/ux.js')) ?>"></script>
</body>
</html>
