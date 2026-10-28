<?php use App\Modules\Eventos\Asistencias; ?>
<style>
.qr-pantalla{text-align:center;max-width:760px;margin:0 auto}.qr-pantalla h1{font-size:26px;margin:4px 0 6px}
#qr{display:inline-block;background:#fff;padding:16px;border-radius:12px;margin:12px 0}#qr img,#qr svg{width:min(70vw,420px);height:auto;display:block}
.qr-clave{font-size:44px;letter-spacing:6px;font-weight:800;color:var(--verde-900,#2C5234)}
@media print{.barra-superior,.qr-noimprimir{display:none}}
</style>
<div class="qr-pantalla">
  <p class="texto-suave" style="margin:0">Registra tu asistencia</p>
  <h1><?= e($ev['titulo']) ?></h1>
  <p>Escanea con tu celular e inicia sesión en Traza FI · +<?= (int) $ev['puntos'] ?> puntos</p>
  <div id="qr" aria-label="Código QR de asistencia"></div>
  <p>¿Sin cámara? En la ficha del evento escribe la palabra clave:</p>
  <p class="qr-clave"><?= e((string) $ev['clave_asistencia']) ?></p>
  <p class="texto-suave qr-noimprimir" style="font-size:12px;word-break:break-all"><?= e($enlace) ?></p>
  <p class="qr-noimprimir"><button class="btn" type="button" onclick="window.print()">Imprimir</button></p>
</div>
<script src="<?= url('assets/vendor/qrcode/qrcode.js') ?>"></script>
<script>
(function () { var q = qrcode(0, 'M'); q.addData(<?= json_encode($enlace) ?>); q.make(); document.getElementById('qr').innerHTML = q.createSvgTag(8, 0); })();
</script>
