<?php
/** @var ?array $o @var string $codigo @var ?bool $comprobacion @var ?array $firmante */
use App\Modules\Oficios\Services\OficiosSub;
?>
<div style="max-width:760px;margin:0 auto;padding:8px 0">
<?php if ($o === null): ?>
  <section class="card">
    <h2 style="margin:0 0 6px;font-size:20px">No se encontró el oficio</h2>
    <p class="texto-suave" style="margin:0">El código «<?= e($codigo) ?>» no corresponde a un oficio firmado de la Subdirección Académica. Revisa el código del QR o pide una copia del oficio.</p>
  </section>
<?php else: ?>
  <section class="card">
    <h2 style="margin:0 0 4px;font-size:20px">Oficio <?= e((string) $o['numero_texto']) ?></h2>
    <p style="margin:0 0 12px"><span class="chip estado-resuelto">Firmado con e.firma del SAT</span></p>
    <dl style="margin:0;display:grid;grid-template-columns:max-content 1fr;gap:6px 16px">
      <dt><strong>Asunto</strong></dt><dd style="margin:0"><?= e((string) $o['asunto']) ?></dd>
      <dt><strong>Fecha del oficio</strong></dt><dd style="margin:0"><?= e(date('d/m/Y', strtotime((string) $o['fecha']))) ?></dd>
      <dt><strong>Firmó</strong></dt><dd style="margin:0"><?= e(implode(' ', (array) ($firmante['cargo'] ?? []))) ?><br><?= e((string) ($firmante['nombre'] ?? '')) ?></dd>
      <dt><strong>Fecha y hora de la firma</strong></dt><dd style="margin:0"><?= e(date('d/m/Y H:i', strtotime((string) $o['firmado_en']))) ?> h</dd>
      <dt><strong>Certificado No.</strong></dt><dd style="margin:0" class="monoespacio"><?= e((string) $o['cert_serie']) ?></dd>
      <dt><strong>Vigencia del certificado</strong></dt><dd style="margin:0"><?= e((string) $o['cert_vigencia']) ?></dd>
      <dt><strong>Cadena del certificado</strong></dt><dd style="margin:0"><?= e((string) $o['cadena_estado']) ?></dd>
      <dt><strong>Huella (SHA-256)</strong></dt><dd style="margin:0;word-break:break-all"><small class="monoespacio"><?= e((string) $o['sha256']) ?></small></dd>
    </dl>
  </section>
  <section class="card">
    <h3 style="margin:0 0 6px;font-size:16px">Comprobar tu copia del PDF</h3>
    <p class="texto-suave" style="margin:0 0 10px">Elige el archivo PDF que recibiste. Su huella se calcula en tu navegador (el archivo no se sube) y se compara con la registrada.</p>
    <?php if ($comprobacion !== null): ?>
      <p role="status" style="margin:0 0 10px;font-weight:700;color:<?= $comprobacion ? '#166534' : '#b91c1c' ?>"><?= $comprobacion ? 'El archivo es idéntico al oficio firmado.' : 'El archivo NO coincide con el oficio firmado: fue modificado o es otro documento.' ?></p>
    <?php endif; ?>
    <input type="file" id="v-pdf" accept="application/pdf,.pdf">
    <script>
      document.getElementById('v-pdf').addEventListener('change', function () {
        var f = this.files[0]; if (!f || !window.crypto || !crypto.subtle) return;
        f.arrayBuffer().then(function (b) { return crypto.subtle.digest('SHA-256', b); }).then(function (h) {
          var hex = Array.prototype.map.call(new Uint8Array(h), function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
          location.href = location.pathname + '?sha=' + hex;
        });
      });
    </script>
  </section>
  <section class="card">
    <h3 style="margin:0 0 6px;font-size:16px">Cómo verificar la firma en tu lector de PDF</h3>
    <p class="texto-suave" style="margin:0">Adobe Acrobat y otros lectores solo marcan en verde las firmas cuya autoridad (el SAT) está en su lista de confianza; si no lo está, muestran «firma válida, identidad desconocida». Descarga los certificados del SAT en el <a href="<?= e(url('oficios/confianza')) ?>">kit de confianza</a> y sigue la guía.</p>
  </section>
<?php endif; ?>
</div>
