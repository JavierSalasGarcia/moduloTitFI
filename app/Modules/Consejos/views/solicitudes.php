<?php
/** @var array $mias @var array $revisar @var array $revisadas @var array $cartera @var array $reuniones */
use App\Core\Auth;
use App\Modules\Consejos\Services\Solicitudes;
include __DIR__ . '/_estilos.php';
$etq = ['borrador' => '', 'enviada' => '', 'visto_bueno' => 'ok', 'devuelta' => 'no', 'incorporada' => 'ok', 'cancelada' => ''];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <h1>Presentar un asunto a los Consejos</h1>
        <p class="cns-sub">Redacta una solicitud para los HH. Consejos Académico y de Gobierno. La revisa la coordinación (Vo. Bo. o de enterado) y, con él, pasa al acta de la siguiente reunión.</p>
      </div>
      <div class="cns-acciones">
        <a class="btn" href="<?= e(url('consejos/manual')) ?>">Manual de captura</a>
        <a class="btn btn-primario" href="<?= e(url('consejos/solicitudes/nueva')) ?>">Nueva solicitud</a>
      </div>
    </div>
  </section>

  <?php if ($cartera): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Cartera de casos para el Consejo (<?= count($cartera) ?>)</h2>
    <p class="cns-sub" style="margin:0 0 8px">Solicitudes con el Vo. Bo. de la coordinación, listas para llevarse a una reunión.</p>
    <table class="cns-tabla"><thead><tr><th>Folio</th><th>Quién solicita</th><th>Solicitud</th><th>Coordinación</th><th>Vo. Bo.</th><th></th></tr></thead><tbody>
    <?php foreach ($cartera as $c): ?>
      <tr><td><?= e($c['folio']) ?></td><td><?= e($c['solicitante']) ?></td>
        <td><?= e(mb_strimwidth(trim(\App\Modules\Consejos\Services\Html::texto((string) $c['solicitud_html'])), 0, 90, '…')) ?></td>
        <td><?= e($c['coordinacion']) ?></td><td><?= e(date('d/m/Y', strtotime((string) $c['vobo_en']))) ?></td>
        <td><a class="btn btn-mini" href="<?= e(url('consejos/solicitudes/' . $c['id'])) ?>">Abrir</a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php if ($reuniones): ?>
    <form method="post" action="<?= e(url('consejos/solicitudes/cartera')) ?>" class="cns-acciones" style="margin-top:10px"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <label style="margin:0">Llevar toda la cartera a la reunión<select name="reunion_id"><?php foreach ($reuniones as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['etiqueta']) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-primario" type="submit">Incorporar la cartera como casos</button>
    </form>
    <?php else: ?><p class="cns-ayuda" style="margin-top:8px">No hay una reunión en preparación: crea la reunión del mes y vuelve para incorporar la cartera.</p><?php endif; ?>
  </section>
  <?php endif; ?>

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
