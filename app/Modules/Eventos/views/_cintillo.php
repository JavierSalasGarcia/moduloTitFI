<?php
// Cintillo superior de «Avisos y eventos»: inauguración de la plataforma (hasta ese día) e invitación a registrarse (solo sin sesión).
use App\Modules\Eventos\Lecturas;
$inaug = Lecturas::inauguracionVigente();
if (!$inaug && empty($visitante)) return;
?>
<div class="cintillo-avisos" role="note">
  <?php if ($inaug): ?>
    <p class="cintillo-linea"><span aria-hidden="true">🎉</span> <strong>Inauguración de Traza FI:</strong> <?= e(Lecturas::INAUGURACION['texto']) ?>. Invitamos a profesores, administrativos y alumnos de la Facultad de Ingeniería.</p>
  <?php endif; ?>
  <?php if (!empty($visitante)): ?>
    <p class="cintillo-linea">Si deseas acceder a más funciones (tus trámites, constancias, calendario y avisos de tu carrera), <a href="<?= e(url('registro')) ?>"><strong>regístrate en Traza FI</strong></a> con tu correo institucional · ¿Ya tienes cuenta? <a href="<?= e(url('login')) ?>">Inicia sesión</a> · ¿Eres egresado sin correo institucional? <a href="<?= e(url('registro/egresado')) ?>">Regístrate aquí</a></p>
  <?php endif; ?>
</div>
<style>
.cintillo-avisos{background:linear-gradient(90deg,#1f4d36,#2c6b4a);color:#fff;border-radius:10px;padding:12px 16px;margin:0 0 18px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.cintillo-avisos a{color:#ffe79a;text-decoration:underline}
.cintillo-linea{margin:0;font-size:14px;line-height:1.5}
.cintillo-linea+.cintillo-linea{margin-top:6px;padding-top:6px;border-top:1px solid rgba(255,255,255,.25)}
</style>
