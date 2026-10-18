<?php include __DIR__ . '/_estilos.php'; ?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">Realiza en línea tu trámite de evaluación profesional: elige tu opción, captura tus datos una sola vez, descarga los formatos ya llenos para firmarlos y sube tus documentos en PDF para que el Departamento de Evaluación Profesional los valide.</p>
  </div>
</div>
<div class="tit-cuadricula">
  <a class="tit-opcion" href="<?= url('titulacion/licenciatura') ?>">
    <strong><?= icono('lista', 18) ?> Licenciatura</strong>
    <span>Sin trabajo escrito (Aprovechamiento, EGEL, Créditos en estudios avanzados) o con trabajo escrito (Tesis, Tesina, Artículo, Ensayo, Memoria, Reportes).</span>
    <small>Asistente paso a paso</small>
  </a>
  <a class="tit-opcion" href="<?= url('titulacion/posgrado') ?>">
    <strong><?= icono('lista', 18) ?> Posgrado</strong>
    <span>Obtención de grado de especialidad y maestría.</span>
    <small>Próximamente</small>
  </a>
  <?php if (!empty($esPersonal)): ?>
  <a class="tit-opcion" href="<?= url('titulacion/gestion') ?>">
    <strong><?= icono('lista', 18) ?> Validar trámites</strong>
    <span>Bandeja del personal de Titulación: revisa documentos, registra fechas y da seguimiento.</span>
    <small>Solo personal autorizado</small>
  </a>
  <?php endif; ?>
</div>
