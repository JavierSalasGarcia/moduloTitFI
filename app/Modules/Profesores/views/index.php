<?php $csrf = e(\App\Core\Auth::csrfToken()); ?>
<style>.rrhh-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px}.rrhh-dato{padding:14px}.rrhh-dato strong{display:block;font-size:26px}.rrhh-tabla{width:100%;border-collapse:collapse}.rrhh-tabla td,.rrhh-tabla th{padding:7px 8px;border-bottom:1px solid var(--gris-100,#eee);text-align:left;vertical-align:top}.rrhh-aviso{font-size:13px;margin:2px 0}@media(max-width:760px){.rrhh-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}</style>

<div class="rrhh-grid">
  <div class="card rrhh-dato"><strong><?= (int) $total['profesores'] ?></strong><span class="texto-suave">profesores en el directorio</span></div>
  <div class="card rrhh-dato"><strong><?= (int) $total['con_cuenta'] ?></strong><span class="texto-suave">con cuenta enlazada</span></div>
  <div class="card rrhh-dato"><strong><?= (int) $total['con_empleado'] ?></strong><span class="texto-suave">con número de empleado</span></div>
  <div class="card rrhh-dato"><strong><?= (int) $total['sin_correo'] ?></strong><span class="texto-suave">sin correo institucional</span></div>
</div>

<section class="card" style="padding:20px;margin-bottom:18px">
  <h3 style="margin-top:0">Subir RFC, número de empleado y correos</h3>
  <p class="texto-suave">El profesor <strong>no necesita</strong> dar su RFC ni su número de empleado para usar la plataforma: se registra con su correo institucional y su cuenta se enlaza sola con su registro del directorio. Estos datos que sube Recursos Humanos sirven como desambiguador interno (reconocer a la misma persona aunque cambie su correo o se escriba distinto su nombre) y <strong>nunca se muestran</strong> al profesor ni en esta pantalla.</p>
  <p class="texto-suave">Archivo CSV o Excel (.xlsx) con una fila por profesor. Columnas: <strong>RFC</strong> (o «Clave»); opcionales <strong>Número de empleado</strong> y <strong>Correo</strong> (el nuevo @uaemex.mx; puede haber varias columnas de correo); y, solo para dar de alta a quien falte, <strong>Nombre</strong>, <strong>Primer apellido</strong>, <strong>Segundo apellido</strong> y <strong>Género</strong> (H/M). No se cambian nombres ni géneros ya registrados.</p>
  <form method="post" action="<?= url('profesores/rrhh/subir') ?>" enctype="multipart/form-data" class="formulario" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Archivo<input type="file" name="archivo" accept=".csv,.xlsx" required></label>
    <button class="btn btn-primario" type="submit">Subir y aplicar</button>
  </form>
  <?php if ($resultado): $r = $resultado['resumen']; ?>
  <div style="margin-top:14px">
    <p><strong>Resultado:</strong> <?= (int) $r['filas'] ?> filas leídas · <?= (int) $r['actualizados'] ?> profesores actualizados · <?= (int) $r['creados'] ?> dados de alta · <?= (int) $r['correos'] ?> correos agregados · <?= (int) $r['cuentas_enlazadas'] ?> cuentas enlazadas · <?= (int) $r['con_aviso'] ?> con aviso.</p>
    <?php foreach ($resultado['avisos'] as $a): ?><p class="rrhh-aviso texto-suave"><?= e($a) ?></p><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="card" style="padding:20px;margin-bottom:18px">
  <h3 style="margin-top:0">Cuentas que parecen ser de un profesor del directorio</h3>
  <p class="texto-suave">Personas que ya se registraron con un correo que el directorio no conoce, pero cuyo nombre coincide con el de un profesor sin cuenta. Si es la misma persona, enlázalas: así no quedan duplicadas ni pierden sus constancias.</p>
  <?php if (!$porEnlazar): ?><p class="texto-suave">No hay coincidencias pendientes.</p><?php else: ?>
  <table class="rrhh-tabla"><thead><tr><th>Cuenta</th><th>Profesor del directorio</th><th></th></tr></thead><tbody>
    <?php foreach ($porEnlazar as $c): foreach ($c['candidatos'] as $cand): ?>
    <tr><td><?= e($c['nombre']) ?><br><small class="texto-suave"><?= e($c['email']) ?></small></td><td><?= e($cand['nombre']) ?></td>
      <td><form method="post" action="<?= url('profesores/rrhh/enlazar') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="usuario_id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="profesor_id" value="<?= (int) $cand['id'] ?>"><button class="btn" type="submit">Son la misma persona</button></form></td></tr>
    <?php endforeach; endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>

<section class="card" style="padding:20px;margin-bottom:18px">
  <h3 style="margin-top:0">Correo nuevo disponible para profesores con cuenta</h3>
  <p class="texto-suave">El directorio ya trae un correo @uaemex.mx distinto del de su cuenta. Si el profesor no puede entrar o cambiarlo él mismo, aquí puedes pasar su cuenta al correo nuevo; la cuenta es la misma y el correo anterior sigue reconocido para iniciar sesión.</p>
  <?php if (!$porPasar): ?><p class="texto-suave">No hay cambios pendientes.</p><?php else: ?>
  <table class="rrhh-tabla"><thead><tr><th>Profesor</th><th>Correo de su cuenta</th><th>Correo nuevo</th><th></th></tr></thead><tbody>
    <?php foreach ($porPasar as $c): ?>
    <tr><td><?= e($c['nombre']) ?></td><td><?= e($c['correo_actual']) ?></td><td><?= e($c['correo_directorio']) ?></td>
      <td><form method="post" action="<?= url('profesores/rrhh/correo') ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="usuario_id" value="<?= (int) $c['usuario_id'] ?>"><input type="hidden" name="email" value="<?= e($c['correo_directorio']) ?>"><button class="btn" type="submit">Usar el correo nuevo</button></form></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</section>

<?php if ($esAdmin): ?>
<section class="card" style="padding:20px">
  <h3 style="margin-top:0">Responsables de Recursos Humanos</h3>
  <p class="texto-suave">Quienes pueden subir este archivo y enlazar cuentas, además de la administración. Deben tener cuenta en la plataforma.</p>
  <?php foreach ($responsables as $r): ?>
  <form method="post" action="<?= url('profesores/rrhh/responsables/quitar') ?>" style="display:flex;gap:10px;align-items:center;margin:4px 0"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="usuario_id" value="<?= (int) $r['id'] ?>"><span><?= e($r['nombre']) ?> · <small class="texto-suave"><?= e($r['email']) ?></small></span><button class="btn" type="submit">Quitar</button></form>
  <?php endforeach; ?>
  <form method="post" action="<?= url('profesores/rrhh/responsables/agregar') ?>" class="formulario" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:10px">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Correo de la persona<input type="email" name="email" maxlength="75" required placeholder="usuario@uaemex.mx"></label>
    <button class="btn" type="submit">Agregar</button>
  </form>
</section>
<?php endif; ?>
