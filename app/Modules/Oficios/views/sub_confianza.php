<?php
/** @var array $certificados */
?>
<div style="max-width:760px;margin:0 auto;padding:8px 0">
  <section class="card">
    <h2 style="margin:0 0 6px;font-size:20px">Kit de confianza del SAT para verificar los oficios</h2>
    <p style="margin:0 0 10px">Los oficios de la Subdirección Académica se firman con la e.firma del SAT. Adobe Acrobat y otros lectores solo muestran una firma como confiable (en verde) si el certificado de la autoridad que la emitió —aquí, el SAT— está en su lista de confianza. Si no lo está, muestran <strong>«firma válida, identidad desconocida»</strong>: la firma sigue siendo íntegra y válida, pero el lector no puede decir de quién es.</p>
    <p class="texto-suave" style="margin:0">Para que tu lector la reconozca, instala una sola vez los certificados del SAT de este kit. La página de verificación del QR de cada oficio también comprueba la firma sin necesitar un lector de PDF.</p>
  </section>

  <section class="card">
    <h3 style="margin:0 0 8px;font-size:18px">Certificados</h3>
    <?php if (!$certificados): ?>
      <p class="texto-suave" style="margin:0">Aún no hay certificados cargados. La administración debe cargarlos (los publica el SAT) en Oficios de la Subdirección → Configuración.</p>
    <?php else: ?>
      <ul style="margin:0;padding-left:18px">
        <?php foreach ($certificados as $c): ?>
          <li style="margin:6px 0"><a href="<?= e(url('oficios/confianza/' . (int) $c['id'])) ?>"><?= e($c['nombre']) ?> (.cer)</a> <small class="texto-suave monoespacio">huella <?= e(substr((string) $c['huella'], 0, 16)) ?>…</small></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3 style="margin:0 0 8px;font-size:18px">Cómo confiar en ellos en Adobe Acrobat</h3>
    <ol style="margin:0;padding-left:20px;line-height:1.6">
      <li>Descarga los certificados de arriba.</li>
      <li>En Acrobat: <strong>Menú → Preferencias → Firmas → Identidades y certificados de confianza → Más…</strong></li>
      <li>Elige <strong>Certificados de confianza → Importar</strong>, selecciona cada archivo .cer y marca <strong>«Usar este certificado como raíz de confianza»</strong> y «Documentos certificados».</li>
      <li>Abre de nuevo el oficio: la firma deberá mostrarse como válida y con identidad conocida.</li>
    </ol>
    <p class="texto-suave" style="margin:10px 0 0">En equipos de la Facultad, Tecnologías de la Información puede instalar los certificados una sola vez para todos (almacén de certificados de Windows) y activar en Acrobat <strong>Preferencias → Firmas → Verificación → «Confiar en todos los certificados raíz del almacén de Windows»</strong>.</p>
  </section>
</div>
