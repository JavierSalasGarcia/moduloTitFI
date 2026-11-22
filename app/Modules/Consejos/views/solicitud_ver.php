<?php
/** @var array $s @var array $solicitante @var ?array $caso @var array $reuniones */
use App\Core\Auth;
use App\Modules\Consejos\ConsejosAccess;
use App\Modules\Consejos\Services\FirmaConfig;
use App\Modules\Consejos\Services\Solicitudes;
include __DIR__ . '/_estilos.php';
$uid = (int) Auth::id();
$soy = (int) $s['solicitante_id'] === $uid;
$coord = Solicitudes::esCoordinadorDe($s, $uid);
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$modoSol = $s['modo_solicitante'] ?: FirmaConfig::efectivo('solicitud_solicitante');
$modoVobo = $s['modo_vobo'] ?: FirmaConfig::efectivo('solicitud_vobo');
$necesitaEscaneado = $modoSol === 'autografa' || $modoVobo === 'autografa';
$nombreSol = $solicitante ? Solicitudes::conGrado($solicitante) : '';
$ruta = static fn(string $x): string => e(url('consejos/solicitudes/' . $x));
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <p class="cns-sub" style="margin:0"><a href="<?= e(url('consejos/solicitudes')) ?>">← Solicitudes</a></p>
        <h1>Solicitud <?= e($s['folio']) ?></h1>
        <p class="cns-sub"><?= e($nombreSol) ?> · para el Vo. Bo. de <strong><?= e($s['coordinacion']) ?></strong></p>
      </div>
      <span class="cns-etq <?= in_array($s['estado'], ['visto_bueno', 'incorporada'], true) ? 'ok' : ($s['estado'] === 'devuelta' ? 'no' : '') ?>"><?= e(Solicitudes::ESTADOS[$s['estado']] ?? $s['estado']) ?></span>
    </div>
    <?php if ($s['estado'] === 'devuelta' && $s['comentario']): ?><p style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:var(--radio);padding:10px 12px"><strong>La coordinación pidió corregir:</strong> <?= e((string) $s['comentario']) ?></p><?php endif; ?>
    <?php if ($caso): ?><p>Quedó como caso <strong><?= e(\App\Modules\Consejos\Services\Texto::clave((string) $caso['grupo'], (int) $caso['numero'])) ?></strong> de la reunión <a href="<?= e(url('consejos/reunion/' . $caso['reunion'])) ?>"><?= e($caso['etiqueta']) ?></a><?= ConsejosAccess::esGestor() ? '' : '.' ?></p><?php endif; ?>
    <?php if ($s['estado'] === 'visto_bueno'): ?><p class="cns-sub">Tiene el <?= $s['vobo_tipo'] === 'enterado' ? 'de enterado' : 'Vo. Bo.' ?> de la coordinación y entrará al acta de la siguiente reunión en cuanto se abra.</p><?php endif; ?>
  </section>

  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Solicitud</h2>
    <div class="cns-doc"><?= $s['solicitud_html'] ?></div>
    <?php if (trim(strip_tags((string) $s['antecedentes_html'])) !== ''): ?><h3 style="margin:14px 0 4px;font-size:15px">Documentos y/o antecedentes</h3><div class="cns-doc"><?= $s['antecedentes_html'] ?></div><?php endif; ?>
    <p class="cns-ayuda" style="margin-top:12px"><?= e(Solicitudes::fundamento()) ?></p>
  </section>

  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Oficio y firmas</h2>
    <?php if ($s['oficio_ruta']): ?>
      <p><a class="btn" href="<?= $ruta($s['id'] . '/oficio') ?>" target="_blank" rel="noopener">Ver oficio en hoja membretada (PDF)</a>
         <a class="btn" href="<?= $ruta($s['id'] . '/oficio?descargar=1') ?>">Descargar</a></p>
    <?php else: ?><p class="cns-sub">El oficio en hoja membretada se genera al enviar la solicitud.</p><?php endif; ?>
    <ul style="margin:6px 0 10px;padding-left:20px">
      <li>Firma de quien solicita: <strong><?= $modoSol === 'interna' ? 'firma interna de la plataforma (sello de conformidad)' : 'autógrafa' ?></strong></li>
      <li>Vo. Bo. de la coordinación: <strong><?= $modoVobo === 'interna' ? 'firma interna de la plataforma (sello de conformidad)' : 'autógrafa' ?></strong></li>
    </ul>
    <?php if ($necesitaEscaneado && $s['estado'] !== 'borrador' && $s['estado'] !== 'cancelada'): ?>
      <p class="cns-ayuda">Con firma autógrafa: imprime el oficio, que firmen quien solicita y la coordinación (Vo. Bo.), y sube aquí el escaneado.</p>
      <?php if ($s['escaneado_ruta']): ?><p>Escaneado subido el <?= e(date('d/m/Y H:i', strtotime((string) $s['escaneado_en']))) ?>: <a href="<?= $ruta($s['id'] . '/escaneado') ?>" target="_blank" rel="noopener">verlo</a></p>
      <?php else: ?><p><span class="cns-etq no">Falta el escaneado firmado</span></p><?php endif; ?>
      <form method="post" action="<?= $ruta($s['id'] . '/escaneado') ?>" enctype="multipart/form-data" class="cns-acciones"><?= $csrf ?>
        <input type="file" name="archivo" accept="application/pdf" required>
        <button class="btn" type="submit"><?= $s['escaneado_ruta'] ? 'Reemplazar el escaneado' : 'Subir escaneado' ?></button></form>
    <?php endif; ?>
  </section>

  <section class="tarjeta">
    <div class="cns-acciones">
      <?php if ($soy && in_array($s['estado'], ['borrador', 'devuelta'], true)): ?>
        <a class="btn" href="<?= $ruta($s['id'] . '/editar') ?>">Editar</a>
        <form method="post" action="<?= $ruta($s['id'] . '/enviar') ?>" style="display:inline"><?= $csrf ?><button class="btn btn-primario" type="submit">Enviar a la coordinación</button></form>
      <?php endif; ?>
      <?php if ($soy && in_array($s['estado'], ['borrador', 'enviada', 'devuelta'], true)): ?>
        <form method="post" action="<?= $ruta($s['id'] . '/cancelar') ?>" style="display:inline" onsubmit="return confirm('¿Cancelar esta solicitud?')"><?= $csrf ?><button class="btn" type="submit">Cancelar solicitud</button></form>
      <?php endif; ?>
    </div>
    <?php if ($coord && $s['estado'] === 'enviada'): ?>
      <h2 style="margin:14px 0 6px;font-size:18px">Tu revisión</h2>
      <form method="post" action="<?= $ruta($s['id'] . '/vobo') ?>" class="cns-acciones"><?= $csrf ?>
        <label style="margin:0">Registrar como<select name="tipo"><option value="vobo">Vo. Bo.</option><option value="enterado">De enterado</option></select></label>
        <button class="btn btn-primario" type="submit">Dar Vo. Bo. y pasar al acta</button></form>
      <form method="post" action="<?= $ruta($s['id'] . '/devolver') ?>" style="margin-top:10px"><?= $csrf ?>
        <label>Devolver para corregir<textarea name="comentario" rows="2" maxlength="1000" placeholder="Qué debe corregirse…" required></textarea></label>
        <button class="btn" type="submit">Devolver</button></form>
    <?php endif; ?>
    <?php if ($reuniones && $s['estado'] === 'visto_bueno'): ?>
      <form method="post" action="<?= $ruta($s['id'] . '/incorporar') ?>" class="cns-acciones" style="margin-top:10px"><?= $csrf ?>
        <label style="margin:0">Incorporar a la reunión<select name="reunion_id"><?php foreach ($reuniones as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['etiqueta']) ?></option><?php endforeach; ?></select></label>
        <button class="btn" type="submit">Incorporar como caso</button></form>
    <?php endif; ?>
  </section>
</div>
