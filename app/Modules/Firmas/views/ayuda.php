<p><a class="enlace" href="<?= url('firmas') ?>">← Volver a firmas</a></p>
<section class="card">
<h2>Firma digital institucional</h2>
<p>Traza FI utiliza PDF Advanced Electronic Signatures (PAdES), estándar que incorpora la firma criptográfica y el certificado dentro del archivo en formato de documento portátil (PDF). Cada persona tiene un certificado X.509 individual, estándar que vincula su identidad con una llave pública.</p>
<p>La infraestructura de llave pública (PKI) interna de la Facultad conserva las llaves privadas cifradas fuera del directorio público. La llave privada no se descarga: el usuario autoriza cada operación con una contraseña de un solo uso basada en tiempo (TOTP), código temporal de seis dígitos generado por su aplicación autenticadora.</p>
<h3>Cómo firmar</h3>
<ol>
  <li>Abre la solicitud, revisa cada documento y compara la huella creada con el Algoritmo de Hash Seguro de 256 bits (SHA-256), función que permite detectar cambios.</li>
  <li>Elige la página y posición de la representación visible.</li>
  <li>Escribe el código TOTP vigente y confirma.</li>
  <li>El servicio agrega la firma PAdES sin eliminar las firmas anteriores y registra certificado, huellas y resultado en la bitácora.</li>
</ol>
<h3>Certificados que se pueden descargar</h3>
<p>Solo se distribuye el certificado <strong>público</strong> de la autoridad raíz, necesario para que Adobe Acrobat o Foxit reconozcan la cadena interna como confiable. Las llaves privadas, los archivos personales y la llave de la autoridad certificadora nunca son descargables.</p>
<?php if (\App\Modules\Firmas\PadesSigner::habilitado()): ?>
  <a class="btn btn-primario" href="<?= url('firmas/certificado') ?>">Descargar certificado público raíz</a>
<?php else: ?>
  <p class="aviso aviso-advertencia"><strong>Pendiente:</strong> la administración todavía no ha publicado la autoridad raíz institucional.</p>
<?php endif; ?>
<h3 id="instalacion" style="margin-top:28px">Instalar en Adobe Acrobat</h3>
<ol>
  <li>Comprueba por un canal institucional independiente que la huella SHA-256 del archivo coincida con la publicada por la Facultad.</li>
  <li>Abre Adobe Acrobat y entra a <strong>Preferencias → Firmas → Identidades y certificados de confianza → Más</strong>.</li>
  <li>Importa el archivo raíz y marca la confianza únicamente para firmas de documentos.</li>
  <li>Cierra y vuelve a abrir Adobe Acrobat; después valida el panel de firmas del PDF.</li>
</ol>
<h3>Instalar en Foxit PDF Editor o Reader</h3>
<ol>
  <li>Comprueba primero la huella SHA-256 publicada por la Facultad.</li>
  <li>Abre <strong>Preferencias → Administrador de confianza</strong> y entra a la lista de certificados confiables.</li>
  <li>Importa el certificado raíz y limita la confianza a la validación de firmas.</li>
  <li>Reinicia Foxit y valida el panel de firmas del documento.</li>
</ol>
<p class="texto-suave">Una cadena interna puede aparecer como válida después de instalar la raíz, pero su efecto jurídico depende de la aprobación y los procedimientos institucionales. El sistema no debe presentarse como firma electrónica avanzada mientras esas condiciones no estén formalmente reconocidas.</p>
</section>
