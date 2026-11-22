<?php
/** @var string $estado invalido|login|registro|otra_cuenta|listo|sin_archivo */
use App\Core\Auth;
$o = $oficio ?? [];
$csrf = Auth::csrfToken();
$envoltura = $estado === 'login' || $estado === 'registro' || $estado === 'invalido';
?>
<?php if ($envoltura): ?><div class="auth-panel"><div class="auth-tarjeta"><?php else: ?><div style="max-width:640px"><section class="tarjeta"><?php endif; ?>
  <h1 style="font-size:21px;margin:0 0 10px">Dictamen de los HH. Consejos</h1>

  <?php if ($estado === 'invalido'): ?>
    <p>Este enlace no es válido o ya venció.</p>
    <p class="texto-suave">Pide a la Subdirección Académica que te vuelva a notificar el dictamen.</p>

  <?php else: ?>
    <p><strong>Dictamen <?= e((string) ($o['numero'] ?? '')) ?></strong> · caso <?= e($clave) ?></p>

    <?php if ($estado === 'login'): ?>
      <p>Para ver y descargar el dictamen debes <strong>iniciar sesión en Traza FI con el correo <?= e($correoMascara) ?></strong>, que es al que se envió este aviso.</p>
      <a class="btn btn-primario btn-bloque" href="<?= e(url('login')) ?>">Iniciar sesión</a>
      <p class="texto-suave" style="margin-top:12px">Al entrar volverás a esta página con el dictamen listo.</p>

    <?php elseif ($estado === 'registro'): ?>
      <p>Aún <strong>no hay una cuenta de Traza FI con el correo <?= e($correoMascara) ?></strong>. Para ver y descargar el dictamen, primero crea tu cuenta con ese mismo correo.</p>
      <a class="btn btn-primario btn-bloque" href="<?= e(url($registro)) ?>">Crear mi cuenta</a>
      <p class="texto-suave" style="margin-top:12px">Cuando termines, vuelve a abrir el enlace del correo e inicia sesión.</p>
      <p style="margin-top:8px"><a href="<?= e(url('login')) ?>">Ya tengo cuenta: iniciar sesión</a></p>

    <?php elseif ($estado === 'otra_cuenta'): ?>
      <p>Este dictamen se notificó a <strong><?= e($correoMascara) ?></strong>, pero tu sesión es de otra cuenta (<?= e($cuenta) ?>).</p>
      <p class="texto-suave">Cierra la sesión y entra con el correo al que llegó el aviso.</p>
      <form method="post" action="<?= e(url('logout')) ?>"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn" type="submit">Cerrar sesión</button></form>

    <?php elseif ($estado === 'sin_archivo'): ?>
      <p>El escaneado de este dictamen ya no está disponible. Consulta con la Subdirección Académica.</p>

    <?php else: ?>
      <p>Tu dictamen firmado y sellado está listo.</p>
      <div class="cns-acciones" style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn-primario" href="<?= e(url('consejos/archivo/' . $pdf['id'])) ?>" target="_blank" rel="noopener">Ver el dictamen</a>
        <a class="btn" href="<?= e(url('consejos/archivo/' . $pdf['id'] . '?descargar=1')) ?>">Descargar PDF</a>
      </div>
      <p class="texto-suave" style="margin-top:12px">También lo encuentras en Constancias → Comisiones.</p>
    <?php endif; ?>
  <?php endif; ?>
<?php if ($envoltura): ?></div></div><?php else: ?></section></div><?php endif; ?>
