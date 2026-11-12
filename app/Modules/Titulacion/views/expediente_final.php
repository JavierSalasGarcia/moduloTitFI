<?php
use App\Core\Auth;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\ExpedienteFinal;
include __DIR__ . '/_estilos.php';
$base = 'titulacion/expedientes/' . (int) $t['id'];
$estado = $exp['estado'] ?? 'en_integracion';
$editable = $elegible && $estado === 'en_integracion';
$chips = ['ok' => ['ok', 'Cumple'], 'no_aplica' => ['na', 'No aplica'], 'falta' => ['mal', 'Falta']];
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/expedientes') ?>">← Expedientes para Servicios Escolares</a></p>
    <h1>Verificación final · <?= e($t['folio']) ?></h1>
    <p class="texto-secundario"><?= e((string) $t['alumno_nombre']) ?> · <?= e($modalidad['nombre'] ?? $t['modalidad']) ?> · Resultado: <strong><?= e((string) $t['resultado']) ?></strong>
      · <a href="<?= url('titulacion/gestion/' . (int) $t['id']) ?>">Ver el trámite</a></p>
  </div>
  <div style="text-align:right">
    <span class="tit-chip <?= $estado === 'en_integracion' ? 'rev' : ($estado === 'verificado' ? 'ok' : 'na') ?>"><?= ['en_integracion' => 'En integración', 'verificado' => 'Verificado', 'enviado' => 'Enviado a Servicios Escolares'][$estado] ?></span>
    <?php if ($exp && $exp['verificado_en']): ?><div class="texto-secundario">Verificó: <?= e((string) $exp['verificador']) ?> · <?= e(fecha_corta($exp['verificado_en'])) ?></div><?php endif; ?>
    <?php if ($exp && $exp['enviado_en']): ?><div class="texto-secundario">Enviado: <?= e(fecha_corta($exp['enviado_en'])) ?></div><?php endif; ?>
  </div>
</div>

<?php if (!$elegible): ?>
  <section class="tarjeta"><p>El expediente se integra cuando la evaluación profesional concluyó con resultado favorable. Este trámite aún no cumple esa condición.</p></section>
<?php else: ?>
<form method="post" action="<?= url($base . '/guardar') ?>">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
  <section class="tarjeta">
    <h2 style="font-size:16px"><?= e(ExpedienteFinal::titulo((string) $t['modalidad'])) ?></h2>
    <p class="texto-secundario">Documentos para solicitar la elaboración del título profesional, en el orden del formato oficial. Lo validado en la plataforma se marca solo; lo que se entrega en físico lo confirmas tú contra el expediente.</p>
    <div class="tabla-contenedor"><table class="tabla">
      <thead><tr><th style="width:36px">#</th><th>Documento</th><th>Estado</th><th>Confirmación</th></tr></thead>
      <tbody>
      <?php foreach ($items as $n => $i): [$cl, $et] = $chips[$i['estado']]; $v = $i['estado'] === 'falta' ? '' : $i['estado']; ?>
        <tr>
          <td><?= $n + 1 ?></td>
          <td><?= e($i['texto']) ?><?php if ($i['detalle']): ?><div class="texto-secundario"><?= e($i['detalle']) ?></div><?php endif; ?>
              <?php if ($i['origen'] === 'plataforma' && !isset($i['generado']) && $i['archivos']): ?><div class="texto-secundario">En la plataforma: <?= count($i['archivos']) ?> archivo(s) PDF.</div><?php endif; ?></td>
          <td><span class="tit-chip <?= $cl ?>"><?= e($et) ?></span></td>
          <td>
            <?php if ($i['manual'] && $editable): ?>
              <select name="m[<?= e($i['k']) ?>]" aria-label="Confirmación de <?= e($i['k']) ?>">
                <option value="">Pendiente</option>
                <option value="ok" <?= $v === 'ok' ? 'selected' : '' ?>>Entregado y revisado</option>
                <?php if ($i['cond']): ?><option value="no_aplica" <?= $v === 'no_aplica' ? 'selected' : '' ?>>No aplica</option><?php endif; ?>
              </select>
            <?php elseif ($i['manual']): ?><?= $i['estado'] === 'ok' ? 'Entregado y revisado' : ($i['estado'] === 'no_aplica' ? 'No aplica' : '—') ?>
            <?php else: ?><span class="texto-secundario"><?= isset($i['generado']) ? 'La plataforma lo genera' : 'Automático' ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="tarjeta" style="margin-top:18px">
    <label>Observaciones para Servicios Escolares (opcional)
      <textarea name="observaciones" rows="3" maxlength="1000" <?= $editable ? '' : 'readonly' ?>><?= e((string) ($exp['observaciones'] ?? '')) ?></textarea></label>
    <div class="acciones-formulario" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
      <?php if ($editable): ?>
        <button class="btn" type="submit" name="accion" value="guardar">Guardar lista</button>
        <button class="btn btn-primario" type="submit" name="accion" value="verificar">Hago constar que el expediente está completo y verificado</button>
      <?php endif; ?>
      <a class="btn" href="<?= url($base . '/formato') ?>">Descargar formato de integración (PDF)</a>
    </div>
    <?php if ($editable && !$completo): ?><p class="texto-secundario">Faltan renglones por cubrir; no se puede verificar hasta que todos cumplan o estén marcados como «No aplica».</p><?php endif; ?>
  </section>
</form>
<?php if ($estado === 'verificado'): ?>
  <section class="tarjeta" style="margin-top:18px">
    <form method="post" action="<?= url('titulacion/expedientes/exportar') ?>" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="ids[]" value="<?= (int) $t['id'] ?>">
      <button class="btn btn-primario" type="submit">Exportar este expediente para Servicios Escolares (ZIP)</button></form>
    <form method="post" action="<?= url($base . '/reabrir') ?>" style="display:inline" onsubmit="return confirm('¿Reabrir la verificación?')"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button class="btn" type="submit">Reabrir verificación</button></form>
  </section>
<?php elseif ($estado === 'enviado'): ?>
  <section class="tarjeta" style="margin-top:18px">
    <form method="post" action="<?= url('titulacion/expedientes/exportar') ?>"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><input type="hidden" name="ids[]" value="<?= (int) $t['id'] ?>">
      <button class="btn" type="submit">Volver a descargar la carpeta comprimida</button></form>
  </section>
<?php endif; endif; ?>
