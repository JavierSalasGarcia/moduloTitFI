<?php
/** @var array $o @var array $a @var array $r @var string $clave @var array $archivos */
use App\Core\Auth;
use App\Modules\Consejos\Services\Dictamenes;
include __DIR__ . '/_estilos.php';
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$desactualizado = (int) $a['version_acuerdo'] > (int) $o['version_acuerdo'];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'] . '/dictamenes')) ?>">← Dictámenes · <?= e($r['etiqueta']) ?></a></p>
        <h1>Oficio <?= e((string) $o['numero']) ?> <span class="cns-etq"><?= e($clave) ?></span></h1>
      </div>
      <a class="btn" href="<?= e(url('consejos/oficio/' . $o['id'] . '/descargar')) ?>">Descargar este oficio en Word</a>
    </div>
    <?php if ($desactualizado): ?><p class="flash flash-alerta" style="margin:10px 0 0">El acuerdo de este caso cambió después de generar el oficio. Usa «Regenerar» en la lista para redactarlo de nuevo.</p><?php endif; ?>
    <?php if ($o['revisar']): ?><p class="flash flash-alerta" style="margin:10px 0 0">El sistema no estuvo seguro de algo (destinatario, cuerpo o acuerdo). Revísalo: al pasar el oficio a «Revisado» se quita la marca.</p><?php endif; ?>
  </section>

  <form class="tarjeta cns-form" method="post" action="<?= e(url('consejos/oficio/' . $o['id'])) ?>">
    <?= $csrf ?>
    <div class="cns-fila">
      <label>Fecha del oficio<input type="date" name="fecha" value="<?= e((string) $o['fecha']) ?>"></label>
      <label>Estado<select name="estado"><?php foreach (['borrador' => 'Borrador', 'revisado' => 'Revisado', 'emitido' => 'Emitido'] as $k => $n): ?><option value="<?= e($k) ?>"<?= $o['estado'] === $k ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Destinatario (una línea por renglón)<textarea name="destinatario" rows="2"><?= e((string) $o['destinatario']) ?></textarea></label>
    <label>Cargo (una línea por renglón)<textarea name="cargo" rows="2"><?= e((string) $o['cargo']) ?></textarea></label>
    <label>Cuerpo (llega hasta «llegando al siguiente:»)<textarea name="cuerpo" rows="5"><?= e((string) $o['cuerpo']) ?></textarea></label>
    <label>Acuerdo<textarea name="acuerdo" rows="5"><?= e((string) $o['acuerdo']) ?></textarea></label>
    <p class="cns-ayuda">No se puede pasar a «Revisado» o «Emitido» un oficio sin destinatario, con campos entre corchetes o sin cuerpo o acuerdo.</p>
    <div class="cns-acciones"><button class="btn btn-primario" type="submit">Guardar</button></div>
  </form>

  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:17px">Dictamen firmado y sellado (escaneado)</h2>
    <p class="cns-sub">Lo verá en «Comisiones» cada profesor mencionado en el caso, cuando termine la sesión del Consejo de Gobierno.</p>
    <?php foreach ($archivos as $f): ?>
      <p><a href="<?= e(url('consejos/archivo/' . $f['id'])) ?>" target="_blank" rel="noopener">📄 <?= e($f['nombre']) ?></a> · <?= e(date('d/m/Y', strtotime((string) $f['creado_en']))) ?>
        <form method="post" action="<?= e(url('consejos/archivo/' . $f['id'] . '/quitar')) ?>" style="display:inline" data-confirm="¿Retirar el escaneado?"><?= $csrf ?><button class="btn btn-mini" type="submit">Quitar</button></form></p>
    <?php endforeach; ?>
    <form class="cns-acciones" method="post" enctype="multipart/form-data" action="<?= e(url('consejos/oficio/' . $o['id'] . '/escaneado')) ?>">
      <?= $csrf ?>
      <input type="file" name="archivo" accept="application/pdf" required>
      <label style="margin:0;font-weight:400"><input type="checkbox" name="avisar" value="1" checked> Notificar el dictamen por correo</label>
      <?php $campo = 'correos[]'; $url = url('consejos/personas') . '?alumnos=1'; $seleccion = []; $id = ''; $max = 0; $libre = true; $valor = 'email'; $placeholder = 'Agregar a otra persona o correo (opcional)'; $etiqueta = 'Otros destinatarios de la notificación';
            include dirname(__DIR__, 3) . '/Views/selector_personas.php'; ?>
      <button class="btn" type="submit"><?= $archivos ? 'Reemplazar' : 'Subir PDF' ?></button>
    </form>
    <p class="cns-ayuda">«Notificar dictamen» envía un correo a los profesores mencionados en el caso (y a quien agregues) con un enlace personal: al abrirlo se les pide iniciar sesión con ese correo, o crear su cuenta si aún no tienen, y entonces ven y descargan el PDF. El enlace vence en 60 días y solo funciona con la cuenta de ese correo.</p>
    <?php if ($archivos): ?>
    <form class="cns-acciones" method="post" action="<?= e(url('consejos/oficio/' . $o['id'] . '/avisar')) ?>">
      <?= $csrf ?>
      <?php $campo = 'correos[]'; $url = url('consejos/personas') . '?alumnos=1'; $seleccion = []; $id = ''; $max = 0; $libre = true; $valor = 'email'; $placeholder = 'Agregar a otra persona o correo (opcional)'; $etiqueta = 'Otros destinatarios de la notificación';
            include dirname(__DIR__, 3) . '/Views/selector_personas.php'; ?>
      <button class="btn" type="submit">✉️ Notificar dictamen</button>
      <?php if (!empty($o['avisado_en'])): ?><span class="cns-ayuda">Último aviso: <?= e(date('d/m/Y H:i', strtotime((string) $o['avisado_en']))) ?></span><?php endif; ?>
    </form>
    <?php endif; ?>
  </section>
</div>
