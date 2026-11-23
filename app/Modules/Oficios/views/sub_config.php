<?php
/** @var bool $rfc @var array $certificados */
use App\Core\Auth;
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
?>
<p style="margin:0 0 10px"><a href="<?= e(url('oficios/subdireccion')) ?>">← Oficios de la Subdirección</a></p>
<section class="card">
  <h2 style="margin:0 0 6px;font-size:18px">RFC del titular de la Subdirección</h2>
  <p class="texto-suave" style="margin:0 0 10px">Sirve para comprobar que la e.firma con la que se firma es la del titular. Se guarda cifrado y <strong>no se vuelve a mostrar</strong>, ni en pantalla ni en los PDF.</p>
  <p style="margin:0 0 10px"><span class="chip"><?= $rfc ? 'RFC registrado' : 'Sin registrar' ?></span></p>
  <form method="post" action="<?= e(url('oficios/subdireccion/configuracion')) ?>" style="display:grid;gap:10px;max-width:420px"><?= $csrf ?>
    <label><?= $rfc ? 'Reemplazar el RFC' : 'RFC' ?><input type="text" name="rfc" maxlength="13" autocomplete="off" placeholder="ABCD800101XYZ" style="text-transform:uppercase"></label>
    <div><button class="btn btn-primario" type="submit">Guardar</button></div>
  </form>
</section>
<section class="card">
  <h2 style="margin:0 0 6px;font-size:18px">Certificados del SAT (kit de confianza)</h2>
  <p class="texto-suave" style="margin:0 0 10px">Carga aquí los certificados públicos de la cadena del SAT (raíz e intermedios, archivos .cer, .crt o .pem que publica el SAT). Se usan para verificar la cadena de cada firma y se ofrecen para descargar en <a href="<?= e(url('oficios/confianza')) ?>">el kit de confianza</a>. No son secretos.</p>
  <?php if ($certificados): ?>
    <ul style="margin:0 0 12px;padding-left:18px">
      <?php foreach ($certificados as $c): ?>
        <li><?= e($c['nombre']) ?> <small class="texto-suave monoespacio">(<?= e(substr((string) $c['huella'], 0, 16)) ?>…)</small>
          <form method="post" action="<?= e(url('oficios/subdireccion/configuracion/certificado/' . (int) $c['id'] . '/quitar')) ?>" style="display:inline"><?= $csrf ?><button class="btn btn-mini" type="submit" onclick="return confirm('¿Quitar este certificado?')">Quitar</button></form></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?><p class="texto-suave">Todavía no hay certificados cargados.</p><?php endif; ?>
  <form method="post" action="<?= e(url('oficios/subdireccion/configuracion')) ?>" enctype="multipart/form-data" style="display:grid;gap:10px;max-width:420px"><?= $csrf ?>
    <label>Certificado del SAT<input type="file" name="certificado" accept=".cer,.crt,.pem" required></label>
    <div><button class="btn" type="submit">Agregar certificado</button></div>
  </form>
</section>
