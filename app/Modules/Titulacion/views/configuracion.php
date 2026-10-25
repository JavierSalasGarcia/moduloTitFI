<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">La plataforma genera cada formato oficial con los datos del trámite; ya no hay plantillas ni coordenadas que ajustar. Los nombres y cargos de quien firma o a quien se dirige cada formato (Director, Subdirector Académico, Jefatura de Evaluación Profesional y Coordinadores de Docencia) se editan en Organización. Aquí defines qué documentos se piden y los parámetros del módulo.</p>
  </div>
  <a class="btn btn-primario" href="<?= url('titulacion/configuracion/documentos') ?>">Requisitos documentales</a>
</div>

<section class="tarjeta" style="margin-bottom:18px">
  <h2>Formatos que genera la plataforma</h2>
  <div class="tabla-contenedor">
  <table class="tabla">
    <thead><tr><th>Formato</th><th>Firma</th><th>Se usa en</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($formatos as $clave => $f): ?>
      <tr>
        <td><strong><?= e($f['nombre']) ?></strong></td>
        <td><?= e($f['firma']) ?></td>
        <td style="max-width:260px"><?= !empty($f['interno']) ? 'Flujo de revisión' : ($f['uso'] ? e(implode(', ', array_slice($f['uso'], 0, 4))) . (count($f['uso']) > 4 ? '…' : '') : 'Solicitudes adicionales del pasante') ?></td>
        <td><?php if ($clave !== 'protocolo'): ?><a class="btn btn-mini" href="<?= url('titulacion/configuracion/formatos/' . $clave . '/muestra') ?>" target="_blank" rel="noopener">Vista previa</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="tarjeta" id="parametros" style="margin-bottom:18px">
  <h2>Parámetros del módulo</h2>
  <form class="formulario" method="post" action="<?= url('titulacion/configuracion/parametros') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <h3>Revisión de protocolos</h3>
    <div class="tit-campos">
      <label>Revisores por protocolo <input type="number" name="revisores" min="1" max="9" value="<?= (int) $param['revisores'] ?>" required></label>
      <label>Plazo de cada revisor (días hábiles; después el semáforo pasa a rojo) <input type="number" name="dias" min="1" max="30" value="<?= (int) $param['dias'] ?>" required></label>
      <label>Firma de las revisiones y del dictamen
        <select name="firma"><option value="sello" <?= $param['firma'] === 'sello' ? 'selected' : '' ?>>Sello electrónico (nombre, fecha y huella)</option>
          <option value="pades" <?= $param['firma'] === 'pades' ? 'selected' : '' ?> <?= $param['pki'] ? '' : 'disabled' ?>>Firma PAdES con segundo factor<?= $param['pki'] ? '' : ' (PKI inactiva)' ?></option></select></label>
      <label>Vigencia extra del enlace del correo (días tras la fecha límite) <input type="number" name="gracia" min="1" max="60" value="<?= (int) $param['gracia'] ?>" required></label>
      <label>Vigencia del protocolo aprobado (años; después se pide oficio de prórroga) <input type="number" name="vigencia" min="1" max="10" value="<?= (int) $param['vigencia'] ?>" required></label>
    </div>
    <h3>Exámenes previo y definitivo</h3>
    <div class="tit-campos">
      <label>Anticipación mínima al elegir fecha (días hábiles) <input type="number" name="examen_dias" min="0" max="60" value="<?= (int) $param['examen']['dias'] ?>" required></label>
      <label>Hora más temprana de inicio <input type="time" name="examen_inicio" value="<?= e($param['examen']['inicio']) ?>" required></label>
      <label>Hora límite de término <input type="time" name="examen_fin" value="<?= e($param['examen']['fin']) ?>" required></label>
      <label>Duración que se aparta el espacio (minutos) <input type="number" name="examen_duracion" min="30" max="480" step="15" value="<?= (int) $param['examen']['duracion'] ?>" required></label>
      <label>Lugar de los exámenes (módulo Auditorios y salas)
        <select name="examen_espacio"><?php foreach ($param['espacios'] as $esp): ?><option value="<?= (int) $esp['id'] ?>" <?= (int) $esp['id'] === (int) $param['examen']['espacio'] ? 'selected' : '' ?>><?= e($esp['nombre']) ?></option><?php endforeach; ?></select></label>
      <label>Sinodales propietarios <input type="number" name="propietarios" min="1" max="5" value="<?= (int) $param['examen']['propietarios'] ?>" required></label>
      <label>Sinodales suplentes <input type="number" name="suplentes" min="0" max="5" value="<?= (int) $param['examen']['suplentes'] ?>" required></label>
    </div>
    <label style="display:block;margin-top:14px">Días no laborables (AAAA-MM-DD, uno por línea; no cuentan como hábiles)
      <textarea name="inhabiles" rows="4" placeholder="2026-11-02&#10;2026-11-16"><?= e($param['inhabiles']) ?></textarea></label>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Guardar parámetros</button></div>
  </form>
</section>

<section class="tarjeta">
  <h2>Últimos cambios</h2>
  <?php if (!$bitacora): ?><p class="texto-secundario">Sin cambios registrados.</p><?php endif; ?>
  <?php foreach ($bitacora as $b): ?>
    <div class="tit-evento"><?= e($b['detalle']) ?><time><?= e($b['nombre']) ?> · <?= e(fecha_corta($b['creado_en'])) ?></time></div>
  <?php endforeach; ?>
</section>
