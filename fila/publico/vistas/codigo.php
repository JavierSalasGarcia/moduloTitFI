<?php $verificacion_id=$verificacion_id??null; ?>
<section class="tarjeta angosta">
  <h1>Confirma tu correo</h1>
  <p>Te enviamos un código de <?= !empty($verificacion_id)?'tres':'tres o seis' ?> dígitos a <strong><?= u_escapar($email) ?></strong>.
     Escríbelo aquí para <?= $tipo === 'cita' ? 'enviar tu solicitud de reunión' : 'apartar tu turno' ?>.</p>
  <?php if ($error): ?><p class="error"><?= u_escapar($error) ?></p><?php endif; ?>
  <?php if (!empty($advertencia)): ?><p class="estado aviso" role="alert"><?= u_escapar($advertencia) ?></p><?php endif; ?>
  <form method="post" action="<?= ruta($dep['clave'] . '/codigo') ?>" class="formulario" data-confirmar-codigo>
    <?= campo_csrf() ?>
    <input type="hidden" name="email" value="<?= u_escapar($email) ?>">
    <input type="hidden" name="tipo" value="<?= u_escapar($tipo) ?>">
    <?php if ($verificacion_id!==null): ?><input type="hidden" name="verificacion_id" value="<?= (int)$verificacion_id ?>"><?php endif; ?>
    <label>Código
      <input name="codigo" inputmode="numeric" pattern="<?= !empty($verificacion_id)?'[0-9]{3}':'[0-9]{3}([0-9]{3})?' ?>" maxlength="<?= !empty($verificacion_id)?3:6 ?>" autocomplete="one-time-code" required autofocus
             class="codigo">
    </label>
    <button class="grande">Confirmar</button>
  </form>
  <p class="meta">Si no llega en un par de minutos, revisa tu carpeta de correo no deseado.</p>
</section>
<script>
document.querySelector('[data-confirmar-codigo]')?.addEventListener('submit', function () {
  const boton=this.querySelector('button[type="submit"],button:not([type])');
  if (!boton || boton.disabled) return;
  boton.disabled=true;
  boton.setAttribute('aria-busy','true');
  boton.textContent='Confirmando…';
});
</script>
