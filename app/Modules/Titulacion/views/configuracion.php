<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">Edita las plantillas y coordenadas de cada formato, retira los que ya no hagan falta y define qué documentos se piden, cuáles se entregan en físico y cuáles requieren cotejo del original. Los cambios aplican desde ya a todos los trámites.</p>
  </div>
  <a class="btn btn-primario" href="<?= url('titulacion/configuracion/documentos') ?>">Requisitos documentales</a>
</div>

<section class="tarjeta" style="margin-bottom:18px">
  <h2>Formatos</h2>
  <div class="tabla-contenedor">
  <table class="tabla">
    <thead><tr><th>Formato</th><th>Se usa en</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($formatos as $clave => $f): ?>
      <tr id="f-<?= e($clave) ?>">
        <td><strong><?= e($f['nombre']) ?></strong><br><span class="texto-secundario"><?= $f['tipo'] === 'pdf' ? 'PDF · ' . (int) $f['campos'] . ' campos' : 'Word sobre hoja membretada' ?> · firma: <?= e($f['firma']) ?></span></td>
        <td style="max-width:260px"><?= !empty($f['interno']) ? 'Lo genera la revisión de protocolos' : ($f['uso'] ? e(implode(', ', array_slice($f['uso'], 0, 4))) . (count($f['uso']) > 4 ? '…' : '') : '<span class="texto-secundario">Sin uso</span>') ?></td>
        <td>
          <span class="tit-chip <?= $f['activo'] ? 'ok' : 'na' ?>"><?= $f['activo'] ? 'Activo' : 'Retirado' ?></span>
          <?php if ($f['personalizado']): ?><span class="tit-chip rev">Añadido</span><?php elseif ($f['modificado']): ?><span class="tit-chip pend">Modificado</span><?php endif; ?>
        </td>
        <td>
          <?php if ($f['tipo'] === 'pdf'): ?><a class="btn btn-mini btn-primario" href="<?= url('titulacion/configuracion/formatos/' . $clave) ?>">Visor de coordenadas</a><?php endif; ?>
          <details style="margin-top:6px"><summary>Plantilla y datos</summary>
            <form class="formulario" method="post" enctype="multipart/form-data" action="<?= url('titulacion/configuracion/formatos/' . $clave . '/reemplazar') ?>" style="margin:8px 0">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <label>Reemplazar <?= $f['tipo'] === 'pdf' ? 'plantilla (PDF)' : 'hoja membretada (.docx)' ?>
                <input type="file" name="plantilla" accept="<?= $f['tipo'] === 'pdf' ? 'application/pdf,.pdf' : '.docx' ?>" required></label>
              <button class="btn btn-mini" type="submit">Reemplazar</button>
            </form>
            <form class="formulario" method="post" action="<?= url('titulacion/configuracion/formatos/' . $clave . '/datos') ?>" style="margin:8px 0">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <label>Nombre <input type="text" name="nombre" maxlength="200" value="<?= e($f['nombre']) ?>"></label>
              <label>Quién firma <input type="text" name="firma" maxlength="200" value="<?= e($f['firma']) ?>"></label>
              <?php if ($f['personalizado']): ?>
                <label>Etapa
                  <select name="etapa"><?php foreach ($etapas as $ec => $et): ?><option value="<?= e($ec) ?>" <?= $f['etapa'] === $ec ? 'selected' : '' ?>><?= e($et) ?></option><?php endforeach; ?></select></label>
                <label style="display:flex;gap:6px"><input type="checkbox" name="todas" value="1" <?= $f['modalidades'] === '*' ? 'checked' : '' ?>> En todas las opciones de esa etapa</label>
                <label>…o solo en
                  <select name="modalidades[]" multiple size="5"><?php foreach ($modalidades as $mc => $m): ?><option value="<?= e($mc) ?>" <?= in_array($mc, explode(',', (string) $f['modalidades']), true) ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?></select></label>
              <?php endif; ?>
              <label style="display:flex;gap:6px"><input type="checkbox" name="activo" value="1" <?= $f['activo'] ? 'checked' : '' ?>> Activo (si lo desmarcas, deja de generarse y pedirse)</label>
              <button class="btn btn-mini btn-primario" type="submit">Guardar</button>
            </form>
            <?php if ($f['personalizado']): ?>
              <form method="post" action="<?= url('titulacion/configuracion/formatos/' . $clave . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar este formato añadido?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini btn-peligro" type="submit">Eliminar formato</button></form>
            <?php elseif ($f['modificado']): ?>
              <form method="post" action="<?= url('titulacion/configuracion/formatos/' . $clave . '/restablecer') ?>" onsubmit="return confirm('¿Volver a la plantilla y coordenadas originales?')"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Restablecer al original</button></form>
            <?php endif; ?>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="tarjeta" style="margin-bottom:18px">
  <h2>Añadir un formato</h2>
  <p class="texto-secundario">Sube la plantilla en PDF; después colocarás sobre ella los campos de datos (nombre, cuenta, carrera, fechas…) en el visor.</p>
  <form class="formulario" method="post" enctype="multipart/form-data" action="<?= url('titulacion/configuracion/formatos/nuevo') ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <div class="tit-campos">
      <label>Nombre del formato <input type="text" name="nombre" maxlength="200" required></label>
      <label>Quién firma <input type="text" name="firma" maxlength="200" placeholder="el pasante"></label>
      <label>Etapa donde se genera
        <select name="etapa" required><?php foreach ($etapas as $ec => $et): ?><option value="<?= e($ec) ?>"><?= e($et) ?></option><?php endforeach; ?></select></label>
      <label>Plantilla (PDF) <input type="file" name="plantilla" accept="application/pdf,.pdf" required></label>
      <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="todas" value="1" checked> En todas las opciones que tengan esa etapa</label>
      <label>…o solo en estas opciones
        <select name="modalidades[]" multiple size="5"><?php foreach ($modalidades as $mc => $m): ?><option value="<?= e($mc) ?>"><?= e($m['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Subir y abrir el visor</button></div>
  </form>
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
