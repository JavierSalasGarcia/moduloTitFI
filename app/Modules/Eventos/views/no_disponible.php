<?php include __DIR__ . '/_cintillo.php'; ?>
<section class="card" style="max-width:640px;margin:30px auto;text-align:center;padding:30px">
  <div aria-hidden="true" style="font-size:40px">📭</div>
  <h1 style="font-size:20px;margin:10px 0">Este aviso no está disponible</h1>
  <p class="texto-suave">Puede que aún no se publique, que haya concluido su vigencia o que el enlace esté incompleto.<?= !empty($visitante) ? ' Algunos avisos solo se ven con sesión iniciada.' : '' ?></p>
  <p style="margin-top:18px"><a class="btn btn-primario" href="<?= e(url('eventos')) ?>">Ver los avisos publicados</a>
  <?php if (!empty($visitante)): ?> <a class="btn" href="<?= e(url('login')) ?>">Iniciar sesión</a><?php endif; ?></p>
</section>
