<?php
/** @var array $s @var array $solicitante @var ?array $caso @var array $reuniones @var array $historial @var array $adjuntos */
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
    <?php if ($s['estado'] === 'devuelta' && $s['comentario']): ?><p style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:var(--radio);padding:10px 12px"><strong>La coordinación hizo observaciones:</strong> <?= e((string) $s['comentario']) ?></p><?php endif; ?>
    <?php if ($caso): ?><p>Quedó como caso <strong><?= e(\App\Modules\Consejos\Services\Texto::clave((string) $caso['grupo'], (int) $caso['numero'])) ?></strong> de la reunión <a href="<?= e(url('consejos/reunion/' . $caso['reunion'])) ?>"><?= e($caso['etiqueta']) ?></a><?= ConsejosAccess::esGestor() ? '' : '.' ?></p><?php endif; ?>
    <?php if ($s['estado'] === 'visto_bueno'): ?><p class="cns-sub">Tiene el <?= $s['vobo_tipo'] === 'enterado' ? 'de enterado' : 'Vo. Bo.' ?> de la coordinación y está en la <strong>cartera de casos para el Consejo</strong>: el secretario académico o su asistente la llevarán a una reunión.</p><?php endif; ?>
  </section>

  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Solicitud</h2>
    <div class="cns-doc"><?= $s['solicitud_html'] ?></div>
    <?php if (trim(strip_tags((string) $s['antecedentes_html'])) !== ''): ?><h3 style="margin:14px 0 4px;font-size:15px">Documentos y/o antecedentes</h3><div class="cns-doc"><?= $s['antecedentes_html'] ?></div><?php endif; ?>
    <p class="cns-ayuda" style="margin-top:12px"><?= e(Solicitudes::fundamento()) ?></p>
  </section>

  <?php $editable = $soy && in_array($s['estado'], ['borrador', 'devuelta'], true); ?>
  <?php if ($adjuntos || $editable): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 4px;font-size:18px">Anexos</h2>
    <?php if (!$adjuntos): ?><p class="cns-sub">Sin anexos todavía.</p><?php endif; ?>
    <?php $lista = $adjuntos; $quitar = $editable; include __DIR__ . '/_adjuntos_lista.php'; ?>
    <?php if ($editable): ?>
    <form method="post" action="<?= $ruta($s['id'] . '/adjuntos') ?>" enctype="multipart/form-data"><?= $csrf ?>
      <p class="cns-ayuda" style="margin:0 0 6px">Agrega documentos que acompañen la solicitud, cada uno con una breve descripción de qué es.</p>
      <?php $admiteOficio = false; include __DIR__ . '/_adjuntos_campos.php'; ?>
      <button class="btn" type="submit" style="margin-top:6px">Subir</button>
    </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($historial): ?>
  <section class="tarjeta">
    <h2 style="margin:0 0 6px;font-size:18px">Observaciones y respuestas</h2>
    <p class="cns-sub" style="margin:0 0 8px">La coordinación puede hacer observaciones y regresar la solicitud cuantas veces haga falta; cuando está bien, da el Vo. Bo.</p>
    <?php foreach ($historial as $h): ?>
      <div style="border-left:4px solid <?= $h['tipo'] === 'observacion' ? '#e0b72e' : 'var(--verde-900)' ?>;background:#fafafa;padding:8px 12px;margin:6px 0;border-radius:0 var(--radio) var(--radio) 0">
        <small class="cns-ayuda"><?= $h['tipo'] === 'observacion' ? 'Observaciones de la coordinación' : 'Respuesta de quien solicita' ?> · ronda <?= (int) $h['ronda'] ?> · <?= e((string) $h['nombre']) ?> · <?= e(date('d/m/Y H:i', strtotime((string) $h['creado_en']))) ?></small><br>
        <?= nl2br(e((string) $h['texto'])) ?>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

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
    <?php $conVobo = in_array($s['estado'], ['visto_bueno', 'incorporada'], true); ?>
    <?php if ($necesitaEscaneado && !$conVobo && in_array($s['estado'], ['enviada', 'devuelta'], true)): ?>
      <p class="cns-ayuda">La revisión y el Vo. Bo. se hacen aquí, dentro de la plataforma. La firma autógrafa va <strong>después</strong> del Vo. Bo. (o del de enterado): entonces se imprime el oficio, firman quien solicita y la coordinación, y se sube el escaneado para el acta.</p>
    <?php endif; ?>
    <?php if ($necesitaEscaneado && $conVobo): ?>
      <p class="cns-ayuda">Ya hay Vo. Bo. de la coordinación. Con firma autógrafa: imprime el oficio, que firmen quien solicita y la coordinación, y sube aquí el escaneado; se incluye en el acta.</p>
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
        <form method="post" action="<?= $ruta($s['id'] . '/enviar') ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap"><?= $csrf ?>
          <?php if ($s['estado'] === 'devuelta'): ?><label style="margin:0;flex:1;min-width:240px">Qué corregiste (opcional)<input type="text" name="respuesta" maxlength="1000" placeholder="Ya corregí las fechas y anexé el oficio…"></label><?php endif; ?>
          <button class="btn btn-primario" type="submit"><?= $s['estado'] === 'devuelta' ? 'Reenviar a la coordinación' : 'Enviar a la coordinación' ?></button></form>
      <?php endif; ?>
      <?php if ($soy && in_array($s['estado'], ['borrador', 'enviada', 'devuelta'], true)): ?>
        <form method="post" action="<?= $ruta($s['id'] . '/cancelar') ?>" style="display:inline" onsubmit="return confirm('¿Cancelar esta solicitud?')"><?= $csrf ?><button class="btn" type="submit">Cancelar solicitud</button></form>
      <?php endif; ?>
    </div>
    <?php if ($coord && $s['estado'] === 'enviada'): ?>
      <h2 style="margin:14px 0 6px;font-size:18px">Tu revisión</h2>
      <p class="cns-ayuda">Si hay algo que corregir, haz tus observaciones y regrésala a quien la redactó; puede haber varias rondas. Cuando esté bien, dale el Vo. Bo.: pasa a la cartera de casos para el Consejo.</p>
      <form method="post" action="<?= $ruta($s['id'] . '/devolver') ?>" style="margin:0 0 12px"><?= $csrf ?>
        <label>Observaciones para quien la redactó<textarea name="comentario" rows="3" maxlength="1000" placeholder="Qué debe corregirse o aclararse…" required></textarea></label>
        <button class="btn" type="submit">Hacer observaciones y regresarla</button></form>
      <form method="post" action="<?= $ruta($s['id'] . '/vobo') ?>" class="cns-acciones"><?= $csrf ?>
        <label style="margin:0">Registrar como<select name="tipo"><option value="vobo">Vo. Bo.</option><option value="enterado">De enterado</option></select></label>
        <button class="btn btn-primario" type="submit">Está bien: dar Vo. Bo. y pasar a cartera</button></form>
    <?php endif; ?>
    <?php if ($reuniones && $s['estado'] === 'visto_bueno'): ?>
      <form method="post" action="<?= $ruta($s['id'] . '/incorporar') ?>" class="cns-acciones" style="margin-top:10px"><?= $csrf ?>
        <label style="margin:0">Llevar de la cartera a la reunión<select name="reunion_id"><?php foreach ($reuniones as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['etiqueta']) ?></option><?php endforeach; ?></select></label>
        <button class="btn" type="submit">Incorporar como caso</button></form>
    <?php endif; ?>
  </section>
</div>
