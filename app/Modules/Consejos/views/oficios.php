<?php
/** @var array $r @var array $oficios @var int $sinOficio @var int $sinAcuerdoGobierno @var int $siguiente */
use App\Core\Auth;
use App\Modules\Consejos\Services\Texto;
include __DIR__ . '/_estilos.php';
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$estados = ['borrador' => ['Borrador', 'av'], 'revisado' => ['Revisado', ''], 'emitido' => ['Emitido', 'ok']];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/reunion/' . $r['id'])) ?>">← <?= e($r['etiqueta']) ?></a></p>
        <h1>Dictámenes</h1>
        <p class="cns-sub">Un oficio por caso (y por alumno cuando el caso trae una tabla de alumnos), redactado con el acuerdo final del Consejo de Gobierno. Lo resaltado en amarillo hay que confirmarlo.</p>
      </div>
      <?php if ($oficios): ?><a class="btn btn-primario" href="<?= e(url('consejos/reunion/' . $r['id'] . '/dictamenes/descargar')) ?>">Descargar todos en Word</a><?php endif; ?>
    </div>
    <?php if ($sinAcuerdoGobierno): ?><p class="flash flash-alerta" style="margin:10px 0 0"><?= $sinAcuerdoGobierno ?> caso<?= $sinAcuerdoGobierno > 1 ? 's' : '' ?> sin acuerdo del Consejo de Gobierno: su oficio saldrá con el del Consejo Académico y marcado para revisión.</p><?php endif; ?>
  </section>

  <section class="tarjeta">
    <form class="cns-form cns-acciones" method="post" action="<?= e(url('consejos/reunion/' . $r['id'] . '/dictamenes/generar')) ?>" style="align-items:flex-end">
      <?= $csrf ?>
      <label style="margin:0">Fecha de los oficios<input type="date" name="fecha" required value="<?= e((string) ($r['gobierno_fecha'] ?: date('Y-m-d'))) ?>"></label>
      <label style="margin:0">Primer número de oficio<input type="text" name="inicio" inputmode="numeric" pattern="\d*" value="" placeholder="<?= (int) $siguiente ?>" style="max-width:120px"><span class="cns-ayuda">Vacío: sigue el consecutivo (<?= (int) $siguiente ?>).</span></label>
      <button class="btn btn-primario" type="submit" <?= $sinOficio ? '' : 'disabled' ?>><?= $sinOficio ? 'Generar los ' . $sinOficio . ' casos sin oficio' : 'Todos los casos ya tienen oficio' ?></button>
    </form>
  </section>

  <?php if ($oficios): ?>
  <section class="tarjeta">
    <table class="cns-tabla">
      <thead><tr><th>Caso</th><th>Oficio</th><th>Destinatario</th><th>Estado</th><th>Escaneado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($oficios as $o): $d = (int) $o['version_asunto'] > (int) $o['version_acuerdo']; $est = $estados[$o['estado']]; ?>
        <tr class="<?= $o['revisar'] ? 'cns-amarillo' : '' ?>">
          <td class="cns-clave"><?= e(Texto::clave($o['grupo'], (int) $o['asunto_numero'])) ?></td>
          <td><?= e($o['numero']) ?></td>
          <td><?= e(str_replace("\n", ' · ', (string) $o['destinatario'])) ?></td>
          <td>
            <span class="cns-etq <?= e($est[1]) ?>"><?= e($est[0]) ?></span>
            <?php if ($o['revisar']): ?><span class="cns-etq av">Revisar</span><?php endif; ?>
            <?php if ($d): ?><span class="cns-etq no" title="El acuerdo cambió después de generar el oficio">Desactualizado</span><?php endif; ?>
          </td>
          <td><?= $o['archivo_id'] ? '<a href="' . e(url('consejos/archivo/' . $o['archivo_id'])) . '" target="_blank" rel="noopener">📄 Ver</a>' : '—' ?></td>
          <td class="cns-acciones">
            <a class="btn btn-mini" href="<?= e(url('consejos/oficio/' . $o['id'])) ?>">Revisar</a>
            <?php if ($d && $o['estado'] !== 'emitido'): ?><form method="post" action="<?= e(url('consejos/oficio/' . $o['id'] . '/regenerar')) ?>" data-confirm="¿Redactar de nuevo el oficio con los acuerdos actuales? Se pierden los cambios hechos a mano."><?= $csrf ?><button class="btn btn-mini" type="submit">Regenerar</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>
