<?php
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
$estados = Catalogo::estadosTramite();
$nombreOpcion = static fn(string $k): string => $modalidades[$k]['nombre'] ?? $k;
$rondaEstado = ['recibida' => 'Recibido', 'en_comision' => 'En la comisión', 'en_evaluacion' => 'En evaluación', 'por_dictaminar' => 'Por dictaminar', 'dictaminada' => 'Dictaminado', 'cancelada' => 'Cancelado'];
$asigEstado = ['pendiente' => 'Pendiente', 'borrador' => 'En borrador', 'firmada' => 'Firmada'];
$vacio = !$propios && !$gestion && !$revisiones && !$comision;
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Estado de los casos que te competen, según tu participación en el proceso.</p>
  <nav style="display:flex;gap:8px;margin-top:8px">
    <a class="btn btn-mini <?= $historico ? '' : 'btn-primario' ?>" href="<?= url('titulacion/seguimiento') ?>">En curso</a>
    <a class="btn btn-mini <?= $historico ? 'btn-primario' : '' ?>" href="<?= url('titulacion/seguimiento?ver=historico') ?>">Histórico</a>
  </nav>
</div></div>

<?php if ($firmas !== null && !$historico): ?>
<section class="tarjeta" style="margin-bottom:18px"><p><strong><?= (int) $firmas ?></strong> documento(s) esperan la firma de la Subdirección. <a href="<?= url('titulacion/firmas') ?>">Abrir bandeja de firmas</a></p></section>
<?php endif; ?>

<?php if ($vacio): ?>
  <div class="ux-empty"><?= $historico ? 'Aún no hay casos cerrados.' : 'No tienes casos en curso.' ?></div>
<?php endif; ?>

<?php if ($propios): ?>
<section class="tarjeta" style="margin-bottom:18px">
  <div class="tarjeta-cabecera"><h2>Mi titulación</h2></div>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Folio</th><th>Opción</th><th>Estado</th><th>Etapa</th><th>Actualizado</th></tr></thead>
    <tbody>
    <?php foreach ($propios as $t): ?>
      <tr>
        <td><a href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>"><?= e($t['folio']) ?></a></td>
        <td><?= e($nombreOpcion((string) $t['modalidad'])) ?></td>
        <td><?= e($estados[$t['estado']] ?? $t['estado']) ?></td>
        <td><?= e((string) $t['etapa_actual']) ?></td>
        <td><?= e(date('d/m/Y', strtotime($t['actualizado_en']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($gestion): ?>
<section class="tarjeta" style="margin-bottom:18px">
  <div class="tarjeta-cabecera"><h2>Trámites del departamento</h2></div>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Folio</th><th>Pasante</th><th>Opción</th><th>Estado</th><th>Por validar</th><th>Actualizado</th></tr></thead>
    <tbody>
    <?php foreach ($gestion as $t): ?>
      <tr>
        <td><a href="<?= url('titulacion/gestion/' . (int) $t['id']) ?>"><?= e($t['folio']) ?></a></td>
        <td><?= e($t['alumno_nombre']) ?></td>
        <td><?= e($nombreOpcion((string) $t['modalidad'])) ?></td>
        <td><?= e($estados[$t['estado']] ?? $t['estado']) ?></td>
        <td><?= (int) $t['por_validar'] ?></td>
        <td><?= e(date('d/m/Y', strtotime($t['actualizado_en']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($comision): ?>
<section class="tarjeta" style="margin-bottom:18px">
  <div class="tarjeta-cabecera"><h2>Protocolos de mi comisión</h2></div>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Folio</th><th>Pasante</th><th>Estado</th><th>Firmadas</th><th>Límite</th></tr></thead>
    <tbody>
    <?php foreach ($comision as $r): ?>
      <tr>
        <td><a href="<?= url('titulacion/comision/' . (int) $r['id']) ?>"><?= e($r['folio']) ?></a></td>
        <td><?= e($r['alumno_nombre']) ?></td>
        <td><?= e($rondaEstado[$r['estado']] ?? $r['estado']) ?></td>
        <td><?= (int) $r['firmadas'] ?>/<?= (int) $r['total'] ?></td>
        <td><?= $r['limite_pendiente'] ? e(date('d/m/Y', strtotime($r['limite_pendiente']))) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($revisiones): ?>
<section class="tarjeta">
  <div class="tarjeta-cabecera"><h2>Protocolos que me asignaron</h2></div>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Folio</th><th>Pasante</th><th>Estado</th><th>Fecha límite</th></tr></thead>
    <tbody>
    <?php foreach ($revisiones as $a): ?>
      <tr>
        <td><a href="<?= url('titulacion/revision/' . (int) $a['id']) ?>"><?= e($a['folio']) ?></a></td>
        <td><?= e($a['alumno_nombre']) ?></td>
        <td><?= e($asigEstado[$a['estado']] ?? $a['estado']) ?></td>
        <td><?= e(date('d/m/Y', strtotime($a['fecha_limite']))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
