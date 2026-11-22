<?php
/** @var array $mias @var array $revisar @var array $revisadas */
use App\Modules\Consejos\Services\Solicitudes;
include __DIR__ . '/_estilos.php';
$etq = ['borrador' => '', 'enviada' => '', 'visto_bueno' => 'ok', 'devuelta' => 'no', 'incorporada' => 'ok', 'cancelada' => ''];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <h1>Solicitudes a los Consejos</h1>
        <p class="cns-sub">Redacta una solicitud para los HH. Consejos Académico y de Gobierno. La revisa la coordinación (Vo. Bo. o de enterado) y, con él, pasa al acta de la siguiente reunión.</p>
      </div>
      <div class="cns-acciones">
        <a class="btn" href="<?= e(url('consejos/manual')) ?>">Manual de captura</a>
        <a class="btn btn-primario" href="<?= e(url('consejos/solicitudes/nueva')) ?>">Nueva solicitud</a>
      </div>
    </div>
  </section>

  <?php if ($revisar): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Esperan tu Vo. Bo. (<?= count($revisar) ?>)</h2>
    <table class="cns-tabla"><thead><tr><th>Folio</th><th>Quién solicita</th><th>Enviada</th><th></th></tr></thead><tbody>
    <?php foreach ($revisar as $r): ?>
      <tr><td><?= e($r['folio']) ?></td><td><?= e($r['solicitante']) ?></td><td><?= e(date('d/m/Y', strtotime((string) $r['enviado_en']))) ?></td>
        <td><a class="btn btn-primario btn-mini" href="<?= e(url('consejos/solicitudes/' . $r['id'])) ?>">Revisar</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>

  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Mis solicitudes</h2>
    <?php if (!$mias): ?>
      <p class="cns-sub">Aún no has redactado ninguna. Empieza con «Nueva solicitud»: eliges el tipo de trámite y el sistema te da la redacción de las actas.</p>
    <?php else: ?>
    <table class="cns-tabla"><thead><tr><th>Folio</th><th>Solicitud</th><th>Coordinación</th><th>Estado</th><th></th></tr></thead><tbody>
    <?php foreach ($mias as $m): ?>
      <tr><td><?= e($m['folio']) ?></td>
        <td><?= e(mb_strimwidth(trim(\App\Modules\Consejos\Services\Html::texto((string) $m['solicitud_html'])), 0, 110, '…')) ?></td>
        <td><?= e($m['coordinacion']) ?></td>
        <td><span class="cns-etq <?= e($etq[$m['estado']] ?? '') ?>"><?= e(Solicitudes::ESTADOS[$m['estado']] ?? $m['estado']) ?></span></td>
        <td><a class="btn btn-mini" href="<?= e(url('consejos/solicitudes/' . $m['id'])) ?>">Abrir</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
  </section>

  <?php if ($revisadas): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Revisadas por ti</h2>
    <table class="cns-tabla"><tbody>
    <?php foreach ($revisadas as $r): ?>
      <tr><td><?= e($r['folio']) ?></td><td><?= e($r['solicitante']) ?></td><td><span class="cns-etq <?= e($etq[$r['estado']] ?? '') ?>"><?= e(Solicitudes::ESTADOS[$r['estado']] ?? $r['estado']) ?></span></td>
        <td><a class="btn btn-mini" href="<?= e(url('consejos/solicitudes/' . $r['id'])) ?>">Abrir</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </section>
  <?php endif; ?>
</div>
