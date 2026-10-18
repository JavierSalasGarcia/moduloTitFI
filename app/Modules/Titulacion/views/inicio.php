<?php include __DIR__ . '/_estilos.php'; ?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">Realiza en línea tu trámite de evaluación profesional: elige tu opción, captura tus datos una sola vez, descarga los formatos ya llenos para firmarlos y sube tus documentos en PDF para que el Departamento de Evaluación Profesional los valide.</p>
  </div>
</div>
<div class="tit-cuadricula">
  <?php if ($veLicenciatura): ?>
  <a class="tit-opcion" href="<?= url('titulacion/licenciatura') ?>">
    <strong><?= icono('lista', 18) ?> Licenciatura</strong>
    <span>Sin trabajo escrito (Aprovechamiento, EGEL, Créditos en estudios avanzados) o con trabajo escrito (Tesis, Tesina, Artículo, Ensayo, Memoria, Reportes).</span>
    <small>Asistente paso a paso</small>
  </a>
  <?php endif; ?>
  <?php if ($vePosgrado): ?>
  <a class="tit-opcion" href="<?= url('titulacion/posgrado') ?>">
    <strong><?= icono('lista', 18) ?> Posgrado</strong>
    <span>Obtención de grado de especialidad y maestría.</span>
    <small>Próximamente</small>
  </a>
  <?php endif; ?>
  <?php if ($esEvaluador): ?>
  <a class="tit-opcion" href="<?= url('titulacion/revision') ?>"><strong><?= icono('lista', 18) ?> Protocolos por evaluar</strong><span>Protocolos que te asignó una comisión de revisión.</span><small>Comisión de evaluación</small></a>
  <?php endif; ?>
  <?php if ($esEncargado): ?>
  <a class="tit-opcion" href="<?= url('titulacion/comision') ?>"><strong><?= icono('lista', 18) ?> Mi comisión</strong><span>Asigna evaluadores, vigila los plazos y firma el dictamen.</span><small>Encargado de comisión</small></a>
  <?php endif; ?>
  <?php if (!empty($esPersonal)): ?>
  <a class="tit-opcion" href="<?= url('titulacion/gestion') ?>">
    <strong><?= icono('lista', 18) ?> Validar trámites y protocolos</strong>
    <span>Bandeja del personal de Titulación: revisa documentos, registra fechas y da seguimiento.</span>
    <small>Solo personal autorizado</small>
  </a>
  <?php endif; ?>
</div>
