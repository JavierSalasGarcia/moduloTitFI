<?php
/** @var bool $autorizada @var bool $marcada */
use App\Core\Auth;
use App\Modules\Consejos\Services\FirmaConfig;
include __DIR__ . '/_estilos.php';
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos')) ?>">← Consejos</a></p>
        <h1>Firmas de los Consejos</h1>
        <p class="cns-sub">Define con qué firma se firma cada documento. Hasta que los Consejos autoricen la firma electrónica, todo lo que se marque como «electrónica» se firma de forma autógrafa: se imprime, se firma a mano y se sube el escaneado.</p>
      </div>
    </div>
  </section>
  <form class="tarjeta cns-form" method="post" action="<?= e(url('consejos/firmas')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <table class="cns-tabla"><thead><tr><th>Documento</th><th>Firma</th><th>Hoy se firma con</th></tr></thead><tbody>
    <?php foreach (FirmaConfig::DOCUMENTOS as $doc => [$nombre, $opera]): ?>
      <tr>
        <td><?= e($nombre) ?><?php if (!$opera): ?><br><small class="cns-ayuda">Por ahora se firma siempre a mano y se sube el escaneado; esta opción se activará cuando el documento se firme dentro de la plataforma.</small><?php endif; ?></td>
        <td><select name="modo_<?= e($doc) ?>" <?= $opera ? '' : 'disabled' ?>><?php foreach (FirmaConfig::MODOS as $k => $txt): ?><option value="<?= e($k) ?>"<?= FirmaConfig::configurado($doc) === $k ? ' selected' : '' ?>><?= e($txt) ?></option><?php endforeach; ?></select></td>
        <td><?= $opera ? (FirmaConfig::efectivo($doc) === 'interna' ? 'Firma interna' : 'Firma autógrafa') : 'Firma autógrafa' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
    <label style="margin-top:14px;display:flex;gap:8px;align-items:flex-start">
      <input type="checkbox" name="electronica" value="1" <?= $marcada ? 'checked' : '' ?>>
      <span>Los Consejos ya autorizaron la firma electrónica.<br><small class="cns-ayuda">Solo surte efecto cuando, además, la firma PAdES de la plataforma está habilitada<?= $autorizada ? ' (ya lo está)' : ' (hoy no lo está, así que sigue operando la firma autógrafa)' ?>.</small></span>
    </label>
    <div class="cns-acciones" style="margin-top:14px"><button class="btn btn-primario" type="submit">Guardar</button></div>
  </form>
  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Cómo funciona cada modo</h2>
    <ul style="margin:0;padding-left:20px;line-height:1.6">
      <li><strong>Autógrafa:</strong> el oficio se genera en la hoja membretada con los espacios de firma; se imprime, firman quien solicita y la coordinación, y se sube el escaneado.</li>
      <li><strong>Firma interna:</strong> quien solicita y quien da el Vo. Bo. firman dentro de la plataforma; el oficio recibe un sello de conformidad con la huella del documento (registro interno, no firma electrónica).</li>
      <li><strong>Electrónica:</strong> queda reservada para cuando los Consejos la autoricen.</li>
    </ul>
  </section>
</div>
