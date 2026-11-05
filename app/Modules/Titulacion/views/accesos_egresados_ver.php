<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\AccesoEgresados;
include __DIR__ . '/_estilos.php';
$carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
$csrf = e(Auth::csrfToken());
$id = (int) $s['id'];
$accion = static fn(string $a): string => url('titulacion/accesos-egresados/' . $id . '/' . $a);
$etq = ['identificacion' => 'Identificación oficial', 'comprobante' => 'Comprobante de estudios'];
?>
<p><a href="<?= url('titulacion/accesos-egresados?estado=' . $s['estado']) ?>">← Accesos de egresados</a></p>
<h1><?= e($s['nombre']) ?></h1>
<p class="texto-secundario">Solicitud #<?= $id ?> · <?= e(AccesoEgresados::ESTADOS[$s['estado']] ?? $s['estado']) ?> · enviada el <?= e(date('d/m/Y H:i', strtotime((string) $s['creado_en']))) ?></p>
<div class="tit-dos">
  <div>
    <h2>Lo que declaró</h2>
    <ul>
      <li><strong>Cuenta:</strong> <?= e($s['cuenta']) ?></li>
      <li><strong>Licenciatura:</strong> <?= e($carreras[$s['carrera']] ?? $s['carrera']) ?></li>
      <li><strong>Correo personal:</strong> <?= e($s['email']) ?> <?= $s['verificado_en'] ? '(verificado ' . e(date('d/m/Y H:i', strtotime((string) $s['verificado_en']))) . ')' : '(sin verificar)' ?></li>
      <li><strong>Teléfono:</strong> <?= e((string) ($s['telefono'] ?: '—')) ?></li>
    </ul>
    <h2>Prevalidación con los registros del departamento</h2>
    <p><strong><?= e(['coincide' => 'Coincide', 'media' => 'Coincide a medias', 'ninguna' => 'No aparece'][(string) $s['prevalidacion']] ?? 'Aún no se calcula') ?></strong><?= $s['prevalidacion_detalle'] ? ': ' . e($s['prevalidacion_detalle']) : '' ?></p>
    <?php if ($otras): ?><p class="texto-secundario">Otras solicitudes con esa cuenta o correo: <?php foreach ($otras as $o): ?><a href="<?= url('titulacion/accesos-egresados/' . (int) $o['id']) ?>">#<?= (int) $o['id'] ?> (<?= e($o['estado']) ?>)</a> <?php endforeach; ?></p><?php endif; ?>
    <h2>Documentos</h2>
    <?php if ($docs): foreach ($docs as $tipo => $d): ?>
      <p><a class="btn" target="_blank" rel="noopener" href="<?= url('titulacion/accesos-egresados/' . $id . '/documento/' . (int) $d['id']) ?>"><?= e($etq[$tipo] ?? $tipo) ?></a>
      <small class="texto-secundario"><?= e($d['nombre_original']) ?> · <?= round((int) $d['tamano'] / 1024) ?> KB · huella <?= e(substr((string) $d['sha256'], 0, 12)) ?></small></p>
    <?php endforeach; else: ?><p class="texto-secundario">No hay documentos vigentes (se borran al resolver o aún no se envían).</p><?php endif; ?>
    <?php if (!empty($s['motivo'])): ?><p class="texto-secundario"><strong>Último motivo:</strong> <?= e($s['motivo']) ?></p><?php endif; ?>
  </div>
  <?php if ($s['estado'] === 'pendiente' || $s['estado'] === 'requiere_docs'): ?>
  <aside>
    <?php if ($s['estado'] === 'pendiente'): ?>
    <form method="post" action="<?= $accion('aprobar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <button class="btn btn-primario btn-bloque" type="submit" onclick="return confirm('¿Aprobar? Se crea la cuenta de egresado con acceso solo a Titulación.')">Aprobar acceso</button></form>
    <form method="post" action="<?= $accion('devolver') ?>" class="formulario" style="margin-top:14px"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Devolver para corregir<textarea name="motivo" rows="3" maxlength="500" required placeholder="Qué debe corregir o completar"></textarea></label>
      <button class="btn" type="submit">Devolver</button></form>
    <?php endif; ?>
    <form method="post" action="<?= $accion('rechazar') ?>" class="formulario" style="margin-top:14px"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Rechazar<textarea name="motivo" rows="3" maxlength="500" required placeholder="Motivo (se envía a la persona)"></textarea></label>
      <button class="btn" type="submit" onclick="return confirm('¿Rechazar la solicitud? Los documentos se borran.')">Rechazar</button></form>
  </aside>
  <?php endif; ?>
</div>
