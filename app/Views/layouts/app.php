<?php
use App\Core\Auth;
use App\Core\ModuleManager;

$usuario = Auth::usuario();
// Recordatorios de los eventos del calendario personal (sin tareas programadas: se generan al usar la plataforma).
if (class_exists(\App\Modules\Eventos\Recordatorios::class)) {
    \App\Modules\Eventos\Recordatorios::generar();
}
$navegacion = \App\Core\InterfaceUi::navegacion();
$secciones = $navegacion['grupos'];
$enlacesMenu = $navegacion['enlaces'];
$flash   = flash_leer();
$moduloActivo = $moduloActivo ?? '';

// Ruta actual (para resaltar entradas dinámicas como recursos/3)
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$rutaActual = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', strlen($base)), '/');
$rutasMenu = [];
foreach ($secciones as $s) { foreach ($s['items'] as $it) { if ($it['ruta'] !== '' && empty($it['dinamico'])) $rutasMenu[] = $it['ruta']; } }
$esItemActivo = function (array $item) use ($moduloActivo, $rutaActual, $rutasMenu): bool {
    if (!empty($item['dinamico'])) {
        return $item['ruta'] === $rutaActual;
    }
    if ($item['ruta'] === '') return $rutaActual === '' || $rutaActual === 'consulta';
    if ($item['ruta'] === 'ajustes') return $rutaActual === 'ajustes';
    if ($item['ruta'] === 'eventos' && str_starts_with($rutaActual, 'eventos/biblioteca')) return false;
    if ($rutaActual !== $item['ruta'] && !str_starts_with($rutaActual, $item['ruta'] . '/')) return false;
    // Si otra entrada del menú coincide con una ruta más específica (p. ej. titulacion/constancias/historial), solo esa queda marcada.
    foreach ($rutasMenu as $otra) {
        if (strlen($otra) > strlen($item['ruta']) && ($rutaActual === $otra || str_starts_with($rutaActual, $otra . '/'))) return false;
    }
    return true;
};
// Enlace activo: la ruta actual es de alguna de sus entradas o es la página de entrada del grupo.
// Si ninguna entrada coincide (p. ej. titulacion/tramite/12), se marca el grupo que comparte la raíz de la ruta.
$raizActual = explode('/', $rutaActual)[0];
$activos = [];
foreach ($enlacesMenu as $i => $enlace) {
    $activos[$i] = $rutaActual === 'seccion/' . $enlace['slug'] || (bool) array_filter($enlace['items'], $esItemActivo);
}
if (!array_filter($activos) && $raizActual !== '') {
    foreach ($enlacesMenu as $i => $enlace) {
        foreach ($enlace['items'] as $it) {
            if (explode('/', $it['ruta'])[0] === $raizActual && empty($it['dinamico'])) { $activos[$i] = true; break 2; }
        }
    }
}
// Migas de regreso: grupo con página de entrada al que pertenece la página actual.
$grupoVolver = null;
if ($rutaActual !== '' && !str_starts_with($rutaActual, 'seccion/') && empty($portalMinimal)) {
    foreach ($enlacesMenu as $i => $enlace) {
        if (!empty($activos[$i]) && $enlace['pagina']) { $grupoVolver = $enlace; break; }
    }
}
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
<?php require __DIR__ . '/_pwa.php'; ?>
</head>
<body class="ux-app <?= !empty($portalMinimal) ? 'portal-minimal' : '' ?>" data-user="<?= (int) $usuario['id'] ?>" data-base="<?= e(url('')) ?>" data-module="<?= e($moduloActivo) ?>">
  <a class="skip-link" href="#contenido-principal">Saltar al contenido</a>
  <header class="barra-superior">
    <?php $pendientesMenu = (int) array_sum(array_column($enlacesMenu, 'contador')); ?>
    <button type="button" class="btn-menu-movil" id="btn-menu-movil" aria-label="Abrir menú<?= $pendientesMenu > 0 ? ' (' . $pendientesMenu . ' por atender)' : '' ?>" aria-controls="menu-lateral" aria-expanded="false">
      <span></span><span></span><span></span>
      <?php if ($pendientesMenu > 0): ?><b class="menu-badge" aria-hidden="true"><?= $pendientesMenu > 99 ? '99+' : $pendientesMenu ?></b><?php endif; ?>
    </button>
    <script>
    // Número del ícono de la app instalada = pendientes del menú; al abrir un aviso se retira su notificación del teléfono
    // (y todas si ya no queda nada pendiente), porque en Android el punto del ícono sigue a las notificaciones visibles.
    (() => { const n = <?= $pendientesMenu ?>;
      try { if ('setAppBadge' in navigator) { n > 0 ? navigator.setAppBadge(n) : navigator.clearAppBadge(); } } catch (e) {}
      try { if ('serviceWorker' in navigator) navigator.serviceWorker.ready.then((r) => r.getNotifications()).then((ns) => ns.forEach((x) => {
        if (n === 0 || (x.data && x.data.url === location.pathname)) x.close(); })).catch(() => {}); } catch (e) {} })();
    </script>
    <a href="<?= url('') ?>" class="logo-barra" aria-label="Inicio" style="background: url('<?= url('assets/img/logo.png') ?>') no-repeat center center; background-size: cover; border-radius: 50%; font-size: 0; width: 44px; height: 44px; display: inline-block;">Traza FI</a>
    <a href="<?= url('') ?>" class="marca" aria-label="Traza FI · Inicio"><strong>Traza FI</strong> <span class="marca-desc">Facultad de Ingeniería · UAEMEX</span></a>
    <?php if (empty($portalMinimal)): ?>
    <button type="button" class="btn-buscar-movil" id="btn-buscar" aria-label="Buscar en Traza FI" aria-expanded="false" aria-controls="busqueda-barra"><?= icono('buscar', 20) ?></button>
    <form class="busqueda-barra" id="busqueda-barra" role="search" method="get" action="<?= url('busqueda') ?>">
      <label class="sr-only" for="busqueda-q">Buscar un trámite o una función</label>
      <input id="busqueda-q" type="search" name="q" maxlength="120" placeholder="¿Qué necesitas hacer? (tecla /)" autocomplete="off" enterkeyhint="search" role="combobox" aria-expanded="false" aria-controls="busqueda-sugerencias" aria-autocomplete="list">
      <button type="submit" class="btn btn-mini" aria-label="Buscar"><?= icono('buscar', 16) ?></button>
      <div id="busqueda-sugerencias" class="busqueda-sugerencias" role="listbox" aria-label="Accesos y sugerencias" hidden></div>
    </form>
    <script>
    (function () {
      var b = document.getElementById('btn-buscar'), f = document.getElementById('busqueda-barra'), q = document.getElementById('busqueda-q'), p = document.getElementById('busqueda-sugerencias');
      if (!b || !f || !q || !p) return;
      var cache = null, activo = -1;
      function norm(s) { return String(s).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim(); }
      // «Comandos»: todo lo que la persona puede abrir según su menú (ya filtrado por rol y condiciones).
      function opciones() {
        if (cache) return cache; cache = []; var visto = {};
        document.querySelectorAll('#menu-lateral a[href]').forEach(function (a) {
          var t = (a.querySelector('.nav-texto') || a).textContent.replace(/\s+/g, ' ').trim();
          if (!t || visto[a.href] || a.getAttribute('href') === '#') return; visto[a.href] = 1;
          var d = a.closest('details'), s = d && d.querySelector('summary .nav-texto');
          var g = s ? s.textContent.replace(/\s+/g, ' ').trim() : '';
          cache.push({ t: t, g: g, h: a.href, n: norm(g + ' ' + t) });
        });
        return cache;
      }
      function op(texto, href, extra, clase) {
        var a = document.createElement('a'); a.href = href; a.setAttribute('role', 'option'); a.className = 'busqueda-op' + (clase ? ' ' + clase : '');
        a.id = 'busqueda-op-' + p.querySelectorAll('.busqueda-op').length; a.textContent = texto;
        if (extra) { var s = document.createElement('small'); s.textContent = ' · ' + extra; a.appendChild(s); }
        p.appendChild(a);
      }
      function pintar() {
        var texto = q.value.trim(), v = norm(texto), palabras = v ? v.split(' ') : [];
        p.textContent = ''; activo = -1; q.removeAttribute('aria-activedescendant');
        // Sin texto no se muestra nada: es un buscador, no un menú. Las sugerencias aparecen al escribir.
        if (!texto) { cerrar(); return; }
        var lista = opciones().filter(function (o) { return palabras.every(function (w) { return o.n.indexOf(w) > -1; }); }).slice(0, 4);
        lista.forEach(function (o) { op(o.t, o.h, o.g); });
        op('Buscar «' + texto + '» en todo Traza FI', f.action + '?q=' + encodeURIComponent(texto), '', 'busqueda-todo');
        p.hidden = false; q.setAttribute('aria-expanded', 'true');
      }
      function cerrar() { p.hidden = true; q.setAttribute('aria-expanded', 'false'); activo = -1; }
      function abrir(v) {
        // En el celular, si el menú lateral está abierto se cierra para que no se encime con la búsqueda.
        var scrim = document.querySelector('.menu-scrim'); if (v && scrim && !scrim.hidden) scrim.click();
        f.classList.toggle('abierta', v); b.setAttribute('aria-expanded', v ? 'true' : 'false'); if (v) q.focus(); else cerrar();
      }
      b.addEventListener('click', function () { abrir(!f.classList.contains('abierta')); });
      q.addEventListener('focus', pintar); q.addEventListener('input', pintar);
      q.addEventListener('keydown', function (e) {
        var ops = [].slice.call(p.querySelectorAll('.busqueda-op'));
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault(); if (p.hidden) pintar(); ops = [].slice.call(p.querySelectorAll('.busqueda-op'));
          activo = (activo + (e.key === 'ArrowDown' ? 1 : -1) + ops.length) % ops.length;
          ops.forEach(function (o, i) { o.classList.toggle('sel', i === activo); });
          ops[activo].scrollIntoView({ block: 'nearest' }); q.setAttribute('aria-activedescendant', ops[activo].id);
        } else if (e.key === 'Enter' && activo > -1) { e.preventDefault(); ops[activo].click(); }
      });
      document.addEventListener('click', function (e) { if (!f.contains(e.target) && e.target !== b && !b.contains(e.target)) cerrar(); });
      document.addEventListener('keydown', function (e) {
        var t = e.target, escribiendo = t && (/^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) || t.isContentEditable);
        if ((e.key === '/' || e.key === '\\') && !escribiendo && !e.ctrlKey && !e.metaKey && !e.altKey) { e.preventDefault(); abrir(true); }
        if (e.key === 'Escape' && !p.hidden) { cerrar(); f.classList.remove('abierta'); b.setAttribute('aria-expanded', 'false'); b.focus(); }
      });
    })();
    </script>
    <?php endif; ?>
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
      <ul class="nav-lista">
        <?php foreach ($enlacesMenu as $i => $enlace): $activo = !empty($activos[$i]); ?>
          <li>
          <?php if (!empty($enlace['pagina'])): ?>
            <?php
              // Grupo con varias opciones: acordeón con sus opciones secundarias (las que tienen subgrupo van bajo su subtítulo).
              $bloquesMenu = [];
              foreach ($enlace['items'] as $it) { $bloquesMenu[trim((string) ($it['grupo'] ?? ''))][] = $it; }
              uksort($bloquesMenu, static fn($a, $b) => ($a !== '') <=> ($b !== ''));
            ?>
            <details class="nav-grupo"<?= $activo ? ' open' : '' ?>>
              <summary class="nav-enlace<?= $activo ? ' activo' : '' ?>">
                <?= icono($enlace['icono'], 20) ?>
                <span class="nav-texto"><?= e($enlace['etiqueta']) ?></span>
                <?php if ($enlace['contador'] > 0): ?><span class="nav-badge"><span aria-hidden="true"><?= (int) $enlace['contador'] ?></span><span class="nav-sr"><?= (int) $enlace['contador'] ?> por atender</span></span><?php endif; ?>
                <span class="nav-flecha" aria-hidden="true"></span>
              </summary>
              <ul class="nav-sub">
                <?php foreach ($bloquesMenu as $subtitulo => $lista): ?>
                  <?php if ($subtitulo !== ''): ?><li class="nav-subtitulo"><?= e($subtitulo) ?></li><?php endif; ?>
                  <?php foreach ($lista as $it): $on = $esItemActivo($it); ?>
                    <li><a class="nav-item<?= $on ? ' activo' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?> href="<?= e(url($it['ruta'])) ?>">
                      <span class="nav-texto"><?= e($it['titulo']) ?></span>
                      <?php if (!empty($it['contador'])): ?><span class="nav-badge"><span aria-hidden="true"><?= (int) $it['contador'] ?></span><span class="nav-sr"><?= (int) $it['contador'] ?> por atender</span></span><?php endif; ?>
                    </a></li>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php else: ?>
            <a class="nav-enlace<?= $activo ? ' activo' : '' ?>" <?= $activo ? 'aria-current="page"' : '' ?> href="<?= e(url($enlace['ruta'])) ?>">
              <?= icono($enlace['icono'], 20) ?>
              <span class="nav-texto"><?= e($enlace['etiqueta']) ?></span>
              <?php if ($enlace['contador'] > 0): ?><span class="nav-badge"><span aria-hidden="true"><?= (int) $enlace['contador'] ?></span><span class="nav-sr"><?= (int) $enlace['contador'] ?> por atender</span></span><?php endif; ?>
            </a>
          <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
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
        <?php $raizRuta = explode('/', $rutaActual)[0]; $nombreRaiz = $raizRuta === 'seccion' ? $titulo : (\App\Core\InterfaceUi::NAMES[$raizRuta] ?? $titulo); if ($raizRuta === 'seccion') $rutaActual = 'seccion'; ?>
        <?php
          // El h1 ya dice en qué página estás: las migas muestran el camino (Inicio / grupo) y solo agregan la página actual si su texto difiere del h1.
          $h1Texto = $rutaActual === $raizRuta ? $nombreRaiz : $titulo;
          $miga = [['url' => url(''), 'texto' => 'Inicio']];
          if ($grupoVolver) { $miga[] = ['url' => url('seccion/' . $grupoVolver['slug']), 'texto' => $grupoVolver['etiqueta']]; }
          elseif ($rutaActual !== $raizRuta) { $miga[] = ['url' => url($raizRuta), 'texto' => $nombreRaiz]; }
          $actualMiga = $rutaActual === $raizRuta && !$grupoVolver ? $nombreRaiz : $titulo;
        ?>
        <nav class="breadcrumbs" aria-label="Tu ubicación"><?php foreach ($miga as $k => $m): ?><?php if ($k > 0): ?><span aria-hidden="true">/</span><?php endif; ?><a href="<?= e($m['url']) ?>"><?= e($m['texto']) ?></a><?php endforeach; ?><?php if (!\App\Core\Titulos::yaDicho((string) $actualMiga, (string) $h1Texto) && !\App\Core\Titulos::yaDicho((string) $h1Texto, (string) $actualMiga)): ?><span aria-hidden="true">/</span><span aria-current="page"><?= e($actualMiga) ?></span><?php endif; ?></nav>
        <h1><?= e($h1Texto) ?></h1>
      <?php endif; ?>
      <?= empty($portalMinimal) && isset($h1Texto) ? \App\Core\Titulos::quitarRepetido((string) $contenido, (string) $h1Texto) : $contenido ?>
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
  <script src="<?= url('assets/js/selector-personas.js?v=' . (string) filemtime(dirname(__DIR__, 3) . '/public/assets/js/selector-personas.js')) ?>"></script>
</body>
</html>
